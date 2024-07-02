<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple text completions.
 */
class SimpleTextCompletion extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text completion.";
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $values = $entity->get($automatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
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
    $total = [];
    foreach ($prompts as $prompt) {
      $value = $this->generateResponse($prompt, $automatorConfig, $entity, $fieldDefinition);
      if (!empty($value)) {
        $total = array_merge_recursive($total, $value);
      }
    }
    return $total;
  }

}
