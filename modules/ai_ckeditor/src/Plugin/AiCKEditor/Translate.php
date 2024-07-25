<?php

namespace Drupal\ai_ckeditor\Plugin\AICKEditor;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin to translate the lanuguage of selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_translate',
  label: new TranslatableMarkup('Translate'),
  description: new TranslatableMarkup('Translate the selected text into other languages.'),
)]
final class Translate extends AiCKEditorPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'autocreate' => FALSE,
      'provider' => NULL,
      'translate_vocabulary' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    if (empty($vocabularies)) {
      return [
        '#markup' => 'You must add at least one taxonomy vocabulary before you can configure this plugin.',
      ];
    }

    $vocabulary_options = [];

    foreach ($vocabularies as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['translate_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose default vocabulary for translation options'),
      '#options' => $vocabulary_options,
      '#description' => $this->t('Select the vocabulary that contains translation options.'),
      '#default_value' => $this->configuration['translate_vocabulary'],
    ];

    $form['autocreate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow autocreate'),
      '#description' => $this->t('If enabled, users with access to this format are able to autocreate new terms in the chosen vocabulary.'),
      '#default_value' => $this->configuration['autocreate'] ?? FALSE,
    ];

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
    $this->configuration['autocreate'] = (bool) $form_state->getValue('autocreate');
    $this->configuration['translate_vocabulary'] = $form_state->getValue('translate_vocabulary');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();

    if (empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('You must select some text before you can translate it.') . '</p>',
      ];
    }

    $form['language'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Choose language'),
      '#tags' => FALSE,
      '#description' => $this->t('Selecting one of the options will translate the selected text.'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => [$this->configuration['translate_vocabulary']],
      ],
    ];

    if ($this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['translate_vocabulary'])) {
      $form['language']['#autocreate'] = [
        'bundle' => $this->configuration['translate_vocabulary'],
      ];
    }

    $form['selected_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Selected text to translate'),
      '#disabled' => TRUE,
      '#default_value' => $storage['selected_text'],
    ];

    $form['response_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Suggested translation'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the editor.'),
      '#prefix' => '<div id="ai-ckeditor-translate-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['generate'] = [
      '#type' => 'button',
      '#value' => $this->t('Translate'),
      '#ajax' => [
        'callback' => [$this, 'ajaxGenerateText'],
        'wrapper' => 'ai-ckeditor-translate-response',
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
        ]
      ]
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
        'value' => strip_tags($values["config"]["plugin_config"]["response_text"]),
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
   */
  public function ajaxGenerateText(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      if (is_array($values['config']['plugin_config']['language']) && reset($values['config']['plugin_config']['language']) instanceof Term) {
        $term = reset($values['config']['plugin_config']['language']);
      } else {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($values['config']['plugin_config']['language']);
      }

      if (empty($term)) {
        throw new \Exception('Term could not be loaded.');
      }

      // @todo: Do we need a vocab perm check on this user too?
      if ($term->isNew() && $this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['translate_vocabulary'])) {
        $term->save();
      }

      $prompt = 'Translate the selected text into ' . $term->label() . ':\r\n"' . $values["config"]["plugin_config"]["selected_text"];
      $text = $this->getResponse($prompt);
      $form_state->setRebuild();
      $form['config']['plugin_config']['response_text']['#value'] = $text;
    } catch (\Exception $e) {
      $this->logger->error("There was an error in the Translate AI plugin for CKEditor.");
      $form['config']['plugin_config']['response_text']['#value'] = "There was an error in the Translate AI plugin for CKEditor.";
    }

    return $form['config']['plugin_config']['response_text'];
  }

}
