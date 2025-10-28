<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides an interface defining an ai automator entity type.
 */
interface AiAutomatorInterface extends ConfigEntityInterface {

  /**
   * Get the field definition for this automator.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition for this automator, or NULL if not applicable.
   */
  public function getFieldDefinition() : ?FieldDefinitionInterface;

  /**
   * Returns a specific automator type.
   *
   * @param string $automator_type
   *   The automator type ID.
   *
   * @return \Drupal\ai_automators\PluginInterfaces\aiAutomatorTypeInterface
   *   The automator type object.
   */
  public function getAutomatorType(string $automator_type): aiAutomatorTypeInterface;

  /**
   * Returns the automator types for this style.
   *
   * @return \Drupal\ai_automators\AutomatorTypePluginCollection|\Drupal\ai_automators\PluginInterfaces\aiAutomatorTypeInterface[]
   *   The automator type plugin collection.
   */
  public function getAutomatorTypes(): AutomatorTypePluginCollection;

  /**
   * Adds an automator type to this automator.
   *
   * @return string[]
   *   The added automator type UUID.
   */
  public function addAutomatorType(array $configuration): string;

  /**
   * Deletes an automator type from this automator by UUID.
   *
   * @param string $uuid
   *   The automator type instance UUID.
   */
  public function deleteAutomatorType(string $uuid): void;

}
