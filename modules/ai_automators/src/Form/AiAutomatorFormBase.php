<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Form;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_automators\AiFieldRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Automator form.
 */
abstract class AiAutomatorFormBase extends EntityForm {

  /**
   * The automator entity.
   *
   * @var \Drupal\ai_automators\Entity\AiAutomator
   */
  protected $entity;

  /**
   * Constructs a new AiAutomatorForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\ai_automators\AiFieldRules $fieldRules
   *   The field rules service.
   */
  public function __construct(
    protected $entityTypeManager,
    protected $entityFieldManager,
    protected $loggerFactory,
    protected AiFieldRules $fieldRules,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory'),
      $container->get('ai_automator.field_rules')
    );
  }

  /**
   * The form builder.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed $entity
   *   The entity being edited.
   * @param mixed $fieldInfo
   *   Additional field info.
   *
   * @return array<string,mixed>
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity = NULL, $fieldInfo = NULL): array {
    $form = parent::buildForm($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AiAutomator::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    // Read prefill from query for add form convenience.
    $request = $this->getRequest();
    $prefill_entity_type = $request->query->get('entity_type');
    $prefill_bundle = $request->query->get('bundle');
    $prefill_field = $request->query->get('field_name');

    // Entity type selector with fieldable entities only.
    $entity_type_options = $this->getFieldableEntityTypes();
    $form['selected_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $entity_type_options,
      '#default_value' => $this->entity->get('entity_type') ?? $prefill_entity_type ?? '',
      '#disabled' => !!$this->entity->get('entity_type'),
      '#empty_option' => $this->t('- Select entity type -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateBundleOptions',
        'wrapper' => 'bundle-wrapper',
        'event' => 'change',
      ],
    ];

    // Bundle selector (populated via AJAX).
    $form['bundle_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'bundle-wrapper'],
    ];

    $selected_entity_type = $form_state->getValue('selected_type') ?? $this->entity->get('entity_type') ?? $prefill_entity_type;
    $bundle_options = [];

    if ($selected_entity_type) {
      $bundle_options = $this->getEntityBundles($selected_entity_type);
      // Add empty option at the beginning.
      $bundle_options = ['' => $this->t('- Select bundle -')] + $bundle_options;
    }

    $form['bundle_wrapper']['selected_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#options' => $bundle_options,
      '#default_value' => $this->entity->get('bundle') ?? $prefill_bundle ?? '',
      '#required' => TRUE,
      '#disabled' => empty($bundle_options) || !!$this->entity->get('bundle'),
      '#empty_option' => $this->t('- Select bundle -'),
      '#ajax' => [
        'callback' => '::updateFieldOptions',
        'wrapper' => 'field-name-wrapper',
        'event' => 'change',
      ],
    ];

    // Field selector (populated via AJAX).
    $form['field_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'field-name-wrapper'],
    ];

    $selected_bundle = $form_state->getValue('selected_bundle') ?? $this->entity->get('bundle') ?? $prefill_bundle;
    $field_options = [];

    if ($selected_entity_type && $selected_bundle) {
      $field_options = $this->getEntityFields($selected_entity_type, $selected_bundle);
    }

    $form['field_name_wrapper']['selected_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#options' => $field_options,
      '#default_value' => $this->entity->get('field_name') ?? $prefill_field ?? '',
      '#empty_option' => $this->t('- Select field -'),
      '#required' => TRUE,
      '#disabled' => empty($field_options) || !!$this->entity->get('field_name'),
    ];

    return $form;
  }

  /**
   * AJAX callback to update bundle options based on selected entity type.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function updateBundleOptions(array &$form, FormStateInterface $form_state): AjaxResponse {
    $selected_entity_type = $form_state->getValue('selected_type');
    $bundle_options = [];

    if ($selected_entity_type) {
      $bundle_options = $this->getEntityBundles($selected_entity_type);
      // Add empty option at the beginning.
      $bundle_options = ['' => $this->t('- Select bundle -')] + $bundle_options;
    }

    // Rebuild the bundle select element with new options.
    $form['bundle_wrapper']['selected_bundle']['#options'] = $bundle_options;
    $form['bundle_wrapper']['selected_bundle']['#disabled'] = empty($bundle_options) || count($bundle_options) === 1;
    $form['bundle_wrapper']['selected_bundle']['#value'] = '';

    // Clear the field options when entity type changes.
    $form['field_name_wrapper']['selected_field']['#options'] = [];
    $form['field_name_wrapper']['selected_field']['#disabled'] = TRUE;
    $form['field_name_wrapper']['selected_field']['#value'] = '';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#bundle-wrapper', $form['bundle_wrapper']));
    $response->addCommand(new ReplaceCommand('#field-name-wrapper', $form['field_name_wrapper']));
    return $response;
  }

  /**
   * AJAX callback to update field options based on selected bundle.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function updateFieldOptions(array &$form, FormStateInterface $form_state): AjaxResponse {
    $selected_entity_type = $form_state->getValue('selected_type');
    $selected_bundle = $form_state->getValue('selected_bundle');
    $field_options = [];

    if ($selected_entity_type && $selected_bundle) {
      $field_options = $this->getEntityFields($selected_entity_type, $selected_bundle);
    }

    // Rebuild the field select element with new options.
    $form['field_name_wrapper']['selected_field']['#options'] = $field_options;
    $form['field_name_wrapper']['selected_field']['#disabled'] = empty($field_options);
    $form['field_name_wrapper']['selected_field']['#value'] = '';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#field-name-wrapper', $form['field_name_wrapper']));
    return $response;
  }

  /**
   * Get all fieldable entity types.
   *
   * @return array<string, mixed>
   *   The fieldable entity types as options.
   */
  protected function getFieldableEntityTypes(): array {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $options = [];

    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Only include fieldable content entity types.
      if (
        $entity_type->entityClassImplements('\Drupal\Core\Entity\FieldableEntityInterface') &&
        $entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')
      ) {
        $options[$entity_type_id] = $entity_type->getLabel();
      }
    }

    // Sort alphabetically by label.
    asort($options);
    return $options;
  }

  /**
   * Get all bundles for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array<mixed>
   *   The bundles as options.
   */
  protected function getEntityBundles(string $entity_type_id): array {
    $bundles = [];

    try {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Check if the entity type has bundles.
      if ($entity_type->hasKey('bundle')) {
        $bundle_entity_type = $entity_type->getBundleEntityType();
        if ($bundle_entity_type) {
          $bundle_entities = $this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple();
          foreach ($bundle_entities as $bundle_entity) {
            $bundles[$bundle_entity->id()] = $bundle_entity->label();
          }
        }
      }
      else {
        // Entity type has no bundles, use the entity type itself.
        $bundles[$entity_type_id] = $entity_type->getLabel();
      }

      // Sort alphabetically by label.
      asort($bundles);
    }
    catch (\Exception $e) {
      // Log error and return empty array.
      $this->loggerFactory->get('ai_automators')->error('Error loading bundles for entity type @type: @message', [
        '@type' => $entity_type_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return $bundles;
  }

  /**
   * Get all fields for a given entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The bundle ID.
   *
   * @return array<string, string>
   *   The fields as options.
   */
  protected function getEntityFields(string $entity_type_id, string $bundle_id): array {
    $fields = [];

    try {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Get field definitions for the specific bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);

      foreach ($field_definitions as $field_name => $field_definition) {
        // Only include configurable fields (not base fields like entity ID).
        if (!$field_definition instanceof FieldConfig &&
            !$field_definition instanceof BaseFieldOverride) {
          continue;
        }

        // Skip certain system fields that are typically not user-editable.
        $system_fields = [
          'uuid',
          'langcode',
          'default_langcode',
          'revision_default',
          'revision_translation_affected',
          'ai_automator_status',
          'promote',
          'sticky',
        ];
        if (in_array($field_name, $system_fields)) {
          continue;
        }

        // Build a dummy entity for rule checks.
        /** @var \Drupal\Core\Entity\ContentEntityInterface $dummyEntity */
        $dummyEntity = $this->entityTypeManager->getStorage($entity_type_id)->create([
          $entity_type->getKey('bundle') => $bundle_id,
        ]);
        $rules = $this->fieldRules->findRuleCandidates($dummyEntity, $field_definition);
        if (empty($rules)) {
          continue;
        }

        $fields[$field_name] = $field_definition->getLabel() . ' (' . $field_name . ')';
      }

      // Sort alphabetically by label.
      asort($fields);
    }
    catch (\Exception $e) {
      // Log error and return empty array.
      $this->loggerFactory->get('ai_automators')->error('Error loading fields for entity type @type and bundle @bundle: @message', [
        '@type' => $entity_type_id,
        '@bundle' => $bundle_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return $fields;
  }

  /**
   * Submit handler for the AI Automator form.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   No return value.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo entity_type and bundle are reserved and should be changed.
    if ($form_state->getValue('selected_type')) {
      $form_state->setValue('entity_type', $form_state->getValue('selected_type'));
    }
    if ($form_state->getValue('selected_bundle')) {
      $form_state->setValue('bundle', $form_state->getValue('selected_bundle'));
    }
    if ($form_state->getValue('selected_field')) {
      $form_state->setValue('field_name', $form_state->getValue('selected_field'));
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Save handler for the AI Automator form.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int
   *   The save result.
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    return $result;
  }

}
