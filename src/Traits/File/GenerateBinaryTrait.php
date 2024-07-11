<?php

namespace Drupal\ai\Traits\File;

/**
 * Trait to add the possibility to store output as binary strings.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateBinaryTrait {

  /**
   * Generate binary string.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file entity.
   */
  public function getAsBinary(): array {
    $strings = [];
    /* @var \Drupal\ai\OperationType\GenericType\FileBase $file */
    foreach ($this->getNormalized() as $file) {
      $strings[] = $file->getBinary();
    }
    return $strings;
  }

}
