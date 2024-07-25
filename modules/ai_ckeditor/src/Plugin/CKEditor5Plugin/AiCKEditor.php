<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Completion plugin configuration.
 */
class AiCKEditor extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'plugins' => [],
  ];

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $pluginManager;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * AI CKEditor plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $plugin_manager
   *   The AI CKEditor plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   */
  final public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, AiCKEditorPluginManager $plugin_manager, AiProviderPluginManager $provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManager = $plugin_manager;
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
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $definitions = $this->pluginManager->getDefinitions();
    if (!$definitions) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];
      return $form;
    }

    $form['plugins'] = [];

    foreach ($definitions as $plugin_id => $definition) {
      $form['plugins'][$plugin_id] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#open' => TRUE,
        '#title' => $definition['label'],
      ];

      $form['plugins'][$plugin_id]['description'] = [
        '#markup' => $definition['description'],
      ];

      $form['plugins'][$plugin_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $this->configuration['plugins'][$plugin_id]['enabled'] ?? FALSE,
        '#description' => $this->t('Enable this editor feature.'),
      ];

      $subform = $form['config']['plugin_config'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $instance = $this->pluginManager->createInstance($plugin_id, $this->configuration['plugins'][$plugin_id] ?? []);
      $form['plugins'][$plugin_id] = $form['plugins'][$plugin_id] + $instance->buildConfigurationForm($subform, $subform_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['plugins'])) {
      foreach ($values['plugins'] as $plugin_id => $plugin) {
        $subform = $form['plugins'][$plugin_id] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance = $this->pluginManager->createInstance($plugin_id);
        $instance->submitConfigurationForm($form, $subform_state);
        $this->configuration['plugins'][$plugin_id] = $instance->getConfiguration();
        $this->configuration['plugins'][$plugin_id]['enabled'] = (bool) $plugin['enabled'];
      }
    }

    $this->setConfiguration($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $ai_ckeditor_dialog_url = Url::fromRoute('ai_ckeditor.dialog')
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['ai_ckeditor_ai']['dialogURL'] = $ai_ckeditor_dialog_url;

    $config = $this->getConfiguration();

    foreach ($config['plugins'] as $plugin_id => $plugin) {
      $static_plugin_config['ai_ckeditor_ai']['plugins'][$plugin_id] = [
        'enabled' => $plugin['enabled'],
        'provider' => $plugin['provider'] ?? NULL,
      ];
    }

    return $static_plugin_config;
  }


}
