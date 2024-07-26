<?php

namespace Drupal\ai_ckeditor\Form;

use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for displaying dialog options in CKEditor.
 */
class AiCKEditorDialogForm extends FormBase {

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $aiCKEditorPluginManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The ajax wrapper id to use for re-rendering the form.
   *
   * @var string
   */
  protected $ajaxWrapper = 'ai-ckeditor-dialog-form-wrapper';

  /**
   * The form constructor.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $ai_ckeditor_plugin_manager
   *   The AI CKEditor plugin manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AiCKEditorPluginManager $ai_ckeditor_plugin_manager, AccountProxyInterface $current_user) {
    $this->aiCKEditorPluginManager = $ai_ckeditor_plugin_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_ckeditor_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL) {
    $request = $this->getRequest();
    $query_parameters = $request->query->all();
    // Since we can't load the editor instance, we can't load the config in the
    // way the CKEditor5PluginManager does. We have to rely on the query string
    // and load the config from the configuration.
    if (!isset($query_parameters['editor_key'])) {
      throw new \InvalidArgumentException('We cannot determine that this is a CKEditor field.');
    }
    // Check so the settings exists.
    $editor_config = $this->configFactory()->get('editor.editor.' . $query_parameters['editor_key']);
    if (empty($editor_config->get('settings'))) {
      throw new \InvalidArgumentException('The editor configuration is empty.');
    }
    // Since this is custom form outside the CKEditor5 context, we have to
    // check that the user has permissions to this specific text format.
    if (!$this->currentUser()->hasPermission('use text format ' . $editor_config->get('format'))) {
      throw new \InvalidArgumentException('The user does not have permission to use this text format.');
    }

    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];
    $config = $form_state->getUserInput()['config'] ?? [];
    $definitions = $this->aiCKEditorPluginManager->getDefinitions();

    if (empty($instance_config['plugins'])) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];
      return $form;
    }

    $definition_options = [];

    foreach ($instance_config['plugins'] as $instance_plugin_id => $instance_plugin) {
      if ($instance_plugin['enabled'] && $this->aiCKEditorPluginManager->hasDefinition($instance_plugin_id)) {
        $definition_options[$instance_plugin_id] = $definitions[$instance_plugin_id]['label'] . ' - ' . $definitions[$instance_plugin_id]['description'];
      }
    }

    $form['config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => $this->ajaxWrapper,
      ],
      'plugin_id' => [
        '#type' => 'select',
        '#title' => $this->t('Action'),
        '#empty_option' => $this->t('- Select an action -'),
        '#default_value' => $config['plugin_id'] ?? NULL,
        '#options' => $definition_options,
        '#ajax' => [
          'callback' => [$this, 'updateFormElement'],
          'event' => 'change',
          'wrapper' => $this->ajaxWrapper,
        ],
      ],
    ];
    if ($config['plugin_id']) {
      /** @var \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $instance */
      try {
        $instance = $this->aiCKEditorPluginManager->createInstance($config['plugin_id'], $instance_config['plugins'][$config['plugin_id']] ?? []);
        $subform = $form['config']['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);

        if (isset($query_parameters['selected_text'])) {
          $subform_state->setStorage(['selected_text' => $query_parameters['selected_text']]);
        }

        $form['config']['plugin_config'] = $instance->buildCkEditorModalForm([], $subform_state);
        $form['config']['plugin_config']['#tree'] = TRUE;
      }
      catch (\Exception $exception) {
        $form['message'] = [
          '#type' => 'status_messages',
        ];
      }
    }

    return $form;
  }

  /**
   * Update the form after selecting a plugin type.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form element.
   */
  public function updateFormElement(array $form, FormStateInterface $form_state): array {
    return $form['config'];
  }

  /**
   * Ajax submit callback to insert content into ckeditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   AJAX response for injecting content into ckeditor.
   */
  public static function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form['config'];
    }

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $instance */
    $plugin_id = $form_state->getValue(['config', 'plugin_id']);
    if ($plugin_id) {
      try {
        $instance = $this->aiCKEditorPluginManager->createInstance($plugin_id, $form_state->getValue([
          'config',
          'plugin_config',
        ]) ?? []);
        $subform = $form['config']['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance->validateCkEditorModalForm($subform, $subform_state);
        $config = $form_state->getValue('config');
        $form_state->setValue('config', $config);
      }
      catch (\Exception $exception) {
        $form_state->setValue('config', []);
      }
    }
    else {
      $form_state->setValue('config', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
