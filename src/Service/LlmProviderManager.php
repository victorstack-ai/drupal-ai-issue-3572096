<?php

namespace Drupal\ai\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ai\Enum\Bundles;
use Drupal\ai\LlmProviderInterface;
use Drupal\ai\Utility\StringUtility;

/**
 * Manages LLM service providers.
 */
class LlmProviderManager {

  /**
   * An array of LLM provider services.
   *
   * @var \Drupal\ai\LlmProviderInterface[]
   */
  protected array $providers = [];

  /**
   * A mapping of model names to provider IDs.
   *
   * @var string[]
   */
  protected array $modelToProviderMap = [];

  /**
   * An array of all the bundles provided by LLM Providers.
   *
   * @var array
   */
  protected array $availableBundles = [];

  /**
   * Module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * HuggingFace provider.
   *
   * @var \Drupal\ai\LlmProviderInterface
   */
  protected LlmProviderInterface $huggingFaceProvider;

  /**
   * Provider Manager constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler interface.
   * @param \Drupal\ai\LlmProviderInterface $huggingFaceProvider
   *   The Hugging Face provider service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, LlmProviderInterface $huggingFaceProvider) {
    $this->moduleHandler = $moduleHandler;
    $this->huggingFaceProvider = $huggingFaceProvider;
    $this->registerKnownProviders();
  }

  /**
   * Register known contributed providers with no compatible API.
   */
  protected function registerKnownProviders(): void {
    if ($this->moduleHandler->moduleExists('huggingface')) {
      $this->addProvider($this->huggingFaceProvider, 'huggingface');
    }
  }

  /**
   * Registers an LLM provider service.
   *
   * @param \Drupal\ai\LlmProviderInterface $provider
   *   The LLM provider service.
   * @param string $id
   *   The provider ID.
   */
  public function addProvider(LlmProviderInterface $provider, string $id): void {
    $this->providers[$id] = $provider;
    foreach ($provider->getConfiguredLlms() as $model_id) {
      $this->modelToProviderMap[$model_id] = $id;
      [$bundle] = explode('-', $model_id, 2);
      $model_bundle = Bundles::fromName(StringUtility::snakeToPascal($bundle));
      if ($model_bundle) {
        $this->availableBundles[$model_bundle->name] = $model_bundle->value;
      }
    }
  }

  /**
   * Returns a provider by a given model name.
   *
   * @param string $model
   *   The model name.
   *
   * @return \Drupal\ai\LlmProviderInterface|null
   *   The LLM provider service, or null if not found.
   */
  public function getProviderByModel(string $model): ?LlmProviderInterface {
    $providerId = $this->modelToProviderMap[$model] ?? NULL;
    return $providerId ? $this->providers[$providerId] : NULL;
  }

  /**
   * Returns available LLM Bundles.
   *
   * @return array
   *   Available bundles.
   */
  public function getBundles(): array {
    return $this->availableBundles;
  }

  /**
   * Returns a list of available models from all registered providers.
   *
   * @param array $bundle_names
   *   Optionally limit shown model bundles.
   *
   * @return array
   *   An associative array of available models, where:
   *   Root keys are Model Bundle names.
   *   Secondary keys are LLM Provider names.
   *   Tertiary keys are Model Implementation names.
   *   Values are model and provider IDs.
   */
  public function getModels(array $bundle_names = []): array {
    $output = [];
    foreach ($this->providers as $provider_id => $provider) {
      $provider_name = $provider->getProviderName();
      $models = $provider->getConfiguredLlms();

      foreach ($models as $model_name => $model_id) {
        [$bundle] = explode('-', $model_id, 2);
        $model_bundle = StringUtility::snakeToPascal($bundle);
        if (empty($bundle_names) || in_array($model_bundle, $bundle_names)) {
          $output[$this->availableBundles[$model_bundle]][$provider_name][$model_name] = [
            'provider' => $provider_id,
            'model' => $model_id,
          ];
        }
      }
    }
    asort($output);

    return $output;
  }

  /**
   * Returns available configurations for the selected model.
   *
   * @param string $provider_id
   *   ID of LLM provider as set in the ID tag in Provider's service yml.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   *
   * @return array
   *   Associative array with configurations.
   */
  public function getAvailableConfiguration(string $provider_id, string $model_id): array {
    if (isset($this->providers[$provider_id])) {
      return $this->providers[$provider_id]->getAvailableConfiguration($model_id);
    }
    return [];
  }

  /**
   * Returns default configuration values of the selected model.
   *
   * @param string $provider_id
   *   ID of LLM provider as set in the ID tag in Provider's service yml.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   *
   * @return array
   *   Associative array with configurations.
   */
  public function getDefaultConfigurationValues(string $provider_id, string $model_id): array {
    if (isset($this->providers[$provider_id])) {
      return $this->providers[$provider_id]->getDefaultConfigurationValues($model_id);
    }
    return [];
  }

  /**
   * Returns an example of the input formatting for the model.
   *
   * @param string $provider_id
   *   ID of LLM provider as set in the ID tag in Provider's service yml.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   *
   * @return mixed
   *   Input example.
   */
  public function getInputExample(string $provider_id, string $model_id): mixed {
    if (isset($this->providers[$provider_id])) {
      return $this->providers[$provider_id]->getInputExample($model_id);
    }
    return NULL;
  }

  /**
   * Returns an example of the authentication data structure for the model.
   *
   * @param string $provider_id
   *   ID of LLM provider as set in the ID tag in Provider's service yml.
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   *
   * @return mixed
   *   Authentication example.
   */
  public function getAuthenticationExample(string $provider_id, string $model_id): mixed {
    if (isset($this->providers[$provider_id])) {
      return $this->providers[$provider_id]->getAuthenticationExample($model_id);
    }
    return NULL;
  }

  /**
   * Returns response of the selected model.
   *
   * @param string $provider_id
   *   The provider ID.
   * @param string $model_id
   *   The ID of the model to use.
   * @param mixed $input
   *   Input data to send to the LLM provider.
   * @param array $authentication
   *   Authentication credentials.
   * @param array $configuration
   *   Model configuration settings.
   * @param bool $normalise_io
   *   Provide only the output expected for this LLM bundle.
   *
   * @return mixed
   *   The response generated by the LLM API.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getResponse(string $provider_id, string $model_id, mixed $input, array $authentication = [], array $configuration = [], bool $normalise_io = TRUE): mixed {
    if (isset($this->providers[$provider_id])) {
      return $this->providers[$provider_id]->generateResponse($model_id, $input, $authentication, $configuration, $normalise_io);
    }
    return NULL;
  }

}
