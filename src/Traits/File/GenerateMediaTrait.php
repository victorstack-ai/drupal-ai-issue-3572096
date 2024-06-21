<?php

namespace Drupal\ai\Traits\File;

use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;

/**
 * Trait to add the possibility to store medias directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateMediaTrait {

  // We need to create files.
  use GenerateFileTrait;
  use GenerateImageTrait;

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
  public function getAsMediaReference(string $media_type, string $file_name, string $path = NULL): array {
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
      $medias[] = ['target_id' => $media->id()];
    }

    return $medias;
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
    $file_path = $this->getMediaToken()->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/'));
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
  private function getMediaToken(): Token {
    return \Drupal::token();
  }

  /**
   * Get the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  private function getMediaCurrentUser(): AccountProxyInterface {
    return \Drupal::currentUser();
  }

}
