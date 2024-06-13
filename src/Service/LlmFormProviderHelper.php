<?php

namespace Drupal\ai\Service;

use Drupal\ai\Enum\Bundles;
use Drupal\ai\LlmProviderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\LlmProviderPluginManager;
use Drupal\ai\Utility\CastUtility;

/**
 * Helper class for modules that implements LLM Providers.
 */
class LlmFormProviderHelper {

  use StringTranslationTrait;

  /**
   * Flag for getting no configurations.
   */
  const FORM_CONFIGURATION_NONE = 0;

  /**
   * Flag for getting the required configurations.
   */
  const FORM_CONFIGURATION_REQUIRED = 1;

  /**
   * Flag for getting the full configurations.
   */
  const FORM_CONFIGURATION_FULL = 2;

  /**
   * The LLM Providers plugin manager.
   *
   * @var \Drupal\ai\LlmProviderPluginManager
   */
  protected $llmProviderPluginManager;

  /**
   * Constructs a new LlmProviderHelper object.
   *
   * @param \Drupal\ai\LlmProviderPluginManager $llmProviderPluginManager
   *   The LLM Providers plugin manager.
   */
  public function __construct(LlmProviderPluginManager $llmProviderPluginManager) {
    $this->llmProviderPluginManager = $llmProviderPluginManager;
  }

  /**
   * Helper function to generate a full list of available LLM providers.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the models for.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   * @param int $config_level
   *   What level of configuration you want to show
   * @param string $provider_id
   *   If you already have the provider id and only want to show the models.
   */
  public function generateLlmProvidersForm(array &$form, FormStateInterface $form_state, Bundles $bundle, string $prefix = '', int $config_level = LlmFormProviderHelper::FORM_CONFIGURATION_NONE, string $provider_id = '') {
    $providers = $this->getLlmProvidersOptions();
    // Make sure the prefix is properly formatted.
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $form_state->set('llm_prefix', $prefix);

    // Don't load the provider selection if a provider is already selected.
    $provider = $provider_id;
    if (!$provider_id) {
      $provider = $form_state->getValue($prefix . 'llm_provider');
      $form[$prefix . 'llm_provider'] = [
        '#type' => 'select',
        '#title' => $this->t('LLM Provider'),
        '#options' => $providers,
        '#default_value' => $provider,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '\Drupal\ai\Service\LlmFormProviderHelper::loadModelsAjaxCallback',
          'wrapper' => $prefix . 'ajax_wrapper',
        ],
      ];
    }

    $form[$prefix . 'ajax_prefix'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => $prefix . 'ajax_wrapper',
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $prefix . 'llm_provider"]' => ['!value' => ''],
        ],
      ],
    ];

    if ($provider) {
      $llmInstance = $this->llmProviderPluginManager->createInstance($provider);
      $model = $form_state->getValue($prefix . 'ai_model');
      $form[$prefix . 'ajax_prefix'][$prefix . 'ai_model'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $llmInstance->getConfiguredLlms($bundle),
        '#default_value' => $model,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '\Drupal\ai\Service\LlmFormProviderHelper::loadModelsAjaxCallback',
          'wrapper' => $prefix . 'ajax_wrapper',
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($bundle, $model);
        $this->generateFormElements($prefix . 'ajax_prefix', $form, $config_level, $configuration);
      }
    }
  }

  /**
   * Generate a configured LLM Provider from the form values.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the models for.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   *
   *
   * @return \Drupal\ai\Provider\LlmProviderInterface
   *   The provider instance.
   */
  public function generateLlmProviderFromFormSubmit(array &$form, FormStateInterface $form_state, Bundles $bundle, string $prefix): LlmProviderInterface {
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $provider = $form_state->getValue($prefix . 'llm_provider');
    $configuration = $this->generateLlmProvidersConfigurationFromForm($form, $form_state, $bundle, $prefix);
    $provider = $this->llmProviderPluginManager->createInstance($provider);
    $provider->setConfiguration($configuration);
    return $provider;
  }

  /**
   * Generate a LLM configuration from the form values.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to get the models for.
   * @param string $model
   *   The model to get the configuration for.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   *
   * @return array
   *   The configuration array.
   */
  public function generateLlmProvidersConfigurationFromForm(array &$form, FormStateInterface $form_state, Bundles $bundle, string $prefix): array {
    // Make sure the prefix is properly formatted.
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $provider = $form_state->getValue($prefix . 'llm_provider');
    $model = $form_state->getValue($prefix . 'ai_model');
    $llmInstance = $this->llmProviderPluginManager->createInstance($provider);
    $schema = $llmInstance->getAvailableConfiguration($bundle, $model);
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    // Hopefully safe namespace.
    $prefix .= 'ajax_prefix_configuration_';
    $configuration = [];
    // We set and cast each value.
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, $prefix) === 0) {
        $real_key = trim(str_replace($prefix, '', $key));
        $type = $schema[$real_key]['type'] ?? 'string';
        $configuration[$real_key] = CastUtility::typeCast($type, trim($value));
      }
    }
    return $configuration;
  }

  /**
   * Ajax callback to load the models for the selected provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface$form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $prefix = $form_state->get('llm_prefix');
    return $form[$prefix . 'ajax_prefix'];
  }

  /**
   * Helper function to generate a full options list of available LLM providers.
   *
   * @return array
   *   The list of available LLM providers.
   */
  private function getLlmProvidersOptions() {
    $providers = $this->llmProviderPluginManager->getDefinitions();
    $options = [
      '' => $this->t('Select a provider'),
    ];
    foreach ($providers as $id => $provider) {
      // Check so its setup.
      $providerInstance = $this->llmProviderPluginManager->createInstance($id);
      if ($providerInstance->isUsable(Bundles::Chat)) {
        $options[$id] = $provider['label'];
      }

    }
    return $options;
  }


  /**
   * Helper function to generate form elements from schema.
   *
   * @param string $prefix
   *   Prefix for the form elements.
   * @param array $form
   *   The form.
   * @param int $config_level
   *   The config level to return.
   * @param array $schema
   *   Configuration schema of the provider.
   */
  private function generateFormElements(string $prefix, array &$form, int $config_level, array $schema): void {
    // If there isn't a configuration or shouldn't be, return.
    if (!isset($schema) || $config_level == LlmFormProviderHelper::FORM_CONFIGURATION_NONE) {
      return;
    }
    foreach ($schema as $key => $definition) {
      // We skip it if it's not required and we only want required.
      if ($config_level == LlmFormProviderHelper::FORM_CONFIGURATION_REQUIRED && empty($definition['required'])) {
        continue;
      }
      $set_key = $prefix .'_configuration_' . $key . "\n";
      $form[$prefix][$set_key]['#type'] = $this->mapSchemaTypeToFormType($definition);
      $form[$prefix][$set_key]['#required'] = $definition['required'] ?? FALSE;
      $form[$prefix][$set_key]['#title'] = $definition['label'] ?? $key;
      $form[$prefix][$set_key]['#description'] = $definition['description'] ?? '';
      $form[$prefix][$set_key]['#default_value'] = $definition['default'] ?? NULL;
      if (isset($definition['constraints'])) {
        foreach ($definition['constraints'] as $form_key => $value) {
          if ($form_key == 'options') {
            $form[$prefix][$set_key]['#options'] = array_combine($value, $value);
            continue;
          }
          $form[$prefix][$set_key]['#' . $form_key] = $value;
        }
      }
    }
  }

  /**
   * Maps schema data types to form element types.
   *
   * @param array $definition
   *   Data type of a configuration value.
   *
   * @return string
   *   Type of widget.
   */
  private function mapSchemaTypeToFormType(array $definition): string {
    // Check first for settings constraints.
    if (isset($definition['constraints']['options'])) {
      return 'select';
    }
    switch ($definition['type']) {
      case 'boolean':
        return 'checkbox';
      case 'int':
      case 'float':
        return 'number';
      case 'string':
      default:
        return 'textfield';
    }
  }

}
