<?php

namespace Drupal\ai\OperationType\TextToImage;

use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Traits\File\GenerateBase64Trait;
use Drupal\ai\Traits\File\GenerateBinaryTrait;
use Drupal\ai\Traits\File\GenerateImageTrait;
use Drupal\ai\Traits\File\GenerateMediaTrait;

/**
 * Data transfer output object for text to speech output.
 */
class TextToImageOutput implements OutputInterface {

  // We want to be able to generate into binary, media, images and base64.
  use GenerateBinaryTrait;
  use GenerateMediaTrait;
  use GenerateImageTrait;
  use GenerateBase64Trait;

  /**
   * The normalized ImageType.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageType[]
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
   * @var \Drupal\ai\OperationType\GenericType\ImageType[] $normalized
   *   The metadata.
   * @var mixed $rawOutput
   *   The raw output
   * @var mixed $metadata
   *   The metadata
   */
  private mixed $metadata;

  public function __construct(array $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns an array of ImageType objects.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageType[]
   *   The ImageType objects.
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
