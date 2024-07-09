<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\FaqField;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an faq field.
 */
#[AiAutomatorType(
  id: 'llm_faq_field',
  label: new TranslatableMarkup('LLM: FAQ Field'),
  field_rule: 'faqfield',
  target: '',
)]
class LlmFaqField extends FaqField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: FAQ Field';

}
