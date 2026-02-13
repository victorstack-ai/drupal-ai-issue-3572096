<?php

namespace Drupal\Tests\ai_chatbot\Unit;

use Drupal\ai_chatbot\StyleFileResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/StyleFileResolver.php';

/**
 * Tests style-file resolution for DeepChat toolbar placement.
 *
 * @group ai_chatbot
 */
class DeepChatFormBlockStyleFileTest extends TestCase {

  /**
   * Tests that toolbar placement always uses the toolbar style file.
   */
  public function testToolbarPlacementAlwaysUsesToolbarStyle(): void {
    $configuration = [
      'placement' => 'toolbar',
      'style_file' => 'module:ai_chatbot:chatgpt.yml',
    ];

    $this->assertSame('module:ai_chatbot:toolbar.yml', StyleFileResolver::resolve($configuration));
  }

  /**
   * Tests that non-toolbar placements keep configured style.
   */
  public function testNonToolbarPlacementUsesConfiguredStyle(): void {
    $configuration = [
      'placement' => 'bottom-right',
      'style_file' => 'module:ai_chatbot:chatgpt.yml',
    ];

    $this->assertSame('module:ai_chatbot:chatgpt.yml', StyleFileResolver::resolve($configuration));
  }

  /**
   * Tests fallback style when style_file is missing.
   */
  public function testMissingStyleFileFallsBackToToolbarStyle(): void {
    $configuration = [
      'placement' => 'bottom-right',
    ];

    $this->assertSame('module:ai_chatbot:toolbar.yml', StyleFileResolver::resolve($configuration));
  }

}
