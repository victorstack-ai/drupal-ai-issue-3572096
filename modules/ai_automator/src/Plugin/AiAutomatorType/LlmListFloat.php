<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Lists;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an list_float field.
 */
#[AiAutomatorType(
  id: 'llm_list_float',
  label: new TranslatableMarkup('LLM: List'),
  field_rule: 'list_float',
  target: '',
)]
class LlmListFloat extends Lists implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: List';

}
