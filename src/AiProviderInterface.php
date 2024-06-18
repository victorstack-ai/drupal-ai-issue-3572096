<?php

namespace Drupal\ai;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for LLM provider services.
 */
interface AiProviderInterface extends PluginInspectionInterface {

  /**
   * Provides associative array with a list of models' IDs.
   *
   * Keyed with human-readable names and optionally filtered by bundle.
   *
   * @param string|null $operation_type
   *   The oepration type.
   *
   * @return array
   *   The list of models.
   */
  public function getConfiguredModels(string $operation_type = NULL): array;

  /**
   * Returns if the provider is setup and ready to use for the bundle.
   *
   * @param string|null $operation_type
   *   Bundle from Bundles Enum.
   *
   * @return bool
   *   Returns TRUE if the provider is setup and ready to use.
   */
  public function isUsable(string $operation_type): bool;

  /**
   * Returns the supported bundles for this provider.
   *
   * @return string[]
   *   List of supported operation types.
   */
  public function getSupportedBundles(): array;

  /**
   * Returns array of available configuration parameters for given bundle.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array
   *   List of all available configurations for given model.
   */
  public function getAvailableConfiguration(string $operation_type, string $model_id): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(string $operation_type, string $model_id): array;

  /**
   * Returns input example for given model.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array|mixed|null
   *   Example of input variable for given model.
   */
  public function getInputExample(string $operation_type, string $model_id): mixed;

  /**
   * Returns authentication data structure for given model.
   *
   * @param string $operation_type
   *   The operation type for the request.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array|mixed|null
   *   Example of authentication variable for given model.
   */
  public function getAuthenticationExample(string $operation_type, string $model_id): mixed;

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
   * Get configuration data for the LLM provider.
   *
   * @return array
   *   Configuration data.
   */
  public function getConfiguration(): array;

}
