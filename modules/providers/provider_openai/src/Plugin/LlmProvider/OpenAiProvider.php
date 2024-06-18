<?php

namespace Drupal\provider_openai\Plugin\LlmProvider;

use Drupal\ai\Attribute\LlmProvider;
use Drupal\ai\Base\LlmProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInterface;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use OpenAI\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'openai' provider.
 */
#[LlmProvider(
  id: 'openai',
  label: new TranslatableMarkup('OpenAI'),
)]
class OpenAiProvider extends LlmProviderClientBase implements
  ContainerFactoryPluginInterface,
  ChatInterface,
  TextToSpeechInterface,
  SpeechToTextInterface,
  TextToImageInterface {

  /**
   * The OpenAI Client.
   *
   * @var \OpenAI\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredLlms(string $operation_type = NULL): array {
    // Load all models, and since OpenAI does not provide information about
    // which models does what, we need to hard code it in a helper function.
    $this->loadClient();
    return $this->getModels($operation_type);
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(string $operation_type = NULL): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that OpenAI supports its usable.
    return in_array($operation_type, $this->getSupportedBundles());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedBundles(): array {
    return [
      'chat',
      'text_to_image',
      'text_to_speech',
      'speech_to_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('provider_openai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('provider_openai')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id): array {
    // If its GPT 3.5 the max tokens are 2048.
    if (preg_match('/gpt-3.5/', $model_id)) {
      return [
        'max_tokens' => [
          'default' => 2048,
        ],
      ];
    }
    if ($model_id == 'dall-e-3') {
      return [
        'n' => [
          'default' => 1,
          'constraints' => [
            'min' => 1,
            'max' => 1,
          ],
        ],
        'quality' => [
          'label' => 'Quality',
          'description' => 'The quality of the images that will be generated.',
          'type' => 'string',
          'default' => 'standard',
          'required' => FALSE,
          'constraints' => [
            'options' => [
              'hd',
              'standard',
            ],
          ],
        ],
        'size' => [
          'default' => '1792x1024',
          'constraints' => [
            'options' => [
              '1024x1024',
              '1024x1792',
              '1792x1024',
            ],
          ],
        ],
        'style' => [
          'label' => 'Style',
          'description' => 'The style of the images that will be generated.',
          'type' => 'string',
          'default' => 'vivid',
          'required' => FALSE,
          'constraints' => [
            'options' => [
              'vivid',
              'neutral',
            ],
          ],
        ],
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \OpenAI\Client
   *   The OpenAI client.
   */
  public function getClient(string $api_key = ''): Client {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the OpenAI Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $this->client = \OpenAI::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient($this->httpClient)
        ->make();
    }
  }

  /**
   * Load API key from key module.
   *
   * @return string
   *   The API key.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))->getKeyValue();
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      foreach ($input->getMessages() as $message) {
        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $message->getMessage(),
        ];
      }
    }
    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    $response = $this->client->chat()->create($payload)->toArray();

    $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content']);
    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;
    $response = $this->client->images()->create($payload)->toArray();

    $images = [];
    if ($this->configuration['response_format'] === 'url') {
      if (empty($response['data'][0])) {
        throw new AiResponseErrorException('No image data found in the response.');
      }
      foreach ($response['data'] as $data) {
        if ($this->configuration['response_format'] === 'url') {
          $images[] = file_get_contents($data['url']);
        }
        else {
          $images[] = base64_decode($data['b64_json']);
        }
      }
    }
    return new TextToImageOutput($images, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }
    // Send the resuest.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    $response = $this->client->audio()->speech($payload);
    // Return a normalized response.
    return new TextToSpeechOutput([$response], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $input = fopen($path, 'r');
    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;
    $response = $this->client->audio()->transcribe($payload)->toArray();

    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * Obtains a list of models from OpenAI and caches the result.
   *
   * This method does its best job to filter out deprecated or unused models.
   * The OpenAI API endpoint does not have a way to filter those out yet.
   *
   * @param string $operation_type
   *   The bundle to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type): array {
    $models = [];

    $cache_data = $this->cacheBackend->get('openai_models_' . $operation_type, $models);

    if (!empty($cache_data)) {
      return $cache_data->data;
    }

    $list = $this->client->models()->list()->toArray();

    foreach ($list['data'] as $model) {
      if ($model['owned_by'] === 'openai-dev') {
        continue;
      }

      if (!preg_match('/^(gpt|text|tts|whisper|dall-e)/i', $model['id'])) {
        continue;
      }

      // Skip unused. hidden, or deprecated models.
      if (preg_match('/(search|similarity|edit|1p|instruct|embed)/i', $model['id'])) {
        continue;
      }

      if (in_array($model['id'], ['tts-1-hd-1106', 'tts-1-1106'])) {
        continue;
      }

      // Bundle specific logic.
      switch ($operation_type) {
        case 'chat':
          if (!preg_match('/^(gpt|text)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'image_to_text':
          if (!preg_match('/^(gpt-4o|gpt-4-turbo|vision)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_image':
          if (!preg_match('/^(dall-e|clip)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'speech_to_text':
          if (!preg_match('/^(whisper)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_speech':
          if (!preg_match('/^(tts)/i', $model['id'])) {
            continue 2;
          }
          break;
      }

      $models[$model['id']] = $model['id'];
    }

    if (!empty($models)) {
      asort($models);
      $this->cacheBackend->set('openai_models_' . $operation_type, $models);
    }

    return $models;
  }

}
