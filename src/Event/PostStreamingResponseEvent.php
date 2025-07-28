<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * For collecting the results post streaming.
 *
 * This event should be used in conjunction with the PostGenerateResponseEvent
 * using the request thread id to connect the two events. There is no
 * manipulation of the data in this event, it is just for collecting the final
 * results.
 */
class PostStreamingResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.post_streaming_response';

  /**
   * The request thread id id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected $role;

  /**
   * The output for the request.
   *
   * @var mixed
   *   The output for the request.
   */
  protected $output;

  /**
   * The metadata to store for the request.
   *
   * @var array
   */
  protected array $metadata;

  /**
   * The amount of input tokens from the AI provider.
   */
  protected ?int $inputTokensUsage = NULL;

  /**
   * The amount of output tokens from the AI provider.
   */
  protected ?int $outputTokensUsage = NULL;

  /**
   * The amount of total tokens from the AI provider.
   */
  protected ?int $totalTokensUsage = NULL;

  /**
   * The amount of reasoning tokens from the AI provider.
   */
  protected ?int $reasoningTokensUsage = NULL;

  /**
   * The amount of cached tokens from the AI provider.
   */
  protected ?int $cachedTokensUsage = NULL;

  /**
   * Constructs the object.
   *
   * @param string $request_thread_id
   *   The unique request thread id.
   * @param mixed $output
   *   The output for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(string $request_thread_id, $output, array $metadata = []) {
    $this->requestThreadId = $request_thread_id;
    $this->output = $output;
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
   * Gets the output.
   *
   * @return mixed
   *   The output.
   */
  public function getOutput() {
    return $this->output;
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
   * Set the total tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setTotalTokenUsage(int $tokens): void {
    $this->totalTokensUsage = $tokens;
  }

  /**
   * Set the input tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setInputTokenUsage(int $tokens): void {
    $this->inputTokensUsage = $tokens;
  }

  /**
   * Set the output tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setOutputTokenUsage(int $tokens): void {
    $this->outputTokensUsage = $tokens;
  }

  /**
   * Set the reasoning tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setReasoningTokenUsage(int $tokens): void {
    $this->reasoningTokensUsage = $tokens;
  }

  /**
   * Set the cached tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setCachedTokenUsage(int $tokens): void {
    $this->cachedTokensUsage = $tokens;
  }

  /**
   * Gets the total tokens used by the AI provider.
   *
   * @return int|null
   *   The total token usage.
   */
  public function getTotalTokenUsage(): ?int {
    return $this->totalTokensUsage;
  }

  /**
   * Gets the input tokens used by the AI provider.
   *
   * @return int|null
   *   The input token usage.
   */
  public function getInputTokenUsage(): ?int {
    return $this->inputTokensUsage;
  }

  /**
   * Gets the output tokens used by the AI provider.
   *
   * @return int|null
   *   The output token usage.
   */
  public function getOutputTokenUsage(): ?int {
    return $this->outputTokensUsage;
  }

  /**
   * Gets the reasoning tokens used by the AI provider.
   *
   * @return int|null
   *   The reasoning token usage.
   */
  public function getReasoningTokenUsage(): ?int {
    return $this->reasoningTokensUsage;
  }

  /**
   * Gets the cached tokens used by the AI provider.
   *
   * @return int|null
   *   The cached token usage.
   */
  public function getCachedTokenUsage(): ?int {
    return $this->cachedTokensUsage;
  }

}
