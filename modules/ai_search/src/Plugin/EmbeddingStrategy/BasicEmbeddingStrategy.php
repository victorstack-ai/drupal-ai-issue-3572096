<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\ai_search\Base\EmbeddingStrategyPluginBase;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Plugin implementation of the Basic embedding strategy.
 *
 * This strategy ignores metadata, chunks body if it doesn't fit the input size.
 */
#[EmbeddingStrategy(
  id: 'basic',
  label: new TranslatableMarkup('Basic embedding'),
)]
class BasicEmbeddingStrategy
  extends EmbeddingStrategyPluginBase
  implements EmbeddingStrategyInterface {

  /**
   * {@inheritDoc}
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);

    // Output variable.
    $embeddings = [];
    foreach ($fields as $field) {

      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      if (!$field instanceof FieldInterface) {
        continue;
      }

      // For each value, break into chunks.
      foreach ($field->getValues() as $delta => $value) {
        $chunks = $this->getChunks($value);

        // Embed every chunk.
        foreach ($chunks as $chunk) {
          $embeddings[] = $this->createEmbedding($chunk, $search_api_item, $field, $delta);
        }
      }
    }

    return $embeddings;
  }

  /**
   * Create the embedding.
   *
   * @param string $chunk
   *   The text chunk.
   * @param ItemInterface $search_api_item
   *   The Search API item.
   * @param FieldInterface $field
   *   The Search API field.
   * @param int $delta
   *   The field delta.
   * @return array
   *   The embedding data.
   */
  protected function createEmbedding(
    string $chunk,
    ItemInterface $search_api_item,
    FieldInterface $field,
    int $delta,
  ): array {
    /** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $embedding_llm */
    $embedding_llm = $this->embeddingLlm;
    $embedding = $embedding_llm->embeddings($chunk, $this->modelId, ["ai_search"])->getNormalized();
    return [
      'id' => $search_api_item->getId() . ':' . $field->getFieldIdentifier() . ':' . $delta,
      'values' => $embedding,
      'metadata' => [
        'content' => $chunk,
      ],
    ];
  }

  /**
   * Get the text chunks.
   *
   * @param mixed $value
   *   The value which may still need conversion to string.
   *
   * @return string[]
   *   The array of chunks from the text chunker.
   */
  protected function getChunks(mixed $value): array {
    $body_part_markdown = $this->converter->convert((string) $value);

    // Create chunks if value is too long.
    return $this->textChunker->chunkText(
      $body_part_markdown,
      $this->chunkSize,
      $this->chunkMinOverlap
    );
  }

  /**
   * @inheritDoc
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool {
    // TODO: Implement fits() method.
    return TRUE;
  }

}
