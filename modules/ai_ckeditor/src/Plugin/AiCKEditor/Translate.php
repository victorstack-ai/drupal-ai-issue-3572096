<?php

namespace Drupal\ai_ckeditor\Plugin\AICKEditor;

use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin to translate the language of selected text.
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
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    if (empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('You must select some text before you can translate it.') . '</p>',
      ];
    }

    $form = parent::buildCkEditorModalForm($form, $form_state);

    $form['language'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Choose language'),
      '#tags' => FALSE,
      '#required' => TRUE,
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
      '#type' => 'text_format',
      '#title' => $this->t('Suggested translation'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
    ];

    $form['actions']['generate']['#value'] = $this->t('Translate');

    return $form;
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
   *   The result of the AJAX operation.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      if (is_array($values['plugin_config']['language']) && reset($values['plugin_config']['language']) instanceof Term) {
        $term = reset($values['plugin_config']['language']);
      }
      else {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($values['plugin_config']['language']);
      }

      if (empty($term)) {
        throw new \Exception('Term could not be loaded.');
      }

      if ($term->isNew() && $this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['translate_vocabulary'])) {
        $term->save();
      }

      $prompt = 'Translate the selected text into ' . $term->label() . ':\r\n"' . $values["plugin_config"]["selected_text"];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error("There was an error in the Translate AI plugin for CKEditor.");
      $form['plugin_config']['response_text']['#value'] = "There was an error in the Translate AI plugin for CKEditor.";
    }

    return $form['plugin_config']['response_text'];
  }

}
