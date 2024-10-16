<?php

namespace Drupal\ai_automators\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_automators\Service\Automate;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_automators_ckeditor',
  label: new TranslatableMarkup('AI Automators CKEditor'),
  description: new TranslatableMarkup('Chained workflows setup with AI Automators.'),
)]
final class AiAutomatorsCKEditor extends AiCKEditorPluginBase {

  /**
   * The automate service.
   *
   * @var \Drupal\ai_automators\Service\Automate
   */
  protected Automate $automate;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $ai_provider_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $account,
    RequestStack $requestStack,
    LoggerChannelFactoryInterface $logger_factory,
    Automate $automate,
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $field_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $ai_provider_manager, $entity_type_manager, $account, $requestStack, $logger_factory);
    $this->automate = $automate;
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('ai_automator.automate'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'workflows' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Create checkboxes.
    foreach ($this->automate->getWorkflows() as $workflow_id => $workflow_label) {
      $form[$workflow_id] = [
        '#type' => 'checkbox',
        '#title' => $workflow_label,
        '#default_value' => $this->configuration['workflows'][$workflow_id]['enabled'] ?? FALSE,
      ];

      $form[$workflow_id . '_advanced'] = [
        '#type' => 'details',
        '#title' => $this->t('%label Settings', [
          '%label' => $workflow_label,
        ]),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="' . $workflow_id . '"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$workflow_id . '_advanced']['inputs'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Inputs'),
        '#description' => $this->t('Select the inputs to use for this workflow that will be exposed to the person using CKEditor.'),
        '#options' => $this->automate->getRequiredFields($workflow_id),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['inputs'] ?? [],
      ];

      $form[$workflow_id . '_advanced']['output'] = [
        '#type' => 'select',
        '#title' => $this->t('Output'),
        '#description' => $this->t('Select the output to use for this workflow that will fill out the content.'),
        '#options' => $this->automate->getAutomatedFields($workflow_id),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['output'] ?? '',
      ];
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
    foreach ($this->automate->getWorkflows() as $workflow_id => $workflow_label) {
      $this->configuration['workflows'][$workflow_id]['enabled'] = $form_state->getValue($workflow_id);
      $this->configuration['workflows'][$workflow_id]['inputs'] = $form_state->getValue($workflow_id . '_advanced')['inputs'];
      $this->configuration['workflows'][$workflow_id]['output'] = $form_state->getValue($workflow_id . '_advanced')['output'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $storage = $form_state->getStorage();
    $form = parent::buildCkEditorModalForm($form, $form_state);

    // Something is wrong with the settings if we don't get the ids.
    if (!isset($settings['config_id']) || !isset($settings['editor_id']) || !isset($settings['plugin_id'])) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Check that the settings exists.
    $editor_config = $this->configFactory->get('editor.editor.' . $settings['editor_id']);
    if (empty($editor_config->get('settings'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Since this is custom form outside the CKEditor5 context, we have to
    // check that the user has permissions to this specific text format.
    if (!$this->account->hasPermission('use text format ' . $editor_config->get('format'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];

    // Make sure its enabled.
    if (empty($instance_config['plugins'][$settings['plugin_id']]['workflows'][$settings['config_id']]['enabled'])) {
      return [
        '#markup' => '<p>' . $this->t('This AI Automator is not enabled. Please enable it in the settings.') . '</p>',
      ];
    }

    // Get the configuration.
    $plugin_config = $instance_config['plugins'][$settings['plugin_id']]['workflows'][$settings['config_id']];

    // Get the fields.
    try {
      $fields = $this->fieldManager->getFieldDefinitions('automator_chain', $settings['config_id']);
    }
    catch (\Exception $e) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Metadata.
    $form['automator_chain'] = [
      '#type' => 'value',
      '#value' => $settings['config_id'],
    ];
    $form['automator_output'] = [
      '#type' => 'value',
      '#value' => $plugin_config['output'],
    ];

    // Get the inputs.
    foreach ($plugin_config['inputs'] as $input) {
      // Make sure the field exists.
      if (isset($fields[$input])) {
        switch ($fields[$input]->getType()) {
          case 'image':
            $form[$input] = [
              '#type' => 'file',
              '#title' => $fields[$input]->getLabel(),
              '#description' => $fields[$input]->getDescription(),
              '#required' => $fields[$input]->isRequired(),
            ];
            break;

          default:
            $form[$input] = [
              '#type' => 'textarea',
              '#title' => $fields[$input]->getLabel(),
              '#description' => $fields[$input]->getDescription(),
              '#required' => $fields[$input]->isRequired(),
              '#default_value' => $storage['selected_text'],
            ];
            break;
        }
      }
    }

    $form['#attached']['library'][] = 'ai_automators/automator_ckeditor';

    // Output is fixed.
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    $form['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Response from AI'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $form_state->setValue('response_text', '<p>test</p>');
    // Generate the response.
    $values = $form_state->getValue('plugin_config');
    // Make sure that the automator chain exists.
    if (empty($values['automator_chain'])) {
      throw new \InvalidArgumentException('The automator chain is missing.');
    }
    $fields = $this->fieldManager->getFieldDefinitions('automator_chain', $values['automator_chain']);

    // Get the inputs.
    $inputs = [];
    foreach ($values as $key => $value) {
      if (!in_array($key, [
        'actions',
        'response_text',
        'automator_chain',
        'automator_output',
      ])) {
        $inputs[$key] = $value;
      }
    }
    $output = $this->automate->run($values['automator_chain'], $inputs);

    if (!isset($output[$values['automator_output']][0])) {
      throw new \Exception('The output field is missing.');
    }

    // Depending on the output type.
    switch ($fields[$values['automator_output']]->getType()) {
      case 'image':
        $result = $this->renderImage($output[$values['automator_output']][0]);
        break;

      default:
        $result = $output[$values['automator_output']][0]['value'];
        break;
    }

    $response->addCommand(new InvokeCommand(
      '#ai-ckeditor-response',
      'automatorUpdateCkEditor',
      [$result],
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function availableEditors() {
    $available_workflows = $this->automate->getWorkflows();
    $editors = [];
    foreach ($this->configuration['workflows'] as $workflow => $data) {
      if (!empty($data['enabled']) && isset($available_workflows[$workflow])) {
        $id = $this->getPluginId() . '__' . $workflow;
        $editors[$id] = $available_workflows[$workflow];
      }
    }
    return $editors;
  }

  /**
   * If the field is an image, render an image.
   *
   * @param array $data
   *   The image data id.
   *
   * @return string
   *   The image markup.
   */
  protected function renderImage(array $data): string {
    if (empty($data['target_id']) && empty($data['width']) && empty($data['height'])) {
      return $this->t('Could not generate image.');
    }
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($data['target_id']);
    if ($file) {
      $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      return '<img data-entity-uuid="' . $file->uuid() . '" data-entity-type="file" src="' . $url . '" width="' . $data['width'] . '" height="' . $data['height'] . '" />';
    }
  }

}
