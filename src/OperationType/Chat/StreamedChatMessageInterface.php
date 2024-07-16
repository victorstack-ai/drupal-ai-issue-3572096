<?php

namespace Drupal\ai\OperationType\Chat;

interface StreamedChatMessageInterface {

  /**
   * Get role.
   *
   * @return string
   */
  public function getRole(): string;

  /**
   * Set role.
   *
   * @param string $role
   *   The role.
   */
  public function setRole(string $role): void;

  /**
   * Get text.
   *
   * @return string
   */
  public function getText(): string;

  /**
   * Set text.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void;

  /**
   * Get metadata.
   *
   * @return array
   */
  public function getMetadata(): array;

  /**
   * Set metadata.
   *
   * @param array $metadata
   *   The metadata.
   */
  public function setMetadata(array $metadata): void;

}
