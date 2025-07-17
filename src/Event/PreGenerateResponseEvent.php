<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\ai\OperationType\OutputInterface;

/**
 * Changes or Exceptions before a AI request is triggered can be done here.
 */
class PreGenerateResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.pre_generate_response';

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The request parent id.
   *
   * @var string
   */
  protected $requestParentId;

  /**
   * The provider to process.
   *
   * @var string
   */
  protected $providerId;

  /**
   * The configuration of the provider.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The operation type for the request.
   *
   * @var string
   */
  protected $operationType;

  /**
   * The model id.
   *
   * @var string
   */
  protected $modelId;

  /**
   * The input for the request.
   *
   * @var mixed
   */
  protected $input;

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected $output;

  /**
   * The tags for the request.
   *
   * @var array
   */
  protected $tags;

  /**
   * The authentication.
   *
   * @var mixed
   */
  protected $authentication;

  /**
   * The debug data.
   *
   * @var array
   */
  protected $debugData;

  /**
   * The metadata to store for the request.
   *
   * @var array
   */
  protected array $metadata;

  /**
   * The output to force return.
   *
   * This is used if a third party wants to stop a request from being sent,
   * gracefully with an expected response, instead of throwing an exception.
   * Examples could be that you want to return a cached response, or a default
   * response that the user does not have access to use AI.
   *
   * @var \Drupal\ai\OperationType\OutputInterface|null
   *   The output.
   */
  protected ?OutputInterface $forcedOutputObject = NULL;

  /**
   * Constructs the object.
   *
   * @param string $request_thread_id
   *   The unique request thread id.
   * @param string $provider_id
   *   The provider to process.
   * @param string $operation_type
   *   The operation type for the request.
   * @param array $configuration
   *   The configuration of the provider.
   * @param mixed $input
   *   The input for the request.
   * @param string $model_id
   *   The model ID for the request.
   * @param array $tags
   *   The tags for the request.
   * @param array $debug_data
   *   The debug data for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(string $request_thread_id, string $provider_id, string $operation_type, array $configuration, mixed $input, string $model_id, array $tags = [], array $debug_data = [], array $metadata = []) {
    $this->requestThreadId = $request_thread_id;
    $this->providerId = $provider_id;
    $this->configuration = $configuration;
    $this->operationType = $operation_type;
    $this->modelId = $model_id;
    $this->input = $input;
    $this->tags = $tags;
    $this->debugData = $debug_data;
    $this->metadata = $metadata;
  }

  /**
   * Gets the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId() {
    return $this->requestThreadId;
  }

  /**
   * Gets the request parent id.
   *
   * @return string
   *   The request parent id.
   */
  public function getRequestParentId() {
    return $this->requestParentId;
  }

  /**
   * Gets the provider.
   *
   * @return string
   *   The provider id.
   */
  public function getProviderId() {
    return $this->providerId;
  }

  /**
   * Gets the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Gets the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperationType() {
    return $this->operationType;
  }

  /**
   * Gets the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getModelId() {
    return $this->modelId;
  }

  /**
   * Gets the input.
   *
   * @return mixed
   *   The input.
   */
  public function getInput() {
    return $this->input;
  }

  /**
   * Helper to set tags on the event.
   *
   * @param array $tags
   *   An array of tags to set. Will completely replace those in $this->tags.
   */
  public function setTags(array $tags): void {
    $this->tags = $tags;
  }

  /**
   * Gets the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags() {
    return $this->tags;
  }

  /**
   * Gets the debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData() {
    return $this->debugData;
  }

  /**
   * Sets the parent request id.
   *
   * @param string $request_parent_id
   *   The parent request id.
   */
  public function setRequestParentId(string $request_parent_id): void {
    $this->requestParentId = $request_parent_id;
  }

  /**
   * Set extra debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugData(string $key, mixed $value) {
    $this->debugData[$key] = $value;
  }

  /**
   * Sets the input.
   *
   * @param mixed $input
   *   The input.
   */
  public function setInput(mixed $input) {
    $this->input = $input;
  }

  /**
   * Sets a new authentication layer.
   *
   * @param mixed $authentication
   *   The authentication.
   */
  public function setAuthentication(mixed $authentication) {
    $this->authentication = $authentication;
  }

  /**
   * Sets a new configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Gets the authentication.
   *
   * Note: This only gets a new authentication layer if set. It does not return
   * the default authentication.
   *
   * @return mixed
   *   The authentication.
   */
  public function getAuthentication() {
    return $this->authentication;
  }

  /**
   * Get all the metadata.
   *
   * @return array
   *   All the metadata.
   */
  public function getAllMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set all metadata replacing existing contents.
   *
   * @param array $metadata
   *   All the metadata.
   */
  public function setAllMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Get specific metadata by key.
   *
   * @param string $metadata_key
   *   The key of the metadata to return.
   *
   * @return mixed
   *   The metadata for the provided key.
   */
  public function getMetadata(string $metadata_key): mixed {
    return $this->metadata[$metadata_key];
  }

  /**
   * Add to the metadata by key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setMetadata(string $key, mixed $value): void {
    $this->metadata[$key] = $value;
  }

  /**
   * Gets the forced output object.
   *
   * @return \Drupal\ai\OperationType\OutputInterface|null
   *   The output.
   */
  public function getForcedOutputObject(): ?OutputInterface {
    return $this->forcedOutputObject;
  }

  /**
   * Sets the forced output object.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $forced_output_object
   *   The output.
   */
  public function setForcedOutputObject(OutputInterface $forced_output_object): void {
    $this->forcedOutputObject = $forced_output_object;
  }

}
