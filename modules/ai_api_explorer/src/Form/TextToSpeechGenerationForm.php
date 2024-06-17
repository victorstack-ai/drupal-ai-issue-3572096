<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\ai\Service\LlmProviderFormHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for audios.
 */
class TextToSpeechGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\LlmProviderHelper
   */
  protected $llmProviderHelper;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_explorer_text_to_speech_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->llmProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('tts_llm_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('tts_ai_model', $request->query->get('model_id'));
    }
    $input = json_decode($request->query->get('input', '[]'));

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['prompt'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->llmProviderHelper->generateLlmProvidersForm($form, $form_state, 'text_to_speech', 'tts_', LlmProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Audio Response'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-audio-response',
      ],
      '#suffix' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-audio-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ audios|raw }}',
      '#weight' => 101,
      '#context' => [
        'audios' => '<h2>Audio will appear here.</h2>',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->llmProviderHelper->generateLlmProviderFromFormSubmit($form, $form_state, 'text_to_speech', 'tts_');
    $audio = $provider->textToSpeech($form_state->getValue('prompt'), $form_state->getValue('tts_ai_model'), ['ai_api_explorer'])->getNormalized();
    $response = '';
    // Save the binary data to a file.
    $file_url = $this->fileSystem->saveData($audio, 'public://text-to-speech-test.mp3', FileSystemInterface::EXISTS_REPLACE);
    $response .= '<audio controls><source src="' . $this->fileUrlGenerator->generateAbsoluteString($file_url) . '" type="audio/mpeg"></audio>';

    // Generation code.
    $code = "<details style=\"background: #ccc; padding: 5px;\"><summary>Code Example</summary><code style=\"display: block; white-space: pre-wrap; padding: 20px;\">";
    $code .= '$prompt = "' . $form_state->getValue('prompt') . '";<br>';
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->getInstance('" . $form_state->getValue('tts_llm_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// \$response will be a string with the audio binary.<br>";
    $code .= "\$response = \$ai_provider->textToSpeech(\$prompt, '" . $form_state->getValue('tts_ai_model') . '\', ["your_module_name"])->getNormalized();';
    $code .= "</code></details>";

    $form['response']['#context'] = [
      'audios' => '<h2>Audio will appear here.</h2>' . $response . $code,
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
