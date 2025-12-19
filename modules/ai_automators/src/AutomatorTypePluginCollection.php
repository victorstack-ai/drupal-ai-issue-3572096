<?php

namespace Drupal\ai_automators;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of automator types.
 */
class AutomatorTypePluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface
   *   The image effect plugin.
   */
  // phpcs:ignore
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * Provides a sort helper for automator types.
   *
   * @param string $aID
   *   The first automator type ID.
   * @param string $bID
   *   The second automator type ID.
   *
   * @return int
   *   The comparison result.
   */
  public function sortHelper($aID, $bID) {
    return $this->get($aID)->getWeight() <=> $this->get($bID)->getWeight();
  }

}
