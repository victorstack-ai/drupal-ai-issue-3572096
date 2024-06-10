<?php

namespace Drupal\ai\Base;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\Enum\Bundles;
use Drupal\ai\LlmProviderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai\Utility\StringUtility;
use GuzzleHttp\ClientInterface;

/**
 * Service to handle API requests server.
 */
abstract class LLMProviderClientBase implements LlmProviderInterface {

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
   * The base URL of the LM Studio local server.
   *
   * @var string
   */
  protected string $baseUrl;

  /**
   * Available configurations for this LLM provider.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Defined models.
   *
   * @var array|array[]
   */
  protected array $modelsSettings;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The config factory.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->config = $this->getConfig();
    $this->modelsSettings = $this->getModelSettings();
  }

  /**
   * Returns configuration of the Client.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Configuration of module.
   */
  abstract public function getConfig(): ImmutableConfig;

  /**
   * Returns root config key.
   *
   * @return string
   *   The first key of root configuration key of the plugin.
   */
  abstract public function getConfigPrefix(): string;

  /**
   * Returns key of branch of config settings to use.
   *
   * @return string
   *   The last key of root configuration key of the plugin.
   */
  abstract public function getConfigSuffix(): string;

  /**
   * Returns array of models' base settings.
   *
   * @return array
   *   The plugin configuration array.
   */
  abstract public function getModelSettings(): array;

  /**
   * {@inheritdoc}
   */
  abstract public function getProviderName(): string;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredLlms(Bundles $bundle = NULL): array {
    $result = [];
    if ($bundle) {
      $bundleName = StringUtility::pascalToSnake($bundle->name);
      $bundleSettings = $this->modelsSettings[$bundle->name] ?? NULL;
      if ($bundleSettings) {
        foreach ($bundleSettings['llms'] as $key => $llm) {
          $result[$llm['title']] = $bundleName . '-' . $key;
        }
      }
    }
    else {
      foreach ($this->modelsSettings as $bundleName => $settings) {
        foreach ($settings['llms'] as $key => $llm) {
          $result[(string) $llm['title']] = $bundleName . '-' . $key;
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableConfiguration(string $model_id): array {
    [$type] = explode('-', $model_id, 2);
    return $this->config->get($this->getConfigPrefix() . $type . $this->getConfigSuffix());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfigurationValues(string $model_id): array {
    [$type, $model] = explode('-', $model_id, 2);
    $configs = $this->config->get($this->getConfigPrefix() . $type . $this->getConfigSuffix());
    $defaults = [];
    foreach ($configs as $key => $values) {
      if (isset($values['default'])) {
        $defaults[$key] = CastUtility::typeCast($values['type'], $values['default']);
      }
    }
    $specific = $this->modelsSettings[$type]['llms'][$model]['settings'] ?? [];
    return array_merge($defaults, $specific);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputExample(string $model_id): mixed {
    [$type] = explode('-', $model_id, 2);
    return $this->config->get($this->getConfigPrefix() . $type . '.input');
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationExample(string $model_id): mixed {
    [$type] = explode('-', $model_id, 2);
    return $this->config->get($this->getConfigPrefix() . $type . '.authentication');
  }

  /**
   * {@inheritdoc}
   */
  abstract public function generateResponse(string $model_id, mixed $input, array $authentication = [], array $configuration = [], bool $normalise_io = TRUE): mixed;

}
