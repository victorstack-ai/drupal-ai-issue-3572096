<?php

namespace Drupal\ai\Base;

use Drupal\ai\Enum\Bundles;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\LlmProviderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to handle API requests server.
 */
abstract class LlmProviderClientBase implements LlmProviderInterface, ContainerFactoryPluginInterface {

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
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
  protected array $configuration = [];

  /**
   * The tags for the prompt.
   *
   * @var array
   */
  protected array $tags = [];

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
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
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
    array $configuration,
    $plugin_id,
    $plugin_definition,
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
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
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
   *
   * @return array
   *   The plugin configuration array.
   */
  abstract public function getModelSettings(string $model_id): array;

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
  public function getAvailableConfiguration(Bundles $bundle, string $model_id): array {
    $generalConfig = $this->getApiDefinition()[$bundle->value]['configuration'] ?? [];
    $modelConfig = $this->getModelSettings($model_id);
    return empty($modelConfig) ? $generalConfig : array_replace_recursive($generalConfig, $modelConfig);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfigurationValues(Bundles $bundle, string $model_id): array {
    $configs = $this->getAvailableConfiguration($bundle, $model_id);
    $defaults = [];
    foreach ($configs as $key => $values) {
      if (isset($values['default']) && !empty($values['required'])) {
        $defaults[$key] = CastUtility::typeCast($values['type'], $values['default']);
      }
    }
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputExample(Bundles $bundle, string $model_id): mixed {
    return $this->config->get('api_defaults')[$bundle->value]['input'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationExample(Bundles $bundle, string $model_id): mixed {
    return $this->config->get('api_defaults')[$bundle->value]['authentication'] ?? '';
  }

  /**
   * Sends a request to the LLM provider to generate a response.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle type to generate a response for.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   * @param array $input
   *   Input for the LLM.
   * @param array $tags
   *   Tags for the request.
   * @param bool $normalise_io
   *   Provide only the output expected for this LLM bundle.
   *
   * @return mixed
   *   Text output returned from LLM API.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  final public function invokeModelResponse(Bundles $bundle, string $model_id, mixed $input, array $tags = [], bool $normalise_io = TRUE): mixed {
    // Normalize the configuration.
    $this->configuration = $this->normalizeConfiguration($bundle, $model_id);

    // Invoke the pre generate response event.
    $pre_generate_event = new PreGenerateResponseEvent($this->getPluginId(), $this->configuration, $bundle, $model_id, $input, $tags, $normalise_io);
    $this->eventDispatcher->dispatch($pre_generate_event, PreGenerateResponseEvent::EVENT_NAME);
    // Get the possible new auth, configuration and input from the event.
    $this->configuration = $pre_generate_event->getConfiguration();
    $input = $pre_generate_event->getInput();
    // Only set the authentication if it is set.
    if ($pre_generate_event->getAuthentication()) {
      $this->setAuthentication($pre_generate_event->getAuthentication());
    }

    // Trigger the provider and try to catch where it went wrong.
    try {
      $response = $this->generateResponse($bundle, $model_id, $input, $normalise_io);
    }
    // Response is wrong.
    catch (GuzzleException | AiResponseErrorException $e) {
      $this->loggerFactory->get('ai')->error('Error invoking model response: @error', ['@error' => $e->getMessage()]);
      throw new AiResponseErrorException('Error invoking model response: ' . $e->getMessage());
    }
    // Its not safe.
    catch (AiUnsafePromptException $e) {
      $this->loggerFactory->get('ai')->error('The Prompt is unsafe: @error', ['@error' => $e->getMessage()]);
      throw new AiUnsafePromptException('The Prompt is unsafe: ' . $e->getMessage());
    }
    // Anything else is probably due to a bad request.
    catch (\Exception $e) {
      $this->loggerFactory->get('ai')->error('Error invoking model response: @error', ['@error' => $e->getMessage()]);
      throw new AiRequestErrorException('Error invoking model response: ' . $e->getMessage());
    }

    // Invoke the post generate response event.
    $post_generate_event = new PostGenerateResponseEvent($this->getPluginId(), $this->configuration, $bundle, $model_id, $input, $response, $tags, $normalise_io);
    $this->eventDispatcher->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);
    // Get a potential new response from the event.
    $response = $post_generate_event->getOutput();

    // Return the response.
    return $response;
  }

  /**
   * Normalize the configuration before runtime.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle type to generate a response for.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   */
  protected function normalizeConfiguration(Bundles $bundle, $model_id): array {
    $values = $this->getDefaultConfigurationValues($bundle, $model_id);
    foreach ($this->configuration as $key => $value) {
      $values[$key] = $value;
    }
    return $values;
  }

  /**
   * Method for the provider to use to send the response.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle type to generate a response for.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   * @param array $input
   *   Input for the LLM.
   * @param bool $normalise_io
   *   Provide only the output expected for this LLM bundle.
   *
   * @return mixed
   *   Text output returned from LLM API.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  abstract protected function generateResponse(Bundles $bundle, string $model_id, mixed $input, bool $normalise_io = TRUE): mixed;

}
