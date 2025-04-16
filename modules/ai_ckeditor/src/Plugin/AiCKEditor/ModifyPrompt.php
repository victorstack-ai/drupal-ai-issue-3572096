<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Plugin to modify text with custom instructions.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_modify_prompt',
  label: new TranslatableMarkup('Modify with a prompt'),
  description: new TranslatableMarkup('Apply custom instructions to the selected text.'),
)]
final class ModifyPrompt extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    array_shift($options);
    array_splice($options, 0, 1);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_template = $prompts_config->get('prompts.modify_prompt');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt template'),
      '#default_value' => $prompt_template ?? '',
      '#description' => $this->t('This template will be used for the "Modify with a prompt" feature. The {{ modify_prompt }} placeholder will be replaced with the user-provided instructions.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_modify_prompt][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.modify_prompt', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array {
    $storage = $form_state->getStorage();
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    if (empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('You must select some text before you can modify it with a prompt.') . '</p>',
      ];
    }

    $form = parent::buildCkEditorModalForm($form, $form_state);

    $form['modify_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your instructions'),
      '#description' => $this->t('Describe how you want the AI to modify the selected text.'),
      '#required' => TRUE,
      '#rows' => 4,
    ];

    $form['selected_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Selected text to convert'),
      '#disabled' => TRUE,
      '#default_value' => $storage['selected_text'],
    ];

    $form['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Result'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
    ];

    $form['actions']['generate']['#value'] = $this->t('Modify text');

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
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $prompt_template = $prompts_config->get('prompts.modify_prompt');

      // Replace the placeholder with the user-provided instructions.
      $prompt = str_replace('{{ modify_prompt }}', $values['plugin_config']['modify_prompt'], $prompt_template);

      // Add the selected text.
      $prompt .= "\n" . $values['plugin_config']['selected_text'];

      $response = new AjaxResponse();
      $response->addCommand(new AiRequestCommand($prompt, $values['editor_id'], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error("There was an error in the 'Modify with a prompt' AI plugin for CKEditor: @error", ['@error' => $e->getMessage()]);
      return $form['plugin_config']['response_text']['#value'] = "There was an error processing your request. Please try again.";
    }
  }

}
