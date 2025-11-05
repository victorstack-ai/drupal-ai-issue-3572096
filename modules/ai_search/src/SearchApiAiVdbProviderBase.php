<?php

namespace Drupal\ai_search;

use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\Validation\EmbeddingValidator;
use Drupal\ai_search\Plugin\Exception\EmbeddingStrategyException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Search API VDB (Vector Database) provider plugins.
 *
 * This class provides the basic functionality for Search API VDB providers,
 * including the settings form and search methods.
 */
abstract class SearchApiAiVdbProviderBase extends AiVdbProviderClientBase implements AiVdbProviderSearchApiInterface {

  /**
   * Constructs a new SearchApiAiVdbProviderBase abstract class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\ai\Validation\EmbeddingValidator $embeddingValidator
   *   The embedding validator.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MessengerInterface $messenger,
    protected EmbeddingValidator $embeddingValidator,
    protected Connection $database,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->configFactory,
      $this->entityFieldManager,
      $this->messenger,
      $this->embeddingValidator,
    );
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AiVdbProviderClientBase | static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('messenger'),
      $container->get('ai.embedding_validator'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['database_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Name'),
      '#description' => $this->t('The database name to use.'),
      '#default_value' => $configuration['database_settings']['database_name'] ?? NULL,
      '#required' => TRUE,
      '#pattern' => '[a-zA-Z0-9_]*',
      '#disabled' => FALSE,
    ];

    $form['collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Collection'),
      '#description' => $this->t('The collection to use. This will be generated if it does not exist and cannot be changed.'),
      '#default_value' => $configuration['database_settings']['collection'] ?? NULL,
      '#required' => TRUE,
      '#pattern' => '[a-zA-Z0-9_]*',
      '#disabled' => FALSE,
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
      '#default_value' => $configuration['database_settings']['metric'] ?? VdbSimilarityMetrics::CosineSimilarity->value,
      '#description' => $this->t('The metric to use for similarity calculations.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array &$form, FormStateInterface $form_state): void {
    $database_settings = $form_state->getValue('database_settings');
    $collections = $this->getCollections();

    // Check that the collection doesn't exist already.
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    if (
      $entity->isNew()
      && isset($collections['data'])
      && isset($database_settings['collection'])
      && in_array($database_settings['collection'], $collections['data'])
    ) {
      $form_state->setErrorByName('database_settings][collection', $this->t('The collection already exists in the selected vector database.'));
    }

    // Ensure the vector database selected has already been configured to
    // avoid a fatal error.
    if (!$this->isSetup()) {
      $form_state->setErrorByName('database_settings][database', $this->t('The selected vector database has not yet been configured. <a href="@url">Please configure it first</a>.', [
        '@url' => Url::fromRoute('ai.admin_vdb_providers')->toString(),
      ]));
    }

    // Ensure that the user has been offered to configure the metrics, needed
    // if JS is disabled.
    if (!isset($database_settings['metric'])) {
      $form_state->setRebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitSettingsForm(array &$form, FormStateInterface $form_state): void {
    $database_settings = $form_state->getValue('database_settings');
    $this->createCollection(
      collection_name: $database_settings['collection'],
      dimension: $form_state->getValue('embeddings_engine_configuration')['dimensions'],
      metric_type: VdbSimilarityMetrics::from($database_settings['metric']),
      database: $database_settings['database_name'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewIndexSettings(array $database_settings): array {
    return [];
  }

  /**
   * In long running chunking, PHP Maximum Execution Time can otherwise be hit.
   *
   * @return int
   *   The number of chunks to index in a batch.
   */
  protected function getMaximumChunksPerIndexItems(): int {
    return 10;
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(
    array $configuration,
    IndexInterface $index,
    array $items,
    EmbeddingStrategyInterface $embedding_strategy,
  ): array {
    $successfulItemIds = [];

    $itemIds = array_values(array_map(function ($item) {
      return $item->getId();
    }, $items));

    // Get the items that are currently being processed, where there was not
    // enough processing budget to handle all chunks.
    $processedStatus = $this->database->select('search_api_item', 'sai')
      ->fields('sai', ['item_id', 'processed_chunks'])
      ->condition('index_id', $index->id())
      ->condition('item_id', $itemIds, 'IN')
      ->execute()
      ->fetchAllKeyed();

    // Delete items that have not yet had processing started. This is needed
    // because the chunk count for the entity can change, so we need to start
    // fresh each reindexing.
    $deleteItemIds = array_diff($itemIds, array_keys(array_filter($processedStatus)));
    if (!empty($deleteItemIds)) {
      $this->deleteIndexItems($configuration, $index, $deleteItemIds);
    }

    $remainingMaximumChunksToProcess = $this->getMaximumChunksPerIndexItems();

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      if ($remainingMaximumChunksToProcess <= 0) {
        break;
      }

      $itemId = $item->getId();
      $allChunks = $embedding_strategy->getChunks(
        $configuration['embeddings_engine'],
        $configuration['embedding_strategy_configuration'],
        $item->getFields(),
        $item,
        $index,
      );
      $totalChunks = count($allChunks);
      $offset = $processedStatus[$itemId] ?? 0;

      // Calculate how many chunks are left to process for this specific item.
      $chunksLeftForItem = $totalChunks - $offset;

      // Determine how many chunks to take in this run: either all remaining
      // chunks for the item, or the rest of our batch budget, whichever is
      // smaller.
      $chunksToTake = min($chunksLeftForItem, $remainingMaximumChunksToProcess);

      if ($chunksToTake <= 0) {
        // This item may be fully processed already, or there's no budget left.
        if ($offset >= $totalChunks) {
          $successfulItemIds[] = $itemId;
        }
        continue;
      }

      $chunks = array_slice($allChunks, $offset, $chunksToTake);

      // If the item is not fully processed, update the processed chunks.
      if (($offset + count($chunks)) < $totalChunks) {
        $this->database->update('search_api_item')
          ->fields([
            'processed_chunks' => $offset + count($chunks),
            'total_chunks' => $totalChunks,
          ])
          ->condition('index_id', $index->id())
          ->condition('item_id', $itemId)
          ->execute();
      }
      else {

        // Store the totals. It is not strictly necessary to track progress on
        // anything other than entities that have not indexed in one go, but it
        // makes it easier to debug.
        $this->database->update('search_api_item')
          ->fields([
            'processed_chunks' => $totalChunks,
            'total_chunks' => $totalChunks,
          ])
          ->condition('index_id', $index->id())
          ->condition('item_id', $itemId)
          ->execute();
      }

      try {
        $embeddings = $embedding_strategy->getEmbedding(
          $chunks,
          $item->getFields(),
          $item,
          $index,
        );
      }
      catch (AiUnsafePromptException $e) {
        $this->getLogger('ai_search')->warning('Skipping item @id due to unsafe prompt: @message', [
          '@id' => $itemId,
          '@message' => $e->getMessage(),
        ]);
        continue;
      }

      /** @var \Drupal\ai\Embedding $embedding */
      foreach ($embeddings as $embedding) {
        // Ensure consistent embedding structure as per
        // EmbeddingStrategyInterface.
        $violations = $this->embeddingValidator->validate($embedding);
        if (count($violations) > 0) {
          throw new EmbeddingStrategyException("The embedding object must be valid: \n$violations");
        }

        // Merge the base array structure with the individual chunk array
        // structure and add additional details.
        $embedding->putMetadata('server_id', $index->getServerId());
        $embedding->putMetadata('index_id', $index->id());
        $data['drupal_long_id'] = $embedding->id;
        $data['drupal_entity_id'] = $itemId;
        $data['vector'] = $embedding->values;
        foreach ($embedding->getMetadata() as $key => $value) {
          $data[$key] = $value;
        }
        $this->insertIntoCollection(
          collection_name: $configuration['database_settings']['collection'],
          data: $data,
          database: $configuration['database_settings']['database_name'],
        );
      }

      // Mark an item as successful only if all chunks have been processed.
      // We otherwise need the batch processing to pick this item up again
      // next batch run and continue where it left off.
      $remainingMaximumChunksToProcess -= count($chunks);
      if (($offset + count($chunks)) >= $totalChunks) {
        $successfulItemIds[] = $itemId;
      }
    }

    return $successfulItemIds;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIndexItems(array $configuration, IndexInterface $index, array $item_ids): void {
    $this->deleteItems($configuration, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(array $configuration, IndexInterface $index, $datasource_id = NULL): void {
    $this->deleteAllItems($configuration, $datasource_id);
  }

}
