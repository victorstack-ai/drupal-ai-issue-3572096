<?php

namespace Drupal\ai_assistant_api\Base;

use Drupal\ai_assistant_api\AiAssistantActionInterface;
use Drupal\ai_assistant_api\AiAssistantInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AI assistant actions.
 */
abstract class AiAssistantActionBase implements AiAssistantActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The assistant.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantInterface
   */
  protected AiAssistantInterface $assistant;

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   */
  protected $aiProvider;

  /**
   * The Temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * Configuration.
   *
   * @var array
   */
  protected array $configuration = [];

  /**
   * The threads id.
   *
   * @var string
   */
  protected string $thread_id;

  /**
   * The messages thread.
   *
   * @var array
   */
  protected array $messages = [];

  /**
   * Constructor.
   */
  public function __construct(array $configuration, PrivateTempStoreFactory $tempStoreFactory) {
    $this->configuration = $configuration;
    $this->tempStoreFactory = $tempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration($configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessages(array $messages): void {
    $this->messages = $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function setAiProvider($provider): void {
    $this->aiProvider = $provider;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssistant(AiAssistantInterface $assistant): void {
    $this->assistant = $assistant;
  }

  /**
   * {@inheritdoc}
   */
  public function setThreadId(string $thread_id): void {
    $this->thread_id = $thread_id;
  }

  /**
   * Get the private tempstore for AI Assistant.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   */
  public function getTempStore() {
    return $this->tempStoreFactory->get('ai_assistant_api');
  }

  /**
   * Get action context from history.
   *
   * @param string $key
   *   The key to get the context from.
   *
   * @return array
   *   The data.
   */
  public function getActionContext(string $key): array {
    if ($this->assistant->get('allow_history') == 'session') {
      $session = $this->getTempStore()->get($this->thread_id);
      return $session['contexts'][$key] ?? [];
    }
    return [];
  }

  /**
   * Store action context for history.
   *
   * @param string $key
   *   The key to set the context to.
   * @param array $data
   *   The data.
   */
  public function storeActionContext(string $key, array $data) {
    $session = $this->getTempStore()->get($this->thread_id);
    $session['contexts'][$key][] = $data;
    $this->getTempStore()->set($this->thread_id, $session);
  }

  /**
   * Sets output context.
   *
   * @param string $key
   *   The key to set the context to.
   * @param string $context
   *   The context.
   */
  public function setOutputContext(string $key, string $context) {
    $session = $this->getTempStore()->get($this->thread_id);
    if (!isset($session['output_contexts'][$key]) || !is_array($session['output_contexts'][$key])) {
      $session['output_contexts'][$key] = [];
    }
    $session['output_contexts'][$key][] = $context;

    $this->getTempStore()->set($this->thread_id, $session);
  }

}
