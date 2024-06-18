<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for embeddings models.
 */
interface EmbeddingsInterface extends OperationTypeInterface {

  /**
   * Generate embeddings.
   *
   * @param string|\Drupal\ai\OperationType\Embeddings\EmbeddingsInput $input
   *   The prompt or the embeddings input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Embeddings\EmbeddingsOutput
   *   The embeddings output.
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput;

}
