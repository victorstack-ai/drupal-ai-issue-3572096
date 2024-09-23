<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Link;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an link field.
 */
#[AiAutomatorType(
  id: 'llm_link',
  label: new TranslatableMarkup('LLM: Link'),
  field_rule: 'link',
  target: '',
)]
class LlmLink extends Link implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Link';

}
