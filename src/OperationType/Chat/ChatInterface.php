<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for chat models.
 */
#[OperationType(
  id: 'chat',
  label: new TranslatableMarkup('Chat'),
)]
interface ChatInterface extends OperationTypeInterface {

  /**
   * Generate chats.
   *
   * @param array|string|\Drupal\ai\Operation\Chat\ChatInput $input
   *   The chat array, string or ChatInput.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The output Output.
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput;

  /**
   * Sets a normalized way of doing system roles.
   *
   * @param string $message
   *   The message to add.
   */
  public function setChatSystemRole(string $message): void;

  /**
   * Gets the system role.
   *
   * @return string
   *   The system role.
   */
  public function getChatSystemRole(): string;

}
