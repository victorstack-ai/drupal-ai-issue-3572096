<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Numeric;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an decimal field.
 */
#[AiAutomatorType(
  id: 'llm_decimal',
  label: new TranslatableMarkup('LLM: Decimal'),
  field_rule: 'decimal',
  target: '',
)]
class LlmDecimal extends Numeric implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Decimal';

}
