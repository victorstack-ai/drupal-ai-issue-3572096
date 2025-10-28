<?php

namespace Drupal\ai_automators;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Event\ValuesChangeEvent;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Run one rule.
 */
class AiAutomatorRuleRunner {

  /**
   * The entity type manager.
   */
  protected EntityTypeManager $entityType;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a new AiAutomatorRuleRunner object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type definition.
   * @param \Drupal\ai_automators\AiFieldRules $fieldRules
   *   The field rule manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManager $entityTypeManager, AiFieldRules $fieldRules, EventDispatcherInterface $eventDispatcher) {
    $this->entityType = $entityTypeManager;
    $this->fieldRules = $fieldRules;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Generate response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorTypeConfig
   *   The automator config.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Throws error or returns entity.
   */
  public function generateResponse(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorTypeConfig) {
    // Get rule.
    // @todo refactor to not need to pass around config to itself.
    $rule = $this->fieldRules->findRule($automatorTypeConfig['id']);

    if (!$rule) {
      throw new AiAutomatorRuleNotFoundException('The rule could not be found: ' . $fieldDefinition->getType());
    }

    // Generate values.
    $values = $rule->generate($entity, $fieldDefinition, $automatorTypeConfig['settings']);

    // Run event to change the values if needed.
    $event = new ValuesChangeEvent($values, $entity, $fieldDefinition, $automatorTypeConfig['settings']);
    $this->eventDispatcher->dispatch($event, ValuesChangeEvent::EVENT_NAME);
    $values = $event->getValues();

    foreach ($values as $key => $value) {
      // Remove values that does not fit.
      if (!$rule->verifyValue($entity, $value, $fieldDefinition, $automatorTypeConfig['settings'])) {
        unset($values[$key]);
      }
    }

    // Save values.
    if ($values && is_array($values)) {
      $rule->storeValues($entity, $values, $fieldDefinition, $automatorTypeConfig['settings']);
    }
    return $entity;
  }

}
