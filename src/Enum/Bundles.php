<?php

namespace Drupal\ai\Enum;

/**
 * Enum for available LLM bundles (types).
 */
enum Bundles: string {
  case Chat = 'Chat';
  case TextToImage = 'Text To Image';
  case Embedding = 'Text Embedding';
  case Tokenizer = 'Tokenizer';
  case Summarization = 'Summarization';

  /**
   * Returns the enum instance matching the provided name or null.
   *
   * @param string $name
   *   PascalCase name of the enum case.
   *
   * @return self|null
   *   Returns Enum object matching its name.
   */
  public static function fromName(string $name): ?self {
    foreach (self::cases() as $case) {
      if ($case->name === $name) {
        return $case;
      }
    }
    return NULL;
  }

}
