<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Item\ItemInterface;

/**
 * Plugin implementation of the Metadata enriched composite embedding strategy.
 *
 * This strategy adds metadata to the chunked body fields, embeds the chunks,
 * and then uses Average Pooling to obtain a single composite vector.
 */
#[EmbeddingStrategy(
  id: 'metadata_average_pool',
  label: new TranslatableMarkup('Metadata enriched composite embedding.'),
)]
class MetadataAveragePoolEmbeddingStrategy extends MetadataEmbeddingBase {

  /**
   * {@inheritDoc}
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);
    [$title, $metadata, $main_fields] = $this->groupFieldData($fields);
    $chunks = $this->getChunks($title, $main_fields, $metadata);

    // Embed and average.
    $raw_embeddings = $this->getRawEmbeddings($chunks);
    $embedding = $this->averagePooling($raw_embeddings);

    return [[
      'id' => $search_api_item->getId(),
      'values' => $embedding,
      'metadata' => [
        'content' => $title . $main_fields . $metadata,
      ],
    ],
    ];
  }

}
