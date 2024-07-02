<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple numeric rules.
 */
class Numeric extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to decimal model.";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['min'] = 'A min numeric value, if set.';
    $tokens['max'] = 'A max numeric value, if set.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig, $delta);
    $tokens['min'] = $config['min'] ?? NULL;
    $tokens['max'] = $config['max'] ?? NULL;
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderTokenPrompt($automatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    foreach ($prompts as $prompt) {
      $values = $this->generateResponse($prompt, $automatorConfig, $entity, $fieldDefinition);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // Has to be number.
    if (!is_numeric($value)) {
      return FALSE;
    }
    // Has to be larger or equal to min.
    if (is_numeric($config['min']) && $config['min'] >= $value) {
      return FALSE;
    }
    // Has to be smaller or equal to max.
    if (is_numeric($config['max']) && $config['max'] <= $value) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

}
