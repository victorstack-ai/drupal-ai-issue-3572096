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
   */
  public function getConfig(): ImmutableConfig;

  /**
   * Get array of existing collections on a database.
   *
   * @param string $database
   *
   * @return array
   */
  public function getCollections(string $database = 'default'): array;

  /**
   * Creates a collection.
   *
   * @param string $collection_name
   * @param int $dimension
   * @param \Drupal\ai\Enum\VdbSimilarityMetrics $metric_type
   * @param string $database
   *
   * @return void
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::EuclideanDistance,
    string $database = 'default'
  ): void;

  /**
   * Drop collection from database.
   *
   * @param string $collection_name
   * @param string $database
   *
   * @return void
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default'
  ): void;

  /**
   * Insert record into collection.
   *
   * @param string $collection_name
   * @param array $data
   * @param string $database
   *
   * @return void
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default'
  ): void;

  /**
   * delete records from collection.
   *
   * @param string $collection_name
   * @param array $ids
   * @param string $database
   *
   * @return void
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default'
  ): void;

  /**
   * Conduct query search.
   *
   * @param string $collection_name
   * @param array $output_fields
   * @param string $filters
   * @param int $limit
   * @param int $offset
   * @param string $database
   *
   * @return array
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default'
  ): array;

  /**
   * Conduct vector search.
   *
   * @param string $collection_name
   * @param array $vector_input
   * @param array $output_fields
   * @param string $filters
   * @param int $limit
   * @param int $offset
   * @param string $database
   *
   * @return array
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default'
  ): array;

  /**
   * Facade method to convert Drupal Entity IDs into Vector DB IDs.
   *
   * @param string $collection_name
   * @param array $drupalIds
   *
   * @return array
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds
  ): array;

}
