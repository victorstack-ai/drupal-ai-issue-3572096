<?php

namespace Drupal\ai\Traits\File;

use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Trait to add the possibility to store files directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateFileEntityTrait {

  use GenerateFileTrait;

  /**
   * Generate file entities.
   *
   * @param string $file_path
   *   The path to the file.
   * @param array $data
   *   The optional data to be saved.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file entity.
   */
  public function getAsFileEntities(string $file_path, $data = []): array {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('file')) {
      throw new AiBrokenOutputException('File module is not installed, getAsFileReference will not work.');
    }
    // Get the directory from the file path.
    $directory = dirname($file_path);
    // Get the file system.
    $file_system = $this->getFileFileSystem();
    // Prepare the directory.
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    // Check if custom data is wanted.
    $data = !empty($data) ? $data : $this->getNormalized();

    $files = [];
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    /** @var \Drupal\ai\OperationType\GenericType\FileBase $save_date */
    foreach ($data as $save_data) {
      // Generate a file from string and rename if it already exists.
      $file_path = $file_system->saveData($save_data->getBinary(), $file_path, FileExists::Rename);
      // Generate a file entity.
      $file = $file_storage->create([
        'uri' => $file_path,
        'status' => 1,
        'uid' => $this->getFileCurrentUser()->id(),
        'filename' => basename($file_path),
      ]);
      $file->save();
      $files[] = $file;
    }
    return $files;
  }

}
