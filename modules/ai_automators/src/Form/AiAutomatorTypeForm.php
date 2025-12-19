<?php

namespace Drupal\ai_automators\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form for configuring an AI automator type.
 */
class AiAutomatorTypeForm extends FormBase {

  /**
   * The AI automator.
   *
   * @var \Drupal\ai_automators\AiAutomatorInterface
   */
  protected $aiAutomator;

  /**
   * The automator type.
   *
   * @var \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface
   */
  protected $aiAutomatorType;

  /**
   * Constructs a new AiAutomatorTypeForm.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $fieldManager,
    protected AiAutomatorTypeManager $automatorTypeManager,
  ) {
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The created instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.ai_automator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_automator_type_form';
  }

  /**
   * The form builder.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface|null $ai_automator
   *   The AI Automator entity.
   * @param string|null $ai_automator_type
   *   The automator type plugin ID.
   *
   * @return array<string,mixed>
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AiAutomatorInterface $ai_automator = NULL, $ai_automator_type = NULL) {
    $this->aiAutomator = $ai_automator;
    try {
      $this->aiAutomatorType = $this->prepareAutomatorType($ai_automator_type);
    }
    catch (PluginNotFoundException) {
      throw new NotFoundHttpException("Invalid effect id: '$ai_automator_type'.");
    }
    $request = $this->getRequest();

    // Load up the config.
    $configuration = $this->aiAutomatorType->getConfiguration();
    $settings = $configuration['settings'] ?? [];

    // Setup entity and fields.
    $entityType = $this->aiAutomator->get('entity_type');
    $entityBundle = $this->aiAutomator->get('bundle');
    $fields = $this->fieldManager->getFieldDefinitions($entityType, $entityBundle);
    $fieldDefinition = $this->aiAutomator->getFieldDefinition();

    // @todo this is horrible and needs to be refactored all over.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $dummyEntity */
    $dummyEntity = $this->aiAutomator->getDummyEntity();

    $form['uuid'] = [
      '#type' => 'value',
      '#value' => $this->aiAutomatorType->getUuid(),
    ];
    $form['id'] = [
      '#type' => 'value',
      '#value' => $this->aiAutomatorType->getPluginId(),
    ];

    // Help text for the automator type.
    if (!empty($this->aiAutomatorType->helpText())) {
      $form['automator_help_text'] = [
        '#type' => 'details',
        '#title' => $this->t('About this rule'),
      ];

      $form['automator_help_text']['help_text'] = [
        '#markup' => $this->aiAutomatorType->helpText(),
      ];
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->aiAutomatorType->label(),
      '#required' => TRUE,
    ];

    $form['automator_container'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Automator Settings'),
      '#weight' => 18,
      '#open' => TRUE,
    ];

    // Plugin base config form.
    $form['automator_container']['plugin_base'] = [];
    $subform_state = SubformState::createForSubform($form['automator_container']['plugin_base'], $form, $form_state);
    $form['automator_container']['plugin_base'] = $this->aiAutomatorType->buildConfigurationForm($form['automator_container']['plugin_base'], $subform_state, $this->aiAutomator);
    $form['automator_container']['plugin_base']['#tree'] = TRUE;

    $modeOptions = [
      'base' => $this->t('Base Mode'),
    ];
    if ($this->aiAutomatorType->advancedMode()) {
      $modeOptions['token'] = $this->t('Advanced Mode (Token)');
    }

    $description = $this->aiAutomatorType->advancedMode() ? $this->t('The Advanced Mode (Token) is available for this Automator Type to use multiple fields as input, you may also choose Base Mode to choose one base field.') :
      $this->t('For this Automator Type, only the Base Mode is available. It uses the base field to generate the content.');
    $form['automator_container']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Automator Input Mode'),
      '#description' => $description,
      '#options' => $modeOptions,
      '#default_value' => $settings['mode'] ?? 'base',
      '#weight' => 5,
      '#attributes' => [
        'name' => 'mode',
      ],
    ];

    // Prompt with token.
    $form['automator_container']['normal_prompt'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#weight' => 11,
      '#states' => [
        'visible' => [
          ':input[name="mode"]' => [
            'value' => 'base',
          ],
        ],
      ],
    ];
    // Create Options for base field.
    $baseFieldOptions = [];
    foreach ($fields as $fieldId => $fieldData) {
      if (in_array($fieldData->getType(), $this->aiAutomatorType->allowedInputs())) {
        $baseFieldOptions[$fieldId] = $fieldData->getLabel();
      }
    }

    $form['automator_container']['normal_prompt']['base_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Automator Base Field'),
      '#description' => $this->t('This is the field that will be used as context field for generating data into this field.'),
      '#options' => $baseFieldOptions,
      '#default_value' => $settings['base_field'] ?? NULL,
      '#weight' => 5,
    ];

    // Prompt if needed.
    if ($this->aiAutomatorType->needsPrompt()) {
      $form['automator_container']['normal_prompt']['prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Automator Prompt'),
        '#description' => $this->t('The prompt to use to fill this field.'),
        '#attributes' => [
          'placeholder' => $this->aiAutomatorType->placeholderText(),
        ],
        '#default_value' => $settings['prompt'] ?? NULL,
        '#weight' => 10,
      ];

      // Placeholders available.
      $form['automator_container']['normal_prompt']['automator_prompt_placeholders'] = [
        '#type' => 'details',
        '#title' => $this->t('Placeholders available'),
        '#weight' => 15,
      ];

      $placeholderText = "";
      foreach ($this->aiAutomatorType->tokens($dummyEntity) as $key => $text) {
        $placeholderText .= "<strong>{{ $key }}</strong> - " . $text . "<br>";
      }
      $form['automator_container']['normal_prompt']['automator_prompt_placeholders']['placeholders'] = [
        '#markup' => $placeholderText,
      ];
    }
    else {
      $form['prompt'] = [
        '#value' => '',
      ];
    }

    // Advanced mode with tokens.
    if ($this->aiAutomatorType->advancedMode()) {
      $form['automator_container']['token_prompt'] = [
        '#type' => 'fieldset',
        '#open' => TRUE,
        '#weight' => 11,
        '#states' => [
          'visible' => [
            ':input[name="mode"]' => [
              'value' => 'token',
            ],
          ],
        ],
      ];

      $form['automator_container']['token_prompt']['token'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Automator Prompt (Token)'),
        '#description' => $this->t('The prompt to use to fill this field.'),
        '#default_value' => $settings['token'] ?? NULL,
      ];

      $tokenTypes = $entityType === 'taxonomy_term' ? ['term'] : [$entityType];
      $form['automator_container']['token_prompt']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => $tokenTypes,
      ];
    }

    $form['automator_container']['edit_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit when changed'),
      '#description' => $this->t('By default the initial value or manual set value will not be overriden. If you check this, it will override if the base text field changes its value.'),
      '#default_value' => $settings['edit_mode'] ?? FALSE,
      '#weight' => 20,
    ];

    $form['automator_container']['automator_advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#weight' => 25,
    ];

    // @todo move this into here and not in the plugin.
    // Also should remove entity and field definitions.
    $pluginAdvancedFields = $this->aiAutomatorType->extraAdvancedFormFields($dummyEntity, $fieldDefinition, $form_state, $settings);
    $form['automator_container']['automator_advanced'] = array_merge($form['automator_container']['automator_advanced'], $pluginAdvancedFields);

    $form['automator_container']['automator_advanced']['plugin_advanced'] = [];
    $subform_state = SubformState::createForSubform($form['automator_container']['automator_advanced']['plugin_advanced'], $form, $form_state);
    $form['automator_container']['automator_advanced']['plugin_advanced'] = $this->aiAutomatorType->buildAdvancedConfigurationForm($form['automator_container']['automator_advanced']['plugin_advanced'], $subform_state, $this->aiAutomator);
    $form['automator_container']['automator_advanced']['plugin_advanced']['#tree'] = TRUE;

    // Check the URL for weight, then the automator type, otherwise use default.
    $form['weight'] = [
      '#type' => 'hidden',
      '#value' => $request->query->has('weight') ? (int) $request->query->get('weight') : $this->aiAutomatorType->getWeight(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#value' => $this->t('Save'),
      '#type' => 'submit',
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->aiAutomator->toUrl('edit-form'),
      '#attributes' => ['class' => ['button']],
    ];
    return $form;
  }

  /**
   * Validates the form for the AI automator type.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   No return value.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the configuration.
    if ($this->aiAutomatorType->needsPrompt() && $form_state->getValue('mode') == 'base' && !$form_state->getValue('prompt')) {
      $form_state->setErrorByName('prompt', $this->t('If you enable AI Automator, you have to give a prompt.'));
    }
    if ($form_state->getValue('mode') == 'base' && !$form_state->getValue('base_field')) {
      $form_state->setErrorByName('base_field', $this->t('If you enable AI Automator, you have to give a base field.'));
    }

    // Pass validation to each plugin.
    $pluginBase = SubformState::createForSubform($form['automator_container']['plugin_base'], $form, $form_state);
    $this->aiAutomatorType->validateConfigurationForm($form['automator_container']['plugin_base'], $pluginBase, $this->aiAutomator);

    // Pass advanced validation to each plugin.
    $pluginAdvanced = SubformState::createForSubform($form['automator_container']['automator_advanced']['plugin_advanced'], $form, $form_state);
    $this->aiAutomatorType->validateAdvancedConfigurationForm($form['automator_container']['automator_advanced']['plugin_advanced'], $pluginAdvanced, $this->aiAutomator);
  }

  /**
   * Submits the form for the AI automator type.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   No return value.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Let plugin process subforms first.
    $pluginBase = SubformState::createForSubform($form['automator_container']['plugin_base'], $form, $form_state);
    $this->aiAutomatorType->submitConfigurationForm($form['automator_container']['plugin_base'], $pluginBase, $this->aiAutomator);

    $pluginAdvanced = SubformState::createForSubform($form['automator_container']['automator_advanced']['plugin_advanced'], $form, $form_state);
    $this->aiAutomatorType->submitAdvancedConfigurationForm($form['automator_container']['automator_advanced']['plugin_advanced'], $pluginAdvanced, $this->aiAutomator);

    // Persist plugin configuration on the automator entity.
    $form_state->cleanValues();
    $configuration = $this->aiAutomatorType->getConfiguration();

    // Set the label in the configuration.
    $configuration['label'] = $form_state->getValue('label');

    // Clean settings.
    $settings = array_merge($configuration['settings'] ?? [], $form_state->getValues());
    unset($settings['id'], $settings['uuid'], $settings['weight'], $settings['label'], $settings['plugin_base'], $settings['plugin_advanced']);
    $configuration['settings'] = $settings;
    $this->aiAutomatorType->setConfiguration($configuration);

    $this->aiAutomatorType->setWeight($form_state->getValue('weight'));
    if ($uuid = $this->aiAutomatorType->getUuid()) {
      $this->aiAutomator->getAutomatorType($uuid)->setConfiguration($this->aiAutomatorType->getConfiguration());
    }
    else {
      $this->aiAutomator->addAutomatorType($this->aiAutomatorType->getConfiguration());
    }
    $this->aiAutomator->save();

    $this->messenger()->addStatus($this->t('Automator configuration saved.'));
    $form_state->setRedirectUrl($this->aiAutomator->toUrl('edit-form'));
  }

  /**
   * Prepares the automator type plugin.
   *
   * @param string $automator_type
   *   The automator type ID.
   *
   * @return \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface
   *   The automator type plugin instance.
   */
  protected function prepareAutomatorType(string $automator_type) {
    $automatorTypes = $this->aiAutomator->getAutomatorTypes();
    if ($automatorTypes->has($automator_type)) {
      return $automatorTypes->get($automator_type);
    }
    else {
      /** @var \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface  $newAutomatorType */
      $newAutomatorType = $this->automatorTypeManager->createInstance($automator_type);
      // Set the initial weight so this effect comes last.
      $newAutomatorType->setWeight(count($automatorTypes));
      return $newAutomatorType;
    }
  }

}
