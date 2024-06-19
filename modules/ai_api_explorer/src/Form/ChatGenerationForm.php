<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
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
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The Explorer Helper.
   *
   * @var \Drupal\ai_api_explorer\ExplorerHelper
   */
  protected $explorerHelper;

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
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->explorerHelper = $container->get('ai_api_explorer.helper');
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
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'chat', 'chat', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-text-response',
      ],
      '#suffix' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-text-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ texts|raw }}',
      '#weight' => 1000,
      '#context' => [
        'texts' => '<h2>Chat response will appear here.</h2>',
      ],
    ];

    $form['markup_end'] = [
      '#markup' => '<div class="ai-break"></div>',
      '#weight' => 1001,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
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

    $response = NULL;
    try {
      $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), ['chat_generation'])->getNormalized();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    // Generation code for normalization.
    $code = $this->normalizeCodeExample($provider, $form_state, $messages);
    $code .= $this->rawCodeExample($provider, $form_state, $messages);

    if (is_object($response) && get_class($response) == ChatMessage::class) {
      $form['response']['#context']['texts'] = '<h4>Role: ' . $response->getRole() . "</h4><p>" . $response->getMessage() . '</p>' . $code;
    }
    else {
      $form['response']['#context']['texts'] = '<p>' . $response . '</p>';
    }
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): string {
    $code = "<details style=\"background: #ccc; padding: 5px;\"><summary>Normalized Code Example</summary><code class=\"ai-code\">";
    $code .= '// Use this when you want to be able to swap the provider. <br>';
    $code .= '$config = [<br>';
    foreach ($provider->getConfiguration() as $key => $value) {
      if (is_string($value)) {
        $code .= '&nbsp;&nbsp;"' . $key . '" => "' . $value . '";<br>';
      }
      else {
        $code .= '&nbsp;&nbsp;"' . $key . '" => ' . $value . ';<br>';
      }
    }
    $code .= ']<br><br>';

    $code .= '$input = new \Drupal\ai\OperationType\Chat\ChatInput([<br>';
    foreach ($messages as $message) {
      $code .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getMessage() . '"),<br>';
    }
    $code .= ']);<br><br>';

    $code .= "\$ai_provider = \Drupal::service('ai.provider')->getInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalized \$response will be a ChatMessage object.<br>";
    $code .= "\$response = \$ai_provider->chat(\$input, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getNormalized();';
    $code .= "</code></details>";
    return $code;
  }

  /**
   * Gets the raw code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return string
   *   The normalized code example.
   */
  public function rawCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): string {
    $code = "<br><details style=\"background: #ccc; padding: 5px;\"><summary>Raw Code Example</summary><code class=\"ai-code\">";
    $code .= '// Another way if you know you always will use ' . $provider->getPluginDefinition()['label'] . ' and want its way of doing stuff. Not recommended. <br>';
    $code .= '$config = [<br>';
    foreach ($provider->getConfiguration() as $key => $value) {
      if (is_string($value)) {
        $code .= '&nbsp;&nbsp;"' . $key . '" => "' . $value . '";<br>';
      }
      else {
        $code .= '&nbsp;&nbsp;"' . $key . '" => ' . $value . ';<br>';
      }
    }
    $code .= ']<br><br>';
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->getInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalized \$response will be what ever the provider gives back.<br>";
    $code .= "\$response = \$ai_provider->chat(\$expectedInputFromProviderClient, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getRaw();';
    $code .= "</code></details>";
    return $code;
  }

}
