<?php

namespace Drupal\ai_search\Plugin\search_api\backend;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\ai_search\Backend\AiSearchBackendPluginBase;
use Drupal\ai_search\EmbeddingStrategyPluginManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Search backend for search api.
 *
 * @SearchApiBackend(
 *   id = "search_api_ai_search",
 *   label = @Translation("AI Search"),
 *   description = @Translation("Index items on Vector DB.")
 * )
 */
class SearchApiAiSearchBackend extends AiSearchBackendPluginBase implements PluginFormInterface {

  /**
   * The AI VDB Provider.
   *
   * @var \Drupal\ai\AiVdbProviderPluginManager
   */
  protected AiVdbProviderPluginManager $vdbProviderManager;

  /**
   * The AI LLM Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The Embedding Strategy manager.
   *
   * @var \Drupal\ai_search\EmbeddingStrategyPluginManager
   */
  protected EmbeddingStrategyPluginManager $embeddingStrategyProviderManager;

  /**
   * The tokenizer interface to get the supported token count models.
   *
   * @var \Drupal\ai\Utility\TokenizerInterface
   */
  protected TokenizerInterface $tokenizer;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current account, proxy interface.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Vector DB client.
   *
   * @var object
   */
  protected object $vdbClient;

  /**
   * Max retries for iterating for access.
   *
   * @var int
   */
  protected int $maxAccessRetries = 10;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->vdbProviderManager = $container->get('ai.vdb_provider');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->embeddingStrategyProviderManager = $container->get('ai_search.embedding_strategy');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->messenger = $container->get('messenger');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->tokenizer = $container->get('ai.tokenizer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    if (!isset($config['database'])) {
      $config['database'] = NULL;
    }
    if (!isset($config['collection'])) {
      $config['collection'] = NULL;
    }
    if (!isset($config['embeddings_strategy'])) {
      $config['embeddings_strategy'] = NULL;
    }
    if (!isset($config['metric'])) {
      $config['metric'] = NULL;
    }
    if (!isset($config['database_name'])) {
      $config['database_name'] = 'default';
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // If a subform is received, we want the full form state.
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }

    // If no provider is installed we can't do anything.
    $errors = [];
    if (!$this->aiProviderManager->hasProvidersForOperationType('embeddings')) {
      $errors[] = '<div class="ai-error">' . $this->t('No AI providers are installed for Embeddings calls, please %install and %configure one first.', [
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
      ]) . '</div>';
    }

    $vdb_providers = $this->vdbProviderManager->getProviders(TRUE);
    if (empty($vdb_providers)) {
      $errors[] = '<div class="ai-error">' . $this->t('No Vector DB providers are installed or setup for search in vectors, please %install and %configure one first.', [
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_vdb_providers')->toString(),
      ]) . '</div>';
    }

    if (count($errors)) {
      $form['markup'] = [
        '#markup' => implode('', $errors),
      ];
      return $form;
    }

    $chosen_database = $this->configuration['database'] ?? NULL;
    if (!$chosen_database) {
      // Try to get from form state.
      $chosen_database = $form_state->get('database') ?? NULL;
    }

    $form['database'] = [
      '#type' => 'select',
      '#title' => $this->t('Vector Database'),
      '#options' => $vdb_providers,
      '#required' => TRUE,
      '#default_value' => $chosen_database,
      '#description' => $this->t('The Vector Database to use.'),
    ];

    // Get all supported models, default to gpt-3.5 model.
    $supported_models = $this->tokenizer->getSupportedModels();
    $default_model_possibilities = array_keys(array_filter($supported_models, function ($model) {
      return str_contains($model, 'gpt-3.5');
    }, ARRAY_FILTER_USE_KEY));
    $default_model = reset($default_model_possibilities);
    $form['chat_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Tokenizer chat model'),
      '#description' => $this->t('This is recommended to ensure the right number of tokens is calculated for the embeddings.'),
      '#default_value' => $this->configuration['chat_model'] ?? $default_model,
      '#options' => $this->tokenizer->getSupportedModels(),
      '#required' => TRUE,
    ];

    $form['database_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Name'),
      '#description' => $this->t('The database name to use.'),
      '#default_value' => $this->configuration['database_name'] ?? NULL,
      '#required' => TRUE,
      '#pattern' => '[a-zA-Z0-9_]*',
      '#disabled' => (bool) FALSE,
    ];

    $form['collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Collection'),
      '#description' => $this->t('The collection to use. This will be generated if it does not exist and cannot be changed.'),
      '#default_value' => $this->configuration['collection'] ?? NULL,
      '#required' => TRUE,
      '#pattern' => '[a-zA-Z0-9_]*',
      '#disabled' => (bool) FALSE,
    ];

    $metric_distance = [
      VdbSimilarityMetrics::CosineSimilarity->value => $this->t('Cosine Similarity'),
      VdbSimilarityMetrics::EuclideanDistance->value => $this->t('Euclidean Distance'),
      VdbSimilarityMetrics::InnerProduct->value => $this->t('Inner Product'),
    ];

    $form['metric'] = [
      '#type' => 'select',
      '#title' => $this->t('Similarity Metric'),
      '#options' => $metric_distance,
      '#required' => TRUE,
      '#default_value' => $this->configuration['metric'] ?? VdbSimilarityMetrics::CosineSimilarity->value,
      '#description' => $this->t('The metric to use for similarity calculations.'),
    ];

    // Add Embeddings Engine or Embeddings Strategy subform.
    $form = parent::buildConfigurationForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!empty($this->configuration['database'])) {
      $vdb_client = $this->vdbProviderManager->createInstance($this->configuration['database']);
      $collections = $vdb_client->getCollections($form_state->getValue('database_name'));
      // Check so the collection doesn't exist already.
      /** @var \Drupal\Core\Entity\Form $form_object */
      $form_object = $form_state->getFormObject();
      $entity = $form_object->getEntity();
      if ($entity->isNew() && isset($collections['data']) && in_array($form_state->getValue('collection'), $collections['data'])) {
        $form_state->setErrorByName('collection', $this->t('The collection already exists in the selected vector database.'));
      }

      // Ensure the vector database selected has already been configured to
      // avoid a fatal error.
      $config = $vdb_client->getConfig()->getRawData();
      if (isset($config['_core'])) {
        unset($config['_core']);
      }
      $config = array_filter($config);
      if (empty($config)) {

        // Explain to the user where to configure the vector database first.
        $form_state->setErrorByName('database', $this->t('The selected vector database has not yet been configured. <a href="@url">Please configure it first</a>.', [
          '@url' => Url::fromRoute('ai.admin_vdb_providers')->toString(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    if ($type === 'embeddings') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the chat model options that the tokenizer supports.
   *
   * @return array
   *   The chat model options that Tokenizer supports.
   */
  protected function getModelTokenizerOptions(): array {
    $model_options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    $model_options = array_filter($model_options, function ($option) {
      return str_contains($option, '__');
    }, ARRAY_FILTER_USE_KEY);
    return $model_options;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $vdb_client = $this->vdbProviderManager->createInstance($this->configuration['database']);
    $vdb_client->createCollection(
      collection_name: $form_state->getValue('collection'),
      dimension: $form_state->getValue('embeddings_engine_configuration')['dimensions'],
      metric_type: VdbSimilarityMetrics::from($form_state->getValue('metric')),
      database: $form_state->getValue('database_name'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function indexItems(IndexInterface $index, array $items): array {
    $embedding_strategy = $this->embeddingStrategyProviderManager->createInstance($this->configuration['embedding_strategy']);
    $successfulItemIds = [];
    $itemBase = [
      'metadata' => [
        'server_id' => $this->server->id(),
        'index_id' => $index->id(),
      ],
    ];

    // Check if we need to delete some items first.
    $this->deleteItems($index, array_values(array_map(function ($item) {
      return $item->getId();
    }, $items)));

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $embeddings = $embedding_strategy->getEmbedding(
        $this->configuration['embeddings_engine'],
        $this->configuration['chat_model'],
        $this->configuration['embedding_strategy_configuration'],
        $item->getFields(),
        $item
      );
      foreach ($embeddings as $embedding) {
        $embedding = array_merge_recursive($embedding, $itemBase);
        $data['drupal_long_id'] = $embedding['id'];
        $data['drupal_entity_id'] = $item->getId();
        $data['vector'] = $embedding['values'];
        foreach ($embedding['metadata'] as $key => $value) {
          $data[$key] = $value;
        }
        $this->getClient()->insertIntoCollection(
          collection_name: $this->configuration['collection'],
          data: $data,
          database: $this->configuration['database_name'],
        );
      }

      $successfulItemIds[] = $item->getId();
    }

    return $successfulItemIds;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    $vdbIds = $this->getClient()->getVdbIds(
      collection_name: $this->configuration['collection'],
      drupalIds: $item_ids,
      database: $this->configuration['database_name'],
    );
    $this->getClient()->deleteFromCollection(
      collection_name: $this->configuration['collection'],
      ids: $vdbIds,
      database: $this->configuration['database_name'],
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    $this->getClient()->dropCollection(
      collection_name: $this->configuration['collection'],
      database: $this->configuration['database_name'],
    );
    $this->getClient()->createCollection(
      collection_name: $this->configuration['collection'],
      dimension: $this->configuration['embeddings_engine_configuration']['dimensions'],
      database: $this->configuration['database_name'],
    );
  }

  /**
   * Set query results.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return void|null
   *   The results.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function search(QueryInterface $query) {
    // Check if we need to do entity access checks.
    $bypass_access = $query->getOption('search_api_bypass_access', FALSE);
    // Check if we have a custom value for the iterator.
    if ($query->getOption('search_api_ai_max_pager_iterations', 0)) {
      $this->maxAccessRetries = $query->getOption('search_api_ai_max_pager_iterations');
    }
    // Check if we should aggregate results.
    $get_chunked = $query->getOption('search_api_ai_get_chunks_result', FALSE);

    // Get index and ensure it is ready.
    if ($query->hasTag('server_index_status')) {
      return NULL;
    }
    $index = $query->getIndex();

    // Get DB Client.
    if (empty($this->configuration['database'])) {
      return NULL;
    }

    // Get query.
    $results = $query->getResults();

    // Prepare params.
    $params = [
      'collection_name' => $this->configuration['collection'],
      'output_fields' => ['id', 'drupal_entity_id', 'drupal_long_id', 'content'],
      // Double the limit if we need to run over access checks.
      'limit' => (int) $query->getOption('limit', 10),
      'offset' => (int) $query->getOption('offset', 0),
    ];

    // Filters.
    $condition_group = $query->getConditionGroup();
    $filters = [];
    foreach ($condition_group->getConditions() as $condition) {
      $fieldData = $index->getField($condition->getField());
      // Get the field type or its intrensic field, like drupal_entity_id.
      $fieldType = $fieldData ? $fieldData->getType() : 'string';
      $isMultiple = $fieldData ? $this->isMultiple($fieldData) : FALSE;
      $values = is_array($condition->getValue()) ? $condition->getValue() : [$condition->getValue()];
      if (in_array($fieldType, ['string', 'full_text'])) {
        $normalizedValues = '"' . implode('","', $values) . '"';
      }
      else {
        $normalizedValues = implode(',', $values);
      }
      if ($isMultiple) {
        if (in_array($condition->getOperator(), [
          '=',
          'IN',
        ])) {
          $filters[] = '(ARRAY_CONTAINS(' . $condition->getField() . ', ' . $normalizedValues . '))';
        }
        else {
          $this->messenger->addWarning('The vector database @name does not support negative operator on multiple fields.', [
            '@name' => $this->getClient()->getPluginId(),
          ]);
        }
      }
      else {
        $filters[] = '(' . $condition->getField() . ' ' . $condition->getOperator() . ' ' . $normalizedValues . ')';
      }
    }
    if ($filters) {
      $params['filters'] = implode(' AND ', $filters);
    }

    // Conduct the search.
    $real_results = [];
    $meta_data = $this->doSearch($query, $params, $bypass_access, $real_results, $params['limit'], $params['offset']);
    // Keep track of items already added so existing result items do not get
    // overwritten by later records containing the same item.
    $stored_items = [];

    // Obtain results.
    foreach ($real_results as $match) {
      $id = $get_chunked ? $match['drupal_entity_id'] . ':' . $match['id'] : $match['drupal_entity_id'];
      $item = $this->getFieldsHelper()->createItem($index, $id);
      $item->setScore($match['distance'] ?? 1);
      $this->extractMetadata($match, $item);
      if (!$get_chunked && !in_array($item->getId(), $stored_items)) {
        $stored_items[] = $item->getId();
        $results->addResultItem($item);
      }
      else {
        $results->addResultItem($item);
      }
    }
    $results->setExtraData('real_offset', $meta_data['real_offset']);
    $results->setExtraData('reason_for_finish', $meta_data['reason']);
    // Get the last vector score.
    $results->setExtraData('current_vector_score', $meta_data['vector_score'] ?? 0);

    // Sort results.
    $sorts = $query->getSorts();
    if (!empty($sorts["search_api_relevance"])) {
      $result_items = $results->getResultItems();
      usort($result_items, function ($a, $b) use ($sorts) {
        $distance_a = $a->getScore();
        $distance_b = $b->getScore();
        return $sorts["search_api_relevance"] === 'DESC' ? $distance_b <=> $distance_a : $distance_a <=> $distance_b;
      });
      $results->setResultItems($result_items);
    }

    // Set results count.
    $results->setResultCount(count($results->getResultItems()));
  }

  /**
   * Run the search until enough items are found.
   */
  protected function doSearch(QueryInterface $query, $params, $bypass_access, &$results, $start_limit, $start_offset, $iteration = 0) {
    // Conduct the search.
    if (!$bypass_access) {
      // Double the results, if we need to run over access checks.
      $params['limit'] = $start_limit * 2;
      $params['offset'] = $start_offset + ($iteration * $start_limit * 2);
    }
    $search_words = $query->getKeys();
    if (!empty($search_words)) {
      [$provider_id, $model_id] = explode('__', $this->configuration['embeddings_engine']);
      $embedding_llm = $this->aiProviderManager->createInstance($provider_id);
      // We don't have to redo this.
      if (!isset($params['vector_input'])) {
        // Handlex complex search queries, but we just normalize to string.
        // It makes no sense to do Boolean or other complex searches on vectors.
        if (is_array($search_words)) {
          if (isset($search_words['#conjunction'])) {
            unset($search_words['#conjunction']);
          }
          $search_words = implode(' ', $search_words);
        }
        $params['vector_input'] = $embedding_llm->embeddings($search_words, $model_id)->getNormalized();
      }
      $response = $this->getClient()->vectorSearch(...$params);
    }
    else {
      $response = $this->getClient()->querySearch(...$params);
    }

    // Obtain results.
    $i = 0;
    foreach ($response as $match) {
      $i++;
      // Do access checks.
      if (!$bypass_access && !$this->checkEntityAccess($match['drupal_entity_id'])) {
        // If we are not allowed to view this entity, we can skip it.
        continue;
      }
      // Passed.
      $results[] = $match;
      // If we found enough items, we can stop.
      if (count($results) == $start_limit) {
        return [
          'real_offset' => $start_offset + ($iteration * $start_limit * 2) + $i,
          'reason' => 'limit',
          'vector_score' => $match->distance ?? 0,
        ];
      }
    }

    // If we reach max retries, we can stop.
    if ($iteration == $this->maxAccessRetries) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'max_retries',
        'vector_score' => $match->distance ?? 0,
      ];
    }
    // If we got less then limit back, it reached the end.
    if (count($response) < $start_limit) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'reached_end',
        'vector_score' => $match->distance ?? 0,
      ];
    }
    // Else we need to continue.
    return $this->doSearch($query, $params, $bypass_access, $results, $start_limit, $start_offset, $iteration + 1);
  }

  /**
   * Extract query metadata values to a result item.
   *
   * @param array $result_row
   *   The result row.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   */
  public function extractMetadata(array $result_row, ItemInterface $item): void {
    foreach ($result_row as $key => $value) {
      if ($key === 'vector' || $key === 'id' || $key === 'distance') {
        continue;
      }
      $item->setExtraData($key, $value);
    }
  }

  /**
   * Figure out cardinality from field item.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   *
   * @return bool
   *   If the cardinality is multiple or not.
   */
  public function isMultiple(FieldInterface $field): bool {
    [$fieldName] = explode(':', $field->getPropertyPath());
    [, $entity_type] = explode(':', $field->getDatasourceId());
    $fields = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
    foreach ($fields as $field) {
      if ($field->getName() === $fieldName) {
        $cardinality = $field->getCardinality();
        return !($cardinality === 1);
      }
    }

    return TRUE;
  }

  /**
   * Get the Vector DB client instance.
   *
   * @return \Drupal\ai\AiVdbProviderInterface
   *   The Vector DB object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getClient(): object {
    if (empty($this->vdbClient)) {
      $this->vdbClient = $this->vdbProviderManager->createInstance($this->configuration['database']);
    }
    return $this->vdbClient;
  }

  /**
   * Check entity access.
   *
   * @param string $drupal_id
   *   The Drupal entity ID.
   *
   * @return bool
   *   If the entity is accessible.
   */
  private function checkEntityAccess(string $drupal_id): bool {
    [$entity_type, $id_lang] = explode('/', str_replace('entity:', '', $drupal_id));
    [$id, $lang] = explode(':', $id_lang);
    /** @var \Drupal\Core\Entity\ContentEntityBase */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
    if ($entity->hasTranslation($lang)) {
      $entity = $entity->getTranslation($lang);
    }
    return $entity->access('view', $this->currentUser);
  }

}
