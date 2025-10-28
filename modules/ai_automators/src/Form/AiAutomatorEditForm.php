<?php

namespace Drupal\ai_automators\Form;

use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for editing AI automators.
 */
class AiAutomatorEditForm extends AiAutomatorFormBase {

  /**
   * The automator type manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager
   */
  protected AiAutomatorTypeManager $automatorTypeManager;

  /**
   * The field process manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager
   */
  protected AiAutomatorFieldProcessManager $processManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory'),
      $container->get('ai_automator.field_rules')
    );
    $instance->automatorTypeManager = $container->get('plugin.manager.ai_automator');
    $instance->processManager = $container->get('plugin.manager.ai_processor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity = NULL, $fieldInfo = NULL): array {
    $user_input = $form_state->getUserInput();
    $form['#title'] = $this->t('Edit automator %name', ['%name' => $this->entity->label()]);
    $form['#tree'] = TRUE;

    // Get the parent form (entity type, bundle, field selection).
    $form = parent::buildForm($form, $form_state, $entity, $fieldInfo);

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processManager->getDefinitions() as $definition) {
      // Check so the processor is allowed.
      $instance = $this->processManager->createInstance($definition['id']);
      // @todo refactor to not need entity.
      $dummyEntity = $this->entity->getDummyEntity();
      $fieldDefinition = $this->entity->getFieldDefinition();
      if ($instance->processorIsAllowed($dummyEntity, $fieldDefinition)) {
        $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
      }
    }

    $form['worker_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Automator Worker'),
      '#options' => $workerOptions,
      '#description' => $this->t('This defines how the saving of an interpolation happens. Direct saving is the easiest, but since it can take time you need to have longer timeouts.'),
      '#default_value' => $this->entity->get('worker_type') ?? 'direct',
    ];

    // Attach admin library for proper table layout and DnD styles.
    $form['#attached']['library'][] = 'ai_automators/admin';
    // Ensure tabledrag behaviors are definitely present.
    $form['#attached']['library'][] = 'core/drupal.tabledrag';

    // Only show automator types table if we have a field selected.
    if ($this->entity->get('entity_type') && $this->entity->get('bundle') && $this->entity->get('field_name')) {
      $this->buildAutomatorTypesTable($form, $form_state, $user_input);
    }

    return $form;
  }

  /**
   * Build the automator types table.
   */
  protected function buildAutomatorTypesTable(array &$form, FormStateInterface $form_state, array $user_input): void {
    // Build the list of existing automator types for this automator.
    $form['automator_types'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Automator Type'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'automator-type-order-weight',
        ],
      ],
      '#attributes' => [
        'id' => 'automator-types',
      ],
      '#empty' => $this->t('There are currently no automator types in this automator. Add one by selecting an option below.'),
      '#weight' => 5,
    ];

    // Iterate plugin instances like core image style form does.
    foreach ($this->entity->getAutomatorTypes() as $automator_type) {
      // Plugins must provide these methods (see AiAutomatorTypeInterface).
      $uuid = method_exists($automator_type, 'getUuid') ? $automator_type->getUuid() : NULL;
      if (!$uuid) {
        // Skip invalid plugin entries.
        continue;
      }
      $label = $automator_type->label();
      $current_weight = method_exists($automator_type, 'getWeight') ? $automator_type->getWeight() : 0;

      $form['automator_types'][$uuid]['#attributes']['class'][] = 'draggable';
      $form['automator_types'][$uuid]['#weight'] = isset($user_input['automator_types']) ? ($user_input['automator_types'][$uuid]['weight'] ?? NULL) : NULL;

      $form['automator_types'][$uuid]['automator_type'] = [
        '#tree' => FALSE,
        'data' => [
          'label' => [
            '#plain_text' => $label,
          ],
        ],
      ];

      // Add optional summary if plugin supports it.
      if (method_exists($automator_type, 'getSummary')) {
        $summary = $automator_type->getSummary();
        if (!empty($summary)) {
          $summary['#prefix'] = ' ';
          $form['automator_types'][$uuid]['automator_type']['data']['summary'] = $summary;
        }
      }

      $form['automator_types'][$uuid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $current_weight,
        '#attributes' => [
          'class' => ['automator-type-order-weight'],
        ],
      ];

      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('ai_automators.ai_automator_type_form', [
          'ai_automator' => $this->entity->id(),
          'ai_automator_type' => $uuid,
        ]),
      ];
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('ai_automators.ai_automator_type_delete', [
          'ai_automator' => $this->entity->id(),
          'ai_automator_type' => $uuid,
        ]),
      ];
      $form['automator_types'][$uuid]['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    // Build the new automator type addition form.
    $this->buildNewAutomatorTypeForm($form, $user_input);
  }

  /**
   * Build the form for adding new automator types.
   */
  protected function buildNewAutomatorTypeForm(array &$form, array $user_input): void {
    // Get available automator types for the current field.
    $available_types = $this->getAvailableAutomatorTypes();

    $form['automator_types']['new'] = [
      '#tree' => FALSE,
      '#weight' => $user_input['weight'] ?? NULL,
      '#attributes' => ['class' => ['draggable']],
    ];

    $form['automator_types']['new']['automator_type'] = [
      'data' => [
        'new' => [
          '#type' => 'select',
          '#title' => $this->t('Automator Type'),
          '#title_display' => 'invisible',
          '#options' => $available_types,
          '#empty_option' => $this->t('Select a new automator type'),
        ],
        [
          'add' => [
            '#type' => 'submit',
            '#value' => $this->t('Add'),
            '#validate' => ['::automatorTypeValidate'],
            '#submit' => ['::submitForm', '::automatorTypeSave'],
          ],
        ],
      ],
      '#prefix' => '<div class="automator-type-new">',
      '#suffix' => '</div>',
    ];

    $form['automator_types']['new']['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for new automator type'),
      '#title_display' => 'invisible',
      '#default_value' => count(iterator_to_array($this->entity->getAutomatorTypes())) + 1,
      '#attributes' => ['class' => ['automator-type-order-weight']],
    ];

    $form['automator_types']['new']['operations'] = [
      'data' => [],
    ];
  }

  /**
   * Get available automator types for the current field.
   */
  protected function getAvailableAutomatorTypes(): array {
    $options = [];

    // Get the field definition to check for applicable rules.
    $entity_type_id = $this->entity->get('entity_type');
    $bundle_id = $this->entity->get('bundle');
    $field_name = $this->entity->get('field_name');

    if (!$entity_type_id || !$bundle_id || !$field_name) {
      return $options;
    }

    try {
      // Get the bundle entity (like content type) - same as base form.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundle_entity = NULL;

      if ($entity_type->hasKey('bundle')) {
        $bundle_entity_type = $entity_type->getBundleEntityType();
        if ($bundle_entity_type) {
          $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($bundle_id);
        }
      }

      // Skip if we can't find the bundle entity.
      if (!$bundle_entity) {
        return $options;
      }

      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
      $field_definition = $field_definitions[$field_name] ?? NULL;

      if ($field_definition) {
        $dummyEntity = $this->entity->getDummyEntity();
        $rules = $this->fieldRules->findRuleCandidates($dummyEntity, $field_definition);
        foreach ($rules as $rule_id => $rule) {
          $options[$rule_id] = $rule->title;
        }

        // Sort options alphabetically by title.
        asort($options);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automators')->error('Error loading automator types: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $options;
  }

  /**
   * Validate handler for automator type.
   */
  public function automatorTypeValidate(array $form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('new')) {
      $form_state->setErrorByName('new', $this->t('Select an automator type to add.'));
    }
  }

  /**
   * Submit handler for automator type.
   */
  public function automatorTypeSave(array $form, FormStateInterface $form_state): void {
    $this->save($form, $form_state);

    // Load the configuration form.
    $form_state->setRedirect(
      'ai_automators.ai_automator_type_form',
      [
        'ai_automator' => $this->entity->id(),
        'ai_automator_type' => $form_state->getValue('new'),
      ],
      ['query' => ['weight' => $form_state->getValue('weight')]]
    );
    $form_state->setIgnoreDestination();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Update automator type weights.
    if (!$form_state->isValueEmpty('automator_types')) {
      $this->updateAutomatorTypeWeights($form_state->getValue('automator_types'));
    }

    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Changes to the automator have been saved.'));
    // Stay on the edit form after saving.
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

  /**
   * Updates automator type weights.
   */
  protected function updateAutomatorTypeWeights(array $automator_types): void {
    // Mirror core ImageStyle behavior: update weights via plugin instances.
    $collection = $this->entity->getAutomatorTypes();

    foreach ($automator_types as $uuid => $automator_type_data) {
      if ($collection->has($uuid) && isset($automator_type_data['weight'])) {
        $collection->get($uuid)->setWeight($automator_type_data['weight']);
      }
    }
  }

}
