<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for moderation models.
 */
interface ModerationInterface extends OperationTypeInterface {

  /**
   * Generate moderation.
   *
   * @param string|\Drupal\ai\OperationType\Moderation\ModerationInput $input
   *   The prompt or the moderation input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Moderation\ModerationOutput
   *   The moderation output.
   */
  public function moderation(string|ModerationInput $input, string $model_id = NULL, array $tags = []): ModerationOutput;

}
