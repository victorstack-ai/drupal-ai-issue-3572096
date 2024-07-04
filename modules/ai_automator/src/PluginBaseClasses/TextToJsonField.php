<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs json output.
 */
class TextToJsonField extends SimpleTextChat {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to JSON field model.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the actor context, give back a list of all the movies that person has been in, together with year of release.\n\nContext:\n{{ context }}\n\n------------------------------\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"movie_title\": \"title of movie\", \"release_year\": \"year of release\"}]";
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
        $total[] = $values;
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Check so its valid JSON.
    if (empty($value)) {
      return FALSE;
    }
    $json = json_decode($value, TRUE);
    if (empty($json)) {
      return FALSE;
    }
    return TRUE;
  }

}
