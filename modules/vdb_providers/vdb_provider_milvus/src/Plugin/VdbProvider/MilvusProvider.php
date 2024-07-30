<?php

namespace Drupal\vdb_provider_milvus\Plugin\VdbProvider;

use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use HelgeSverre\Milvus\Milvus;

/**
 * Plugin implementation of the 'Milvus DB' provider.
 */
#[AiVdbProvider(
  id: 'milvus',
  label: new TranslatableMarkup('Milvus DB'),
)]
class MilvusProvider extends AiVdbProviderClientBase
  implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Milvus host.
   *
   * @var string
   *   The Milvus host.
   */
  protected string $baseHost = 'milvus-standalone';

  /**
   * The Milvus port.
   *
   * @var string
   *   The Milvus port.
   */
  protected string $port = '19530';

  /**
   * The API key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * The configuration.
   *
   * @var array
   */
  protected array $configuration = [];

  /**
   * The Milvus client
   *
   * @var \HelgeSverre\Milvus\Milvus|null
   */
  protected ?Milvus $client;

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('vdb_provider_milvus.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomConfig(array $config): void {
    $this->config = $config;
  }

  /**
   * Set key for authentication of the client.
   *
   * @param mixed $authentication
   *
   * @return void
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * Gets the raw client.
   *
   * This is the client for inference.
   *
   * @return \HelgeSverre\Milvus\Milvus
   *   The OpenAI client.
   */
  public function getClient(): Milvus {
    if (empty($this->client)) {
      $config = $this->getConfig();
      $token = $this->configuration['api_key'] ?? $config->get('api_key');
      if ($token) {
        $token = $this->keyRepository->getKey($token)->getKeyValue();
      }

      $server = $this->configuration['api_key'] ?? $config->get('server');
      $port = $this->configuration['port'] ?? $config->get('port');
      $this->client = new Milvus(
        token: $token,
        host: $server ?? $this->baseHost,
        port: $port ?? $this->port,
      );
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollections(string $database = 'default'): array {
    return json_decode(
      $this->getClient()->collections()->list(
        dbName: $database,
      ),
      TRUE
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::CosineSimilarity,
    string $database = 'default'
  ): void {
    $metric_name = match ($metric_type) {
      VdbSimilarityMetrics::EuclideanDistance => 'L2',
      VdbSimilarityMetrics::CosineSimilarity => 'COSINE',
      VdbSimilarityMetrics::InnerProduct => 'IP',
    };
    $collections = $this->getCollections($database);
    if (!isset($collections['data']) || !in_array($collection_name, $collections['data'])) {
      $response = $this->getClient()->collections()->create(
        collectionName: $collection_name,
        dimension: $dimension,
        dbName: $database,
        metricType: $metric_name,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default'
  ): void {
    $this->getClient()->collections()->drop(
      collectionName: $collection_name,
      dbName: $database,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default'
  ): void {
    $this->getClient()->vector()->insert(
      collectionName: $collection_name,
      data: $data,
      dbName: $database,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default'
  ): void {
    $this->getClient()->vector()->delete(
      id: $ids,
      collectionName: $collection_name,
      dbName: $database
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters = 'id not in [0]',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default'
  ): array {
    $params = [
      'collectionName' => $collection_name,
      'filter' => $filters,
      'outputFields' => $output_fields,
      'dbName' => $database,
      'limit' => $limit,
      'offset' => $offset
    ];

    $response = $this->getClient()->vector()->query(...$params);
    $data = json_decode($response, TRUE, flags: \JSON_THROW_ON_ERROR);
    return $data['data'] ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default'
  ): array {
    $params = [
      'collectionName' => $collection_name,
      'vector' => $vector_input,
      'outputFields' => $output_fields,
      'dbName' => $database,
      'limit' => $limit,
      'offset' => $offset
    ];

    if ($filters !== '') {
      $params['filter'] = $filters;
    }

    $response = $this->getClient()->vector()->search(...$params);
    $data = json_decode($response, TRUE, flags: \JSON_THROW_ON_ERROR);
    return $data['data'] ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds
  ): array {
    $data = $this->querySearch(
      collection_name: $collection_name,
      output_fields: ['id'],
      filters: "drupal_entity_id in [\"" . implode('","', $drupalIds) . "\"]"
    );
    $ids = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $ids[] = $item['id'];
      }
    }
    return $ids;
  }

}
