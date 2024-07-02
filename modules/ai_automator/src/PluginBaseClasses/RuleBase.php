<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class for all rule helpers.
 */
abstract class RuleBase implements AiAutomatorFieldRuleInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The LLM type.
   *
   * @var string
   */
  protected string $llmType = 'chat';

  /**
   * The plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiPluginManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected AiProviderFormHelper $formHelper;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   */
  final public function __construct(
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper
  ) {
    $this->aiPluginManager = $pluginManager;
    $this->formHelper = $formHelper;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('ai.form_helper')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return 'Enter a prompt here.';
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'text_long',
      'text',
      'string',
      'string_long',
      'text_with_summary',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    return [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    // Load the AI models.
    $providers = $this->formHelper->getAiProvidersOptions($this->llmType);
    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType);
    $provider = $formState->getValue('automator_ai_provider');
    if (!$provider && !empty($defaults['provider_id'])) {
      $provider = $defaultValues['automator_ai_provider'] ?? $defaults['provider_id'];
    }
    $form['automator_ai_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('LLM Provider'),
      '#options' => $providers,
      '#default_value' => $provider,
      '#ajax' => [
        'callback' => '\Drupal\ai_automator\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
        'wrapper' => 'provider_ajax_wrapper',
      ],
    ];
    $form['ajax_prefix'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => 'provider_ajax_wrapper',
      ],
      '#states' => [
        'visible' => [
          ':input[name="automator_ai_provider"]' => ['!value' => ''],
        ],
      ],
    ];

    if ($provider) {
      $llmInstance = $this->aiPluginManager->createInstance($provider);
      $model = $formState->getValue('automator_ai_model');
      if (!$model && !empty($defaults['model_id'])) {
        $model = $defaultValues['automator_ai_model'] ?? $defaults['model_id'];
      }

      $form['ajax_prefix']['automator_ai_model'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $llmInstance->getConfiguredModels($this->llmType),
        '#default_value' => $model,
        '#ajax' => [
          'callback' => '\Drupal\ai_automator\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
          'wrapper' => 'provider_ajax_wrapper',
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($this->llmType, $model);

        if (count($configuration)) {
          $form['ajax_prefix']['ai_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ];
          foreach ($configuration as $key => $definition) {
            $set_key = 'automator_configuration_' . $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['ajax_prefix']['ai_settings'][$set_key]['#required'] = $definition['required'] ?? FALSE;
            $form['ajax_prefix']['ai_settings'][$set_key]['#title'] = $definition['label'] ?? $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#description'] = $definition['description'] ?? '';
            $form['ajax_prefix']['ai_settings'][$set_key]['#default_value'] = $defaultValues[$set_key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $form['ajax_prefix']['ai_settings'][$set_key]['#options'] = array_combine($value, $value);
                  continue;
                }
                $form['ajax_prefix']['ai_settings'][$set_key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $values = $entity->get($automatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * Gets the general helper.
   *
   * @return \Drupal\ai_automator\Rulehelpers\GeneralHelper
   *   The general helper.
   */
  public function getGeneralHelper() {
    return \Drupal::service('ai_automator.rule_helper.general');
  }

  /**
   * Ajax callback to load the models for the selected provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['automator_container']['automator_advanced']['ajax_prefix'];
  }

}
