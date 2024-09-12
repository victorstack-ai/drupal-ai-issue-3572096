<?php

namespace Drupal\ai_translate\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\TwigEnvironment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Translate module.
 */
class AiTranslateSettingsForm extends ConfigFormBase {

  const MINIMAL_PROMPT_LENGTH = 50;

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_translate.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->twig = $container->get('twig');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_translate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load config.
    $config = $this->config(static::CONFIG_NAME);

    $form['prompt'] = [
      '#title' => $this->t('Translation prompt'),
      '#type' => 'textarea',
      '#default_value' => $config->get('prompt') ?? '',
      '#description' => $this->t('Prompt used for translating the content.'),
    ];
    $helpText = $this->moduleHandler->moduleExists('help')
      ? Link::createFromRoute($this->t('Read more'), 'help.help_topic',
        ['id' => 'ai_translate.prompt'])
      : $this->t('Enable <em>@module</em> module to read more', ['@module' => 'help']);
    $form['longer_description'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Prompt is rendered using Twig rendering engine and supports the following tokens:'),
        '{{ source_lang }} - ' . $this->t('ISO language code (i.e. fr) of the source'),
        '{{ source_lang_name }} - ' . $this->t('Human readable name of the source language'),
        '{{ dest_lang }} - ' . $this->t('ISO language code (i.e. de) of the desired translation'),
        '{{ dest_lang_name }} - ' . $this->t('Human readable name of the desired translation language'),
        '{{ input_text }} - ' . $this->t('Text to translate'),
        $helpText,
      ],
    ];
    $form['reference_defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity reference translation'),
      '#description' => $this->moduleHandler->moduleExists('help')
        ? Link::createFromRoute($this->t('Read more'), 'help.help_topic',
          ['id' => 'ai_translate.references'])
        : $this->t('Enable <em>@module</em> module to read more', ['@module' => 'help']),
    ];
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if (!($entityType instanceof ContentEntityTypeInterface)) {
        continue;
      }
      $options[$entityTypeId] = $entityType->getLabel();
    }
    $form['reference_defaults']['reference_defaults'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('These entity types will be translated by default when referencing entity is translated'),
      '#options' => $options,
      '#description' => $this->t('This setting can be overriden in entity reference field settings.'),
      '#default_value' => $config->get('reference_defaults'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      if (strlen($this->twig->renderInline($form_state->getValue('prompt'), [
        'source_lang_name' => 'Test 1',
        'dest_lang_name' => 'Test 2',
        'input_text' => 'Text to translate',
      ])) < self::MINIMAL_PROMPT_LENGTH) {
        $form_state->setErrorByName('prompt',
          $this->t('Prompt cannot be shorter than @num characters',
          ['@num' => self::MINIMAL_PROMPT_LENGTH]));
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('prompt', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('prompt', $form_state->getValue('prompt'))
      ->set('reference_defaults', array_keys(array_filter($form_state->getValue('reference_defaults'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
