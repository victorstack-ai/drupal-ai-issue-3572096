<?php

namespace Drupal\provider_openai\Plugin\LlmProvider;

use Drupal\ai\Attribute\LlmProvider;
use Drupal\ai\Base\LlmProviderClientBase;
use Drupal\ai\Enum\Bundles;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
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
class OpenAiProvider extends LlmProviderClientBase {

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
  public function getConfiguredLlms(Bundles $bundle = NULL): array {
    // Load all models, and since OpenAI does not provide information about
    // which models does what, we need to hard code it in a helper function.
    $this->loadClient();
    return $this->getModels($bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(Bundles $bundle = NULL): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that OpenAI supports its usable.
    return in_array($bundle, $this->getSupportedBundles());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedBundles(): array {
    return [
      Bundles::Chat,
      Bundles::TextToImage,
      Bundles::ImageToText,
      Bundles::TextToSpeech,
      Bundles::SpeechToText,
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
   * {@inheritdoc}
   */
  protected function generateResponse(Bundles $bundle, string $model_id, mixed $input, bool $normalise_io = TRUE): mixed {
    $this->loadClient();
    switch ($bundle) {
      // Text to image is the same thing as chat, just fewer models.
      case Bundles::Chat:
      case Bundles::ImageToText:
        return $this->chat($model_id, $input, $normalise_io);

      case Bundles::TextToImage:
        return $this->textToImage($model_id, $input, $normalise_io);

      case Bundles::TextToSpeech:
        return $this->textToSpeech($model_id, $input, $normalise_io);

      case Bundles::SpeechToText:
        return $this->speechToText($model_id, $input, $normalise_io);
    }
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
      $this->client = OpenAi::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient(\Drupal::httpClient())
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
   * Chat message.
   *
   * @param string $model_id
   *   The model ID.
   * @param mixed $input
   *   The input.
   * @param bool $normalise_io
   *   Should the output be normalised.
   *
   * @return mixed
   *   The response.
   */
  protected function chat(string $model_id, mixed $input, bool $normalise_io = TRUE): mixed {
    $payload = [
      'model' => $model_id,
      'messages' => $input,
    ] + $this->configuration;
    $response = $this->client->chat()->create($payload)->toArray();
    if ($normalise_io) {
      return $response['choices'][0]['message']['content'] ? trim($response['choices'][0]['message']['content']) : 'No response content found.';
    }
    return $response;
  }

  /**
   * Image to text.
   *
   * @param string $model_id
   *   The model ID.
   * @param mixed $input
   *   The input.
   * @param bool $normalise_io
   *   Should the output be normalised.
   *
   * @return mixed
   *   The response.
   */
  protected function textToImage(string $model_id, mixed $input, bool $normalise_io = TRUE): mixed {
    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;
    $response = $this->client->images()->create($payload)->toArray();
    if ($normalise_io) {
      // Base64 encoded image.
      $images = [];
      if ($this->configuration['response_format'] === 'url') {
        if (empty($response['data'][0])) {
          return 'No response content found.';
        }
        foreach ($response['data'] as $data) {
          if ($this->configuration['response_format'] === 'url') {
            $images[] = base64_encode(file_get_contents($data['url']));
          }
          else {
            $images[] = $data['b64_json'];
          }
        }
      }
      return $images;
    }
    return $response;
  }

  /**
   * Text to speech.
   *
   * @param string $model_id
   *   The model ID.
   * @param mixed $input
   *   The input.
   * @param bool $normalise_io
   *   Should the output be normalised.
   *
   * @return mixed
   *   The response.
   */
  protected function textToSpeech(string $model_id, mixed $input, bool $normalise_io = TRUE): mixed {
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    $response = $this->client->audio()->speech($payload);
    if ($normalise_io) {
      return [$response];
    }
    return $response;
  }

  /**
   * Speech to text.
   *
   * @param string $model_id
   *   The model ID.
   * @param mixed $input
   *   The input.
   * @param bool $normalise_io
   *   Should the output be normalised.
   *
   * @return mixed
   *   The response.
   */
  protected function speechToText(string $model_id, mixed $input, bool $normalise_io = TRUE): mixed {
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileSystemInterface::EXISTS_REPLACE);
    $input = fopen($path, 'r');
    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;
    $response = $this->client->audio()->transcribe($payload)->toArray();

    // Remove the file.
    $this->fileSystem->delete($path);
    if ($normalise_io) {
      if (!empty($this->configuration['response_format'])) {
        switch ($this->configuration['response_format']) {
          case 'text':
            return $response['text'];

          case 'json':
            return $response['text'];
        }
      }
    }
    return $response;
  }

  /**
   * Obtains a list of models from OpenAI and caches the result.
   *
   * This method does its best job to filter out deprecated or unused models.
   * The OpenAI API endpoint does not have a way to filter those out yet.
   *
   * @param \Drupal\ai\Enum\Bundles $bundle
   *   The bundle to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(Bundles $bundle): array {
    $models = [];

    $cache_data = $this->cacheBackend->get('openai_models_' . $bundle->value, $models);

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
      switch ($bundle) {
        case Bundles::Chat:
          if (!preg_match('/^(gpt|text)/i', $model['id'])) {
            continue 2;
          }
          break;

        case Bundles::ImageToText:
          if (!preg_match('/^(gpt-4o|gpt-4-turbo|vision)/i', $model['id'])) {
            continue 2;
          }
          break;

        case Bundles::TextToImage:
          if (!preg_match('/^(dall-e|clip)/i', $model['id'])) {
            continue 2;
          }
          break;

        case Bundles::SpeechToText:
          if (!preg_match('/^(whisper)/i', $model['id'])) {
            continue 2;
          }
          break;

        case Bundles::TextToSpeech:
          if (!preg_match('/^(tts)/i', $model['id'])) {
            continue 2;
          }
          break;
      }

      $models[$model['id']] = $model['id'];
    }

    if (!empty($models)) {
      asort($models);
      $this->cacheBackend->set('openai_models_' . $bundle->value, $models);
    }

    return $models;
  }

}
