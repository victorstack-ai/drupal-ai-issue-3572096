<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple telephone rules.
 */
class Telephone extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find telephone numbers in text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all telephone numbers listed with a country code added.\n\nContext:\n{{ context }}";
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
      $prompt .= "\n\n\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"plain telephone number with plus and country code\"}]";
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
    // Has to be valid telephone number.
    $pattern = '/^\+\(?[0-9]{1,3}\)?[ ]?[0-9]{6,12}$/';
    if (preg_match($pattern, $value)) {
      return TRUE;
    }
    // Otherwise it is not ok.
    return FALSE;
  }

}
