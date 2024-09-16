<?php

namespace Drupal\ai_search\Plugin\AiAssistantAction;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\Attribute\AiAssistantAction;
use Drupal\ai_assistant_api\Base\AiAssistantActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The RAG action.
 */
#[AiAssistantAction(
  id: 'rag_action',
  label: new TranslatableMarkup('RAG Actions'),
)]
class RagAction extends AiAssistantActionBase {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;


  /**
   * The Drupal renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, PrivateTempStoreFactory $tmpStore, EntityTypeManagerInterface $entityTypeManager, Renderer $renderer) {
    parent::__construct($configuration, $tmpStore);
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    $this->ragSegment($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function listActions(): array {
    return [
      'search_rag' => [
        'id' => 'search_rag',
        'plugin' => 'rag_action',
        'label' => new TranslatableMarkup('Search RAG'),
        'description' => new TranslatableMarkup('Search RAG for a specific topic.'),
      ],
      'reuse_rag' => [
        'id' => 'reuse_rag',
        'plugin' => 'rag_action',
        'label' => new TranslatableMarkup('Reuse RAG'),
        'description' => new TranslatableMarkup('Reuse RAG for a specific topic.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function listContexts(): array {
    $rags[0]['title'] = 'RAG databases available to search';
    foreach ($this->configuration as $rag_config) {
      $rags[0]['description'][] = $rag_config['database'] . " - " . $rag_config['description'];
    }
    // Get all the send RAG contexts.
    $rag = $this->getActionContext('rag');
    if (count($rag)) {
      $rags[1]['title'] = 'RAG contexts available to reuse';
      foreach ($rag as $key => $old) {
        $rags[1]['description'][] = 'key: ' . $key . ", query: " . $old['query'] . ", database: " . $old['database'];
      }
    }
    return $rags;
  }

  /**
   * {@inheritdoc}
   */
  public function triggerAction(string $action_id, $parameters = []): void {
    switch ($action_id) {
      case 'search_rag':
        $this->searchRagAction($parameters['database'], $parameters['query']);
        break;

      case 'reuse_rag':
        $this->reuseRagAction($parameters['key']);
        break;
    }
  }

  /**
   * Get all search databases.
   */
  private function getSearchDatabases(): array {
    $databases = [];
    $databases[''] = $this->t('-- Select --');
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      $databases[$index->id()] = $index->label() . ' (' . $index->id() . ')';
    };
    return $databases;
  }

  /**
   * Take rag action.
   */
  protected function searchRagAction($db, $query) {
    foreach ($this->configuration as $config) {
      if ($config['database'] == $db) {
        $rag_database = $config;
        break;
      }
    }
    if (!isset($rag_database)) {
      $this->setOutputContext('rag', 'No RAG database found.');
      return;
    }
    $results = $this->getRagResults($rag_database, $query);
    // Get the results we are interested in as a string.
    $this->setOutputContext('rag', $this->renderRagResponseAsString($results, $query, $rag_database));
  }

  /**
   * Reuse rag action.
   */
  protected function reuseRagAction($index) {
    $this->setOutputContext('rag', $this->getRagContextHistory()[$index]['response']);
  }

  /**
   * Gets the RAG context history.
   *
   * @return array
   *   The RAG context history.
   */
  public function getRagContextHistory() {
    return $this->getActionContext('rag');
  }

  /**
   * Render the RAG response as string.
   *
   * @param \Drupal\search_api\Query\ResultSet $results
   *   The RAG results.
   * @param string $query
   *   The query to search for (optional).
   * @param array $rag_database
   *   The RAG database array data.
   *
   * @return string
   *   The RAG response.
   */
  protected function renderRagResponseAsString($results, string $query, array $rag_database) {
    $response = '';

    foreach ($results as $result) {
      // Filter the results.
      if ($rag_database['score_threshold'] > $result->getScore()) {
        continue;
      }
      // Chunked mode is easy.
      if ($rag_database['output_mode'] == 'chunks') {
        $response .= $result->getExtraData('content') . "\n\n";
        $response .= '----------------------------------------' . "\n\n";
      }
      else {
        // LLM checking results.
        $response .= $this->fullEntityCheck($result, $query, $rag_database);
      }
    }
    // Store the response in context.
    $this->storeActionContext('rag', [
      'query' => $query,
      'database' => $rag_database,
      'response' => $response,
    ]);
    return $response;
  }

  /**
   * Process RAG.
   *
   * @param array $rag_database
   *   The RAG database array data.
   * @param string $query_string
   *   The query to search for (optional).
   *
   * @return \Drupal\search_api\Query\ResultSet
   *   The RAG response.
   */
  protected function getRagResults(array $rag_database, string $query_string = '') {
    /** @var \Drupal\search_api\Entity\Index */
    $rag_storage = $this->entityTypeManager->getStorage('search_api_index');
    // Get the index.
    $index = $rag_storage->load($rag_database['database']);
    if (!$index) {
      throw new \Exception('RAG database not found.');
    }

    // Then we try to search.
    try {
      $query = $index->query([
        'limit' => $rag_database['max_results'],
      ]);
      $query->setOption('search_api_bypass_access', FALSE);
      $query->setOption('search_api_ai_get_chunks_result', $rag_database['output_mode'] == 'chunks');
      $queries = $query_string;
      $query->keys($queries);
      $results = $query->execute();
    }
    catch (\Exception $e) {
      throw new \Exception('Failed to search: ' . $e->getMessage());
    }
    return $results;
  }

  /**
   * Full entity check with a LLM checking the rendered entity.
   *
   * @param \Drupal\search_api\Query\Result $result
   *   The result to check.
   * @param string $query_string
   *   The query to search for.
   * @param array $rag_database
   *   The RAG database array data.
   *
   * @return string
   *   The response.
   */
  protected function fullEntityCheck($result, string $query_string, array $rag_database) {
    $entity_string = $result->getExtraData('drupal_entity_id');
    // Load the entity from search api key.
    // @todo probably exists a function for this.
    [, $entity_parts, $lang] = explode(':', $entity_string);
    [$entity_type, $entity_id] = explode('/', $entity_parts);
    /** @var \Drupal\Core\Entity\ContentEntityBase */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    // Get translated if possible.
    if (method_exists($entity, 'hasTranslation')) {
      if ($entity->hasTranslation($lang)) {
        $entity = $entity->getTranslation($lang);
      }
    }
    // Render the entity in default view mode.
    $pre_render_entity = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity);
    $rendered_entity = nl2br(trim(strip_tags($this->renderer->render($pre_render_entity))));
    $message = str_replace([
      '[question]',
      '[entity]',
    ], [
      $query_string,
      $rendered_entity,
    ], nl2br($rag_database['aggregated_llm']));

    // Now we have the entity, we can check it with the LLM.
    $provider = $this->aiProvider->createInstance($this->assistant->get('llm_provider'));
    $config = [];
    foreach ($rag_database['llm_configuration'] as $key => $val) {
      $config[$key] = $val;
    }
    $provider->setConfiguration($config);
    $input = new ChatInput([
      new ChatMessage('user', $message),
    ]);
    $output = $provider->chat($input, $this->assistant->get('llm_model'));
    $response = $output->getNormalized()->getText() . "\n";
    $response .= '----------------------------------------' . "\n\n";
    return $response;
  }

  /**
   * Create a RAG segment.
   */
  protected function ragSegment(&$form, FormStateInterface $form_state, $i = 0) {

    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $form['rag_' . $i] = [
      '#type' => 'fieldset',
      '#title' => $this->t('RAG database @i', ['@i' => $i + 1]),
      '#states' => [
        'visible' => [
          ':input[name="action_plugin_rag_action[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rag_' . $i]['database'] = [
      '#type' => 'select',
      '#title' => $this->t('RAG database'),
      '#options' => $this->getSearchDatabases(),
      '#default_value' => $this->configuration['rag_' . $i]['database'] ?? $form_state->getValue('database'),
    ];

    $form['rag_' . $i]['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG description'),
      '#description' => $this->t('A description of what is possible to find in this database. Be verbose, an advanced AI Assistant might use it for choosing where to search.'),
      '#default_value' => $this->configuration['rag_' . $i]['description'] ?? $form_state->getValue('description'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('This database will return article segments, together with their Titles, node ids and links.'),
      ],
    ];

    $threshold = $this->configuration['rag_' . $i]['score_threshold'] ?? $form_state->getValue('score_threshold');
    $threshold = $threshold ?? 0.6;

    $form['rag_' . $i]['score_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG threshold'),
      '#description' => $this->t('This is the threshold that the answer have to meet to be thought of as a valid response. Note that the number may shift depending on the similar metric you are using.'),
      '#default_value' => $threshold,
      '#attributes' => [
        'placeholder' => 0.6,
      ],
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];

    $min_results = $this->configuration['rag_' . $i]['min_results'] ?? $form_state->getValue('min_results');
    $min_results = $min_results ?? 1;

    $form['rag_' . $i]['min_results'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG minimum results'),
      '#description' => $this->t('The minimum chunks needed to pass the threshold, before leaving a response based on RAG.'),
      '#default_value' => $min_results,
      '#attributes' => [
        'placeholder' => 1,
      ],
    ];

    $max_results = $this->configuration['rag_' . $i]['max_results'] ?? $form_state->getValue('max_results');
    $max_results = $max_results ?? 5;

    $form['rag_' . $i]['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG max results'),
      '#description' => $this->t('The maximum results that passed the threshold, to take into account.'),
      '#default_value' => $max_results,
      '#attributes' => [
        'placeholder' => 20,
      ],
    ];

    $form['rag_' . $i]['output_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('RAG context mode'),
      '#description' => $this->t('The context mode for the list given to the Assistant. <br>The <strong>chunk mode</strong> will return the chunk as they are and the LLM will act on this - if chunked correctly this produces very quick answer for chatbots that needs to answer quickly.<br>If you return <strong>aggregated and rendered entities</strong>, there will be an LLM agent first checking each of the answers over the whole entity, and then return an aggregated answer to the Assistant. This is slower, but more accurate.'),
      '#default_value' => $this->configuration['rag_' . $i]['output_mode'] ?? $form_state->getValue('output_mode'),
      '#options' => [
        'chunks' => $this->t('Chunks'),
        'rendered' => $this->t('Aggregated and Rendered entities'),
      ],
    ];

    $form['rag_' . $i]['aggregated_llm'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG LLM Agent'),
      '#description' => $this->t('With Aggregated and Rendered entities, this agent will take each of the entities returned and create one summarized answer to feed to the assistant. This can take the tokens [question] and [entity] or even specific tokens from the entity below.'),
      '#default_value' => $this->configuration['rag_' . $i]['aggregated_llm'] ?? $form_state->getValue('aggregated_llm'),
      '#attributes' => [
        'rows' => 10,
        'placeholder' => $this->t('Can you summarize if the following article is relevant to the question?
If it is not, please just answer "no answer".
If it is, answer with the details that are needed to answer this from a larger perspective.

The question is:
-----------------------
[question]
-----------------------

The article is:
-----------------------
[entity]
-----------------------'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="action_plugin_rag_action[configuration][rag_' . $i . '][output_mode]"]' => ['value' => 'rendered'],
        ],
      ],
    ];

    $form['rag_' . $i]['access_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('RAG access check'),
      '#description' => $this->t('With this enabled the system will do a post query access check on every chunk to see if the user has access to that content. Note that this might lead to no results and be slower, but it makes sure that none-accessible items are not reached. This is done before the Assistant prompt, so its secure to prompt injection.'),
      '#default_value' => $this->configuration['rag_' . $i]['access_check'] ?? $form_state->getValue('access_check'),
    ];

    $form['rag_' . $i]['try_reuse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reuse RAG Context'),
      '#description' => $this->t('If the RAG has been used before, try to reuse the answer from the last time. This will do a pre-call to the AI provider to ask if the current context is enough, meaning a higher cost if you enable this.'),
      '#default_value' => $this->configuration['rag_' . $i]['try_reuse'] ?? $form_state->getValue('try_reuse'),
    ];

    $form['rag_' . $i]['context_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Context threshold'),
      '#description' => $this->t('This is the threshold that the answer have to meet to be thought of as a valid response in context. Note that the similarity value is generally lower on a specific question in context, so lower values are needed.'),
      '#default_value' => $this->configuration['rag_' . $i]['context_threshold'] ?? $form_state->getValue('context_threshold'),
      '#attributes' => [
        'placeholder' => 0.1,
      ],
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#states' => [
        'visible' => [
          ':input[name="use_context"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

}
