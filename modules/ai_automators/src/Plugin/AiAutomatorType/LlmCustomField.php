<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\CustomField;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for the custom field.
 */
#[AiAutomatorType(
  id: 'llm_custom_field',
  label: new TranslatableMarkup('LLM: Custom field'),
  field_rule: 'custom',
  target: '',
)]
class LlmCustomField extends CustomField implements AiAutomatorTypeInterface {

  /**
   * The title of the automator.
   *
   * @var string
   */
  public $title = 'LLM: Custom Field';

}
