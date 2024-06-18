<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for moderation input.
 */
class ModerationInput implements InputInterface {

  /**
   * The prompts to convert to verify.
   *
   * @var array
   */
  private string $prompt;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to verify.
   */
  public function __construct(array $prompt) {
    $this->prompt = $prompt;
  }

  /**
   * Get the prompt.
   *
   * @return array
   *   The prompt.
   */
  public function getPrompt(): array {
    return $this->prompt;
  }

  /**
   * Set the prompt.
   *
   * @param array $prompt
   *   The prompt.
   */
  public function setPrompt(array $prompt) {
    $this->prompt = $prompt;
  }

}
