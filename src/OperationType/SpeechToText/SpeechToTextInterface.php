<?php

namespace Drupal\ai\OperationType\SpeechToText;

use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text to speech models.
 */
interface SpeechToTextInterface extends OperationTypeInterface {

  /**
   * Generate text from speech.
   *
   * @param string|\Drupal\ai\Operation\SpeechToText\SpeechToTextInput $input
   *   The text to generate audio from or a DTO.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\SpeechToText\SpeechToTextDto
   *   The output DTO.
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextDto;

}
