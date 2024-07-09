<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\OfficeHours;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an office_hours field.
 */
#[AiAutomatorType(
  id: 'llm_office_hours',
  label: new TranslatableMarkup('LLM: Office Hours'),
  field_rule: 'office_hours',
  target: '',
)]
class LlmOfficeHours extends OfficeHours implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Office Hours';

}
