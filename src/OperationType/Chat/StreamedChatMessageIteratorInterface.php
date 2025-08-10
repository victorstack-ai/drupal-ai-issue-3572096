<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * For streaming chat message.
 */
interface StreamedChatMessageIteratorInterface extends \IteratorAggregate {

  public function __construct(\IteratorAggregate $iterator);

  /**
   * The getIterator method to return a generator.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Move all logic to
   * doIterate() instead.
   * @see https://www.drupal.org/project/ai/issues/3538341
   *
   * @return \Generator
   *   A generator that yields streamed chat messages.
   */
  public function getIterator(): \Generator;

  /**
   * The actual implementation of the generator.
   *
   * @return \Generator
   *   A generator that yields streamed chat messages.
   */
  public function doIterate(): \Generator;

  /**
   * Set an request thread id.
   *
   * @param string $request_thread_id
   *   The request thread id.
   */
  public function setRequestThreadId(string $request_thread_id): void;

  /**
   * Get the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId(): string;

  /**
   * Sets on stream chat message.
   *
   * @param string $role
   *   The role.
   * @param string $message
   *   The message.
   * @param array $metadata
   *   The metadata.
   * @param array|null $tools
   *   The tools.
   * @param array|null $raw
   *   The raw data.
   *
   * @return \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface
   *   The streamed chat message.
   */
  public function createStreamedChatMessage(string $role, string $message, array $metadata, ?array $tools = NULL, ?array $raw = NULL,): StreamedChatMessageInterface;

  /**
   * Gets the stream chat messages.
   *
   * @return array
   *   The stream chat messages.
   */
  public function getStreamChatMessages(): array;

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void;

  /**
   * Create a chat output from the streamed messages.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output.
   */
  public function reconstructChatOutput(): ChatOutput;

}
