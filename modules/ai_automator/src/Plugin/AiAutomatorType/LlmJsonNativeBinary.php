<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\PluginBaseClasses\TextToJsonField;
/**
 * The rules for an json_native_binary field.
 */
#[AiAutomatorType(
  id: 'llm_json_native_binary_field',
  label: new TranslatableMarkup('LLM: JSON Field'),
  field_rule: 'json_native_binary',
  target: '',
)]
class LlmJsonNativeBinary extends TextToJsonField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: JSON Field';

}
