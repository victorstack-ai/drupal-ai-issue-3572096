<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\SimpleTextChat;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'llm_simple_text_long',
  label: new TranslatableMarkup('LLM: Text (simple)'),
  field_rule: 'text_long',
  target: '',
)]
class LlmSimpleTextLong extends SimpleTextChat implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Text (simple)';

}
