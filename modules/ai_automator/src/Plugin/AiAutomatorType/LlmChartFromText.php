<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorType;

use Drupal\ai_automator\Attribute\AiAutomatorType;
use Drupal\ai_automator\PluginBaseClasses\Chart;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for an charts field.
 */
#[AiAutomatorType(
  id: 'llm_chart_from_text',
  label: new TranslatableMarkup('LLM: Chart From Text'),
  field_rule: 'chart_config',
  target: '',
)]
class LlmChartFromText extends Chart {


  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Chart From Text';

}
