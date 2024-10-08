<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\ai_search\Base\EmbeddingStrategyPluginBase;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Base class for the metadata strategies.
 */
class EmbeddingBase extends EmbeddingStrategyPluginBase implements EmbeddingStrategyInterface {

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
    IndexInterface $index,
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);
    [$title, $contextual_content, $main_content] = $this->groupFieldData($fields, $index);
    $chunks = $this->getChunks($title, $main_content, $contextual_content);
    $metadata = $this->buildBaseMetadata($fields, $index);
    $raw_embeddings = $this->getRawEmbeddings($chunks);
    $embeddings = [];
    foreach ($chunks as $key => $chunk) {
      $metadata = $this->addContentToMetadata($metadata, $chunk, $index);
      $embedding = [
        'id' => $search_api_item->getId() . ':' . $key,
        'values' => $raw_embeddings[$key],
        'metadata' => $metadata,
      ];
      $embeddings[] = $embedding;
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
      $raw_embeddings[] = $embedding_llm->embeddings(
        $chunk,
        $this->modelId,
        ['ai_search'],
      )->getNormalized();
    }
    return $raw_embeddings;
  }

  /**
   * Group the fields into title, contextual content, and main content.
   *
   * @param array $fields
   *   The Search API fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The title, contextual content, and main content.
   */
  protected function groupFieldData(array $fields, IndexInterface $index): array {
    $title = '';
    $contextual_content = '';
    $main_content = '';
    $index_config = $this->configFactory->get('search_api.index.' . $index->id())->getRawData();
    $indexing_options = $index_config['indexing_options'];
    $allowed_options = [
      EmbeddingStrategyIndexingOptions::MAIN_CONTENT->getKey(),
      EmbeddingStrategyIndexingOptions::CONTEXTUAL_CONTENT->getKey(),
    ];
    foreach ($fields as $field) {

      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      // Ensure that we only operate on Main Content and Contextual Content
      // here.
      if (
        !$field instanceof FieldInterface
        || !isset($indexing_options[$field->getFieldIdentifier()]['indexing_option'])
        || !in_array($indexing_options[$field->getFieldIdentifier()]['indexing_option'], $allowed_options, TRUE)
      ) {
        continue;
      }
      $label_key = '';

      // Get the label field.
      $entity = $field->getDatasource();
      if ($entity) {
        $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
        $label_key = $entity_type->getKey('label');
      }

      // Get and flatten the value to prepare for conversion to vector.
      $value = $this->getValue($field);
      if (is_array($value)) {
        $value = implode(', ', $value);
      }

      // The title field.
      if ($field->getFieldIdentifier() == $label_key) {
        $title = $value;
      }

      // Determine whether this is the main content to be chunked or the
      // contextual content to be prepended to every chunk to provide additional
      // context.
      switch ($indexing_options[$field->getFieldIdentifier()]['indexing_option']) {
        case 'main_content':
          $main_content .= $value . "\n\n";
          break;

        case 'contextual_content':
          $contextual_content .= $field->getLabel() . ": " . $value . "\n\n";
          break;
      }
    }
    return [
      $title,
      $contextual_content,
      $main_content,
    ];
  }

  /**
   * Get the text chunks.
   *
   * @param string $title
   *   The title content.
   * @param string $main_content
   *   The main field content.
   * @param string $contextual_content
   *   The contextual content.
   *
   * @return string[]
   *   The array of chunks from the text chunker.
   */
  protected function getChunks(string $title, string $main_content, string $contextual_content): array {

    // This determines the available space in each chunk used by metadata vs
    // the main fields. See the description for metaDataMaxPercentage for more
    // details.
    $max_metadata = $this->metaDataMaxPercentage / 100;
    $max_main_fields = 1 - $max_metadata;

    if (strlen($title . $main_content . $contextual_content) <= $this->chunkSize) {
      // Ideal situation, all fits min single embedding.
      $chunks = $this->textChunker->chunkText(
        $this->prepareChunkText($title, $main_content, $contextual_content),
        $this->chunkSize,
        $this->chunkMinOverlap
      );
    }
    else {
      $chunks = [];
      if ((strlen($title . $contextual_content) / $this->chunkSize) < $max_metadata) {
        // Arbitrarily suppose that if 30% of embedding are metadata it is fine.
        $main_chunks = $this->textChunker->chunkText(
          $main_content,
          intval($this->chunkSize * $max_main_fields),
          $this->chunkMinOverlap
        );
        foreach ($main_chunks as $main_chunk) {
          $chunks[] = $this->prepareChunkText($title, $main_chunk, $contextual_content);
        }
      }
      else {
        // Both metadata and main fields need chunking.
        $available_chunk_size = $this->chunkSize - strlen($title);
        $contextual_chunk_size = intval($available_chunk_size * $max_metadata);
        $main_chunk_size = intval($available_chunk_size * $max_main_fields);
        $contextual_chunks = $this->textChunker->chunkText(
          $contextual_content,
          $contextual_chunk_size,
          $this->chunkMinOverlap
        );
        $main_chunks = $this->textChunker->chunkText(
          $main_content,
          $main_chunk_size,
          $this->chunkMinOverlap
        );
        foreach ($main_chunks as $main_chunk) {
          foreach ($contextual_chunks as $contextual_chunk) {
            $chunks[] = $this->prepareChunkText($title, $main_chunk, $contextual_chunk);
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
   * @param string $contextual_chunk
   *   The metadata related content.
   *
   * @return string
   *   The rendered chunk.
   */
  protected function prepareChunkText(string $title, string $main_chunk, string $contextual_chunk): string {
    $parts = [];
    // Only render the title if it is not empty.
    if (!empty($title)) {
      $parts[] = '# ' . strtoupper($title);
    }
    $parts[] = $main_chunk;
    if (!empty($contextual_chunk)) {
      $parts[] = $contextual_chunk;
    }
    return implode("\n\n", $parts);
  }

  /**
   * Build the base metadata.
   *
   * @param array $fields
   *   The Search API configured fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The metadata to attach to the vector database record.
   */
  public function buildBaseMetadata(array $fields, IndexInterface $index): array {
    $metadata = [];
    $index_config = $this->configFactory->get('search_api.index.' . $index->id())->getRawData();
    $indexing_options = $index_config['indexing_options'];
    foreach ($fields as $field) {

      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      // Ensure that we only operate on Filterable Attributes here.
      if (
        !$field instanceof FieldInterface
        || !isset($indexing_options[$field->getFieldIdentifier()]['indexing_option'])
        || $indexing_options[$field->getFieldIdentifier()]['indexing_option'] !== EmbeddingStrategyIndexingOptions::ATTRIBUTES->getKey()
      ) {
        continue;
      }
      $metadata[$field->getFieldIdentifier()] = $this->getValue($field);
    }
    return $metadata;
  }

  /**
   * Maybe add the content chunk itself to the metadata.
   *
   * @param array $metadata
   *   The metadata prepared thus far.
   * @param string $content
   *   The Main Content chunk to store.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The metadata to attach to the vector database record.
   */
  public function addContentToMetadata(array $metadata, string $content, IndexInterface $index): array {
    $index_config = $this->configFactory->get('search_api.index.' . $index->id())->getRawData();
    if (!$index_config['exclude_chunk_from_metadata']) {
      $metadata['content'] = $content;
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool {
    // @todo Implement fits() method.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(EmbeddingStrategyCapability $capability): bool {
    // At this time we are flagging that no strategies support the one
    // available capability.
    return FALSE;
  }

  /**
   * Concatenates multi-value fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Search API field.
   *
   * @return int|string|bool|float
   *   The field value.
   */
  protected function getValue(FieldInterface $field): int|array|string|bool|float {
    $values = $field->getValues();

    // Always composite if field supports multiple. Otherwise, if the field is
    // a single value, we can choose base on the field type At some point we
    // probably need to consider what field types the Vector Database supports
    // as metadata, but for now let's assume, strings, floats, integers, and
    // boolean values are fine for all.
    if (in_array($field->getType(), ['date', 'boolean', 'integer']) && count($values) === 1) {
      return (int) reset($values);
    }
    elseif (in_array($field->getType(), ['boolean']) && count($values) === 1) {
      return (bool) reset($values);
    }
    elseif (in_array($field->getType(), ['decimal']) && count($values) === 1) {
      return (float) reset($values);
    }
    elseif (count($values) == 1) {
      return $this->converter->convert((string) reset($values));
    }
    elseif (count($values) > 1) {

      // Some Vector Databases support arrays, return that in the metadata
      // and leave it to the Provider to flatten if needed.
      $parts = [];
      foreach ($field->getValues() as $value) {
        if (in_array($field->getType(), ['date', 'boolean', 'integer'])) {
          $parts[] = (int) $value;
        }
        else {
          $parts[] = $this->converter->convert((string) $value);
        }
      }
      return $parts;
    }
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }
    $form = parent::getConfigurationSubform($configuration);
    $form['contextual_content_max_percentage'] = [
      '#title' => $this->t('Contextual content maximum percentage'),
      '#description' => $this->t('Title and other contextual content are prepended to all chunks to provide context. This setting defines the maximum space they are allowed to take up. Setting to 30 means 30% of the chunk is allowed to be metadata, leaving 70% for the Main Content information. Defaults to 30% if left blank.'),
      '#required' => TRUE,
      '#type' => 'number',
      '#min' => 1,
      '#max' => 99,
      '#default_value' => $configuration['contextual_content_max_percentage'] ?? '30',
      '#field_suffix' => '%',
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    parent::init($embedding_engine, $chat_model, $configuration);
    if (!empty($configuration['contextual_content_max_percentage'])) {
      $this->metaDataMaxPercentage = $configuration['contextual_content_max_percentage'];
    }
  }

}
