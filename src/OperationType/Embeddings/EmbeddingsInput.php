<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\GenericType\ImageFile;
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
   * If its an image to convert to vectors.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile|null
   */
  private ImageFile|NULL $image;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to vectors.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The image to convert to vectors.
   */
  public function __construct(string $prompt = '', ImageFile $image = NULL) {
    $this->prompt = $prompt;
    $this->image = $image;
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
   * Get the image.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image.
   */
  public function getImage(): ImageFile {
    return $this->image;
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
   * Set the image.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   */
  public function setImage(ImageFile $image) {
    $this->image = $image;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->prompt;
  }

}
