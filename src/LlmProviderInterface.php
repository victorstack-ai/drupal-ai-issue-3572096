<?php

namespace Drupal\ai;

use Drupal\ai\Enum\Bundles;

/**
 * Defines an interface for LLM provider services.
 */
interface LlmProviderInterface {

  /**
   * Returns a name of the LLM provider, e.g. HuggingFace.
   *
   * @return string
   *   Returns name of the actual LLM Provider.
   */
  public function getProviderName(): string;

  /**
   * Provides associative array with a list of models' IDs.
   *
   * Keyed with human-readable names and optionally filtered by bundle.
   *
   * @param \Drupal\ai\Enum\Bundles|null $bundle
   *   Bundle from Bundles Enum.
   *
   * @return array
   *   The list of models.
   */
  public function getConfiguredLlms(Bundles $bundle = NULL): array;

  /**
   * Returns if the provider is setup and ready to use for the bundle.
   *
   * @param \Drupal\ai\Enum\Bundles|null $bundle
   *   Bundle from Bundles Enum.
   *
   * @return bool
   *   Returns TRUE if the provider is setup and ready to use.
   */
  public function isUsable(Bundles $bundle): bool;

  /**
   * Returns array of available configuration parameters for given bundle.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the configuration for.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array
   *   List of all available configurations for given model.
   */
  public function getAvailableConfiguration(Bundles $bundle, string $model_id): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the configuration for.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(Bundles $bundle, string $model_id): array;

  /**
   * Returns input example for given model.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the input example for.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array|mixed|null
   *   Example of input variable for given model.
   */
  public function getInputExample(Bundles $bundle, string $model_id): mixed;

  /**
   * Returns the supported bundles for this provider.
   *
   * @return \Drupal\ai\Enum\Bundles[]
   *   List of supported bundles.
   */
  public function getSupportedBundles(): array;

  /**
   * Returns authentication data structure for given model.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the authentication example for.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array|mixed|null
   *   Example of authentication variable for given model.
   */
  public function getAuthenticationExample(Bundles $bundle, string $model_id): mixed;

  /**
   * Set authentication data for the LLM provider.
   *
   * @param mixed $authentication
   *   Authentication data.
   */
  public function setAuthentication(mixed $authentication): void;

  /**
   * Set configuration data for the LLM provider.
   *
   * @param array $configuration
   *   Configuration data.
   */
  public function setConfiguration(array $configuration): void;

  /**
   * Sends a request to the LLM provider to generate a response.
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
  public function invokeModelResponse(Bundles $bundle, string $model_id, mixed $input, bool $normalise_io = TRUE): mixed;

}
