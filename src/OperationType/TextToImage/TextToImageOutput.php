<?php

namespace Drupal\ai\OperationType\TextToImage;

use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Traits\File\GenerateBase64Trait;
use Drupal\ai\Traits\File\GenerateImageTrait;
use Drupal\ai\Traits\File\GenerateMediaTrait;

/**
 * Data transfer output object for text to speech output.
 */
class TextToImageOutput implements OutputInterface {

  // We want to be able to generate into media, images and base64.
  use GenerateMediaTrait;
  use GenerateImageTrait;
  use GenerateBase64Trait;

  /**
   * The normalized image binaries.
   *
   * @var array
   */
  private array $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  public function __construct(mixed $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns an array of image binaries.
   *
   * @return array
   *   The image binaries.
   */
  public function getNormalized(): array {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
