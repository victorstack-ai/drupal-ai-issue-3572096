<?php

namespace Drupal\ai\OperationType\TextToSpeech;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for text to speech input.
 */
class TextToSpeechInput implements InputInterface {
  /**
   * The text to convert to speech.
   *
   * @var string
   */
  private string $text;

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to convert to speech.
   */
  public function __construct(string $text) {
    $this->text = $text;
  }

  /**
   * Get the text to convert to speech.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }
}
