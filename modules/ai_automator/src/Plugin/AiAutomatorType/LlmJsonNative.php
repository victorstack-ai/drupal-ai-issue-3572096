<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\TextToJsonField;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an json_native field.
 */
#[AiAutomatorType(
  id: 'llm_json_native_field',
  label: new TranslatableMarkup('LLM: JSON Field'),
  field_rule: 'json_native',
  target: '',
)]
class LlmJsonNative extends TextToJsonField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: JSON Field';

}
