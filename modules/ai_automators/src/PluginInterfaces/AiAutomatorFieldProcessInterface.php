<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for automator modifiers.
 */
interface AiAutomatorFieldProcessInterface {

  /**
   * Loads a Archive entity by its uuid.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition interface.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The AiAutomatorType plugin instance.
   *
   * @return bool
   *   Success or not.
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType);

  /**
   * Preprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   */
  public function preProcessing(EntityInterface $entity);

  /**
   * Postprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   */
  public function postProcessing(EntityInterface $entity);

  /**
   * Check if the processor is allowed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition interface.
   *
   * @return bool
   *   If the processor is allowed.
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

}
