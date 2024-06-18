<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for embeddings input.
 */
class EmbeddingsInput implements InputInterface {
  /**
   * The prompts to convert to vectors.
   *
   * @var array
   */
  private string $prompt;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to vectors.
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
