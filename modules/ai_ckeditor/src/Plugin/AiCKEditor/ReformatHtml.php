<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Plugin to reformat the selected HTML.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_reformat_html',
  label: new TranslatableMarkup('Reformat HTML'),
  description: new TranslatableMarkup('Reformat the HTML of the selected markup.'),
  module_dependencies: [],
)]
final class ReformatHtml extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
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
    $prompt_reformat = $prompts_config->get('prompts.reformat');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reformat prompt'),
      '#default_value' => $prompt_reformat,
      '#description' => $this->t('This prompt will be used to reformat the html.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_reformat_html][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.reformat', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getGenerateButtonLabel(): TranslatableMarkup {
    return $this->t('Reformat');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel(): TranslatableMarkup {
    return $this->t('Selected text/markup to reformat');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel(): TranslatableMarkup {
    return $this->t('Suggested markup');
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state): ?AjaxResponse {
    $values = $form_state->getValues();

    try {
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $prompt = $prompts_config->get('prompts.reformat');
      $prompt = $prompt . '\r\n"' . $values["plugin_config"]["selected_text"];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      assert(is_array($this->pluginDefinition));
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Reformat HTML plugin for CKEditor.");
      $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Reformat HTML plugin for CKEditor.';
    }
    return NULL;
  }

}
