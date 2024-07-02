<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\Numeric;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an decimal field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_decimal',
  label: new TranslatableMarkup('LLM Decimal'),
  field_rule: 'decimal',
  target: '',
)]
class LlmDecimal extends Numeric implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Llm Decimal';
}
