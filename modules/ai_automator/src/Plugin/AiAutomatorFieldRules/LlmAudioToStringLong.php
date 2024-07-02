<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\AudioToText;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_audio_to_string_long',
  label: new TranslatableMarkup('LLM Audio to String Long'),
  field_rule: 'string_long',
  target: '',
)]
class LlmAudioToStringLong extends AudioToText implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Audio to String Long';
}
