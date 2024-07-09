<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Numeric;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an float field.
 */
#[AiAutomatorType(
  id: 'llm_float',
  label: new TranslatableMarkup('LLM: Float'),
  field_rule: 'float',
  target: '',
)]
class LlmFloat extends Numeric implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Float';

}
