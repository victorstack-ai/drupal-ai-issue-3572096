<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Telephone;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a telephone field.
 */
#[AiAutomatorType(
  id: 'llm_telephone',
  label: new TranslatableMarkup('LLM: Telephone'),
  field_rule: 'telephone',
  target: '',
)]
class LlmTelephone extends Telephone implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Telephone';

}
