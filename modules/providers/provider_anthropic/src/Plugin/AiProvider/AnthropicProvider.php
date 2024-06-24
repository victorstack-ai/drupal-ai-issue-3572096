<?php

namespace Drupal\provider_anthropic\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Yaml;
use WpAi\Anthropic\AnthropicAPI;

/**
 * Plugin implementation of the 'anthropic' provider.
 */
#[AiProvider(
  id: 'anthropic',
  label: new TranslatableMarkup('Anthropic'),
)]
class AnthropicProvider extends AiProviderClientBase implements
  ChatInterface {

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
  public function getConfiguredModels(string $operation_type = NULL): array {
    // Anthropic hard codes :/.
    $version = $this->getConfig()->get('version');
    if ($operation_type == 'chat') {
      return [
        'claude-3-5-sonnet-' . $version => 'Claude 3.5 Sonnet',
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
  public function isUsable(string $operation_type = NULL): bool {
    // If its not configured, it is not usable.
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Anthropic supports its usable.
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
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
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    $system_prompt = '';
    if ($input instanceof ChatInput) {
      $chat_input = [];
      foreach ($input->getMessages() as $message) {
        // System prompts are a variable.
        if ($message->getRole() == 'system') {
          $system_prompt = $message->getText();
          continue;
        }
        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $message->getText(),
        ];
      }
    }
    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    if (!isset($payload['system']) && $system_prompt) {
      $payload['system'] = $system_prompt;
    }
    // Unset Max Tokens.
    $max_tokens = $payload['max_tokens'];
    unset($payload['max_tokens']);
    $response = $this->client->messages()->maxTokens($max_tokens)->create($payload)->content;
    if (!isset($response[0]['text'])) {
      throw new AiResponseErrorException('Invalid response from Anthropic');
    }
    $message = new ChatMessage('', $response[0]['text']);

    return new ChatOutput($message, $response, []);
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

}
