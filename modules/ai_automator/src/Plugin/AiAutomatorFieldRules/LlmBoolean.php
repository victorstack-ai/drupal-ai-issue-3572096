<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\Boolean;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an boolean field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_boolean',
  label: new TranslatableMarkup('LLM Boolean'),
  field_rule: 'boolean',
  target: '',
)]
class LlmBoolean extends Boolean implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Boolean';
}
