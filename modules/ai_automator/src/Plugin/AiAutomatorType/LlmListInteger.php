<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Lists;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an list_integer field.
 */
#[AiAutomatorType(
  id: 'llm_list_integer',
  label: new TranslatableMarkup('LLM: List'),
  field_rule: 'list_integer',
  target: '',
)]
class LlmListInteger extends Lists implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: List';

}
