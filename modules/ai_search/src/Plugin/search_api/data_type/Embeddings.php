<?php

namespace Drupal\ai_search\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

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

}
