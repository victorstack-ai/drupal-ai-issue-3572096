<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\TextToMediaImage;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'llm_media_image_generation',
  label: new TranslatableMarkup('LLM: Media Image Generation'),
  field_rule: 'entity_reference',
  target: 'media',
)]
class LlmMediaImageGeneration extends TextToMediaImage implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Media Image Generation';

}
