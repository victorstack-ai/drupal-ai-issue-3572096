<?php

namespace Drupal\provider_mistral\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mistral API access.
 */
class MistralConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'provider_mistral.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mistral_settings';
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
      '#title' => $this->t('Mistral API Key'),
      '#description' => $this->t('The API Key. Can be found on <a href="https://console.mistral.ai/api-keys/">https://console.mistral.ai/api-keys/</a>.'),
      '#default_value' => $config->get('api_key'),
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

    parent::submitForm($form, $form_state);
  }

}
