<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Taxonomy;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a taxonomy_term field.
 */
#[AiAutomatorType(
  id: 'llm_taxonomy',
  label: new TranslatableMarkup('LLM: Taxonomy'),
  field_rule: 'entity_reference',
  target: 'taxonomy_term',
)]
class LlmTaxonomy extends Taxonomy implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Taxonomy';

}
