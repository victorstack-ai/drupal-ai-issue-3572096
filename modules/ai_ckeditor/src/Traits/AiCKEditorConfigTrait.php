<?php

namespace Drupal\ai_ckeditor\Traits;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Trait for loading configurations onto any plugin.
 */
trait AiCKEditorConfigTrait {

  /**
   * Get the configuration factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The configuration factory.
   */
  public function getConfigFactory(): ConfigFactoryInterface {
    return \Drupal::configFactory();
  }

}
