<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'llm_string_long',
  label: new TranslatableMarkup('LLM: Text'),
  field_rule: 'string_long',
  target: '',
)]
class LlmStringLong extends ComplexTextChat implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Text';

}
