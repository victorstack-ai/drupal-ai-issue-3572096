<?php

namespace Drupal\ai\Traits\File;

use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Trait to add the possibility to store medias directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateFileTrait {

  /**
   * Generate file.
   *
   * @param string $file_path
   *   The path to the file.
   * @param array $data
   *   The optional data to be saved.
   *
   * @return \Drupal\file\Entity\File[]
   *   The file entity.
   */
  public function getAsFileReference(string $file_path, $data = []): array {
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
    foreach ($data as $save_data) {
      // Generate a file from string and rename if it already exists.
      $file_path = $file_system->saveData($save_data, $file_path, FileExists::Rename);
      // Generate a file entity.
      $file = $file_storage->create([
        'uri' => $file_path,
        'status' => 1,
        'uid' => $this->getFileCurrentUser()->id(),
        'filename' => basename($file_path),
      ]);
      $file->save();
      $files[] = ['target_id' => $file->id()];
    }
    return $files;
  }

  /**
   * Get the file system.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system.
   */
  private function getFileFileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

  /**
   * Get the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  private function getFileCurrentUser(): AccountProxyInterface {
    return \Drupal::currentUser();
  }

}
