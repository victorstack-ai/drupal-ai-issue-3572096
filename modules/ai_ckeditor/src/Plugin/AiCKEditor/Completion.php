<?php

namespace Drupal\ai_ckeditor\Plugin\AICKEditor;

use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\Ajax\EditorDialogSave;

/**
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_completion',
  label: new TranslatableMarkup('Completion'),
  description: new TranslatableMarkup('Get ideas and text completion assistance from AI.'),
)]
final class Completion extends AiCKEditorPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $this->aiProviderManager->getSimpleProviderModelOptions('chat'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

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
    $this->configuration['provider'] = $form_state->getValue('provider');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->pluginDefinition['description'] . '</p>',
    ];

    $form['text_to_submit'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What would you like to ask or get ideas for?'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['response_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response from AI'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the editor.'),
      '#prefix' => '<div id="ai-ckeditor-completion-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['generate'] = [
      '#type' => 'button',
      '#value' => $this->t('Generate'),
      '#ajax' => [
        'callback' => [$this, 'ajaxGenerateText'],
        'wrapper' => 'ai-ckeditor-completion-response',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Save changes to editor'),
      '#ajax' => [
        'callback' => [$this, 'submitCkEditorModalForm'],
      ],
      '#attributes' => [
        'class' => [
          'align-right',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateCkEditorModalForm(array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();

    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'value' => strip_tags($values["plugin_config"]["response_text"]),
        'returnsHtml' => FALSE,
      ],
    ]));

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Generate text callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The response text.
   */
  public function ajaxGenerateText(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      $text = $this->getResponse($values["plugin_config"]["text_to_submit"]);
      $form_state->setRebuild();
      $form['plugin_config']['response_text']['#value'] = $text;
    }
    catch (\Exception $e) {
      $this->logger->error("There was an error in the Completion AI plugin for CKEditor.");
      $form['plugin_config']['response_text']['#value'] = "There was an error in the Summarize AI plugin for CKEditor.";
    }

    return $form['plugin_config']['response_text'];
  }

}
