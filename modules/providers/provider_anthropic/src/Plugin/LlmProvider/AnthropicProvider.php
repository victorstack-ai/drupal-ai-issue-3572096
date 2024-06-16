<?php

namespace Drupal\provider_anthropic\Plugin\LlmProvider;

use Drupal\ai\Attribute\LlmProvider;
use Drupal\ai\Base\LlmProviderClientBase;
use Drupal\ai\Enum\Bundles;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Yaml;
use WpAi\Anthropic\AnthropicAPI;

/**
 * Plugin implementation of the 'anthropic' provider.
 */
#[LlmProvider(
  id: 'anthropic',
  label: new TranslatableMarkup('Anthropic'),
)]
class AnthropicProvider extends LlmProviderClientBase {

  /**
   * The Anthropic Client.
   *
   * @var \WpAi\Anthropic\AnthropicAPI|null
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
    // Anthropic hard codes :/.
    $version = $this->getConfig()->get('version');
    if ($bundle == Bundles::Chat) {
      return [
        'claude-3-opus-' . $version => 'Claude 3 Opus',
        'claude-3-sonnet-' . $version => 'Claude 3 Sonnet',
        'claude-3-haiku-' . $version => 'Claude 3 Haiku',
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(Bundles $bundle = NULL): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Anthropic supports its usable.
    return in_array($bundle, $this->getSupportedBundles());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedBundles(): array {
    return [
      Bundles::Chat,
      Bundles::ImageToText,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('provider_anthropic.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('provider_anthropic')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id): array {
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
  protected function generateResponse(Bundles $bundle, string $model_id, mixed $input, bool $normalize_io = TRUE): mixed {
    $this->loadClient();
    switch ($bundle) {
      // Text to image is the same thing as chat, just fewer models.
      case Bundles::Chat:
      case Bundles::ImageToText:
        return $this->chat($model_id, $input, $normalize_io);
    }
    return NULL;
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
   * @return \WpAi\Anthropic\AnthropicAPI
   *   The Anthropic client.
   */
  public function getClient(string $api_key = ''): AnthropicAPI {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Anthropic Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $this->client = new AnthropicAPI($this->apiKey);
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
   * @param bool $normalize_io
   *   Should the output be normalized.
   *
   * @return mixed
   *   The response.
   */
  protected function chat(string $model_id, mixed $input, bool $normalize_io = TRUE): mixed {
    $payload = [
      'model' => $model_id,
      'messages' => $input,
    ] + $this->configuration;
    // Unset Max Tokens.
    $max_tokens = $payload['max_tokens'];
    unset($payload['max_tokens']);
    $response = $this->client->messages()->maxTokens($max_tokens)->create($payload)->content;

    if ($normalize_io) {
      return $response[0]['text'] ? trim($response[0]['text']) : 'No response content found.';
    }
    return $response;
  }

}
