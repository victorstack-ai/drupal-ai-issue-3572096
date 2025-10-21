<?php

namespace Drupal\Tests\ai\Unit\Validation;

use Drupal\ai\Embedding;
use Drupal\ai\Validation\EmbeddingValidator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the EmbeddingValidator.
 *
 * @group ai
 */
class EmbeddingValidatorTest extends UnitTestCase {

  /**
   * Tests the validation of a valid embedding.
   */
  public function testValidEmbedding(): void {
    $embedding = new Embedding(
      'model1:embedding1',
      [0.1, 0.2, 0.3],
      ['source' => 'test']
    );

    $validator = new EmbeddingValidator();
    $violations = $validator->validate($embedding);

    $this->assertCount(0, $violations, 'Valid embedding should have no violations.');
  }

  /**
   * Tests the validation of an invalid embedding.
   */
  public function testInvalidEmbedding(): void {
    $embedding = new Embedding(
      'invalid_id',
      // Empty values should trigger a violation.
      []
    );

    $validator = new EmbeddingValidator();
    $violations = $validator->validate($embedding);

    $this->assertCount(2, $violations);
    $this->assertEquals('Id should be in the format "prefix:suffix"', $violations[0]->getMessage());
    $this->assertEquals('Values should not be empty', $violations[1]->getMessage());
  }

}
