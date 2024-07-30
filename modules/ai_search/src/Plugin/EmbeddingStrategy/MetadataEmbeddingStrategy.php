<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the Metadata enriched embedding strategy.
 *
 * This strategy adds metadata to the chunked body fields, embeds the chunks.
 */
#[EmbeddingStrategy(
  id: 'metadata_chunks',
  label: new TranslatableMarkup('Metadata enriched chunks.'),
)]
class MetadataEmbeddingStrategy extends MetadataEmbeddingBase {

}
