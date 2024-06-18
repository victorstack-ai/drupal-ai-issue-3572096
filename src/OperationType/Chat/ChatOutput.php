<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\DtoInterface;

/**
 * Data transfer output object for text to speech output.
 */
class ChatOutput implements DtoInterface {

  /**
   * The chat message.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage
   */
  private ChatMessage $normalized;

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

  public function __construct(ChatMessage $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the new chat message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The text string.
   */
  public function getNormalized(): ChatMessage {
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
