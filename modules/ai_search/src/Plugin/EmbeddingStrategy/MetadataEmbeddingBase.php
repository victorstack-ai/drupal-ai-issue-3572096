<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai_search\Base\EmbeddingStrategyPluginBase;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Base class for the metadata strategies.
 */
class MetadataEmbeddingBase extends EmbeddingStrategyPluginBase implements EmbeddingStrategyInterface {

  /**
   * The maximum percentage that metadata is allowed to take.
   *
   * The rest of the space is consumed by the main field data; however, we
   * prepend the title and basic metadata to give context to each chunk.
   * 30 in this case is 30%, allowing 70% of the space to be taken by the
   * main field data.
   *
   * @var int
   */
  protected int $metaDataMaxPercentage = 30;

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

    $raw_embeddings = $this->getRawEmbeddings($chunks);
    $embeddings = [];
    foreach ($chunks as $key => $chunk) {
      $embeddings[] = [
        'id' => $search_api_item->getId(),
        'values' => $raw_embeddings[$key],
        'metadata' => [
          'content' => $chunk,
        ],
      ];
    }

    return $embeddings;
  }

  /**
   * Get the raw embeddings.
   *
   * @param array $chunks
   *   The text chunks.
   *
   * @return array
   *   The raw embeddings.
   */
  protected function getRawEmbeddings(array $chunks): array {
    $raw_embeddings = [];

    /** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $embedding_llm */
    $embedding_llm = $this->embeddingLlm;
    foreach ($chunks as $chunk) {
      $raw_embeddings[] = $embedding_llm->embeddings($chunk, $this->modelId, ["ai_search"])->getNormalized();
    }
    return $raw_embeddings;
  }

  /**
   * Group the fields into title, metadata, and main fields.
   *
   * @param array $fields
   *   The Search API fields.
   *
   * @return array
   *   The title, metadata, and main fields.
   */
  protected function groupFieldData(array $fields): array {
    $title = '';
    $metadata = '';
    $main_fields = '';
    foreach ($fields as $field) {
      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      if (!$field instanceof FieldInterface) {
        continue;
      }
      $label_key = '';

      // Get the label field.
      $entity = $field->getDatasource();
      if ($entity) {
        $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
        $label_key = $entity_type->getKey('label');
      }

      $value = $this->compositeValues($field);
      // The title field.
      if ($field->getFieldIdentifier() == $label_key) {
        $title = $value;
      }
      // The embeddings fields.
      elseif ($field->getType() == 'embeddings') {
        $main_fields .= $value . "\n\n";
      }
      // Everything else is metadata.
      else {
        $metadata .= $field->getLabel() . ": " . $value . "\n\n";
      }
    }
    return [
      $title,
      $metadata,
      $main_fields,
    ];
  }

  /**
   * Get the text chunks.
   *
   * @param string $title
   *   The title content.
   * @param string $main_fields
   *   The main field content.
   * @param string $metadata
   *   The metadata related content.
   *
   * @return string[]
   *   The array of chunks from the text chunker.
   */
  protected function getChunks(string $title, string $main_fields, string $metadata): array {

    // This determines the available space in each chunk used by metadata vs
    // the main fields. See the description for metaDataMaxPercentage for more
    // details.
    $max_metadata = $this->metaDataMaxPercentage / 100;
    $max_main_fields = 1 - $max_metadata;

    if (strlen($title . $main_fields . $metadata) <= $this->chunkSize) {
      // Ideal situation, all fits min single embedding.
      $chunks = $this->textChunker->chunkText(
        $this->prepareChunkText($title, $main_fields, $metadata),
        $this->chunkSize,
        $this->chunkMinOverlap
      );
    }
    else {
      $chunks = [];
      if ((strlen($title . $metadata) / $this->chunkSize) < $max_metadata) {
        // Arbitrarily suppose that if 30% of embedding are metadata it is fine.
        $main_chunks = $this->textChunker->chunkText(
          $main_fields,
          intval($this->chunkSize * $max_main_fields),
          $this->chunkMinOverlap
        );
        foreach ($main_chunks as $main_chunk) {
          $chunks[] = $this->prepareChunkText($title, $main_chunk, $metadata);
        }
      }
      else {
        // Both metadata and main fields need chunking.
        $available_chunk_size = $this->chunkSize - strlen($title);
        $metadata_chunk_size = intval($available_chunk_size * $max_metadata);
        $main_chunk_size = intval($available_chunk_size * $max_main_fields);
        $metadata_chunks = $this->textChunker->chunkText(
          $metadata,
          $metadata_chunk_size,
          $this->chunkMinOverlap
              );
        $main_chunks = $this->textChunker->chunkText(
                $main_fields,
                $main_chunk_size,
                $this->chunkMinOverlap
              );
        foreach ($main_chunks as $main_chunk) {
          foreach ($metadata_chunks as $metadata_chunk) {
            $chunks[] = $this->prepareChunkText($title, $main_chunk, $metadata_chunk);
          }
        }
      }
    }
    return $chunks;
  }

  /**
   * Render the chunks.
   *
   * @param string $title
   *   The title content.
   * @param string $main_chunk
   *   The main field content.
   * @param string $metadata_chunk
   *   The metadata related content.
   *
   * @return string
   *   The rendered chunk.
   */
  protected function prepareChunkText(string $title, string $main_chunk, string $metadata_chunk): string {
    $parts = [];
    // Only render the title if it is not empty.
    if (!empty($title)) {
      $parts[] = '# ' . strtoupper($title);
    }
    $parts[] = $main_chunk;
    if (!empty($metadata_chunk)) {
      $parts[] = $metadata_chunk;
    }
    return implode("\n\n", $parts);
  }

  /**
   * {@inheritDoc}
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool {
    // @todo Implement fits() method.
    return TRUE;
  }

  /**
   * Concatenates multi-value fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Search API field.
   *
   * @return string
   *   The composite field.
   */
  private function compositeValues(FieldInterface $field): string {
    $composite_field = '';
    foreach ($field->getValues() as $value) {
      $composite_field .= $this->converter->convert((string) $value);
    }
    return $composite_field;
  }

  /**
   * Return merged embedding via Average Pooling.
   *
   * @param array $embeddings
   *   The embeddings.
   *
   * @return array
   *   The updated average embeddings.
   */
  protected function averagePooling(array $embeddings): array {
    $numEmbeddings = count($embeddings);
    $embeddingSize = count($embeddings[0]);

    $averageEmbedding = array_fill(0, $embeddingSize, 0.0);

    foreach ($embeddings as $embedding) {
      for ($i = 0; $i < $embeddingSize; $i++) {
        $averageEmbedding[$i] += $embedding[$i];
      }
    }

    for ($i = 0; $i < $embeddingSize; $i++) {
      $averageEmbedding[$i] /= $numEmbeddings;
    }

    return $averageEmbedding;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }
    $form = parent::getConfigurationSubform($configuration);
    $form['metadata_max_percentage'] = [
      '#title' => $this->t('Metadata maximum percentage'),
      '#description' => $this->t('The title and metadata are prepended to all chunks to provide context. This setting defines the maximum space they are allowed to take up. Setting to 30 means 30% of the chunk is allowed to be metadata, leaving 70% for the main field information. Defaults to 30% if left blank.'),
      '#required' => TRUE,
      '#type' => 'number',
      '#min' => 1,
      '#max' => 99,
      '#default_value' => $configuration['metadata_max_percentage'] ?? '30',
      '#field_suffix' => '%',
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    parent::init($embedding_engine, $chat_model, $configuration);
    if (!empty($configuration['metadata_max_percentage'])) {
      $this->metaDataMaxPercentage = $configuration['metadata_max_percentage'];
    }
  }

}
