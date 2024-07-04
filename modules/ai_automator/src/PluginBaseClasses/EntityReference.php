<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs entity reference rule.
 */
abstract class EntityReference extends RuleBase {

  /**
   * Allowed field types initially.
   *
   * @var array
   */
  public $allowedTypes = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
    'list_string',
    'email',
    'telephone',
    'integer',
    'list_integer',
    'decimal',
    'float',
    'list_float',
  ];

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can take a field on the parent node and seed entity reference nodes.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text I want a title and a description.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'] ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    // Load the target type.
    $targetType = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'];
    // Check if the target type has bundles.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($targetType);
    if ($bundles) {
      $options = [
        '' => t('Select a bundle'),
      ];
      foreach ($bundles as $bundle => $info) {
        $options[$bundle] = $info['label'];
      }
      $chosenBundle = $defaultValues['entity_reference_bundle'] ?? '';
      $form['automator_entity_reference_bundle'] = [
        '#type' => 'select',
        '#title' => t('Bundle'),
        '#options' => $options,
        '#description' => 'Select the bundle to use to create the entity reference.',
        '#weight' => 20,
        '#default_value' => $chosenBundle,
      ];
    }

    // If bundle is chosen or if there are no bundles, show the fields.
    if ($chosenBundle || !$bundles) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($targetType, $chosenBundle);
      $options = [
        '' => t('Select a field'),
      ];
      $form['ai_automator_fields'] = [
        '#type' => 'details',
        '#title' => t('Fields to generate'),
        '#weight' => 20,
        '#open' => TRUE,
      ];
      foreach ($fields as $field => $info) {
        // Only string, string_long, text, text_long and text_with_summary.
        if (in_array($info->getType(), $this->allowedTypes)) {
          $form['ai_automator_fields']['automator_entity_field_enable_' . $field] = [
            '#type' => 'checkbox',
            '#title' => $info->getLabel(),
            '#description' => 'Check this box to enable this field for the generation.',
            '#weight' => 20,
            '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', 'automator_entity_field_enable_' . $field, FALSE),
          ];

          $form['ai_automator_fields']['automator_entity_field_generate_' . $field] = [
            '#type' => 'textarea',
            '#title' => $info->getLabel(),
            '#description' => 'Describe specifically how this field should be filled out.',
            '#weight' => 20,
            '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', 'automator_entity_field_generate_' . $field, ''),
            '#states' => [
              'visible' => [
                ':input[name="automator_entity_field_enable_' . $field . '"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }

      }
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    // If the bundle is set, but no fields, please notify the user.
    $foundField = FALSE;
    $isEnabled = FALSE;
    foreach ($formState->getValues() as $key => $value) {
      if (strpos($key, 'automator_entity_field_enable_') !== FALSE) {
        $foundField = TRUE;
        if ($value) {
          $isEnabled = TRUE;
        }
      }
    }
    if ($formState->getValue('automator_enabled') && !$isEnabled && $foundField) {
      $formState->setErrorByName('ai_automator_fields', t('You need to enable at least one field to generate.'));
    }
    if ($formState->getValue('automator_enabled') && $formState->getValue('automator_entity_reference_bundle') && !$foundField) {
      \Drupal::messenger()->addWarning(t('AI Automator Warning: Because of the structure of the entity reference, you now to go back and edit the prompts for the fields before it works.'));
    }
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

    // Build up the prompt.
    $configs = [];
    foreach ($automatorConfig as $key => $value) {
      if (strpos($key, 'entity_field_enable_') !== FALSE && $value) {
        $field = str_replace('entity_field_enable_', '', $key);
        $promptPart = $automatorConfig['entity_field_generate_' . $field];
        $configs[] = '"' . $field . '": "' . $promptPart . '"';
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation with one to many objects in it depending one what is requested:\n[{\"value\":{";
      $prompt .= implode(', ', $configs);
      $prompt .= '}}]';
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
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    if (!is_array($value)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $target = $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', 'automator_entity_reference_bundle', '');
    $baseFields = $this->getBaseFields($entity->getEntityTypeId());
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $textFormat = $this->getGeneralHelper()->getTextFormat($fieldDefinition);

    $targets = [];
    foreach ($values as $parts) {
      // @var \Drupal\Core\Entity\ContentEntityInterface $newEntity */
      $newEntity = $storage->create([
        $baseFields['owner'] => \Drupal::currentUser()->id(),
        $baseFields['status'] => 1,
        $baseFields['bundle'] => $target,
      ]);
      foreach ($parts as $key => $value) {
        // Check if formatted field type.
        if ($this->isFormattedField($key, $newEntity)) {
          $newEntity->set($key, [
            'value' => $value,
            'format' => $textFormat,
          ]);
        }
        else {
          $newEntity->set($key, $value);
        }
      }
      $newEntity->save();
      $targets[] = $newEntity->id();
    }
    $entity->set($fieldDefinition->getName(), $targets);
  }

  /**
   * Checks if a field type is formatted.
   *
   * @param string $fieldName
   *   The field name.
   * @param ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the field is formatted.
   */
  public function isFormattedField($fieldName, ContentEntityInterface $entity) {
    $fieldDefinition = $entity->getFieldDefinition($fieldName);
    return in_array($fieldDefinition->getType(), ['text', 'text_long', 'text_with_summary']);
  }

  /**
   * Get the base fields for the entity, like label, owner, status etc.
   *
   * @return array
   *   The base fields.
   */
  public function getBaseFields($entityType) {
    $entityTypeDef = \Drupal::entityTypeManager()->getDefinition($entityType);

    // Get the entity keys.
    $entityKeys = $entityTypeDef->getKeys();
    return [
      'owner' => $entityKeys['owner'] ?? FALSE,
      'status' => $entityKeys['status'] ?? FALSE,
      'bundle' => $entityKeys['bundle'] ?? FALSE,
    ];
  }

}
