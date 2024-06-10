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
   * Returns array of available configuration parameters for given bundle.
   *
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array
   *   List of all available configurations for given model.
   */
  public function getAvailableConfiguration(string $model_id): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(string $model_id): array;

  /**
   * Returns input example for given model.
   *
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array|mixed|null
   *   Example of input variable for given model.
   */
  public function getInputExample(string $model_id): mixed;

  /**
   * Returns authentication data structure for given model.
   *
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredLlms().
   *
   * @return array|mixed|null
   *   Example of authentication variable for given model.
   */
  public function getAuthenticationExample(string $model_id): mixed;

  /**
   * Sends a request to the LLM provider to generate a response.
   *
   * @param string $model_id
   *   ID of model as set in getConfiguredLlms().
   * @param array $input
   *   Input for the LLM.
   * @param array $authentication
   *   Authentication credentials can be overridden here.
   * @param array $configuration
   *   Configuration of the model can be overridden here.
   * @param bool $normalise_io
   *   Provide only the output expected for this LLM bundle.
   *
   * @return mixed
   *   Text output returned from LLM API.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function generateResponse(string $model_id, mixed $input, array $authentication = [], array $configuration = [], bool $normalise_io = TRUE): mixed;

}
