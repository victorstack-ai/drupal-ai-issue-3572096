<?php

namespace Drupal\ai_search;

use Drupal\ai\AiVdbProviderInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Embedding strategy is algorithm by which embedding happens.
 */
interface EmbeddingStrategyInterface extends PluginInspectionInterface {

  /**
   * Returns array of vectors for given body and metadata.
   *
   * Depending on the strategy, one or more vectors are returned in an array.
   *
   * @param string $embedding_engine
   *   The embedding engine.
   * @param string $chat_model
   *   The chat model ID for token calculations.
   * @param array $configuration
   *   The embedding strategy configuration.
   * @param array $fields
   *   The fields.
   * @param \Drupal\search_api\Item\ItemInterface $search_api_item
   *   The search API item.
   *
   * @return array
   *   The vectors.
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
  ): array;

  /**
   * Not all the embedding strategies can be used with every Vector DB.
   *
   * This method returns TRUE if this strategy fits the given VDB
   * capabilities.
   *
   * @param \Drupal\ai\AiVdbProviderInterface $vdb_provider
   *   The VDB provider.
   *
   * @return bool
   *   TRUE if the strategy fits the VDB, FALSE otherwise.
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool;

  /**
   * Get the configuration subform for the Search API plugin embedding strategy.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return array
   *   The form API render array.
   */
  public function getConfigurationSubform(array $configuration): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(): array;

}
