<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint to add lists of items in tool calling.
 */
#[ConstraintAttribute(
  id: 'ComplexToolItems',
  label: new TranslatableMarkup('Tool Items', [], ['context' => 'Validation'])
)]
class ComplexToolItemsConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $message = "The value '%value' has to be an class or array of classes that implements the FunctionCallInterface.";

}
