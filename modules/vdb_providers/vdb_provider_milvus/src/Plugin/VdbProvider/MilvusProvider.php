<?php

namespace Drupal\vdb_provider_milvus\Plugin\VdbProvider;

use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\KeyRepositoryInterface;
use Drupal\vdb_provider_milvus\MilvusV2;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * Constructs an override for the AiVdbClientBase class to add Milvus V2.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\vdb_provider_milvus\MilvusV2 $client
   *   The Milvus V2 API client.
   */
  public function __construct(
    protected string $pluginId,
    protected mixed $pluginDefinition,
    protected ConfigFactoryInterface $configFactory,
    protected KeyRepositoryInterface $keyRepository,
    protected EventDispatcherInterface $eventDispatcher,
    protected MilvusV2 $client,
  ) {
    parent::__construct(
      $this->pluginId,
      $this->pluginDefinition,
      $this->configFactory,
      $this->keyRepository,
      $this->eventDispatcher,
    );
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AiVdbProviderClientBase|static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('event_dispatcher'),
      $container->get('milvus_v2.api'),
    );
  }

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
   * Get v2 client.
   *
   * This is needed for creating collections.
   *
   * @return \Drupal\vdb_provider_milvus\MilvusV2
   *   The Milvus v2 client.
   */
  public function getClient(): MilvusV2 {
    $config = $this->getConnectionData();
    $this->client->setBaseUrl($config['server']);
    $this->client->setPort($config['port']);
    $this->client->setApiKey($config['api_key']);
    return $this->client;
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
      $return = $this->getClient()->listCollections();
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
    return $this->getClient()->listCollections($database);
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
    $client = $this->getClient();
    $client->createCollection(
      $collection_name,
      $database,
      $dimension,
      $metric_name,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
  ): void {
    $this->getClient()->dropCollection($collection_name);
  }

  /**
   * {@inheritdoc}
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default',
  ): void {
    $response = $this->getClient()->insertIntoCollection($collection_name, $data, $database);

    if (!isset($response['code']) || ($response['code'] !== 0 && $response['code'] !== 200)) {
      throw new \Exception("Failed to insert into collection: " . $response['message']);
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
    $this->getClient()->deleteFromCollection($collection_name, $ids, $database);
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
    $data = $this->getClient()->query(
      $collection_name,
      $output_fields,
      $filters,
      $limit,
      $offset,
      $database
    );
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
    $data = $this->getClient()->search(
      $collection_name,
      $vector_input,
      $output_fields,
      $filters,
      $limit,
      $offset,
      $database
    );
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
    string $database = 'default',
  ): array {
    $data = $this->querySearch(
      collection_name: $collection_name,
      output_fields: ['id'],
      filters: "drupal_entity_id in [\"" . implode('","', $drupalIds) . "\"]",
      database: $database
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
