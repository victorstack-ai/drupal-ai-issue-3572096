<?php

namespace Drupal\ai\Traits\OperationType;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\Component\Utility\Crypt;

/**
 * Chat specific base methods.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait EmbeddingsTrait {

  /**
   * Caching wrapper for the embeddings operation.
   *
   * This method checks for a cached result in the 'ai_embedding' bin. If a
   * result is not found, it executes the provided $fetcher callable to
   * generate the result and stores it in the cache for one week.
   *
   * @param \Drupal\ai\OperationType\Embeddings\EmbeddingsInput $input
   *   The embeddings input object.
   * @param string $model_id
   *   The model ID being used.
   * @param callable $fetcher
   *   A function that returns an EmbeddingsOutput object on cache miss.
   *
   * @return \Drupal\ai\OperationType\Embeddings\EmbeddingsOutput
   *   The embeddings output.
   */
  public function getCachedEmbeddings(EmbeddingsInput $input, string $model_id, callable $fetcher): EmbeddingsOutput {
    // During indexing, for example, it is better not to cache the input as it
    // is unlikely to be repeated.
    if (!$input->shouldCache()) {
      return $fetcher();
    }

    // Use the persistent 'ai_embedding' cache bin. Ignore non-dependency
    // injection to avoid breaking change.
    // @codingStandardsIgnoreLine @phpstan-ignore-next-line
    $cache = \Drupal::cache('ai_embeddings');

    // Generate a unique cache ID from the input prompt and model ID.
    $string = $input->getPrompt() . ':' . $model_id;
    $cid = 'embedding:' . Crypt::hashBase64($string);

    // If the item exists in the cache, return it.
    if ($cached = $cache->get($cid)) {
      return $cached->data;
    }

    // On a cache miss, execute the fetcher to get the live result.
    $result = $fetcher();

    // Store the new result in the cache with a 1-week expiry.
    // @todo Make this configurable.
    $expiry = strtotime('+1 week');
    $cache->set($cid, $result, $expiry);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput(string $model_id = ''): int {
    return 1024;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    $cache = \Drupal::cache('ai');
    $cid = 'embeddings_size:' . $this->getPluginId() . ':' . $model_id;
    if ($cached = $cache->get($cid)) {
      return $cached->data;
    }

    // Just until all providers have the trait.
    if (!method_exists($this, 'embeddings')) {
      return 0;
    }
    // Normalize the input.
    $input = new EmbeddingsInput('Hello world!');
    $embedding = $this->embeddings($input, $model_id);
    try {
      $size = count($embedding->getNormalized());
    }
    catch (\Exception $e) {
      return 0;
    }
    $cache->set($cid, $size);

    return $size;
  }

}
