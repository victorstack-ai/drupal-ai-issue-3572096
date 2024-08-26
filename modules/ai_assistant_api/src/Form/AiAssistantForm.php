<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Assistant form.
 */
final class AiAssistantForm extends EntityForm {

  /**
   * The AI Form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  private AiProviderFormHelper $formHelper;

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  private AiProviderPluginManager $aiProvider;

  /**
   * Constructor.
   *
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The AI Form helper.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider.
   */
  public function __construct(AiProviderFormHelper $formHelper, AiProviderPluginManager $aiProvider) {
    $this->formHelper = $formHelper;
    $this->aiProvider = $aiProvider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.form_helper'),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Article finder assistant'),
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [AiAssistant::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
      '#description' => $this->t('A 1-2 sentence description of the AI assistant and what it does.'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('An assistant that can find old articles and also publish and unpublish them.'),
      ],
    ];

    $form['system_role'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System role'),
      '#default_value' => $entity->get('system_role'),
      '#description' => $this->t('The system role that this AI assistant should have. This is used to determine how and with what the AI Assistant should act.'),
      '#required' => TRUE,
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('You are an assistant helping people find old articles in the archive using natural language. Answer in a professional and neutral tone. Be short and concise. You may use the following HTML tags - a, em, strong, ul, ol, li, pre. Link to the article in question using its title.'),
      ],
    ];

    // Only allow RAG if AI Search is enabled.
    if ($this->moduleHandler->moduleExists('ai_search')) {
      $form['rag'] = [
        '#type' => 'details',
        '#title' => $this->t('RAG settings'),
        '#open' => TRUE,
        '#description' => $this->t('Configure the RAG (Retrieval-Augmented Generation) settings for this AI assistant. You may use multiple databases, but if so you have to define a search strategy.'),
      ];

      $form['rag']['rag_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable RAG'),
        '#default_value' => $entity->get('rag_enabled'),
      ];

      // We only allow one for now.
      $i = 0;
      foreach ($entity->get('rag_databases') as $value) {
        $this->ragSegment($form, $form_state, $i);
        $i++;
      }
      if ($i == 0) {
        $this->ragSegment($form, $form_state, $i);
      }

    }
    else {
      $form['no_ai_search'] = [
        '#type' => 'markup',
        '#markup' => $this->t('AI Search module is not enabled. RAG settings will not be available.'),
      ];
    }

    $form['rag']['no_results_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG No results message'),
      '#description' => $this->t('This is a hard coded message you can answer if the threshold of RAG is not enough, this will bypass the assistant message and send it out right away. Can be left empty if you want a natural answer.'),
      '#default_value' => $entity->get('no_results_message') ?? $this->t('I am sorry, but I could not find any relevant information in the archives. Please try to ask the question in a different way or try to ask a different question.'),
      '#states' => [
        'visible' => [
          ':input[name="rag_enabled"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, but I could not find any relevant information in the archives. Please try to ask the question in a different way or try to ask a different question.'),
        'rows' => 2,
      ],
    ];

    $form['assistant_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG Assistant message'),
      '#description' => $this->t('Use the token [rag_context] for providing the snippets or full rendered entities. Use the token [question] for providing the question from the end-user.<br />The assistant message is for responses created by the LLM.'),
      '#default_value' => $entity->get('assistant_message'),
      '#attributes' => [
        'placeholder' => $this->t("Based on the following results that were fetched from an article database and the following question, check if you can answer the question truthfully. If you can not answer the question, please respond that you do not have enough information to do so. Do NOT make up information. Answer in a professional and concise manner. If a link is provided with the article, use HTML to link to the article using the articles title.

The question is:
-----------------------
[question]
-----------------------

The following articles were found:
-----------------------
[rag_context]
-----------------------"),
        'rows' => 15,
      ],
    ];

    $form['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('This is the answer if we run into any error on the way. You may use the token [error_message] to get the error message from the backend in your message, but it might be a security concern to show this to none escalated users.'),
      '#default_value' => $entity->get('error_message') ?? $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
        'rows' => 2,
      ],
    ];

    // Set form state if empty.
    if ($form_state->getValue('llm_ai_provider') == NULL) {
      $form_state->setValue('llm_ai_provider', $entity->get('llm_provider'));
    }
    if ($form_state->getValue('llm_ai_model') == NULL) {
      $form_state->setValue('llm_ai_model', $entity->get('llm_model'));
    }
    // phpcs:ignore
    \Drupal::service('ai.form_helper')->generateAiProvidersForm($form, $form_state, 'chat', 'llm', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    // Set default values.
    $llm_configs = $entity->get('llm_configuration');
    if ($llm_configs && count($llm_configs)) {
      foreach ($llm_configs as $key => $value) {
        $form['llm_ajax_prefix']['llm_ajax_prefix_configuration_' . $key]['#default_value'] = $value;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // phpcs:ignore
    \Drupal::service('ai.form_helper')->validateAiProvidersConfig($form, $form_state, 'chat', 'llm');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;

    // RAG settings.
    $rag_databases = [];
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, 'rag_') === 0 && $key !== 'rag_enabled') {
        $parts = explode('_', $key);
        $sub_key = implode('_', array_slice($parts, 2));
        if (!empty($form_state->getValue('rag_' . $parts[1] . '_database'))) {
          $rag_databases[$parts[1]][$sub_key] = $val;
        }
      }
    }
    $entity->set('rag_databases', $rag_databases);
    // LLM provider.
    $entity->set('llm_provider', $form_state->getValue('llm_ai_provider'));
    $entity->set('llm_model', $form_state->getValue('llm_ai_model'));
    $llm_config = [];
    // phpcs:ignore
    $provider = \Drupal::service('ai.provider')->createInstance($form_state->getValue('llm_ai_provider'));
    $schema = $provider->getAvailableConfiguration('chat', $form_state->getValue('llm_ai_model'));
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, 'llm_') === 0 && $key !== 'llm_ai_provider' && $key !== 'llm_ai_model') {

        $real_key = str_replace('llm_ajax_prefix_configuration_', '', $key);
        $type = $schema[$real_key]['type'] ?? 'string';
        $llm_config[$real_key] = CastUtility::typeCast($type, $val);
      }
    }
    $entity->set('llm_configuration', $llm_config);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Create a RAG segment.
   */
  protected function ragSegment(&$form, FormStateInterface $form_state, $i = 0) {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $form['rag']['rag_wrapper_' . $i] = [
      '#type' => 'fieldset',
      '#title' => $this->t('RAG database @i', ['@i' => $i + 1]),
      '#states' => [
        'visible' => [
          ':input[name="rag_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_database'] = [
      '#type' => 'select',
      '#title' => $this->t('RAG database'),
      '#options' => $this->getSearchDatabases(),
      '#default_value' => $entity->get('rag_databases')[$i]['database'] ?? $form_state->getValue('rag_' . $i . '_database'),
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG description'),
      '#description' => $this->t('A description of what is possible to find in this database. Be verbose, an advanced AI Assistant might use it for chosing where to search.'),
      '#default_value' => $entity->get('rag_databases')[$i]['description'] ?? $form_state->getValue('rag_' . $i . '_description'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('This database will return article segments, together with their Titles, node ids and links.'),
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_score_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG threshold'),
      '#description' => $this->t('This is the threshold that the answer have to meet to be thought of as a valid response. Note that the number may shift depending on the similary metric you are using.'),
      '#default_value' => $entity->get('rag_databases')[$i]['score_threshold'] ?? $form_state->getValue('rag_' . $i . '_score_threshold'),
      '#attributes' => [
        'placeholder' => 0.6,
      ],
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_min_results'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG minimum results'),
      '#description' => $this->t('The minimum chunks needed to pass the threshold, before leaving a response based on RAG.'),
      '#default_value' => $entity->get('rag_databases')[$i]['min_results'] ?? $form_state->getValue('rag_' . $i . '_min_results'),
      '#attributes' => [
        'placeholder' => 1,
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('RAG max results'),
      '#description' => $this->t('The maximum results that passed the threshold, to take into account.'),
      '#default_value' => $entity->get('rag_databases')[$i]['max_results'] ?? $form_state->getValue('rag_' . $i . '_max_results'),
      '#attributes' => [
        'placeholder' => 20,
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_output_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('RAG context mode'),
      '#description' => $this->t('The context mode for the list given to the Assistant. <br>The <strong>chunk mode</strong> will return the chunk as they are and the LLM will act on this - if chunked correctly this produces very quick answer for chatbots that needs to answer quickly.<br>If you return <strong>aggregated and rendered entities</strong>, there will be an LLM agent first checking each of the answers over the whole entity, and then return an aggregated answer to the Assistant. This is slower, but more accurate.'),
      '#default_value' => $entity->get('rag_databases')[$i]['output_mode'] ?? $form_state->getValue('rag_' . $i . '_output_mode'),
      '#options' => [
        'chunks' => $this->t('Chunks'),
        'rendered' => $this->t('Aggregated and Rendered entities'),
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_aggregated_llm'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG LLM Agent'),
      '#description' => $this->t('With Aggregated and Rendered entities, this agent will take each of the entities returned and create one summarized answer to feed to the assistant. This can take the tokens [question] and [entity] or even specific tokens from the entity below.'),
      '#default_value' => $entity->get('rag_databases')[$i]['aggregated_llm'] ?? $form_state->getValue('rag_' . $i . '_aggregated_llm'),
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
          ':input[name="rag_' . $i . '_output_mode"]' => ['value' => 'rendered'],
        ],
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_access_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('RAG access check'),
      '#description' => $this->t('With this enabled the system will do a post query access check on every chunk to see if the user has access to that content. Note that this might lead to no results and be slower, but it makes sure that none-accessible items are not reached. This is done before the Assistant prompt, so its secure to prompt injection.'),
      '#default_value' => $entity->get('rag_databases')[$i]['access_check'] ?? $form_state->getValue('rag_' . $i . '_access_check'),
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_use_context'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use route context'),
      '#description' => $this->t('Use the route context to only ask questions about the content on the current page.'),
      '#default_value' => $entity->get('rag_databases')[$i]['use_context'] ?? $form_state->getValue('rag_' . $i . '_use_context'),
      '#attributes' => [
        'placeholder' => 1,
      ],
    ];

    $form['rag']['rag_wrapper_' . $i]['rag_' . $i . '_context_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Context threshold'),
      '#description' => $this->t('This is the threshold that the answer have to meet to be thought of as a valid response in context. Note that the similarity value is generally lower on a specific question in context, so lower values are needed.'),
      '#default_value' => $entity->get('rag_databases')[$i]['context_threshold'] ?? $form_state->getValue('rag_' . $i . '_context_threshold'),
      '#attributes' => [
        'placeholder' => 0.1,
      ],
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#states' => [
        'visible' => [
          ':input[name="rag_' . $i . '_use_context"]' => ['checked' => TRUE],
        ],
      ],
    ];

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

}
