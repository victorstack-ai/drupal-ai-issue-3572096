<?php

namespace Drupal\ai\Base;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\key\KeyRepositoryInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to handle API requests server.
 */
abstract class AiProviderClientBase implements AiProviderInterface, ContainerFactoryPluginInterface {

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The HTTP client.
   *
   * @var \Psr\Http\Client\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Available configurations for this LLM provider.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * Key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The API definition.
   *
   * @var array
   */
  protected array $apiDefinition = [];

  /**
   * The configuration to add to the call.
   *
   * @var array
   */
  public array $configuration = [];

  /**
   * The tags for the prompt.
   *
   * @var array
   */
  protected array $tags = [];

  /**
   * Streamed output wanted.
   *
   * @var bool
   */
  protected bool $streamed = FALSE;

  /**
   * Sets a chat system role.
   *
   * @var string|null
   */
  protected string|NULL $chatSystemRole = '';

  /**
   * The plugin definition.
   *
   * @var \Drupal\Core\Plugin\PluginDefinitionInterface|array
   */
  protected $pluginDefinition;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Psr\Http\Client\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  final public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache_backend,
    KeyRepositoryInterface $key_repository,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $event_dispatcher,
    FileSystemInterface $file_system,
  ) {
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->config = $this->getConfig();
    $this->apiDefinition = $this->getApiDefinition();
    $this->cacheBackend = $cache_backend;
    $this->keyRepository = $key_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->fileSystem = $file_system;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('key.repository'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('file_system')
    );
  }

  /**
   * Returns configuration of the Client.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Configuration of module.
   */
  abstract public function getConfig(): ImmutableConfig;

  /**
   * Returns array of API definition.
   *
   * @return array
   *   The plugin configuration array.
   */
  abstract public function getApiDefinition(): array;

  /**
   * Returns array of models custom settings.
   *
   * @param string $model_id
   *   The model ID.
   * @param array $generalConfig
   *   The general configuration.
   *
   * @return array
   *   The plugin configuration array.
   */
  abstract public function getModelSettings(string $model_id, array $generalConfig = []): array;

  /**
   * {@inheritDoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [];
  }

  /**
   * Does this provider support this capability.
   *
   * @param enum $capability
   *   The capability to check.
   *
   * @return bool
   *   TRUE if the capability is supported.
   */
  public function supportsCapability(string $capability): bool {
    return in_array($capability, $this->getSupportedCapabilities());
  }

  /**
   * Does this model support these capabilities.
   *
   * @param string $operation_type
   *   The operation type to check for.
   * @param string $model_id
   *   The model ID.
   * @param \Drupal\ai\Enum\AiModelCapability[] $capabilities
   *   The capabilities to check.
   *
   * @return bool
   *   TRUE if the capability is supported.
   */
  public function modelSupportsCapabilities(string $operation_type, string $model_id, array $capabilities): bool {
    $list = $this->getConfiguredModels($operation_type, $capabilities);
    return isset($list[$model_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setChatSystemRole(string|NULL $message): void {
    $this->chatSystemRole = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableConfiguration(string $operation_type, string $model_id): array {
    $generalConfig = $this->getApiDefinition()[$operation_type]['configuration'] ?? [];
    $modelConfig = $this->getModelSettings($model_id, $generalConfig);
    return $modelConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfigurationValues(string $operation_type, string $model_id): array {
    $configs = $this->getAvailableConfiguration($operation_type, $model_id);
    $defaults = [];
    foreach ($configs as $key => $values) {
      if (isset($values['default']) && !empty($values['required'])) {
        $defaults[$key] = CastUtility::typeCast($values['type'], $values['default']);
      }
    }
    return $defaults;
  }

  /**
   * Get cast of configuration values.
   */

  /**
   * {@inheritdoc}
   */
  public function getInputExample(string $operation_type, string $model_id): mixed {
    return $this->config->get('api_defaults')[$operation_type]['input'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationExample(string $operation_type, string $model_id): mixed {
    return $this->config->get('api_defaults')[$operation_type]['authentication'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTag(string $tag): void {
    $this->tags[] = $tag;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTag(string $tag): void {
    $this->tags = array_diff($this->tags, [$tag]);
  }

  /**
   * Set the streamed output.
   *
   * @param bool $streamed
   *   Streamed output or not.
   */
  public function streamedOutput(bool $streamed = TRUE): void {
    $this->streamed = $streamed;
  }

  /**
   * Normalize the configuration before runtime.
   *
   * @param string $operation_type
   *   The operation type to generate a response for.
   * @param string $model_id
   *   ID of model as set in getConfiguredModels().
   */
  public function normalizeConfiguration(string $operation_type, $model_id): array {
    $values = $this->getDefaultConfigurationValues($operation_type, $model_id);
    foreach ($this->configuration as $key => $value) {
      $values[$key] = $value;
    }
    return $values;
  }

}
