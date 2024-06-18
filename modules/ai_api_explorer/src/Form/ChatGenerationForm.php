<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\LlmProviderFormHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for answers.
 */
class ChatGenerationForm extends FormBase {

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
    return 'ai_api_chat_generation';
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
    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['markup'] = [
      '#markup' => '<div class="ai-left-side">',
    ];

    $form['prompts'] = [
      '#type' => 'details',
      '#title' => $this->t('Chat Messages'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>Please note: This is not a chat, its an explorer of the chat endpoint to build chat logic!</strong> <br />Enter your chat messages here, each message has to have a role and a message. Role will no always be used by all providers/models.'),
    ];

    // Loop five times to generate five roles and messages.
    for ($i = 0; $i < 2; $i++) {
      $form['prompts']['role_' . $i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Role'),
        '#attributes' => [
          'placeholder' => $this->t('user, system, assistant, etc.'),
        ],
        '#default_value' => $i ? 'user' : 'system',
        '#required' => !$i,
      ];
      $form['prompts']['message_' . $i] = [
        '#type' => 'textarea',
        '#title' => $this->t('Message'),
        '#required' => !$i,
        '#default_value' => !$i ? $this->t('You are an helpful assistant') : '',
      ];
    }

    // Load the LLM configurations.
    $this->llmProviderHelper->generateLlmProvidersForm($form, $form_state, 'chat', 'chat', LlmProviderFormHelper::FORM_CONFIGURATION_FULL);

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
      '#suffix' => '</div>',
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response from the Provider'),
      '#attributes' => [
        'readonly' => 'readonly',
      ],
      '#weight' => 1000,
      '#prefix' => '<div id="ai-prompt-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#description' => $this->t('The response from the provider will appear in the textbox above.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->llmProviderHelper->generateLlmProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
    $values = $form_state->getValues();
    // Get the messages.
    $messages = [];
    foreach ($values as $key => $value) {
      if (strpos($key, 'role_') === 0) {
        $index = substr($key, 5);
        $role = $value;
        $message = $values['message_' . $index];
        $messages[] = new ChatMessage($role, $message);
      }
    }
    $input = new ChatInput($messages);

    $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), ['chat_generation'])->getNormalized();

    if (get_class($response) == ChatMessage::class) {
      $form['response']['#value'] = 'Role: ' . $response->getRole() . "\n" . $response->getMessage();
    }
    else {
      $form['response']['#value'] = 'Error: Invalid response from the provider.';
    }
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
