<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\TextToImage;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an image field.
 */
#[AiAutomatorType(
  id: 'llm_image_generation',
  label: new TranslatableMarkup('LLM: Image Generation'),
  field_rule: 'image',
  target: 'file',
)]
class LlmImageGeneration extends TextToImage implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Image Generation';

}
