<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Lists;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an list_string field.
 */
#[AiAutomatorType(
  id: 'llm_list_string',
  label: new TranslatableMarkup('LLM: List'),
  field_rule: 'list_string',
  target: '',
)]
class LlmListString extends Lists implements AiAutomatorTypeInterface {

  /**
   * The title of the automator.
   *
   * @var string
   */
  public $title = 'LLM: List';

}
