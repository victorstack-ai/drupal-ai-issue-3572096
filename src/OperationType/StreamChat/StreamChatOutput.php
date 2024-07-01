<?php

namespace Drupal\ai\OperationType\StreamChat;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for streamed chat.
 */
class StreamChatOutput implements OutputInterface {

  /**
   * The chat message.
   *
   * @var \Drupal\ai\OperationType\StreamChat\StreamChatResponse
   */
  private StreamChatResponse $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  public function __construct(StreamChatMessage $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the response iterator.
   *
   * @return \Drupal\ai\OperationType\StreamChat\StreamChatResponse
   *   The response iterator.
   */
  public function getNormalized(): StreamChatResponse {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }
}
