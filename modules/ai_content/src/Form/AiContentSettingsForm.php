<?php

namespace Drupal\ai_content\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Content module.
 */
class AiContentSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_content.settings';

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_content_settings';
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
    $moderation_models = $this->providerManager->getSimpleProviderModelOptions('moderation');
    $chat_models = $this->providerManager->getSimpleProviderModelOptions('chat');
    $default_moderation_model = $this->providerManager->getSimpleDefaultProviderOptions('moderation');
    $default_chat_model = $this->providerManager->getSimpleDefaultProviderOptions('chat');

    $form['analyse_policies_enabled'] = [
      '#type' => 'select',
      '#options' => $moderation_models,
      '#disabled' => count($moderation_models) == 0,
      '#default_value' => $config->get('analyse_policies_enabled') ?? $default_moderation_model,
      '#description' => $this->t('<em>AI can analyze content and tell you what content policies it may violate. This is beneficial if your audience are certain demographics and sensitive to certain categories. Note that this is only a useful guide.'),
      '#title' => $this->t('Choose Content analysis sidebar widget model.'),
    ];
    $form['tone_adjust_enabled'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('Have AI check your content and adjust the tone of it for different reader audiences for you.'),
      '#default_value' => $config->get('tone_adjust_enabled') ?? $default_chat_model,
      '#title' => $this->t('Choose Adjust content tone widget model.'),
    ];
    $form['summarise_enabled'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('This will use the selected field and OpenAI will attempt to summarize it for you. You can use the result to help generate a summary/teaser, social media share text, or similar.'),
      '#default_value' => $config->get('summarise_enabled') ?? $default_chat_model,
      '#title' => $this->t('Choose Summarisation widget model.'),
    ];
    $form['suggest_title_enabled'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('AI can try to suggest an SEO friendly title from the selected field.'),
      '#default_value' => $config->get('suggest_title_enabled') ?? $default_chat_model,
      '#title' => $this->t('Choose Title suggestion widget model.'),
    ];
    $form['suggest_tax_enabled'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('AI can attempt to suggest possible classification terms to use as taxonomy.'),
      '#default_value' => $config->get('suggest_tax_enabled') ?? $default_chat_model,
      '#title' => $this->t('Choose Taxonomy term suggestion widget model.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('analyse_policies_enabled', $form_state->getValue('analyse_policies_enabled'))
      ->set('tone_adjust_enabled', $form_state->getValue('tone_adjust_enabled'))
      ->set('summarise_enabled', $form_state->getValue('summarise_enabled'))
      ->set('suggest_title_enabled', $form_state->getValue('suggest_title_enabled'))
      ->set('suggest_tax_enabled', $form_state->getValue('suggest_tax_enabled'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
