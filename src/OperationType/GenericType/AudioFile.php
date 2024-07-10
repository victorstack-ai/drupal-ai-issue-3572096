<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\file\Entity\File;

/**
 * The Audio file.
 */
class AudioFile {

  /**
   * The mime type of the file.
   *
   * @var string
   */
  private string $mime_type;

  /**
   * The filename if it exists.
   *
   * @var string
   */
  private string $filename;

  /**
   * The binary of the file
   *
   * @var string
   */
  private string $binary;

  /**
   * The constructor.
   *
   * @param string $binary
   *   The binary of the file.
   * @param string $mime_type
   *   The mime type of the file.
   * @param string $filename
   *   The filename of the file.
   */
  public function __construct(string $binary = "", string $mime_type = "", string $filename = "") {
    $this->binary = $binary;
    $this->mime_type = $mime_type;
    $this->filename = $filename;
  }

  /**
   * Get the mime type.
   *
   * @return string
   *   The mime type.
   */
  public function getMimeType(): string {
    return $this->mime_type;
  }

  /**
   * Get the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * Get the binary.
   *
   * @return string
   *   The binary.
   */
  public function getBinary(): string {
    return $this->binary;
  }

  /**
   * Set the mime type.
   *
   * @param string $mime_type
   *   The mime type.
   */
  public function setMimeType(string $mime_type): void {
    $this->mime_type = $mime_type;
  }

  /**
   * Set the filename.
   *
   * @param string $filename
   *   The filename.
   */
  public function setFilename(string $filename): void {
    $this->filename = $filename;
  }

  /**
   * Set the binary.
   *
   * @param string $binary
   *   The binary.
   */
  public function setBinary(string $binary): void {
    $this->binary = $binary;
  }

  /**
   * Sets the audio from an url.
   *
   * @param string $url
   *   The url.
   */
  public function setAudioFromUrl(string $url): void {
    // Get mime type from the uri.
    $this->mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $this->binary = file_get_contents($url);
    $this->filename = basename($url);
  }

  /**
   * Set the audio from a Drupal uri.
   *
   * @param string $uri
   *   The uri.
   */
  public function setAudioFromUri(string $uri): void {
    // Get mime type from the uri.
    $this->mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $this->binary = file_get_contents($uri);
    $this->filename = basename($uri);
  }

  /**
   * Sets the image from a Drupal file.
   *
   * @param \Drupal\file\Entity\File $file
   *  The file.
   */
  public function setAudioFromFile(File $file): void {
    $this->mime_type = $file->getMimeType();
    $this->binary = file_get_contents($file->getFileUri());
    $this->filename = $file->getFilename();
  }

  /**
   * Get the file mime type guesser.
   *
   * @return \Drupal\Core\File\MimeType\MimeTypeGuesser
   *   The stream wrapper.
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesser {
    return \Drupal::service('file.mime_type.guesser');
  }
}
