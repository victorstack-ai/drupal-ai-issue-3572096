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
   * @return array
   *   An array of base64 encoded strings.
   */
  public function getAsBase64EncodedStrings(string $data_url_scheme = ''): array {
    $strings = [];
    /* @param \Drupal\ai\OperationType\GenericType\FileBase $file */
    foreach ($this->getNormalized() as $file) {
      $base64 = base64_encode($file->getBinary());
      if ($data_url_scheme) {
        $base64 = $data_url_scheme . ';base64,' . $base64;
      }
      else if ($file->getMimeType()) {
        $base64 = 'data:' . $file->getMimeType() . ';charset=utf-8;base64,' . $base64;
      }
      $strings[] = $base64;
    }
    return $strings;
  }

}
