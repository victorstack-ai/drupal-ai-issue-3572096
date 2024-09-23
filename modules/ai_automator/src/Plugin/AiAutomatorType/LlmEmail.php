<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Email;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an email field.
 */
#[AiAutomatorType(
  id: 'llm_email',
  label: new TranslatableMarkup('LLM: Email'),
  field_rule: 'email',
  target: '',
)]
class LlmEmail extends Email implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Email';

}
