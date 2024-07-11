<?php

namespace Drupal\ai\Traits\File;

use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Trait to add the possibility to store images directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateImageTrait {

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
  public function getAsImageReference(string $file_path, array $data = []): array {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('image')) {
      throw new AiBrokenOutputException('Image module is not installed, getAsImageReference will not work.');
    }
    // Get the directory from the file path.
    $directory = dirname($file_path);
    // Get the file system.
    $file_system = $this->getImageFileSystem();
    // Prepare the directory.
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    // Check if custom data is wanted.
    $data = !empty($data) ? $data : $this->getNormalized();

    $files = [];
    /* @var \Drupal\ai\OperationType\GenericType\FileBase $save_data */
    foreach ($data as $save_data) {
      // Generate a file from string and rename if it already exists.
      $file_path = $file_system->saveData($save_data->getBinary(), $file_path, FileExists::Rename);
      // Get some meta data for images.
      $resolution = getimagesize($file_path);
      // Generate a file entity.
      $file = \Drupal::entityTypeManager()->getStorage('file')->create([
        'uri' => $file_path,
        'status' => 1,
        'uid' => $this->getImageCurrentUser()->id(),
        'filename' => basename($file_path),
        'width' => $resolution[0],
        'height' => $resolution[1],
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
  private function getImageFileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

  /**
   * Get the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  private function getImageCurrentUser(): AccountProxyInterface {
    return \Drupal::currentUser();
  }

}
