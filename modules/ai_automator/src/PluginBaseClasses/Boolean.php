<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple boolean rules.
 */
class Boolean extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to boolean model.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text answer with a {{ true }} if there is some information about Pippi Longstockings. Otherwise answer {{ false }}.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return empty($value[0]['value']) ? [] : $value;
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['true'] = 'The true value.';
    $tokens['false'] = 'The false value.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig);
    $tokens['true'] = 'TRUE';
    $tokens['false'] = 'FALSE';
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
    } elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"TRUE or FALSE\"}\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->aiPluginManager->createInstance($automatorConfig['ai_provider']);

    // Get configuration.
    $config = [];
    $configCast = $instance->getAvailableConfiguration('chat', $automatorConfig['ai_model']);
    foreach ($automatorConfig as $key => $val) {
      if (strpos($key, 'configuration_') === 0 && $val) {
        $configKey = str_replace('configuration_', '', $key);
        $config[$configKey] = CastUtility::typeCast($configCast[$configKey]['type'], $val);
      }
    }
    foreach ($prompts as $prompt) {
      // Create new messages.
      $input = new ChatInput([
        new ChatMessage("user", $prompt),
      ]);

      $instance->setConfiguration($config);
      $response = $instance->chat($input, $automatorConfig['ai_model'])->getNormalized();

      // Normalize the response.
      $values = json_decode(str_replace("\n", "", trim(str_replace(['```json', '```'], '', $response->getText()))), TRUE);

      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Has to be string boolean.
    if (!in_array($value['value'], ['TRUE', 'FALSE', '0', '1', 0, 1])) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Transform string to boolean.
    foreach ($values as $key => $value) {
      $values[$key] = in_array($value['value'], ['TRUE', '1', 1]) ? TRUE : FALSE;
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
