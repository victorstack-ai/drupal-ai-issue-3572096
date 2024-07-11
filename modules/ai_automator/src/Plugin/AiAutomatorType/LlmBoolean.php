<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Boolean;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an boolean field.
 */
#[AiAutomatorType(
  id: 'llm_boolean',
  label: new TranslatableMarkup('LLM: Boolean'),
  field_rule: 'boolean',
  target: '',
)]
class LlmBoolean extends Boolean implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Boolean';

}
