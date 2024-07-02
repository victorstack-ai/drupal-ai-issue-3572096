<?php

namespace Drupal\ai_automator\Plugin\AiEvaluationTool;

use Drupal\ai_evaluation\PluginInterface\AiToolPluginInterface;
use Drupal\ai_evaluation\Attribute\AiTool;
use Drupal\Core\StringTranslation\TranslatableMarkup;


#[AiTool(
  id: 'ai_automator_tool',
  label: new TranslatableMarkup('AI Automator'),
  description: new TranslatableMarkup('Use the AI Automator to compare workflows.'),
)]
class AiAutomatorTool implements AiToolPluginInterface {

}
