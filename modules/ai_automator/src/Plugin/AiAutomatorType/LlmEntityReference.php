<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\EntityReference;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an entity reference field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_entity_reference",
 *   title = @Translation("OpenAI Entity Reference"),
 *   field_rule = "entity_reference",
 *   target = "any"
 * )
 */
#[AiAutomatorType(
  id: 'llm_entity_reference',
  label: new TranslatableMarkup('LLM Entity Reference'),
  field_rule: 'entity_reference',
  target: 'any',
)]
class LlmEntityReference extends EntityReference implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Entity Reference';

}
