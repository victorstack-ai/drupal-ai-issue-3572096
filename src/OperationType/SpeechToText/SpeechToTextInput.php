<?php

namespace Drupal\ai\OperationType\SpeechToText;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for speech to text input.
 */
class SpeechToTextInput implements InputInterface {
  /**
   * The binary to convert to text.
   *
   * @var string
   */
  private string $binary;

  /**
   * The constructor.
   *
   * @param string $binary
   *   The binary to convert to text.
   */
  public function __construct(string $binary) {
    $this->binary = $binary;
  }

  /**
   * Get the mp3 binary to convert into text.
   *
   * @return string
   *   The text.
   */
  public function getBinary(): string {
    return $this->binary;
  }

}
