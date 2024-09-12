<?php

namespace Drupal\ai;

use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Defines an interface for VDB (Vector Database) provider services.
 */
interface AiVdbProviderInterface extends PluginInspectionInterface {

  /**
   * Sets configuration of the database connection.
   *
   * @param array $config
   *   Configuration of client.
   */
  public function setCustomConfig(array $config): void;

  /**
   * Gets the configuration of the database.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration.
   */
  public function getConfig(): ImmutableConfig;

  /**
   * Ping to check so the service/server is available.
   *
   * @return bool
   *   True if the service is available.
   */
  public function ping(): bool;

  /**
   * Checks if the service is setup.
   *
   * @return bool
   *   True if the service is setup.
   */
  public function isSetup(): bool;

  /**
   * Get array of existing collections on a database.
   *
   * @param string $database
   *   The database name.
   *
   * @return array
   *   Array of collection names.
   */
  public function getCollections(string $database = 'default'): array;

  /**
   * Creates a collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param int $dimension
   *   The dimension of the vectors.
   * @param \Drupal\ai\Enum\VdbSimilarityMetrics $metric_type
   *   The metric type.
   * @param string $database
   *   The database name.
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::EuclideanDistance,
    string $database = 'default',
  ): void;

  /**
   * Drop collection from database.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param string $database
   *   The database name.
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
  ): void;

  /**
   * Insert record into collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $data
   *   The data to insert.
   * @param string $database
   *   The database name.
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default',
  ): void;

  /**
   * Delete records from collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $ids
   *   The IDs to delete.
   * @param string $database
   *   The database name.
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default',
  ): void;

  /**
   * Conduct query search.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $output_fields
   *   The output fields.
   * @param string $filters
   *   The filters.
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The results.
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array;

  /**
   * Conduct vector search.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $vector_input
   *   The vector input.
   * @param array $output_fields
   *   The output fields.
   * @param string $filters
   *   The filters.
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The results.
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array;

  /**
   * Facade method to convert Drupal Entity IDs into Vector DB IDs.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $drupalIds
   *   The Drupal IDs.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The VDB IDs.
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds,
    string $database = 'default',
  ): array;

}
