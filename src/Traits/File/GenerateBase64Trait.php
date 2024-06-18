<?php

namespace Drupal\ai\Traits\File;

/**
 * Trait to add the possibility to store output base64 encoded strings.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateBase64Trait {

  /**
   * Generate base64 encoded string.
   *
   * @param string $data_url_scheme
   *   Add a data url scheme, like 'data:image/png'.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file entity.
   */
  public function getAsBase64EncodedString(string $data_url_scheme = ''): array {
    $strings = [];
    foreach ($this->getNormalized() as $binary) {
      $base64 = base64_encode($binary);
      $strings[] = $data_url_scheme ? $data_url_scheme . ';base64,' . $base64 : $base64;
    }
    return $strings;
  }

}
