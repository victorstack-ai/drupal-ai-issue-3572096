<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\AudioToText;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_audio_to_text_long',
  label: new TranslatableMarkup('LLM Audio to Text Long'),
  field_rule: 'text_long',
  target: '',
)]
class LlmAudioToTextLong extends AudioToText implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Audio to Text Long';
}
