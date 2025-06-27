<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_automators\AiAutomatorEntityModifier;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The AltText action.
 */
#[FieldWidgetAction(
  id: 'automator_alt_text',
  label: new TranslatableMarkup('Automator Alt Text'),
  widget_types: ['image_image'],
  field_types: ['image'],
)]
class AltText extends FieldWidgetActionBase {

  /**
   * The AI provider plugins manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The automators plugin manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager
   */
  protected AiAutomatorTypeManager $automatorTypeManager;

  /**
   * The entity modifier.
   *
   * @var \Drupal\ai_automators\AiAutomatorEntityModifier
   */
  protected AiAutomatorEntityModifier $entityModifier;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'settings' => [
        'automator_id' => '',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aiProvider = $container->get('ai.provider');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->automatorTypeManager = $container->get('plugin.manager.ai_automator');
    $instance->entityModifier = $container->get('ai_automator.entity_modifier');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $settings = $this->getConfiguration();
    if (!empty($settings['settings'])) {
      $settings = $settings['settings'];
    }
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    $element['enabled']['#title'] = $this->t('Enable Automators');
    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Enable an Automator'),
    ];
    $field_definition = $this->getFieldDefinition();
    if (!empty($field_definition)) {
      $element['settings']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][third_party_settings][field_widget_actions][' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
        ],
      ];
    }

    // Get the entity type and bundle from the field definition.
    $entity_type = $this->getFieldDefinition()->getTargetEntityTypeId();
    $bundle = $this->getFieldDefinition()->getTargetBundle();
    $field_name = $this->getFieldDefinition()->getName();

    $options = $this->getAutomatorsOptions($entity_type, $bundle, $field_name);

    $element['settings']['automator_id'] = [
      '#title' => $this->t('Automator to use for suggestions'),
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Pick an automator -'),
      '#default_value' => $settings['automator_id'] ?? '',
    ];

    $element['settings']['button'] = [
      '#title' => $this->t('Button label'),
      '#description' => $this->t('Button will appear near the form element. Default label is "AI Suggestions"'),
      '#type' => 'textfield',
      '#default_value' => $settings['button'] ?? '',
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return ['ai_content_suggestions/field_widget'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'aiAutomatorsAjax';
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    if ($this->getAjaxCallback()) {
      // Add wrapper.
      $prefix = $form['#prefix'] ?? '';
      $suffix = $form['#suffix'] ?? '';
      $form['#prefix'] = '<div id="field-widget-action-' . $context['items']->getFieldDefinition()->getName() . '" class="field-widget-action-element-wrapper">' . $prefix;
      $form['#suffix'] = $suffix . '</div>';
    }
    // We only do single for now.
    $form['#attributes']['class'][] = 'ai-content-suggestions--enabled';
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    $this->actionButton($form, $form_state, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function getButtonLabel(): string {
    if (!empty($this->configuration['settings']['button'])) {
      return $this->configuration['settings']['button'];
    }
    return parent::getButtonLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // If the field definition is not set, its not the setup form.
    if (!$this->getFieldDefinition()) {
      return TRUE;
    }
    $entity_type = $this->getFieldDefinition()->getTargetEntityTypeId();
    $bundle = $this->getFieldDefinition()->getTargetBundle();
    $field_name = $this->getFieldDefinition()->getName();
    // Only show if an Automator is configured for the field widget.
    return count($this->getAutomatorsOptions($entity_type, $bundle, $field_name)) > 0;
  }

  /**
   * Helper function to check if automators are enabled for the field widget.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   An array of automators that are enabled for the field widget.
   */
  public function getAutomatorsOptions(string $entity_type, string $bundle, string $field_name): array {
    // Get all automator rules.
    $automators = $this->automatorTypeManager->getDefinitions();
    $automator_rules = [];
    foreach ($automators as $id => $definition) {
      if (in_array($definition['field_rule'], [
        'text',
        'string',
        'image',
      ])) {
        $automator_rules[] = $id;
      }
    }
    $options = [];
    // Load all automator configurations.
    $automator_configurations = $this->entityTypeManager->getStorage('ai_automator')->loadMultiple();
    foreach ($automator_configurations as $automator) {
      // Check so the entity type, bundle and rule match.
      $configured_entity_type = $automator->get('entity_type');
      $configured_bundle = $automator->get('bundle');
      $configured_rule = $automator->get('rule');
      $configured_field_name = $automator->get('field_name');
      if (
        in_array($configured_rule, $automator_rules) &&
        $configured_entity_type === $entity_type &&
        $configured_field_name === $field_name &&
        ($configured_bundle === $bundle || empty($configured_bundle))
      ) {
        $options[$automator->id()] = $automator->label();
      }
    }
    return $options;
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    // @todo Best practice.
    $form_key = $array_parents[0];
    $key = $array_parents[2] ?? 0;
    // Get the content entity from form object.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);
    // Delete all values from the field, so you can recreate.
    $entity->{$form_key} = [];
    // Run the automator for the entity.
    $entity = $this->entityModifier->saveEntity($entity);
    // Ensure the widget has enough elements for all values.
    $form[$form_key]['widget']['#items_count'] = count($entity->{$form_key});

    if (isset($entity->{$form_key}[0])) {
      $item = $entity->{$form_key}[0];
      if ($item->value) {
        $form[$form_key]['widget'][$key]['value']['#value'] = $item->value;
      }
    }

    return $form[$form_key];
  }

}
