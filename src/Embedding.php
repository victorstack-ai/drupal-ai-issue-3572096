<?php

declare(strict_types=1);

namespace Drupal\ai;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents an embedding.
 *
 * This class is used to encapsulate the details of an embedding,
 * including its unique identifier, the vector values, and any associated
 * metadata.
 */
class Embedding {

  public function __construct(
    #[Assert\Regex(
      pattern: '/^.+:.+$/',
      message: 'Id should be in the format "prefix:suffix"',
    )]
    readonly public string $id,
    #[Assert\NotBlank(message: 'Values should not be empty')]
    readonly public array $values,
    #[Assert\Type(type: 'array', message: 'Metadata should be an array')]
    protected array $metadata = [],
  ) {}

  /**
   * Puts a metadata key-value pair into the embedding.
   */
  public function putMetadata(string $key, mixed $value): void {
    $this->metadata[$key] = $value;
  }

  /**
   * Gets the metadata.
   *
   * @return array<string, mixed>
   *   An associative array of metadata key-value pairs.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

}
