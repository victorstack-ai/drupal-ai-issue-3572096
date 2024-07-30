<?php

namespace Drupal\vdb_provider_milvus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure  Milvus DB service.
 */
class MilvusConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'vdb_provider_milvus.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'milvus_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server'),
      '#required' => TRUE,
      '#description' => $this->t('The server to connect to. If you use Zilliz Cloud, this can be found under Public Endpoint.'),
      '#default_value' => $config->get('server'),
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#required' => TRUE,
      '#description' => $this->t('The server port to connect to. If you use Zilliz Cloud, this is 443.'),
      '#default_value' => $config->get('port'),
    ];

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('The API key to use for authentication. This is optional if your Milvus Vector Database is installed within your hosting environment (e.g. when using via DDEV).'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::CONFIG_NAME)
      ->set('server', $form_state->getValue('server'))
      ->set('port', $form_state->getValue('port'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
