<?php

namespace Drupal\ai_automator\Traits;

/**
 * Trait to add the possibility to store output base64 encoded strings.
 *
 * @package Drupal\ai_automator\Traits
 */
trait FileHelperTrait {

  /**
   * Gets the file helper.
   *
   * @return \Drupal\ai_automator\Rulehelpers\FileHelper
   *   The file helper.
   */
  public function getFileHelper() {
    return \Drupal::service('ai_automator.rule_helper.file');
  }

}
