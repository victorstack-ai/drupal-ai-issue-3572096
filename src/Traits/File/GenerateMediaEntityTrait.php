<?php

namespace Drupal\ai\Traits\File;

use Drupal\ai\Exception\AiBrokenOutputException;

/**
 * Trait to add the possibility to store medias directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateMediaEntityTrait {

  // We need to create files.
  use GenerateFileTrait;
  use GenerateImageTrait;
  use GenerateMediaTrait;

  /**
   * Generate media.
   *
   * @param string $media_type
   *   The media type.
   * @param string $file_name
   *   The file name.
   * @param string $path
   *   An optional path instead of the field configuration one.
   *
   * @return \Drupal\media\Entity\Media[]
   *   The media entity.
   */
  public function getAsMediaEntity(string $media_type, string $file_name, string $path = NULL): array {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      throw new AiBrokenOutputException('Media module is not installed, getAsMediaReference will not work.');
    }
    // Check if the media type exists.
    if (!\Drupal::entityTypeManager()->getStorage('media_type')->load($media_type)) {
      throw new AiBrokenOutputException('Media type does not exist.');
    }
    // Get the base field.
    $base_field = $this->getBaseMediaFieldDefinition($media_type);
    if (!$base_field) {
      throw new AiBrokenOutputException('Media type does not have a base field.');
    }
    // Generate the path.
    if (!$path) {
      $path = $this->getMediaFilePath($base_field, $file_name);
    }

    $medias = [];
    /** @var \Drupal\ai\OperationType\GenericType\FileBase $data */
    foreach ($this->getNormalized() as $data) {
      // Create the file, depending on type.
      $file = NULL;
      if ($base_field->getType() === 'image') {
        $file = $this->getAsImageReference($path, [$data])[0];
      }
      else {
        $file = $this->getAsFileReference($path, [$data])[0];
      }

      // Create the media.
      $media = \Drupal::entityTypeManager()->getStorage('media')->create([
        'bundle' => $media_type,
        'uid' => $this->getMediaCurrentUser()->id(),
        'status' => 1,
        $base_field->getName() => [
          'target_id' => $file->id(),
          'alt' => $file_name,
        ],
      ]);
      $media->save();
      $medias[] = $media->id();
    }

    return $medias;
  }

}
