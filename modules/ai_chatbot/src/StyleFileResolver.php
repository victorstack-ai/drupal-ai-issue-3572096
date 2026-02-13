<?php

namespace Drupal\ai_chatbot;

/**
 * Resolves style files for DeepChat block placement.
 */
final class StyleFileResolver {

  /**
   * Toolbar style should always be used for toolbar placement.
   *
   * @param array $configuration
   *   The DeepChat block configuration.
   *
   * @return string
   *   The style file key.
   */
  public static function resolve(array $configuration): string {
    if (($configuration['placement'] ?? 'toolbar') === 'toolbar') {
      return 'module:ai_chatbot:toolbar.yml';
    }

    return $configuration['style_file'] ?? 'module:ai_chatbot:toolbar.yml';
  }

}
