<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Numeric;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

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
