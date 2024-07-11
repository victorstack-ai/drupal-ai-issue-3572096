<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\AudioToText;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text_with_summary field.
 */
#[AiAutomatorType(
  id: 'llm_audio_to_text_with_summary',
  label: new TranslatableMarkup('LLM: Audio to Text'),
  field_rule: 'text_with_summary',
  target: '',
)]
class LlmAudioToTextWithSummary extends AudioToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio to Text';

}
