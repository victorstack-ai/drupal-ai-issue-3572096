<?php

namespace Drupal\provider_openai\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenAI API access.
 */
class OpenAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'provider_openai.settings';

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Constructs a new GroqConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager) {
    $this->aiProviderManager = $ai_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_settings';
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
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('OpenAI API Key'),
      '#description' => $this->t('The API Key. Can be found on <a href="https://platform.openai.com/">https://platform.openai.com/</a>.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['moderation'] = [
      '#markup' => '<p>' . $this->t('Moderation is always on by default for any text based call. You can disable it for each request either via code or by changing manually in provider_openai.settings.yml.') . '</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    // Set some defaults.
    $this->aiProviderManager->defaultIfNone('chat', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('chat_with_image_vision', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('chat_with_complex_json', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('text_to_image', 'openai', 'dall-e-3');
    $this->aiProviderManager->defaultIfNone('embeddings', 'openai', 'text-embedding-3-small');
    $this->aiProviderManager->defaultIfNone('text_to_speech', 'openai', 'tts-1-hd');
    $this->aiProviderManager->defaultIfNone('speech_to_text', 'openai', 'whisper-1');

    parent::submitForm($form, $form_state);
  }

}
