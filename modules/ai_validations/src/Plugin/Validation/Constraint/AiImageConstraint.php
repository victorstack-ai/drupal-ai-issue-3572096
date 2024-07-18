<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;


use Symfony\Component\Validator\Constraint;

/**
 * Ai check constraint.
 *
 * @Constraint(
 *   id = "AiImagePrompt",
 *   label = @Translation("AI check", context = "Validation"),
 * )
 */
class AiImageConstraint extends Constraint {
  public $prompt = null;
  public $message = '';
  public $provider = '';
}
