<?php

namespace Drupal\ai\OperationType\StreamChat;

use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for chat models.
 */
#[OperationType(
  id: 'stream_chat',
  label: new TranslatableMarkup('Stream Chat'),
)]
interface StreamChatInterface extends OperationTypeInterface {

  /**
   * Generate streamed chats.
   *
   * @param array|string|\Drupal\ai\Operation\StreamChat\StreamChatInput $input
   *   The chat array, string or StreamChatInput.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\StreamChat\StreamChatOutputChunk
   *   The output Output.
   */
  public function streamChat(array|string|StreamChatInput $input, string $model_id, array $tags = []): StreamChatOutputChunk;

}
