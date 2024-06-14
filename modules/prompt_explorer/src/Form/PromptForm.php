<?php

declare(strict_types=1);

namespace Drupal\prompt_explorer\Form;

use Drupal\ai\Enum\Bundles;
use Drupal\ai\Service\LlmProviderFormHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for answers.
 */
class PromptForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\LlmProviderHelper
   */
  protected $llmProviderHelper;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'prompt_explorer_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->llmProviderHelper = $container->get('ai.form_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response from the Provider'),
      '#attributes' => [
        'readonly' => 'readonly',
      ],
      '#prefix' => '<div id="ai-prompt-response">',
      '#suffix' => '</div>',
      '#description' => $this->t('The response from the provider will appear in the textbox above.')
    ];

    // Load the LLM configurations.
    $this->llmProviderHelper->generateLlmProvidersForm($form, $form_state, Bundles::Chat, 'prompt_explorer', LlmProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-prompt-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->llmProviderHelper->generateLlmProviderFromFormSubmit($form, $form_state, Bundles::Chat, 'prompt_explorer');
    $messages = [
      [
        'role' => 'user',
        'content' => $form_state->getValue('prompt'),
      ]
    ];
    $tags = [
      'prompt_explorer',
      'prompt_explorer_chat',
    ];
    $response = $provider->invokeModelResponse(Bundles::Chat, $form_state->getValue('prompt_explorer_ai_model'), $messages, $tags, TRUE);

    $form['response']['#value'] = trim($response) ?? $this->t('No answer was provided.');
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
