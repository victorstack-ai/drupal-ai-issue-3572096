<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for chat models.
 */
interface ChatInterface extends OperationTypeInterface {

  /**
   * Generate chats.
   *
   * @param array|\Drupal\ai\Operation\Chat\ChatInput $input
   *   The chat array from or a Output.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The output Output.
   */
  public function chat(array|ChatInput $input, string $model_id, array $tags = []): ChatOutput;

}
