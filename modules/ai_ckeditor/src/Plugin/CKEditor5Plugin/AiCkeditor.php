<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Completion plugin configuration.
 */
class AiCkeditor extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'completion' => [
      'enabled' => FALSE,
      'provider' => '',
    ],
  ];

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * OpenAI CKEditor plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   */
  final public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, AiProviderPluginManager $provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['completion'] = [
      '#title' => $this->t('Text completion'),
      '#type' => 'details',
      '#description' => $this->t('The following setting controls the behavior of the text completion, translate, tone, and summary actions in CKEditor.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['completion']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->configuration['completion']['enabled'] ?? FALSE,
      '#description' => $this->t('Enable this editor feature.'),
    ];

    $form['completion']['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $this->providerManager->getSimpleProviderModelOptions('chat'),
      // Load the set value or the default if it exists.
      '#default_value' => $this->configuration['completion']['provider'] ?? $this->providerManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use to analyze text. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Empty provider is not allowed.
    if (empty($form_state->getValue(['completion', 'provider']))) {
      $form_state->setError($form['completion']['provider'], $this->t('Please select an AI provider.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['completion']['enabled'] = (bool) $values['completion']['enabled'];
    $this->configuration['completion']['provider'] = $values['completion']['provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $options = $static_plugin_config;
    $config = $this->getConfiguration();

    return [
      'ai_ckeditor' => [
        'completion' => [
          'enabled' => $config['completion']['enabled'] ?? $options['completion']['enabled'],
          'provider' => $config['completion']['provider'] ?? $options['completion']['provider'],
        ],
      ],
    ];
  }

}
