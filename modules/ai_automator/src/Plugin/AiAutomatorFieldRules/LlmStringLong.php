<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_string_long',
  label: new TranslatableMarkup('LLM String Long'),
  field_rule: 'string_long',
  target: '',
)]
class LlmStringLong extends ComplexTextChat implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM String Long';

}
