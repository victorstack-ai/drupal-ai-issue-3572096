<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\CustomField;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for the custom field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_custom_field',
  label: new TranslatableMarkup('LLM Custom field'),
  field_rule: 'custom',
  target: '',
)]
class LlmCustomField extends CustomField implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Custom Field';

}
