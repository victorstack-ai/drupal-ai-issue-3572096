<?php

namespace Drupal\ai_search;

use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai_search\Plugin\Exception\EmbeddingStrategyException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;

/**
 * Base class for Search API VDB (Vector Database) provider plugins.
 *
 * This class provides the basic functionality for Search API VDB providers,
 * including the settings form and search methods.
 */
abstract class SearchApiAiVdbProviderBase extends AiVdbProviderClientBase implements AiVdbProviderSearchApiInterface {

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
   * {@inheritdoc}
   */
  public function indexItems(array $configuration, IndexInterface $index, array $items, EmbeddingStrategyInterface $embedding_strategy): array {
    $successfulItemIds = [];

    // Check if we need to delete some items first.
    $this->deleteIndexItems($configuration, $index, array_values(array_map(function ($item) {
      return $item->getId();
    }, $items)));

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $item_id = $item->getId();
      try {
        $embeddings = $embedding_strategy->getEmbedding(
          $configuration['embeddings_engine'],
          $configuration['embedding_strategy_configuration'],
          $item->getFields(),
          $item,
          $index,
        );
      }
      catch (AiUnsafePromptException $e) {
        // Log the exception and skip this item.
        $logger = $this->getLogger('ai_search');
        $logger->warning('Skipping item @id due to unsafe prompt: @message', [
          '@id' => $item_id,
          '@message' => $e->getMessage(),
        ]);
        continue;
      }

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
        $data['drupal_entity_id'] = $item_id;
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

      $successfulItemIds[] = $item_id;
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
