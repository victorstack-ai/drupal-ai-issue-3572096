<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for automator modifiers.
 */
interface AiAutomatorFieldProcessInterface {

  /**
   * Loads a Archive entity by its uuid.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition interface.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The AiAutomatorType plugin instance.
   *
   * @return bool
   *   Success or not.
   */
  public function modify(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType);

  /**
   * Preprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   *
   * @return void
   *   Nothing returned.
   */
  public function preProcessing(ContentEntityInterface $entity);

  /**
   * Postprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   *
   * @return void
   *   Nothing returned.
   */
  public function postProcessing(ContentEntityInterface $entity);

  /**
   * Check if the processor is allowed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition interface.
   *
   * @return bool
   *   If the processor is allowed.
   */
  public function processorIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

}
