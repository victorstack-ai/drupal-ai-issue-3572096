<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for moderation endpoints.
 */
class ModerationGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_moderation_generation';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['prompt'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'moderation', 'moderation', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

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
        'texts' => '<h2>Moderation response will appear here.</h2>',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'moderation', 'moderation');

    $response = $provider->moderation($form_state->getValue('prompt'), $form_state->getValue('moderation_ai_model'), ['moderation_generation'])->getNormalized();

    // Generation code for normalization.
    $code = $this->normalizeCodeExample($provider, $form_state, $form_state->getValue('prompt'));

    if (get_class($response) == ModerationResponse::class) {
      $flagged = $response->isFlagged() ? 'Yes' : 'No';
      $form['response']['#context']['texts'] = '<h4>Got Flagged: ' . $flagged . "</h4><p>Information dump:<pre>" . print_r($response->getInformation(), TRUE) . '</pre></p>' . $code;
    }
    else {
      $form['response']['#context']['texts'] = '<p>' . $this->t('Error: Invalid response from the provider.') . '</p>';
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
   * @param string $prompt
   *   The prompt.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt): string {
    $code = "<details style=\"background: #ccc; padding: 5px;\"><summary>Normalized Code Example</summary><code class=\"ai-code\">";
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->getInstance('" . $form_state->getValue('moderation_ai_provider') . '\');<br>';
    $code .= "// Normalized \$response will be a ModerationResponse object.<br>";
    $code .= "\$prompt = '" . $prompt . "';<br>";
    $code .= "\$response = \$ai_provider->moderation(\$input, '" . $form_state->getValue('moderation_ai_model') . '\', ["your_module_name"])->getNormalized();';
    $code .= "</code></details>";
    return $code;
  }

}
