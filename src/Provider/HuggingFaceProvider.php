<?php

namespace Drupal\ai\Provider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\huggingface\HuggingfaceApi;
use Drupal\ai\Base\AiClientBase;
use Drupal\ai\Config\ModelConfigLoader;
use Drupal\ai\Enum\Bundles;
use Drupal\ai\Utility\StringUtility;
use GuzzleHttp\ClientInterface;

/**
 * LLM provider for HuggingFace API.
 */
class HuggingFaceProvider extends AiClientBase {

  /**
   * HuggingFace API class.
   *
   * @var \Drupal\huggingface\HuggingfaceApi
   */
  protected HuggingfaceApi $api;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The config factory.
   * @param \Drupal\huggingface\HuggingfaceApi $huggingFaceApi
   *   The HuggingFace API.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    HuggingfaceApi $huggingFaceApi
  ) {
    parent::__construct(
      $http_client,
      $config_factory,
      $logger_factory
    );
    $this->api = $huggingFaceApi;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigPrefix(): string {
    return 'known_providers.huggingface.';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSuffix(): string {
    return '.configuration.model';
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(): array {
    return ModelConfigLoader::loadSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string {
    return 'Hugging Face';
  }

  /**
   * {@inheritdoc}
   */
  public function generateResponse(string $model_id, mixed $input, array $authentication = [], array $configuration = [], bool $normalise_io = TRUE): mixed {
    [$type] = explode('-', $model_id, 2);
    $configuration = array_merge($this->getDefaultConfigurationValues($model_id), $configuration);
    $endpoint = $this->modelsSettings[$type]['endpoint'];
    $use_cache = $this->config->get($this->getConfigPrefix() . $type . '.configuration.api.use_cache.default');
    $wait_for_model = $this->config->get($this->getConfigPrefix() . $type . '.configuration.api.wait_for_model.default');
    $response = NULL;
    try {
      switch ($type) {
        case StringUtility::pascalToSnake(Bundles::Chat->name):
          if ($normalise_io && !is_string($input)) {
            $input = $this->findLastUserContent($input);
          }
          $response = $this->api->conversational($endpoint, $input, $configuration, $use_cache, $wait_for_model);
          break;

        case StringUtility::pascalToSnake(Bundles::Summarization->name):
          $response = $this->api->summarization($endpoint, $input, $configuration, $use_cache, $wait_for_model);
          break;
      }
      if ($normalise_io) {
        if ($type == StringUtility::pascalToSnake(Bundles::Chat->name)) {
          $data = json_decode($response, TRUE);
          if (!empty($data)) {
            $response = $data[0]['generated_text'];
            $pattern = '/^' . preg_quote($input, '/') . '\s*/';
            $response = preg_replace($pattern, '', $response);
          }
          else {
            $response = "No data found.";
          }
        }
      }
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai')->error($e->getMessage());
      return 'An error occurred while processing your request.';
    }
  }

  /**
   * HuggingFace chat accepts only string. Use the last string of discussion.
   *
   * @param array $entries
   *   LLM input as an array.
   *
   * @return string
   *   String of last User entry.
   */
  private function findLastUserContent(array $entries): string {
    for ($i = count($entries) - 1; $i >= 0; $i--) {
      if ($entries[$i]['role'] === 'user') {
        return $entries[$i]['content'];
      }
    }
    return "";
  }

}
