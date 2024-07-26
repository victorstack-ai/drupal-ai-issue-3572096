<?php

namespace Drupal\ai_ckeditor;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin for AiCKEditorPlugin plugins.
 */
abstract class AiCKEditorPluginBase extends PluginBase implements AiCKEditorPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiProviderPluginManager $ai_provider_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $account, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->aiProviderManager = $ai_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->logger = $logger_factory->get('ai_ckeditor');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
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
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Retrieves a response from the AI provider.
   *
   * @param string $prompt
   *   The prompt submitted from the plugin.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The response from AI.
   */
  protected function getResponse(string $prompt) {
    $ai_provider = $this->aiProviderManager->loadProviderFromSimpleOption($this->configuration['provider']);
    $ai_model = $this->aiProviderManager->getModelNameFromSimpleOption($this->configuration['provider']);
    $messages = new ChatInput([
      new ChatMessage('system', 'You are helpful website assistant for content writing and editing. Please avoid responses in the first, second or third person form.'),
      new ChatMessage('user', $prompt),
    ]);
    $message = $ai_provider->chat($messages, $ai_model)->getNormalized();
    return trim($message->getText()) ?? $this->t('No result could be generated.');
  }

}
