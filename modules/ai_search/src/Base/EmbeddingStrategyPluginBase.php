<?php

namespace Drupal\ai_search\Base;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Utility\TextChunker;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class to provide an embedding strategy to break content into chunks.
 */
abstract class EmbeddingStrategyPluginBase implements EmbeddingStrategyInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Vector Database Provider ID.
   *
   * @var string
   */
  protected string $providerId;

  /**
   * The Model ID.
   *
   * @var string
   */
  protected string $modelId;

  /**
   * The chunk size.
   *
   * @var int
   */
  protected int $chunkSize;

  /**
   * The chunk minimum overlap.
   *
   * @var int
   */
  protected int $chunkMinOverlap;

  /**
   * The EmbeddingInterface proxied via ProviderProxy.
   *
   * @var \Drupal\ai\Plugin\ProviderProxy
   */
  protected ProviderProxy $embeddingLlm;

  /**
   * Constructs a new Embedding Strategy abstract class.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   The AI provider plugin manager.
   * @param \League\HTMLToMarkdown\HtmlConverter $converter
   *   The html to markdown converter.
   * @param \Drupal\ai\Utility\TextChunker $textChunker
   *   The text chunker.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionList
   *   The module extension list.
   */
  final public function __construct(
    protected string $pluginId,
    protected mixed $pluginDefinition,
    protected AiProviderPluginManager $aiProviderManager,
    protected HtmlConverter $converter,
    protected TextChunker $textChunker,
    protected EntityTypeManager $entityTypeManager,
    protected ModuleExtensionList $extensionList,
  ) {
    // Set the default converter settings.
    $this->converter->getConfig()->setOption('strip_tags', TRUE);
    $this->converter->getConfig()->setOption('strip_placeholder_links', TRUE);
    $this->converter->getEnvironment()->addConverter(new TableConverter());
  }

  /**
   * Initialise the settings for the embedding strategy.
   *
   * @param string $embedding_engine
   *   The embedding engine.
   * @param string $chat_model
   *   The chat model ID for token calculations.
   * @param array $configuration
   *   The embedding strategy configuration.
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    [$this->providerId, $this->modelId] = explode('__', $embedding_engine);
    $chat_model_id = $this->aiProviderManager->getModelNameFromSimpleOption($chat_model);
    $chat_model_id = $chat_model_id ?: 'gpt-3.5';
    $this->textChunker->setModel($chat_model_id);
    /** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $embeddingLlm */
    $this->embeddingLlm = $this->aiProviderManager->createInstance($this->providerId);
    if (!empty($configuration['chunk_size']) && is_numeric($configuration['chunk_size'])) {
      $this->chunkSize = (int) $configuration['chunk_size'];
    }
    else {
      $this->chunkSize = $this->embeddingLlm->maxEmbeddingsInput($this->modelId);
    }

    if (!empty($configuration['chunk_min_overlap'])) {
      $this->chunkMinOverlap = (int) $configuration['chunk_min_overlap'];
    }
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): EmbeddingStrategyPluginBase|static {
    /** @var \Drupal\ai\AiProviderPluginManager $ai_provider */
    $ai_provider = $container->get('ai.provider');
    /** @var \Drupal\ai\Utility\TextChunker $text_chunker */
    $text_chunker = $container->get('ai.text_chunker');
    return new static(
      $plugin_id,
      $plugin_definition,
      $ai_provider,
      new HtmlConverter(),
      $text_chunker,
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    $form = [];
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }
    $form['chunk_size'] = [
      '#title' => $this->t('Maximum chunk size'),
      '#description' => $this->t('The number of tokens allowed per chunk of content. Leave blank to use the maximum size provided by the selected model.'),
      '#required' => FALSE,
      '#type' => 'number',
      '#default_value' => $configuration['chunk_size'] ?? '',
    ];
    $form['chunk_size_details'] = [
      '#type' => 'details',
      '#title' => $this->t('How to select your chunk size'),
    ];
    $path = $this->extensionList->getPath('ai_search');
    $file = $path . '/assets/html/chunk-size-advice.html';
    $form['chunk_size_details']['content'] = [
      '#markup' => file_get_contents($file),
    ];
    $form['chunk_min_overlap'] = [
      '#title' => $this->t('Minimum chunk overlap'),
      '#description' => $this->t('The number of tokens to retrieve from the preceding chunk to provide overlapping context for each chunk of text.'),
      '#required' => TRUE,
      '#type' => 'number',
      '#default_value' => $configuration['chunk_min_overlap'] ?? '',
    ];
    return $form;
  }

  /**
   * Returns array of default configuration values for given strategy.
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(): array {
    return [
      'chunk_size' => 500,
      'chunk_min_overlap' => 100,
    ];
  }

}
