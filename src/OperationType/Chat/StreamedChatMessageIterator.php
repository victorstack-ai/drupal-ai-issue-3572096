<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  /**
   * The iterator.
   *
   * @var \IteratorAggregate
   */
  protected $iterator;

  /**
   * Constructor.
   */
  public function __construct(\IteratorAggregate $iterator) {
    $this->iterator = $iterator;
  }

}
