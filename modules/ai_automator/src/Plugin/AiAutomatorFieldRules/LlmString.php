<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_string',
  label: new TranslatableMarkup('LLM String'),
  field_rule: 'string',
  target: '',
)]
class LlmString extends ComplexTextChat implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM String';

}
