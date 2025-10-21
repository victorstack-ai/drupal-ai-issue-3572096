<?php

declare(strict_types=1);

namespace Drupal\ai\Validation;

use Drupal\ai\Embedding;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates an Embedding object.
 *
 * This class uses Symfony's Validator component to validate the properties
 * of an Embedding object based on defined constraints.
 */
class EmbeddingValidator {

  /**
   * The configured Symfony validator instance.
   */
  protected ValidatorInterface $validator;

  public function __construct(?ValidatorInterface $validator = NULL) {
    if (!$validator) {
      $validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
    }
    $this->validator = $validator;
  }

  /**
   * Validates the given Embedding object.
   *
   * @param \Drupal\ai\Embedding $embedding
   *   The Embedding object to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   A list of constraint violations, if any.
   */
  public function validate(Embedding $embedding): ConstraintViolationListInterface {
    return $this->validator->validate($embedding);
  }

}
