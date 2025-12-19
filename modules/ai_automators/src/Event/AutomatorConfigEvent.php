<?php

namespace Drupal\ai_automators\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Changes to the automator config can be made here.
 */
class AutomatorConfigEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_automator.automator_config';

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The configuration for the automator.
   *
   * @var array<string,mixed>
   */
  protected $automatorConfig;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param array<string,mixed> $automatorConfig
   *   The configuration for the automator.
   */
  public function __construct(ContentEntityInterface $entity, array $automatorConfig) {
    $this->entity = $entity;
    $this->automatorConfig = $automatorConfig;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the automator config.
   *
   * @return array<string,mixed>
   *   The automator config.
   */
  public function getAutomatorConfig() {
    return $this->automatorConfig;
  }

  /**
   * Set the automator config.
   *
   * @param array<string,mixed> $automatorConfig
   *   The automator config.
   *
   * @return void
   *   The automator config.
   */
  public function setAutomatorConfig(array $automatorConfig): void {
    $this->automatorConfig = $automatorConfig;
  }

}
