<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
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
  const array DEFAULT_CONFIGURATION = [
    'dialog' => [
      'autoresize' => 'min-width: 600px',
      'height' => '750',
      'width' => '900',
      'dialog_class' => 'ai-ckeditor-modal',
    ],
    'plugins' => [],
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * AI CKEditor plugin constructor.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $pluginManager
   *   The AI CKEditor plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The AI provider manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    protected AiCKEditorPluginManager $pluginManager,
    protected AiProviderPluginManager $providerManager,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider'),
      $container->get('current_user'),
    );
  }

  /**
   * Form constructor.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $definitions = $this->pluginManager->getDefinitions();
    if (!$definitions) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];
      return $form;
    }

    $form['dialog'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#open' => TRUE,
      '#title' => $this->t('Modal dialog options'),
    ];

    $form['dialog']['autoresize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auto-resize dialog'),
      '#description' => $this->t('Auto-resize the dialog when a modal is loaded, based on a CSS value. Leave blank to disable auto-resize. An example value for CSS could be: "min-width: 600px"'),
      '#default_value' => $this->configuration['dialog']['autoresize'] ?? FALSE,
    ];

    $form['dialog']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('A pixel or percent value of what the height of the modal should be. For pixel value, do not include "px".'),
      '#default_value' => $this->configuration['dialog']['height'] ?? 750,
    ];

    $form['dialog']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('A pixel or percent value of what the width of the modal should be. For pixel value, do not include "px".'),
      '#default_value' => $this->configuration['dialog']['width'] ?? 900,
    ];

    $form['dialog']['dialog_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog CSS class'),
      '#description' => $this->t('A CSS class or classes to apply to the modal dialog.'),
      '#default_value' => $this->configuration['dialog']['dialog_class'] ?? 'ai-ckeditor-modal',
    ];

    $form['plugins'] = [];

    foreach ($definitions as $plugin_id => $definition) {
      $form['plugins'][$plugin_id] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#open' => FALSE,
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
      if ($instance instanceof AiCKEditorPluginBase) {
        $form['plugins'][$plugin_id] += $instance->buildConfigurationForm($subform, $subform_state);
      }
    }

    return $form;
  }

  /**
   * Form validation handler.
   *
   * @param array<mixed> $form
   *   An associative array containing the structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $definitions = $this->pluginManager->getDefinitions();
    // Let the plugins validate their own configuration.
    foreach ($definitions as $plugin_id => $definition) {
      $subform = $form['plugins'][$plugin_id] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $instance = $this->pluginManager->createInstance($plugin_id, $this->configuration['plugins'][$plugin_id] ?? []);
      if ($instance instanceof AiCKEditorPluginBase) {
        $instance->validateConfigurationForm($subform, $subform_state);
      }
    }
  }

  /**
   * The form submission handler.
   *
   * @param array<mixed> $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    if (!empty($values['dialog'])) {
      $this->configuration['dialog']['autoresize'] = (is_string($values["dialog"]["autoresize"]) && !empty($values["dialog"]["autoresize"])) ? $values["dialog"]["autoresize"] : FALSE;
      $this->configuration['dialog']['height'] = is_string($values["dialog"]["height"]) ? $values["dialog"]["height"] : $this->defaultConfiguration()['height'];
      $this->configuration['dialog']['width'] = is_string($values["dialog"]["width"]) ? $values["dialog"]["width"] : $this->defaultConfiguration()['width'];
      $this->configuration['dialog']['dialog_class'] = is_string($values["dialog"]["dialog_class"]) ? $values["dialog"]["dialog_class"] : $this->defaultConfiguration()['dialog_class'];
    }

    if (!empty($values['plugins'])) {
      foreach ($values['plugins'] as $plugin_id => $plugin) {
        $subform = $form['plugins'][$plugin_id] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance = $this->pluginManager->createInstance($plugin_id);
        if ($instance instanceof AiCKEditorPluginBase) {
          $instance->submitConfigurationForm($form, $subform_state);
          $this->configuration['plugins'][$plugin_id] = $instance->getConfiguration();
          $this->configuration['plugins'][$plugin_id]['enabled'] = (bool) $plugin['enabled'];
        }
      }
    }

    $this->setConfiguration($this->configuration);
  }

  /**
   * Allows a plugin to modify its static configuration.
   *
   * @param array<mixed> $static_plugin_config
   *   The ckeditor5.config entry from the YAML or annotation, if any. If none
   *   is specified in the YAML or annotation, then the empty array.
   * @param \Drupal\editor\EditorInterface $editor
   *   A configured text editor object.
   *
   * @return array<mixed>
   *   Returns the received $static_plugin_config plus dynamic additions or
   *   alterations.
   *
   * @see \Drupal\ckeditor5\Annotation\CKEditor5AspectsOfCKEditor5Plugin::$config
   * @see \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition::getCKEditor5Config()
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $ai_ckeditor_dialog_url = Url::fromRoute('ai_ckeditor.dialog')
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['ai_ckeditor_ai']['dialogURL'] = $ai_ckeditor_dialog_url;

    $config = $this->getConfiguration();

    $static_plugin_config['ai_ckeditor_ai']['hasAccess'] = $this->currentUser->hasPermission('use ai ckeditor');

    if (!empty($config["dialog"])) {
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['autoResize'] = (is_string($config["dialog"]["autoresize"]) && !empty($config["dialog"]["autoresize"])) ? $config["dialog"]["autoresize"] : FALSE;
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['height'] = is_string($config["dialog"]["height"]) ? $config["dialog"]["height"] : $this->defaultConfiguration()['height'];
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['width'] = is_string($config["dialog"]["width"]) ? $config["dialog"]["width"] : $this->defaultConfiguration()['width'];
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['dialogClass'] = is_string($config["dialog"]["dialog_class"]) ? $config["dialog"]["dialog_class"] : $this->defaultConfiguration()['dialog_class'];
    }

    $all_disabled = TRUE;
    foreach ($config['plugins'] as $plugin_id => $plugin) {
      $this->pluginManager->getDefinition($plugin_id);
      if ($all_disabled && $plugin['enabled']) {
        $all_disabled = FALSE;
      }

      // Load the plugin.
      $instance = $this->pluginManager->createInstance($plugin_id, $plugin);
      if ($instance instanceof AiCKEditorPluginBase) {
        // Check the editors each plugin gives back.
        foreach ($instance->availableEditors() as $id => $label) {
          $static_plugin_config['ai_ckeditor_ai']['plugins'][$id] = [
            'enabled' => $plugin['enabled'],
            'provider' => $plugin['provider'] ?? NULL,
            'meta' => [
              'label' => $label,
              'id' => $id,
            ],
          ];
        }
      }
    }

    if (isset($static_plugin_config['ai_ckeditor_ai']['plugins'])) {
      foreach ($static_plugin_config['ai_ckeditor_ai']['plugins'] as $plugin_id => $plugin) {
        if ($plugin_id === 'ai_ckeditor_help') {
          unset($static_plugin_config['ai_ckeditor_ai']['plugins'][$plugin_id]);
          $static_plugin_config['ai_ckeditor_ai']['plugins'][$plugin_id] = $plugin;
          break;
        }
      }
    }

    // Hide if nothing is enabled.
    return $all_disabled ? [] : $static_plugin_config;
  }

}
