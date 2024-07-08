<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Utility\CastUtility;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs simple custom field rules.
 */
class CustomField extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find complex amount of data and fill in complex field types with it.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context extract all quotes and fill in the quote, a translated quote into english, the persons name and the persons role.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return isset($value[0]) && $value[0][key($value[0])] ? [1] : FALSE;
  }

  /**
   * Adds the custom form fields.
   *
   * @param string $prefix
   *   The prefix.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return array
   *   The form.
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    if (isset($config['field_settings'])) {
      foreach ($config['field_settings'] as $key => $value) {
        $form["automator_llm_custom_value_" . $key] = [
          '#type' => 'textfield',
          '#title' => $value['widget_settings']['label'],
          '#description' => $this->t('One sentence how the %label should be filled out. For instance "the original quote".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $defaultValues["automator_llm_custom_value_" . $key] ?? '',
          '#weight' => 14,
        ];

        $form["automator_llm_custom_oneshot_" . $key] = [
          '#type' => 'textfield',
          '#title' => $this->t('Example %label', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#description' => $this->t('One example %label of a filled out value for one shot learning. For instance "To be or not to be".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $defaultValues["automator_llm_custom_oneshot_" . $key] ?? '',
          '#weight' => 14,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_custom_value_')) {
        $example[substr($key, strlen('llm_custom_value_'))] = $value;
      }
      elseif (str_starts_with($key, 'llm_custom_oneshot_')) {
        $oneShot[substr($key, strlen('llm_custom_oneshot_'))] = $value;
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($example) . "}]";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($oneShot) . "}]\n";
      $prompts[$key] = $prompt;
    }

    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      // Create new messages.
      $input = new ChatInput([
        new ChatMessage("user", $prompt),
      ]);

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
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be array, otherwise no validation for now.
    if (!is_array($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

}
