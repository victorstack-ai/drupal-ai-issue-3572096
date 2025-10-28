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
   * {@inheritdoc}
   */
  public function sortHelper($aID, $bID) {
    return $this->get($aID)->getWeight() <=> $this->get($bID)->getWeight();
  }

}
