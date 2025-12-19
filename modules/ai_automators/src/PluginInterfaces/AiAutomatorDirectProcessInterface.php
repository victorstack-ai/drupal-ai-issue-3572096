<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for automator modifiers.
 */
interface AiAutomatorDirectProcessInterface extends AiAutomatorFieldProcessInterface {

  /**
   * If the automator should process the field directly.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array<string,mixed> $automatorConfig
   *   The configuration for the automator.
   *
   * @return bool
   *   TRUE if the automator should process the field directly, FALSE otherwise.
   */
  public function shouldProcessDirectly(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool;

}
