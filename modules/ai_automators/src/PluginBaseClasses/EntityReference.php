<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs entity reference rule.
 */
abstract class EntityReference extends RuleBase {

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param array<mixed> $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeBundleInfo $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $pluginManager, $formHelper, $promptJsonDecoder);
  }

  /**
   * Load from dependency injection container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array<string,mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Allowed field types initially.
   *
   * @var array<string>
   */
  public array $allowedTypes = [
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
  public function helpText(): string {
    return "This can take a field on the parent node and seed entity reference nodes.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText(): string {
    return "Based on the context text I want a title and a description.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition): bool {
    $storage = $fieldDefinition->getFieldStorageDefinition();
    if (isset($storage->getSettings()['target_type'])) {
      if ($storage->getSettings()['target_type'] !== 'media') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, AiAutomatorInterface $automator): array {
    $entityType = $automator->get('entity_type');
    // Check if the target type has bundles.
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityType);
    $chosenBundle = NULL;
    if ($bundles) {
      $options = [
        '' => $this->t('Select a bundle'),
      ];
      foreach ($bundles as $bundle => $info) {
        $options[$bundle] = $info['label'];
      }
      $chosenBundle = $this->configuration['automator_entity_reference_bundle'] ?? '';
      $form['automator_entity_reference_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#options' => $options,
        '#description' => $this->t('Select the bundle to use to create the entity reference.'),
        '#weight' => 20,
        '#default_value' => $chosenBundle,
      ];
    }

    // If bundle is chosen or if there are no bundles, show the fields.
    if ($chosenBundle || !$bundles) {
      $form['ai_automator_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Fields to generate'),
        '#weight' => 20,
        '#open' => TRUE,
      ];
      foreach ($this->entityFieldManager->getFieldDefinitions($entityType, $chosenBundle) as $field => $info) {
        // Only string, string_long, text, text_long and text_with_summary.
        if (in_array($info->getType(), $this->allowedTypes)) {
          $form['ai_automator_fields']['automator_entity_field_enable_' . $field] = [
            '#type' => 'checkbox',
            '#title' => $info->getLabel(),
            '#description' => $this->t('Check this box to enable this field for the generation.'),
            '#weight' => 20,
            '#default_value' => $this->configuration['automator_entity_field_enable_' . $field] ?? FALSE,
          ];

          $form['ai_automator_fields']['automator_entity_field_generate_' . $field] = [
            '#type' => 'textarea',
            '#title' => $info->getLabel(),
            '#description' => $this->t('Describe specifically how this field should be filled out.'),
            '#weight' => 20,
            '#default_value' => $this->configuration['automator_entity_field_generate_' . $field] ?? '',
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state, AiAutomatorInterface $automator): void {
    // If the bundle is set, but no fields, please notify the user.
    $foundField = FALSE;
    $isEnabled = FALSE;
    foreach ($form_state->getValues() as $key => $value) {
      if (str_contains($key, 'automator_entity_field_enable_')) {
        $foundField = TRUE;
        if ($value) {
          $isEnabled = TRUE;
        }
      }
    }
    if ($form_state->getValue('automator_enabled') && !$isEnabled && $foundField) {
      $form_state->setErrorByName('ai_automator_fields', $this->t('You need to enable at least one field to generate.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): array {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Build up the prompt.
    $configs = [];
    foreach ($automatorConfig as $key => $value) {
      if (str_contains($key, 'entity_field_enable_') && $value) {
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
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool {
    if (!is_array($value)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): void {
    $target = $automatorConfig['entity_reference_bundle'] ?? '';
    $baseFields = $this->getBaseFields($entity->getEntityTypeId());
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $textFormat = $this->getGeneralHelper()->calculateTextFormat($fieldDefinition);

    $targets = [];
    foreach ($values as $parts) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $newEntity */
      $newEntity = $storage->create([
        $baseFields['owner'] => $this->currentUser->id(),
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the field is formatted.
   */
  public function isFormattedField(string $fieldName, ContentEntityInterface $entity): bool {
    $fieldDefinition = $entity->getFieldDefinition($fieldName);
    if ($fieldDefinition === NULL) {
      return FALSE;
    }
    return in_array($fieldDefinition->getType(), [
      'text',
      'text_long',
      'text_with_summary',
    ], TRUE);
  }

  /**
   * Get the base fields for the entity, like label, owner, status etc.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return array<string, mixed>
   *   The base fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBaseFields($entityType): array {
    $entityTypeDef = $this->entityTypeManager->getDefinition($entityType);

    // Get the entity keys.
    $entityKeys = $entityTypeDef->getKeys();
    return [
      'owner' => $entityKeys['owner'] ?? FALSE,
      'status' => $entityKeys['status'] ?? FALSE,
      'bundle' => $entityKeys['bundle'] ?? FALSE,
    ];
  }

}
