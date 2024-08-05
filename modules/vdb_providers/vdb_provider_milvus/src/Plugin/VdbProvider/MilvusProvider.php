<?php

namespace Drupal\vdb_provider_milvus\Plugin\VdbProvider;

use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\vdb_provider_milvus\MilvusV2;
use HelgeSverre\Milvus\Milvus;

/**
 * Plugin implementation of the 'Milvus DB' provider.
 */
#[AiVdbProvider(
  id: 'milvus',
  label: new TranslatableMarkup('Milvus DB'),
)]
class MilvusProvider extends AiVdbProviderClientBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The API key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * The Milvus client.
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
   * Set key for authentication of the client.
   *
   * @param mixed $authentication
   *   The authentication.
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
   *   The Milvus client.
   */
  public function getClient(): Milvus {
    if (empty($this->client)) {
      $config = $this->getConnectionData();
      $this->client = new Milvus(
        token: $config['api_key'],
        host: $config['server'],
        port: $config['port'],
      );
    }
    return $this->client;
  }

  /**
   * Get v2 client.
   *
   * This is needed for creating collections.
   *
   * @return \Drupal\vdb_provider_milvus\MilvusV2
   *   The Milvus v2 client.
   */
  public function getV2Client(): MilvusV2 {
    $service = \Drupal::service('milvus_v2.api');
    $config = $this->getConnectionData();
    $service->setBaseUrl($config['server']);
    $service->setPort($config['port']);
    $service->setApiKey($config['api_key']);
    return $service;
  }

  /**
   * Get connection data.
   *
   * @return array
   *   The connection data.
   */
  public function getConnectionData() {
    $config = $this->getConfig();
    $output['server'] = $this->configuration['server'] ?? $config->get('server');
    // Fail if server is not set.
    if (!$output['server']) {
      throw new \Exception('Milvus server is not configured');
    }
    $token = $config->get('api_key');
    $output['api_key'] = '';
    if ($token) {
      $output['api_key'] = $this->keyRepository->getKey($token)->getKeyValue();
    }
    if (!empty($this->configuration['api_key'])) {
      $output['api_key'] = $this->configuration['api_key'];
    }

    $output['port'] = $this->configuration['port'] ?? $config->get('port');
    if (!$output['port']) {
      $output['port'] = (substr($output['server'], 0, 5) === 'https') ? 443 : 80;
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    try {
      $return = json_decode($this->getClient()->collections()->list(), TRUE);
      // Wrong API Key.
      if (isset($return['code']) && $return['code'] === 80001) {
        return FALSE;
      }
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isSetup(): bool {
    if ($this->getConfig()->get('server')) {
      return TRUE;
    }
    return FALSE;
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
    string $database = 'default',
  ): void {
    $metric_name = match ($metric_type) {
      VdbSimilarityMetrics::EuclideanDistance => 'L2',
      VdbSimilarityMetrics::CosineSimilarity => 'COSINE',
      VdbSimilarityMetrics::InnerProduct => 'IP',
    };
    $collections = $this->getCollections($database);
    if (!isset($collections['data']) || !in_array($collection_name, $collections['data'])) {
      $client = $this->getV2Client();
      $response = $client->createCollection(
        $collection_name,
        $database,
        $dimension,
        $metric_name,
      );
      if (!isset($response['code']) || ($response['code'] !== 0 && $response['code'] !== 200)) {
        throw new \Exception('Failed to create collection');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
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
    string $database = 'default',
  ): void {
    $response = json_decode($this->getClient()->vector()->insert(
      collectionName: $collection_name,
      data: $data,
      dbName: $database,
    ), TRUE);

    if (!isset($response['code']) || ($response['code'] !== 0 && $response['code'] !== 200)) {
      throw new \Exception("Failed to create collection: ");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default',
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
    string $database = 'default',
  ): array {
    $params = [
      'collectionName' => $collection_name,
      'filter' => $filters,
      'outputFields' => $output_fields,
      'dbName' => $database,
      'limit' => $limit,
      'offset' => $offset,
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
    string $database = 'default',
  ): array {
    $params = [
      'collectionName' => $collection_name,
      'vector' => $vector_input,
      'outputFields' => $output_fields,
      'dbName' => $database,
      'limit' => $limit,
      'offset' => $offset,
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
    array $drupalIds,
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
