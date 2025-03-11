<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ComplexTollItemsConstraint constraint.
 */
class ComplexToolItemsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $function_call_plugin_manager
   *   The function call plugin manager.
   */
  public function __construct(
    protected FunctionCallPluginManager $function_call_plugin_manager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.ai_function_call')
    );
  }

  /**
   * All the classes that implement the FunctionCallInterface.
   *
   * @var array
   */
  protected array $functionCallClasses = [];

  /**
   * Checks if the value is valid.
   */
  public function validate($value, Constraint $constraint) {
    // Load the function call classes.
    if (empty($this->functionCallClasses)) {
      foreach ($this->function_call_plugin_manager->getDefinitions() as $definition) {
        $this->functionCallClasses[] = $definition['class'];
      }
    }
    // If its array we have to iterate over it.
    if (is_array($value)) {
      foreach ($value as $item) {
        $this->validateItem($item, $constraint);
      }
    }
    else {
      $this->validateItem($value, $constraint);
    }
  }

  /**
   * Validate the item.
   *
   * @param mixed $item
   *   The item to validate.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   */
  protected function validateItem($item, Constraint $constraint) {
    // Check so the items is a class and implements the FunctionCallInterface.
    if (!class_exists($item)) {
      $this->context->addViolation($constraint->message, ['%value' => $item]);
    }
    elseif (!in_array($item, $this->functionCallClasses)) {
      $this->context->addViolation($constraint->message, ['%value' => $item]);
    }
  }

}
