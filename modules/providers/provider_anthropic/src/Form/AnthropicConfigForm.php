<?php

namespace Drupal\provider_anthropic\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Anthropic API access.
 */
class AnthropicConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'provider_anthropic.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anthropic_settings';
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

    $form['#attached']['library'][] = 'provider_anthropic/verification';

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Anthropic API Key'),
      '#description' => $this->t('The API Key. Can be found on <a href="https://console.anthropic.com/settings/keys">https://console.anthropic.com/settings/keys</a>.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Anthropic Version'),
      '#description' => $this->t('The version of the Anthropic API to use. This could need to be changed if the API gets updated with a better model. See https://docs.anthropic.com/en/docs/models-overview.'),
      '#default_value' => $config->get('version'),
      '#required' => TRUE,
    ];

    $form['advanced']['moderation'] = [
      '#type' => 'select',
      '#title' => $this->t('Request Moderation'),
      '#description' => $this->t('This makes sure that for each request being sent, we do a moderation check via Anthropic before. This costs money, but ensures that your account will not be banned.'),
      '#options' => [
        0 => $this->t('No moderation'),
        1 => $this->t('Moderation'),
      ],
      '#default_value' => $config->get('moderation'),
    ];

    $form['advanced']['moderation_checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify Moderation'),
      '#description' => $this->t('I hereby understand that disabling the moderation, might lead to a prompt that will be seen as malicious to Anthropic, THAT WILL GET ME BANNED.'),
      '#states' => [
        'visible' => [
          ':input[name="moderation"]' => ['value' => 0],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('moderation') == 0 && !$form_state->getValue('moderation_checkbox')) {
      $form_state->setErrorByName('moderation_checkbox', $this->t('You need to verify that you understand the consequences of disabling moderation.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('moderation', $form_state->getValue('moderation'))
      ->set('version', $form_state->getValue('version'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
