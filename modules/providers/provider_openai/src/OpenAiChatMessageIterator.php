<?php

namespace Drupal\provider_openai;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Generator;

class OpenAiChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function getIterator(): Generator {
    foreach ($this->iterator->getIterator() as $data) {
      yield new StreamedChatMessage(
        $data->choices[0]->delta->role ?? '',
        $data->choices[0]->delta->content ?? '',
        $data->usage ?? []
      );
    }
  }

}
