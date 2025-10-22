<?php

namespace Drupal\ai\Base;

use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai\Validation\EmbeddingValidator;
use Drupal\ai_search\Plugin\Exception\EmbeddingStrategyException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to handle API requests server.
 */
abstract class AiVdbProviderClientBase extends PluginBase implements AiVdbProviderInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Constructs a new AiVdbClientBase abstract class.
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
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MessengerInterface $messenger,
    protected EmbeddingValidator $embeddingValidator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
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
    );
  }

  /**
   * Get the API client.
   *
   * @return mixed
   *   The client.
   */
  abstract public function getClient(): mixed;

  /**
   * {@inheritdoc}
   */
  public function setCustomConfig(array $config): void {
    $this->configuration = $config;
  }

  /**
   * Validate that the retrieving embedding chunks match the expected format.
   *
   * @param array $embedding
   *   The individual embedding returned in the array of embeddings from the
   *   EmbeddingStrategyInterface::getEmbedding() method.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0.
   *   Use \Drupal\ai\Validation\EmbeddingValidator::validate() instead.
   *
   * @see https://www.drupal.org/node/3540894
   */
  public function validateRetrievedEmbedding(array $embedding): void {
    @trigger_error(sprintf('Calling %s() is deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use \Drupal\ai\Validation\EmbeddingValidator::validate() instead. See https://www.drupal.org/node/3540894', __METHOD__), E_USER_DEPRECATED);

    if (!isset($embedding['id'])) {
      throw new EmbeddingStrategyException('The individual embedding chunks must have an id.');
    }
    if (!str_contains($embedding['id'], ':')) {
      throw new EmbeddingStrategyException('The individual embedding IDs must have a unique key per chunk even if only one chunk is returned.');
    }
    $id_parts = explode(':', $embedding['id']);
    if (empty($id_parts[0]) || empty($id_parts[1])) {
      throw new EmbeddingStrategyException('The individual embedding ID prefix and suffix must be filled in.');
    }
    if (!isset($embedding['values'])) {
      throw new EmbeddingStrategyException('The individual embedding chunks must have the vector values.');
    }
    if (!isset($embedding['metadata'])) {
      throw new EmbeddingStrategyException('The individual embedding chunks must have an attached metadata array.');
    }
  }

  /**
   * {@inheritdoc}
   */
  abstract public function deleteItems(array $configuration, array $item_ids): void;

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems(array $configuration, $datasource_id = NULL): void {
    $this->dropCollection(
      collection_name: $configuration['database_settings']['collection'],
      database: $configuration['database_settings']['database_name'],
    );
    $this->createCollection(
      collection_name: $configuration['database_settings']['collection'],
      dimension: $configuration['embeddings_engine_configuration']['dimensions'],
      metric_type: VdbSimilarityMetrics::from($configuration['database_settings']['metric']),
      database: $configuration['database_settings']['database_name'],
    );
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
   * {@inheritdoc}
   */
  public function supportsGrouping(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function vectorSearchWithGrouping(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    QueryInterface $query,
    mixed $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $group_by_field = 'drupal_entity_id',
    int $group_size = 1,
    bool $strict_group_size = FALSE,
    string $database = 'default',
    array $excluded_entity_ids = [],
  ): array {
    throw new \Exception('Grouping search is not supported by this VDB provider.');
  }

  /**
   * {@inheritdoc}
   */
  public function getRawEmbeddingFieldName(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenizerForModel(string $model_id): string {
    // Fallback to the same encoding used by gpt3.5-turbo as a sensible
    // default when the model is not yet supported by TikToken PHP.
    // @see https://github.com/yethee/tiktoken-php/blob/master/src/EncoderProvider.php
    return 'cl100k_base';
  }

}
