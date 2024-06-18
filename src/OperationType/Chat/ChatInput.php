<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for chat input.
 */
class ChatInput implements InputInterface {
  /**
   * The message to convert to text.
   *
   * @var array
   */
  private array $messages;

  /**
   * The constructor.
   *
   * @param string $messages
   *   The messages to chat.
   */
  public function __construct(array $messages) {
    $this->messages = $messages;
  }

  /**
   * Get the messages to chat.
   *
   * @return array
   *   The messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * Set the messages to chat.
   *
   * @param array $messages
   *   The messages.
   */
  public function setMessages(array $messages) {
    $this->messages = $messages;
  }

}
