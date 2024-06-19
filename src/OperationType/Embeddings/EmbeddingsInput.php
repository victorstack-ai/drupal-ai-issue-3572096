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
   * @var string
   */
  private string $prompt;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to vectors.
   */
  public function __construct(string $prompt) {
    $this->prompt = $prompt;
  }

  /**
   * Get the prompt.
   *
   * @return string
   *   The prompt.
   */
  public function getPrompt(): string {
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

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->prompt;
  }

}
