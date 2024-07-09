<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\CustomField;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
   * {@inheritDoc}
   */
  public $title = 'LLM: Custom Field';

}
