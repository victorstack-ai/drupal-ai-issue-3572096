<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Each of the chat messages for chat input.
 */
class ChatMessage {
  /**
   * The role of the message.
   *
   * @var string
   */
  private string $role;

  /**
   * The message.
   *
   * @var string
   */
  private string $message;

  /**
   * The constructor.
   *
   * @param string $role
   *   The role of the message.
   * @param string $message
   *   The message.
   */
  public function __construct(string $role, string $message) {
    $this->role = $role;
    $this->message = $message;
  }

  /**
   * Get the role of the message.
   *
   * @return string
   *   The role.
   */
  public function getRole(): string {
    return $this->role;
  }

  /**
   * Get the message.
   *
   * @return string
   *   The message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Set the role of the message.
   *
   * @param string $role
   *   The role.
   */
  public function setRole(string $role): void {
    $this->role = $role;
  }

  /**
   * Set the message.
   *
   * @param string $message
   *   The message.
   */
  public function setMessage(string $message): void {
    $this->message = $message;
  }

}
