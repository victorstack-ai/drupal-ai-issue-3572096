<?php

namespace Drupal\ai\Enum;

/**
 * Enum for available LLM bundles (types).
 */
enum Bundles: string {
  case Chat = 'chat';
  case TextCompletion = 'text_completion';
  case TextClassification = 'text_classification';
  case TextToImage = 'text_to_image';
  case ImageToText = 'image_to_text';
  case Embedding = 'text_embedding';
  case Tokenizer = 'tokenizer';
  case Summarization = 'summarization';
  case SpeechToText = 'speech_to_text';
  case TextToSpeech = 'text_to_speech';

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
