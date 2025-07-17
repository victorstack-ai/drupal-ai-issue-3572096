<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * OpenAI Streamed Chat Message Iterator implementation.
 *
 * This class is a copy of the OpenAiChatMessageIterator,
 * adapted to Drupal's AI module structure. It can be extended as needed for
 * OpenAI-based streaming responses.
 */
class OpenAiTypeStreamedChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    foreach ($this->iterator->getIterator() as $data) {
      $metadata = $data->usage ? $data->usage->toArray() : [];
      yield $this->createStreamedChatMessage(
        $data->choices[0]->delta->role ?? '',
        $data->choices[0]->delta->content ?? '',
        $metadata,
      );
    }
    $this->triggerEvent();
  }

}
