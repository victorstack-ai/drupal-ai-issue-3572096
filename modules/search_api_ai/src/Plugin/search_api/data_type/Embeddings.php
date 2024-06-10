<?php

namespace Drupal\search_api_ai\Plugin\search_api\data_type;

use Drupal\node\Entity\Node;
use Drupal\openai\Utility\StringHelper;
use Drupal\search_api\DataType\DataTypePluginBase;
use Drupal\search_api_ai\DataType\EmbeddingsDataTypePluginBase;
use Drupal\search_api_ai\EmbeddingEngineStatic;
use Drupal\search_api_ai\TextChunker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the embeddings data type.
 *
 * @SearchApiDataType(
 *   id = "embeddings",
 *   label = @Translation("Embeddings"),
 *   description = @Translation("LLM Vector Embeddings")
 * )
 */
class Embeddings extends DataTypePluginBase {

  /**
   * Embeddings engine.
   *
   * @var \Drupal\search_api_ai\EmbeddingEngineInterface
   *   The embeddings engine.
   */
  protected $embeddingsEngine;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EmbeddingEngineStatic $embeddingEngineStatic) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Check if the embeddings engine is in the configuration.
    if (isset($configuration['embeddings_engine'])) {
      $this->embeddingsEngine = $configuration['embeddings_engine'];
    }
    else {
      // Otherwise load it from static.
      $this->embeddingsEngine = $embeddingEngineStatic->getEmbeddingEngine();
    }
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('search_api_ai.embedding_engine_static')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $node = Node::load($value);
    $covered = $node->field_security_covered->value == 'covered_by_security_advisory' ? "yes\n" : "no\n";
    $value = $node->field_module_description->value;
    $prefix = "Module Name: " . $node->title->value . "\n";
    $prefix .= "Usage: " . $node->field_project_usage->value . "\n";
    $prefix .= "Security Covered: " . $covered;
    $prefix .= "Version Status: " . $node->field_version_available->value . "\n";
    $prefix .= "Summary: " . $node->field_module_summary->value . "\n\n";
    $prefix .= "Description: \n";
    $chunkMaxSize = $this->embeddingsEngine->getDimension();
    $chunkMinOverlap = 64;

    if (!$value) {
      return [];
    }

    $chunks = TextChunker::chunkText($value, $chunkMaxSize, $chunkMinOverlap);
    // @todo: Here we need to add stuff for advanced RAG.

    $items = [];
    foreach ($chunks as $delta => $chunk) {
      // Ignore empty strings.
      if (!mb_strlen($chunk)) {
        continue;
      }

      $text = $prefix . StringHelper::prepareText($chunk, [], $chunkMaxSize);

      $vectors = $this->embeddingsEngine->generateEmbeddings($text);

      if (is_array($vectors)) {
        $items[$delta] = [
          'content' => $text,
          'vectors' => $vectors,
        ];
      }
    }

    return $items;
  }

}
