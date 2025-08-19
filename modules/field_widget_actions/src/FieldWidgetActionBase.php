<?php

namespace Drupal\field_widget_actions;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for FieldWidgetAction plugins.
 */
abstract class FieldWidgetActionBase extends PluginBase implements FieldWidgetActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The target property of the form element.
   */
  const FORM_ELEMENT_PROPERTY = 'value';

  /**
   * The widget plugin instance.
   *
   * @var \Drupal\Core\Field\WidgetInterface|null
   */
  protected ?WidgetInterface $widget = NULL;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  protected ?FieldDefinitionInterface $fieldDefinition = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'button_label' => $this->getLabel(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->getPluginDefinition()['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetTypes(): array {
    return $this->pluginDefinition['widget_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes(): array {
    return $this->pluginDefinition['field_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = [];
    $configuration = $this->getConfiguration();
    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $configuration['enabled'] ?? FALSE,
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $configuration['button_label'] ?? '',
    ];
    $element['plugin_id'] = [
      '#type' => 'value',
      '#value' => $this->getPluginId(),
    ];
    $element['weight'] = [
      '#type' => 'hidden',
      '#default_value' => $configuration['weight'] ?? 0,
      '#attributes' => [
        'class' => ['field-widget-action-element-order-weight'],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * Build the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity this field is attached to or NULL.
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ContentEntityFormInterface) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_state->getFormObject()->buildEntity($form, $form_state);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget(): ?WidgetInterface {
    return $this->widget;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidget(?WidgetInterface $widget): void {
    $this->widget = $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldDefinition(?FieldDefinitionInterface $fieldDefinition): void {
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [];
  }

  /**
   * Gets the button label.
   *
   * @return string
   *   The button label.
   */
  public function getButtonLabel(): string {
    return $this->configuration['button_label'] ?: $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return NULL;
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
      $form['#attributes']['class'][] = 'field-widget-action-element';
    }
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['multiple'])) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    $plugin_definition = $this->getPluginDefinition();
    if (!empty($plugin_definition['multiple'])) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Returns the action button depending on the `multiple` value of definition.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $context
   *   The context.
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    $fieldName = $context['items']->getFieldDefinition()->getName();
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }
    $weight = $this->configuration['weight'] ?? 0;
    $form[$widgetId] = [
      '#type' => 'button',
      '#value' => $this->getButtonLabel(),
      '#weight' => $weight + 10,
      '#name' => $widgetId,
      '#attributes' => [
        'class' => [
          'button--primary',
          'primary',
          'btn-primary',
          'field-widget-action-widget-button',
          'field-widget-action-' . $this->getPluginId(),
        ],
        'data-wrapper-id' => 'field-widget-action-' . $fieldName,
        'data-widget-id' => $this->getPluginId(),
        'data-widget-field' => $fieldName,
        'data-widget-settings' => json_encode($this->getConfiguration()),
      ],
      '#field_widget_action_settings' => $this->getConfiguration(),
    ];
    if ($this->getAjaxCallback()) {
      $form[$widgetId]['#ajax'] = [
        'callback' => [$this, $this->getAjaxCallback()],
        'wrapper' => 'field-widget-action-' . $fieldName,
        'prevent' => 'submit',
        'suppress_required_fields_validation' => TRUE,
      ];
    }

    // Add needed libraries.
    if (empty($form[$widgetId]['#attached']['library'])) {
      $form[$widgetId]['#attached']['library'] = [];
    }
    $form[$widgetId]['#attached']['library'] = array_merge($form[$widgetId]['#attached']['library'], $this->getLibraries());
  }

}
