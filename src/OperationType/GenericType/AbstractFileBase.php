<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * The file base.
 */
abstract class AbstractFileBase implements FileBaseInterface {

  /**
   * The mime type of the file.
   *
   * @var string
   */
  private string $mimeType;

  /**
   * The filename if it exists.
   *
   * @var string
   */
  private string $filename;

  /**
   * The binary of the file.
   *
   * @var string
   */
  private string $binary;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $binary = "", string $mime_type = "", string $filename = "") {
    $this->binary = $binary;
    $this->mimeType = $mime_type;
    $this->filename = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType(): string {
    return $this->mimeType;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getBinary(): string {
    return $this->binary;
  }

  /**
   * {@inheritdoc}
   */
  public function getAsBase64EncodedString() {
    if ($this->mimeType) {
      return "data:" . $this->mimeType . ";base64," . base64_encode($this->binary);
    }
    return base64_encode($this->binary);
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType(string $mime_type): void {
    $this->mimeType = $mime_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilename(string $filename): void {
    $this->filename = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function setBinary(string $binary): void {
    $this->binary = $binary;
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromUrl(string $url): void {
    // Get mime type from the uri.
    $this->mimeType = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $this->binary = file_get_contents($url);
    $this->filename = basename($url);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromUri(string $uri): void {
    // Get mime type from the uri.
    $this->mimeType = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $this->binary = file_get_contents($uri);
    $this->filename = basename($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromFile(File $file): void {
    $this->mimeType = $file->getMimeType();
    $this->binary = file_get_contents($file->getFileUri());
    $this->filename = $file->getFilename();
  }

  /**
   * Get as binary with same naming convention.
   *
   * @return string
   *   The binary.
   */
  public function getAsBinary(): string {
    return $this->getBinary();
  }

  /**
   * Generate media.
   *
   * @param string $media_type
   *   The media type.
   * @param string $file_path
   *   An optional path instead of the field configuration one.
   * @param string $filename
   *   The optional file name.
   *
   * @return \Drupal\media\Entity\Media
   *   The media entity.
   */
  public function getAsMediaEntity(string $media_type, string $file_path = "", string $filename = ""): Media {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      throw new AiBrokenOutputException('Media module is not installed, getAsMediaReference will not work.');
    }
    // Check if the media type exists.
    if (!$this->getEntityTypeManager()->getStorage('media_type')->load($media_type)) {
      throw new AiBrokenOutputException('Media type does not exist.');
    }
    // Get the base field.
    $base_field = $this->getBaseMediaFieldDefinition($media_type);
    if (!$base_field) {
      throw new AiBrokenOutputException('Media type does not have a base field.');
    }

    if (!$filename) {
      if ($this->filename) {
        $filename = $this->filename;
      }
      else {
        $filename = uniqid();
      }
    }
    // Generate the path.
    if (!$file_path) {
      $file_path = $this->getMediaFilePath($base_field, $filename);
    }

    // Create the file, depending on type.
    $file = NULL;
    if ($base_field->getType() === 'image') {
      $file = $this->getAsImageEntity($file_path, $filename);
    }
    else {
      $file = $this->getAsFileEntity($file_path, $filename);
    }

    // Create the media.
    $media = $this->getEntityTypeManager()->getStorage('media')->create([
      'bundle' => $media_type,
      'uid' => $this->getCurrentUser()->id(),
      'status' => 1,
      $base_field->getName() => [
        'target_id' => $file->id(),
        'alt' => $filename,
      ],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Get as image entity.
   *
   * @param string $file_path
   *   The file path.
   * @param string $filename
   *   The filename.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   */
  public function getAsImageEntity(string $file_path = "", string $filename = ""): File {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('image')) {
      throw new AiBrokenOutputException('Image module is not installed, getAsImageReference will not work.');
    }
    // Get the file.
    $file = $this->getAsFileEntity($file_path, $filename);
    // Get the resolution.
    $resolution = getimagesize($file->getFileUri());
    $file->set('width', $resolution[0]);
    $file->set('height', $resolution[1]);
    $file->save();
    return $file;
  }

  /**
   * Get as file entity.
   *
   * @param string $file_path
   *   The file path.
   * @param string $filename
   *   The filename.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   */
  public function getAsFileEntity($file_path = "", $filename = ""): File {
    // Set defaults.
    if (!$file_path) {
      $file_path = 'public://';
    }
    if (!$filename) {
      if ($this->filename) {
        $filename = $this->filename;
      }
      else {
        $filename = uniqid();
      }
    }
    // Get the directory from the file path.
    $directory = dirname($file_path);
    // Get the file system.
    $file_system = $this->getFileSystem();
    // Prepare the directory.
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $file_storage = $this->getEntityTypeManager()->getStorage('file');
    // Generate a file from string and rename if it already exists.
    $file_path = $file_system->saveData($this->getBinary(), $file_path, FileExists::Rename);
    // Generate a file entity.
    $file = $file_storage->create([
      'uri' => $file_path,
      'status' => 1,
      'uid' => $this->getCurrentUser()->id(),
      'filename' => basename($file_path),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Gets the path from the field defintion.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_definition
   *   The field definition.
   * @param string $file_name
   *   The file name.
   *
   * @return string
   *   The path.
   */
  private function getMediaFilePath(FieldConfigInterface $field_definition, string $file_name): string {
    $config = $field_definition->getSettings();
    $file_path = $this->getToken()->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/'));
    return $file_path . '/' . $file_name;
  }

  /**
   * Get the base media field for the media type.
   *
   * @param string $media_type
   *   The media type.
   *
   * @return string
   *   The base media field.
   */
  private function getBaseMediaField(string $media_type): string {
    /** @var \Drupal\media\Entity\MediaType $media_type */
    $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);
    $base_field = $media_type->getSource()->getConfiguration()['source_field'] ?? '';
    return $base_field;
  }

  /**
   * Gets the field definition for the source field on the media type.
   *
   * @param string $media_type
   *   The media type.
   *
   * @return \Drupal\Core\Field\FieldConfigInterface|null
   *   The field definition.
   */
  private function getBaseMediaFieldDefinition(string $media_type): ?FieldConfigInterface {
    $base_field = $this->getBaseMediaField($media_type);
    /** @var \Drupal\field\FieldConfigStorage */
    $field_config_storage = \Drupal::entityTypeManager()->getStorage('field_config');
    $field_definition = $field_config_storage->load('media.' . $media_type . '.' . $base_field);
    return $field_definition;
  }

  /**
   * Gets the token replacement service.
   *
   * @return \Drupal\Core\Utility\Token
   *   The token replacement service.
   */
  private function getToken(): Token {
    return \Drupal::token();
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesser {
    return \Drupal::service('file.mime_type.guesser');
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::service('entity_type.manager');
  }

  /**
   * Get the file system.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system.
   */
  private function getFileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

  /**
   * Get the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  private function getCurrentUser(): AccountProxyInterface {
    return \Drupal::currentUser();
  }

}
