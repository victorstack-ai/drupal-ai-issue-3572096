<?php

namespace Drupal\ai\Event;

use Drupal\ai\Enum\Bundles;
use Drupal\Component\EventDispatcher\Event;

/**
 * Changes or Exceptions before a AI request is triggered can be done here.
 */
class PreGenerateResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.pre_generate_response';

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
   * Should the response be normalized.
   *
   * @var bool
   */
  protected $normaliseIo;

  /**
   * The authentication.
   *
   * @var mixed
   */
  protected $authentication;

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
   * @param array $tags
   *   The tags for the request.
   * @param bool $normaliseIo
   *   Should the response be normalized.
   */
  public function __construct(String $providerId, array $configuration, Bundles $bundle, string $modelId, mixed $input, array $tags = [], bool $normaliseIo = TRUE) {
    $this->providerId = $providerId;
    $this->configuration = $configuration;
    $this->bundle = $bundle;
    $this->modelId = $modelId;
    $this->input = $input;
    $this->tags = $tags;
    $this->normaliseIo = $normaliseIo;
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
   * Gets the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags() {
    return $this->tags;
  }

  /**
   * Gets the normalise IO.
   *
   * @return bool
   *   The normalise IO.
   */
  public function getNormaliseIo() {
    return $this->normaliseIo;
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

}
