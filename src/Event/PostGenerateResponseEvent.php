<?php

namespace Drupal\ai\Event;

use Drupal\ai\Enum\Bundles;
use Drupal\Component\EventDispatcher\Event;

/**
 * Changes or Exceptions to the output of a AI request can be done here.
 */
class PostGenerateResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.post_generate_response';

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
   * The bundle for the request.
   *
   * @var \Drupal\ai\Enum\Bundles
   */
  protected $bundle;

  /**
   * The model ID for the request.
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
   * Should the response be normalized.
   *
   * @var bool
   */
  protected $normalizeIo;

  /**
   * Constructs the object.
   *
   * @param string $providerId
   *   The provider to process.
   * @param array $configuration
   *   The configuration of the provider.
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle for the request.
   * @param string $modelId
   *   The model ID for the request.
   * @param mixed $input
   *   The input for the request.
   * @param mixed $output
   *   The output for the request.
   * @param array $tags
   *   The tags for the request.
   * @param bool $normalizeIo
   *   Should the response be normalized.
   */
  public function __construct(String $providerId, array $configuration, Bundles $bundle, string $modelId, mixed $input, mixed $output, array $tags = [], bool $normalizeIo = TRUE) {
    $this->providerId = $providerId;
    $this->configuration = $configuration;
    $this->bundle = $bundle;
    $this->modelId = $modelId;
    $this->input = $input;
    $this->output = $output;
    $this->tags = $tags;
    $this->normalizeIo = $normalizeIo;
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
   * Gets the bundle.
   *
   * @return \Drupal\ai\Enum\Bundles
   *   The bundle.
   */
  public function getBundle() {
    return $this->bundle;
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
   * Gets the output.
   *
   * @return mixed
   *   The output.
   */
  public function getOutput() {
    return $this->output;
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
   * Gets the normalize IO.
   *
   * @return bool
   *   The normalize IO.
   */
  public function getNormaliseIo() {
    return $this->normalizeIo;
  }

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Sets the output.
   *
   * @param mixed $output
   *   The output.
   */
  public function setOutput(mixed $output) {
    $this->output = $output;
  }

}
