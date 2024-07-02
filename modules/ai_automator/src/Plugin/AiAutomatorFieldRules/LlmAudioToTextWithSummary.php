<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\AudioToText;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text_with_summary field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_audio_to_text_with_summary',
  label: new TranslatableMarkup('LLM Audio to Text with Summary'),
  field_rule: 'text_with_summary',
  target: '',
)]
class LlmAudioToTextWithSummary extends AudioToText implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Audio to Text with Summary';
}
