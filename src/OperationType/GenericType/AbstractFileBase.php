<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\file\Entity\File;

/**
 * The file base.
 */
abstract class AbstractFileBase implements FileBaseInterface {

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
   * {@inheritdoc}
   */
  public function __construct(string $binary = "", string $mime_type = "", string $filename = "") {
    $this->binary = $binary;
    $this->mime_type = $mime_type;
    $this->filename = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType(): string {
    return $this->mime_type;
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
    if ($this->mime_type) {
      return "data:" . $this->mime_type . ";base64," . base64_encode($this->binary);
    }
    return base64_encode($this->binary);
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType(string $mime_type): void {
    $this->mime_type = $mime_type;
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
    $this->mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $this->binary = file_get_contents($url);
    $this->filename = basename($url);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromUri(string $uri): void {
    // Get mime type from the uri.
    $this->mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $this->binary = file_get_contents($uri);
    $this->filename = basename($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromFile(File $file): void {
    $this->mime_type = $file->getMimeType();
    $this->binary = file_get_contents($file->getFileUri());
    $this->filename = $file->getFilename();
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesser {
    return \Drupal::service('file.mime_type.guesser');
  }

}
