<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\AudioToText;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'llm_audio_to_text_long',
  label: new TranslatableMarkup('LLM: Audio to Text'),
  field_rule: 'text_long',
  target: '',
)]
class LlmAudioToTextLong extends AudioToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio to Text';

}
