<?php

namespace Drupal\ai_search\Plugin\search_api\backend;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\ai_search\Backend\AiSearchBackendPluginBase;
use Drupal\ai_search\EmbeddingStrategyPluginManager;
use Drupal\search_api\Backend\BackendSpecificInterface;
use Drupal\search_api\IndexInterface;
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
class SearchApiAiSearchBackend extends AiSearchBackendPluginBase implements PluginFormInterface, BackendSpecificInterface {

  use PluginDependencyTrait;

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
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    $instance->logger = $container->get('logger.factory')->get('ai_search');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors(): array {
    return [
      // We convert to markdown which LLMs understand.
      'html_filter',
      // Boosting does not apply here.
      'number_field_boost',
      // There is no point, vectors inherently do not need this.
      'stemmer',
      // We use our own more advanced embedding strategies.
      'tokenizer',
      // Boosting does not apply here.
      'type_boost',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    if (!isset($config['database'])) {
      $config['database'] = NULL;
    }
    if (!isset($config['database_settings'])) {
      $config['database_settings'] = [];
    }
    if (!isset($config['embedding_strategy'])) {
      $config['embedding_strategy'] = NULL;
    }
    // Add default for including raw embedding vector.
    $config['include_raw_embedding_vector'] = FALSE;
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
      $errors[] = '<div class="ai-error">' . $this->t('No AI providers are installed for Embeddings calls. Choose a provider from the <a href="@ai">AI module homepage</a>, add it to your project, then %install and %configure it first.', [
        '%ai' => 'https://www.drupal.org/project/ai',
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
      ]) . '</div>';
    }

    $search_api_vdb_providers = $this->vdbProviderManager->getSearchApiProviders(TRUE);
    if (empty($search_api_vdb_providers)) {
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

    // Get all supported models, default to gpt-3.5 model.
    $supported_models = $this->tokenizer->getSupportedModels();
    $default_model_possibilities = array_keys(array_filter($supported_models, function ($model) {
      return str_contains($model, 'gpt-3.5');
    }, ARRAY_FILTER_USE_KEY));
    $default_model = reset($default_model_possibilities);
    $form['chat_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Tokenizer chat counting model'),
      '#description' => $this->t('This is recommended to ensure the right number of tokens is calculated for the embeddings. Depending on the vector database and dimensions, the number of Tokens allowed per chunk of content differs. This service is used to count the number of tokens in your content as accurately as possible to better make use of the available space.'),
      '#default_value' => $this->configuration['chat_model'] ?? $default_model,
      '#options' => $this->tokenizer->getSupportedModels(),
      '#weight' => 2,
    ];

    $form['include_raw_embedding_vector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include raw embedding vector in results'),
      '#description' => $this->t("If checked, the raw embedding vector will be fetched from the VDB and added to the search result item's extra data. This is useful for features like re-ranking but may have a minor performance impact."),
      '#default_value' => $this->configuration['include_raw_embedding_vector'] ?? FALSE,
      '#weight' => 2.5,
    ];

    $chosen_database = $this->configuration['database'] ?? NULL;
    if (!$chosen_database) {
      // Try to get from form state.
      $chosen_database = $form_state->get('database') ?? NULL;
    }

    $form['database'] = [
      '#type' => 'select',
      '#title' => $this->t('Vector Database'),
      '#options' => $search_api_vdb_providers,
      '#required' => TRUE,
      '#default_value' => $chosen_database,
      '#description' => $this->t("The Vector Database to use. This is where the generated Embeddings (vectorized representations of your content) are stored. The user's queries are then vectorized in the same manner and the mathematical distance between the query and the vectors stored in the database are compared to find the nearest results."),
      '#ajax' => [
        'callback' => [$this, 'updateVectorDatabaseSettingsForm'],
        'event' => 'change',
        'method' => 'replaceWith',
        'wrapper' => 'database-settings-wrapper',
      ],
      '#weight' => 3,
    ];

    // Container for database-specific settings.
    $form['database_settings'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#attributes' => ['id' => 'database-settings-wrapper'],
      '#title' => $this->t('Vector Database Configuration'),
      '#weight' => 4,
    ];

    // If a Vector Database has been chosen, build the custom fields.
    if ($chosen_database) {

      // Only open the settings once there is a chosen database.
      $form['database_settings']['#open'] = TRUE;

      $vdb_client = $this->vdbProviderManager->createInstance($chosen_database);
      $form['database_settings'] = $vdb_client->buildSettingsForm(
        $form['database_settings'],
        $form_state,
        $this->configuration
      );
    }

    // Add Embeddings Engine or Embeddings Strategy subform.
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * AJAX callback to update the database-specific fields.
   */
  public function updateVectorDatabaseSettingsForm(array &$form, FormStateInterface $form_state): array {
    return $form['backend_config']['database_settings'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check whether Vector DB providers are available.
    if (empty($this->vdbProviderManager->getSearchApiProviders(TRUE))) {
      $form_state->setError($form, $this->t('No Vector DB providers are installed or setup for search in vectors, please %install and %configure one first.', [
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_vdb_providers')->toString(),
      ]));
    }

    if (
      !empty($values['embeddings_engine'])
      && isset($values['embeddings_engine_configuration']['dimensions'])
      && $values['embeddings_engine_configuration']['dimensions'] <= 0
    ) {
      $form_state->setErrorByName('embeddings_engine_configuration][dimensions', $this->t('Embeddings engine configuration "dimensions" must be provided and must be greater than 0'));
    }

    if (!empty($form_state->getValue('database'))) {
      try {
        $vdb_client = $this->vdbProviderManager->createInstance($form_state->getValue('database'));
        $vdb_client->validateSettingsForm($form, $form_state);
      }
      catch (\Exception $exception) {
        $form_state->setErrorByName('database', $this->t('An error occurred: "@error"', [
          '@error' => $exception->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
    if ($this->configuration['embedding_strategy_container']) {
      $this->configuration = array_merge($this->configuration, $this->configuration['embedding_strategy_container']);
      unset($this->configuration['embedding_strategy_container']);
    }
    parent::setConfiguration($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    $is_configured = FALSE;
    $vdb_client_available = FALSE;
    try {
      $vdb_client = $this->getVectorDb();
      $is_configured = $vdb_client->isSetup();
      $vdb_client_available = $vdb_client->ping();

      return $is_configured && $vdb_client_available;
    }
    catch (\Exception $exception) {
      $this->logException($exception);
      // If any exception was thrown we consider the server to be unavailable.
      return FALSE;
    }
    finally {
      if ($is_configured && !$vdb_client_available) {
        $this->messenger
          ->addWarning($this->t('Server %server is configured, but the configured collection %core is not available.', [
            '%server' => $this->getServer()->label(),
            '%core' => $this->configuration['database_settings']['collection'] ?? 'n/a',
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
    $this->setConfiguration($form_state->getValues());
    if (!$this->ensureCollectionExists()) {
      $this->messenger()->addError($this->t('Could not create the collection.'));
    }
  }

  /**
   * Ensure that the backend collection has been created.
   */
  protected function ensureCollectionExists() {
    $vdb_client = $this->getVectorDb();
    return $this->vdbProviderManager->ensureCollectionExists($vdb_client, $this->configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function indexItems(IndexInterface $index, array $items): array {
    $embedding_strategy = $this->embeddingStrategyProviderManager->createInstance($this->configuration['embedding_strategy']);
    return $this->getVectorDb()->indexItems($this->configuration, $index, $items, $embedding_strategy);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    /** @var \Drupal\ai\AiVdbProviderInterface $vdb_client */
    $vdb_client = $this->getVectorDb();
    $vdb_client->deleteIndexItems($this->configuration, $index, $item_ids);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    $vdb_client = $this->getVectorDb();
    $vdb_client->deleteAllIndexItems($this->configuration, $index, $datasource_id);
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

    // Determine default relevance order. Typically, this is in descending order
    // but a search could specify that it wants the least relevant.
    $sorts = $query->getSorts();
    $relevance_order = $sorts['search_api_relevance'] ?? 'DESC';

    // Prepare params.
    $params = [
      'collection_name' => $this->configuration['database_settings']['collection'],
      'output_fields' => ['id', 'drupal_entity_id', 'drupal_long_id', 'content'],
      // If an access check is in place, multiple iterations of the query are
      // run to attempt to reach this limit.
      'limit' => (int) $query->getOption('limit', 10),
      'offset' => (int) $query->getOption('offset', 0),
    ];

    /** @var \Drupal\ai\AiVdbProviderInterface $vdb_client */
    $vdb_client = $this->getVectorDb();

    // Check if we need to include the raw embedding vector.
    if (!empty($this->configuration['include_raw_embedding_vector'])) {
      $raw_embedding_field_name = $vdb_client->getRawEmbeddingFieldName();
      if (!empty($raw_embedding_field_name)) {
        $params['output_fields'][] = $raw_embedding_field_name;
        // Store for extractMetadata to use, without changing its signature.
        $query->setOption('search_api_ai_retrieved_embedding_field_name', $raw_embedding_field_name);
      }
    }

    if ($filters = $vdb_client->prepareFilters($query)) {
      $params['filters'] = $filters;
    }

    // Conduct the search.
    $real_results = [];
    $meta_data = $this->doSearch($query, $params, $bypass_access, $real_results, $params['limit'], $params['offset']);

    // Keep track of items already added so existing result items do not get
    // overwritten by later records containing the same item. Store by ID
    // as key, and score as value. This is more efficient than checking if the
    // item already exists in the result set.
    $stored_items = [];

    // Obtain results.
    foreach ($real_results as $match) {
      $id = $get_chunked ? $match['drupal_entity_id'] . ':' . $match['id'] : $match['drupal_entity_id'];
      $item = $this->getFieldsHelper()->createItem($index, $id);
      $item->setScore($match['distance'] ?? 1);
      $this->extractMetadata($match, $item, $query);

      // Adding result items always overwrites, see the Result Set class in
      // Search API. Ensure that the items with the desired highest or lowest
      // score are what get stored.
      if (array_key_exists($item->getId(), $stored_items)) {

        // In this scenario, we have already added the item. Decide whether to
        // overwrite based on score.
        if ($relevance_order === 'DESC' && $item->getScore() > $stored_items[$item->getId()]) {

          // Store only the highest score.
          $results->addResultItem($item);
          $stored_items[$item->getId()] = $item->getScore();
        }
        elseif ($relevance_order === 'ASC' && $item->getScore() < $stored_items[$item->getId()]) {

          // Store only the lowest score.
          $results->addResultItem($item);
          $stored_items[$item->getId()] = $item->getScore();
        }
      }
      else {

        // This is a new result in the result set, not previously found in other
        // chunks: store it.
        $results->addResultItem($item);
        $stored_items[$item->getId()] = $item->getScore();
      }
    }
    $results->setExtraData('real_offset', $meta_data['real_offset']);
    $results->setExtraData('reason_for_finish', $meta_data['reason']);
    // Get the last vector score.
    $results->setExtraData('current_vector_score', $meta_data['vector_score'] ?? 0);

    // Sort results.
    if (!empty($sorts['search_api_relevance'])) {
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
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param array $params
   *   Search parameters.
   * @param bool $bypass_access
   *   Whether to bypass access checks.
   * @param array $results
   *   Array to store results (passed by reference).
   * @param int $start_limit
   *   Maximum number of results to return.
   * @param int $start_offset
   *   Starting offset for results.
   * @param int $iteration
   *   Current iteration number for recursive calls.
   *
   * @return array
   *   Metadata about the search including offset, reason, and vector score.
   */
  protected function doSearch(QueryInterface $query, array $params, bool $bypass_access, array &$results, int $start_limit, int $start_offset, int $iteration = 0): array {
    $params['database'] = $this->configuration['database_settings']['database_name'];
    $params['collection_name'] = $this->configuration['database_settings']['collection'];

    try {
      $vdb_client = $this->getVectorDb();
    }
    catch (PluginException $e) {
      $this->logger->error('Failed to get VDB client: @message', ['@message' => $e->getMessage()]);
      return [
        'real_offset' => $start_offset,
        'reason' => 'client_error',
        'vector_score' => 0,
        'error' => $e->getMessage(),
      ];
    }

    $get_chunked = $query->getOption('search_api_ai_get_chunks_result', FALSE);
    $use_grouping = !$bypass_access && !$get_chunked && method_exists($vdb_client, 'supportsGrouping') && $vdb_client->supportsGrouping();

    // Prepare search parameters.
    $search_words = $query->getKeys();
    if (!empty($search_words)) {
      [$provider_id, $model_id] = explode('__', $this->configuration['embeddings_engine']);
      $embedding_llm = $this->aiProviderManager->createInstance($provider_id);

      if (!isset($params['vector_input'])) {
        if (is_array($search_words)) {
          if (isset($search_words['#conjunction'])) {
            unset($search_words['#conjunction']);
          }
          $search_words = implode(' ', $search_words);
        }
        $input = new EmbeddingsInput($search_words, NULL, FALSE);
        $params['vector_input'] = $embedding_llm->embeddings($input, $model_id)->getNormalized();
      }
      $params['query'] = $query;
    }

    // Use grouping if supported and entity-level results wanted.
    if ($use_grouping) {
      if (!empty($params['vector_input'])) {
        $response = $vdb_client->vectorSearchWithGrouping(...$params);
      }
      else {
        $response = $vdb_client->querySearch(...$params);
      }

      return $this->processResults($response, $query, $bypass_access, $results, $start_limit, $start_offset);
    }

    // Standard search with iteration for access checks if needed.
    try {
      return $this->doSearchWithIteration($query, $params, $bypass_access, $results, $start_limit, $start_offset, $iteration);
    }
    catch (PluginException $e) {
      $this->logger->error('Failed to execute search with iteration: @message', ['@message' => $e->getMessage()]);
      return [
        'real_offset' => $start_offset,
        'reason' => 'search_error',
        'vector_score' => 0,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Process search results with optional access checks.
   *
   * @param array $response
   *   The search response from VDB.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param bool $bypass_access
   *   Whether to bypass access checks.
   * @param array $results
   *   Array to store results (passed by reference).
   * @param int $start_limit
   *   Maximum number of results to return.
   * @param int $start_offset
   *   Starting offset for results.
   *
   * @return array
   *   Metadata about the search including offset, reason, and vector score.
   */
  protected function processResults(
    array $response,
    QueryInterface $query,
    bool $bypass_access,
    array &$results,
    int $start_limit,
    int $start_offset,
  ): array {

    $i = 0;
    foreach ($response as $match) {
      if (is_object($match)) {
        $match = (array) $match;
      }
      $i++;

      if (!$bypass_access && !$this->checkEntityAccess($match['drupal_entity_id'])) {
        continue;
      }

      $results[] = $match;
      if (count($results) == $start_limit) {
        break;
      }
    }

    return [
      'real_offset' => $start_offset + $i,
      'reason' => count($results) == $start_limit ? 'limit' : 'reached_end',
      'vector_score' => end($results)['distance'] ?? 0,
    ];
  }

  /**
   * Search implementation with iteration for VDBs without advanced features.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param array $params
   *   Search parameters.
   * @param bool $bypass_access
   *   Whether to bypass access checks.
   * @param array $results
   *   Array to store results (passed by reference).
   * @param int $start_limit
   *   Maximum number of results to return.
   * @param int $start_offset
   *   Starting offset for results.
   * @param int $iteration
   *   Current iteration number for recursive calls.
   * @param array $excluded_entity_ids
   *   Entity IDs to exclude from results.
   *
   * @return array
   *   Metadata about the search including offset, reason, and vector score.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function doSearchWithIteration(
    QueryInterface $query,
    array $params,
    bool $bypass_access,
    array &$results,
    int $start_limit,
    int $start_offset,
    int $iteration = 0,
    array $excluded_entity_ids = [],
  ): array {

    // Track excluded IDs for NOT IN filtering in subsequent iterations.
    // The key we use depends on whether we're getting chunked results or not.
    $local_excluded_ids = [];

    // Conduct the search.
    if (!$bypass_access) {
      // Double the results, if we need to run over access checks.
      $params['limit'] = $start_limit * 2;
      $params['offset'] = $start_offset + ($iteration * $start_limit * 2);
    }

    try {
      $vdb_client = $this->getVectorDb();
    }
    catch (PluginException $e) {
      // Log the error and return empty results with error metadata.
      $this->logger->error('Failed to get VDB client: @message', ['@message' => $e->getMessage()]);
      return [
        'real_offset' => $start_offset,
        'reason' => 'client_error',
        'vector_score' => 0,
        'error' => $e->getMessage(),
      ];
    }

    $vector_input = $this->getSearchVectorInput($query, $params);
    if (!empty($vector_input)) {
      $params['vector_input'] = $vector_input;
      $params['query'] = $query;
      $response = $vdb_client->vectorSearch(...$params);
    }
    else {
      $response = $vdb_client->querySearch(...$params);
    }

    // Obtain results.
    $i = 0;
    $get_chunked = $query->getOption('search_api_ai_get_chunks_result', FALSE);
    foreach ($response as $match) {
      if (is_object($match)) {
        $match = (array) $match;
      }
      $i++;

      // Determine the appropriate ID to check for exclusion.
      // If getting chunked results, use drupal_long_id to avoid duplicate
      // chunks but allow multiple chunks from the same entity.
      // If getting entity results, use drupal_entity_id to avoid duplicate
      // entities.
      $exclusion_id = $get_chunked ? $match['drupal_long_id'] : $match['drupal_entity_id'];

      // Skip if this ID was already found in previous iterations.
      if (in_array($exclusion_id, $excluded_entity_ids) || in_array($exclusion_id, $local_excluded_ids)) {
        continue;
      }

      // Do access checks.
      if (!$bypass_access && !$this->checkEntityAccess($match['drupal_entity_id'])) {
        // If we are not allowed to view this entity, we can skip it.
        continue;
      }
      // Passed.
      $results[] = $match;
      $local_excluded_ids[] = $exclusion_id;

      // If we found enough items, we can stop.
      if (count($results) == $start_limit) {
        return [
          'real_offset' => $start_offset + ($iteration * $start_limit * 2) + $i,
          'reason' => 'limit',
          'vector_score' => $match['distance'] ?? 0,
        ];
      }
    }

    // If we reach max retries, we can stop.
    if ($iteration == $this->maxAccessRetries) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'max_retries',
        'vector_score' => $match['distance'] ?? 0,
      ];
    }
    // If we got less then limit back, it reached the end.
    if (count($response) < $start_limit) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'reached_end',
        'vector_score' => $match['distance'] ?? 0,
      ];
    }
    // Else we need to continue.
    $combined_excluded_ids = array_merge($excluded_entity_ids, $local_excluded_ids);

    // Update the exclusions when we are not getting chunks.
    if (!empty($combined_excluded_ids) && !$get_chunked) {
      $query->setOption('search_api_ai_excluded_entity_ids', $combined_excluded_ids);
    }

    return $this->doSearchWithIteration($query, $params, $bypass_access, $results, $start_limit, $start_offset, $iteration + 1, $combined_excluded_ids);
  }

  /**
   * Get the Vector Input for the search.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API Query.
   * @param array $params
   *   The params being prepared for passing to the VDB Provider client.
   *
   * @return array
   *   The vector input to search on. The array could be empty if unable to
   *   generate embeddings from the content input.
   */
  protected function getSearchVectorInput(QueryInterface $query, array $params): array {
    // If we already have vector input set via iteration, no need to get it
    // again.
    if (isset($params['vector_input']) && !empty($params['vector_input'])) {
      return $params['vector_input'];
    }

    // Allow developers to provide the input prior to calling the AI Search
    // back-end. This could be done for example in a Views Filter Handler.
    if ($vector_input = $query->getOption('vector_input', FALSE)) {
      return $vector_input;
    }

    // Fall back to generating new vector input based on the search terms
    // provided.
    $search_words = $query->getKeys();
    if (!empty($search_words) && is_array($search_words)) {

      // Search words are sent to Search API as separate terms. For semantic
      // vector search however, we want to just pass the full input as a string
      // to retrieve a single vector input based on the complete input content.
      if (isset($search_words['#conjunction'])) {
        unset($search_words['#conjunction']);
      }
      // Final check that it's still not empty after removing conjunction, as
      // embeddings input must receive a string.
      if (!empty($search_words)) {
        $search_words = implode(' ', $search_words);

        // Convert the search terms to vector input.
        [$provider_id, $model_id] = explode('__', $this->configuration['embeddings_engine']);
        $embedding_llm = $this->aiProviderManager->createInstance($provider_id);
        $input = new EmbeddingsInput($search_words, NULL, FALSE);
        return $embedding_llm->embeddings($input, $model_id)->getNormalized();
      }
    }

    return [];
  }

  /**
   * Extract query metadata values to a result item.
   *
   * @param array $result_row
   *   The result row.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   * @param \Drupal\search_api\Query\QueryInterface|null $query
   *   The search query, or NULL if not available.
   */
  public function extractMetadata(array $result_row, ItemInterface $item, ?QueryInterface $query = NULL): void {
    $raw_embedding_field_name = $query ? $query->getOption('search_api_ai_retrieved_embedding_field_name') : NULL;

    foreach ($result_row as $key => $value) {
      // Skip default fields and the dynamically retrieved raw embedding field.
      if ($key === 'vector' || $key === 'id' || $key === 'distance' || ($raw_embedding_field_name && $key === $raw_embedding_field_name)) {
        continue;
      }
      $item->setExtraData($key, $value);
    }

    // If a raw embedding field name was configured and,
    // its data exists in the result, add it.
    if ($raw_embedding_field_name && isset($result_row[$raw_embedding_field_name])) {
      $item->setExtraData('raw_vector', $result_row[$raw_embedding_field_name]);
    }
  }

  /**
   * Get the Vector DB client instance.
   *
   * @return \Drupal\ai\AiVdbProviderInterface
   *   The Vector DB object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getVectorDb(): object {
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
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);

    // If the entity fails to load, assume false.
    if (!$entity instanceof EntityInterface) {
      return FALSE;
    }

    // Get the entity translation if a specific language is requested so long
    // as the entity is translatable in the first place.
    if (
      $entity instanceof TranslatableInterface
      && $entity->hasTranslation($lang)
    ) {
      $entity = $entity->getTranslation($lang);
    }
    return $entity->access('view', $this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings(): array {
    $info = [];

    $database_settings = $this->configuration['database_settings'];
    $vdb_provider_status = NULL;
    if ($this->vdbProviderManager->hasDefinition($this->configuration['database'])) {
      $vdb_client = $this->getVectorDb();
      $vdb_provider_label = $vdb_client->getPluginDefinition()['label'];
      if ($vdb_client->isSetup()) {
        $vdb_info = $vdb_provider_label;
      }
      else {
        $vdb_info = $this->t('The %provider vector database provider has not been fully setup.', [
          '%provider' => $vdb_provider_label,
        ]);
        $vdb_provider_status = 'warning';
      }
    }
    else {
      $vdb_info = $this->t('The %provider vector database provider is not currently available.', [
        '%provider' => $this->configuration['database'],
      ]);
      $vdb_provider_status = 'error';
    }
    $info[] = [
      'label' => $this->t('Vector Database'),
      'info' => $vdb_info,
      'status' => $vdb_provider_status,
    ];

    $vdb_client = $this->getVectorDb();
    $info[] = [
      'label' => $this->t('Database name'),
      'info' => $database_settings['database_name'],
    ];
    $info[] = [
      'label' => $this->t('Collection name'),
      'info' => $database_settings['collection'],
    ];
    $collections = $this->ensureCollectionExists();
    $collection_status = [
      'label' => $this->t('Collection status'),
    ];
    if ($collections) {
      $collection_status['info'] = $this->t('Successfully connected');
    }
    else {
      $collection_status['info'] = $this->t('The collection %collection could not be connected to or created.', [
        '%collection' => $database_settings['collection'],
      ]);
      $collection_status['status'] = 'error';
      $this->messenger()->addWarning($collection_status['info']);
    }
    $info[] = $collection_status;
    $supported_models = $this->tokenizer->getSupportedModels();
    $info[] = [
      'label' => $this->t('Chat model'),
      'info' => $supported_models[$this->configuration['chat_model']] ?? $this->t('Could not resolve the %chat_model chat model.', [
        '%chat_model' => $this->configuration['chat_model'],
      ]),
      'status' => !isset($supported_models[$this->configuration['chat_model']]) ? 'error' : NULL,
    ];
    $embedding_options = $this->getEmbeddingEnginesOptions();
    $info[] = [
      'label' => $this->t('Embeddings engine'),
      'info' => $embedding_options[$this->configuration['embeddings_engine']] ?? $this->t('Could not resolve the %embeddings_engine embeddings engine.', [
        '%embeddings_engine' => $this->configuration['embeddings_engine'],
      ]),
      'status' => !isset($embedding_options[$this->configuration['embeddings_engine']]) ? 'error' : NULL,
    ];

    return array_merge($info, $vdb_client->viewIndexSettings($this->configuration['database_settings']));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $vdb_client = $this->getVectorDb();
    // @todo This ignore next line can be removed after Search API 2.0.x is
    // released.
    // @phpstan-ignore-next-line
    return $this->getPluginDependencies($vdb_client);
  }

}
