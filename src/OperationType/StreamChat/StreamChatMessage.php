<?php

namespace Drupal\ai\OperationType\StreamChat;

use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\file\Entity\File;

/**
 * Each of the chat messages for chat input.
 */
class StreamChatMessage {
  /**
   * The role of the message.
   *
   * @var string
   */
  private string $role;

  /**
   * The text.
   *
   * @var string
   */
  private string $text;

  /**
   * The base64 encoded images in an array.
   *
   * @var array
   */
  private array $images;

  /**
   * The constructor.
   *
   * @param string $role
   *   The role of the message.
   * @param string $text
   *   The text.
   * @param array $images
   *   The images.
   */
  public function __construct(string $role = "", string $text = "", array $images = []) {
    $this->role = $role;
    $this->text = $text;
    $this->images = $images;
  }

  /**
   * Get the role of the text.
   *
   * @return string
   *   The role.
   */
  public function getRole(): string {
    return $this->role;
  }

  /**
   * Get the text.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the role of the message.
   *
   * @param string $role
   *   The role.
   */
  public function setRole(string $role): void {
    $this->role = $role;
  }

  /**
   * Set the text.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * Get the images.
   *
   * @return array
   *   The images.
   */
  public function getImages(): array {
    return $this->images;
  }

  /**
   * Set the image.
   *
   * @param string $image
   *   The image.
   */
  public function setImage(string $image): void {
    $this->images[] = $image;
  }

  /**
   * Sets the image from a binary string.
   *
   * @param string $binary
   *   The binary string.
   * @param string $mime_type
   *   The mime type.
   */
  public function setImageFromBinary(string $binary, string $mime_type): void {
    $this->images[] = 'data:' . $mime_type . ';base64,' . base64_encode($binary);
  }

  /**
   * sets the image from an url.
   *
   * @param string $url
   *   The url.
   */
  public function setImageFromUrl(string $url): void {
    // Get mime type from the uri.
    $mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $this->images[] = 'data:' . $mime_type . ';base64,' . base64_encode((file_get_contents($url)));
  }

  /**
   * Set the image from a Drupal uri.
   *
   * @param string $uri
   *   The uri.
   */
  public function setImageFromUri(string $uri): void {
    // Get mime type from the uri.
    $mime_type = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $this->images[] = 'data:' . $mime_type . ';base64,' . base64_encode((file_get_contents($uri)));
  }

  /**
   * Sets the image from a Drupal file.
   *
   * @param \Drupal\file\Entity\File $file
   *  The file.
   */
  public function setImageFromFile(File $file): void {
    $this->images[] = 'data:' . $file->getMimeType() . ';base64,' . base64_encode(file_get_contents($file->getFileUri()));
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
