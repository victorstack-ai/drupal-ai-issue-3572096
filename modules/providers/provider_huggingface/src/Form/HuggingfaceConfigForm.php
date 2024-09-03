<?php

namespace Drupal\provider_huggingface\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Configure Huggingface access.
 */
class HuggingfaceConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'provider_huggingface.settings';

  /**
   * Supported Types.
   *
   * @var array
   */
  protected $supportedTypes = [
    'chat' => [
      'label' => 'Chat',
      'filter' => 'text-generation',
    ],
    'embeddings' => [
      'label' => 'Embeddings',
      'filter' => 'feature-extraction',
    ],
    'image_classification' => [
      'label' => 'Image Classification',
      'filter' => 'image-classification',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'huggingface_settings';
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

    $models = [];
    if (!$form_state->get('models')) {
      $models = $config->get('models');
    }
    else {
      $models = $form_state->get('models');
    }

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Huggingface Access Token'),
      '#description' => $this->t('The Access Token. Can be found on @link admin pages. <strong>Make sure that the token has the correct rights.</strong>', [
        '@link' => Link::fromTextAndUrl('Huggingface', Url::fromUri('https://huggingface.co/settings/tokens'))->toString(),
      ]),
      '#default_value' => $config->get('api_key'),
    ];

    foreach ($this->supportedTypes as $type => $type_info) {
      $form[$type] = [
        '#type' => 'fieldset',
        '#title' => $type_info['label'],
        '#description' => $this->t('Add the models you want to use for @link by autocompleting them. Follow the link to @link for the full list.', [
          '@link' => Link::fromTextAndUrl($type_info['label'], Url::fromUri('https://huggingface.co/models?pipeline_tag=' . $type_info['filter'] . '&sort=trending'))->toString(),
        ]),
        '#prefix' => '<div id="' . $type . '-wrapper">',
        '#suffix' => '</div>',
        // Only visible if the key is set.
        '#states' => [
          'visible' => [
            ':input[name="api_key"]' => ['filled' => TRUE],
          ],
        ],
      ];

      $i = 0;
      if (!empty($models[$type])) {
        foreach ($models[$type] as $model) {
          $form[$type]['model__' . $type . '__' . $i] = [
            '#type' => 'textfield',
            '#autocomplete_route_name' => 'provider_huggingface.autocomplete.models',
            '#autocomplete_route_parameters' => [
              'model_type' => $type_info['filter'],
            ],
            '#default_value' => $model,
          ];
          $i++;
        }
      }
      $form[$type]['model__' . $type . '__' . $i] = [
        '#type' => 'textfield',
        '#autocomplete_route_name' => 'provider_huggingface.autocomplete.models',
        '#autocomplete_route_parameters' => [
          'model_type' => $type_info['filter'],
        ],
      ];

      $form[$type]['add_more_' . $type] = [
        '#type' => 'submit',
        '#value' => $this->t('Add another @type model', ['@type' => strtolower($type_info['label'])]),
        '#submit' => ['::addMoreModel'],
        '#attributes' => [
          'data-type' => $type,
        ],
        '#ajax' => [
          'callback' => '::addMoreModelCallback',
          'wrapper' => $type . '-wrapper',
        ],
      ];

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $models = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (substr($key, 0, 7) == 'model__' && $value) {
        $parts = explode('__', $key);
        $models[$parts[1]][] = $value;
      }
    }

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('models', $models)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Add more model.
   */
  public function addMoreModel(array &$form, FormStateInterface $form_state) {
    $models = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (substr($key, 0, 7) == 'model__' && $value) {
        $parts = explode('__', $key);
        $models[$parts[1]][] = $value;
      }
    }
    $form_state->set('models', $models);
    $form_state->setRebuild();
  }

  /**
   * Add more model callback.
   */
  public function addMoreModelCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $type = $trigger['#attributes']['data-type'];
    return $form[$type];
  }

}
