<?php

namespace Drupal\ai\OperationType\Chat;

use IteratorAggregate;

/**
 * For streaming chat message.
 */
interface StreamedChatMessageIteratorInterface extends IteratorAggregate {

  public function __construct(IteratorAggregate $iterator);

}
