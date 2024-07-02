<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs complex text chat.
 */
class ComplexTextChat extends SimpleTextChat {

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    $this->getGeneralHelper()->addJoinerConfigurationFormField('automator', $form, $entity, $fieldDefinition);
    return $form;
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

    // If we should join, we do that.
    if (isset($interpolatorConfig['joiner']) && $interpolatorConfig['joiner']) {
      $joiner = $interpolatorConfig['joiner'];
      if ($joiner == 'other') {
        $joiner = $interpolatorConfig['joiner_other'];
      }
      // Reset values.
      $values = [$this->getGeneralHelper()->joinValues($values, $joiner)];
    }
    return $values;
  }

}
