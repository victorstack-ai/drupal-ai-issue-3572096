<?php

namespace Drupal\Tests\ai\Unit\Traits\File;

use Drupal\ai\Traits\File\FileMimeTypeTrait;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the FileMimeTypeTrait trait works correctly.
 *
 * @group traits
 * @covers \Drupal\ai\Traits\File\FileMimeTypeTrait
 */
class FileMimeTypeTraitTest extends TestCase {

  /**
   * The trait object.
   *
   * @var \Drupal\ai\Traits\File\FileMimeTypeTrait
   */
  protected $traitObject;

  /**
   * The mime type guesser mock.
   *
   * @var \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mimeTypeGuesser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Create a mock for the MimeTypeGuesser.
    $this->mimeTypeGuesser = $this->createMock(MimeTypeGuesser::class);

    // Create a container and set the 'file.mime_type.guesser' service.
    $container = new ContainerBuilder();
    $container->set('file.mime_type.guesser', $this->mimeTypeGuesser);
    \Drupal::setContainer($container);

    // Create an instance of the class using the trait.
    $this->traitObject = $this->getMockForTrait(FileMimeTypeTrait::class);
  }

  /**
   * Tests the getFileMimeTypeGuesser method.
   */
  public function testGetFileMimeTypeGuesser() {
    $this->assertSame($this->mimeTypeGuesser, $this->traitObject->getFileMimeTypeGuesser());
  }

}
