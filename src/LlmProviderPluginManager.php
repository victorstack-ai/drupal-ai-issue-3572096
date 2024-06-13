<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Attribute\LlmProvider;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;


/**
 * Large Language Model plugin manager.
 */
final class LlmProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/LlmProvider', $namespaces, $module_handler, LlmProviderInterface::class, LlmProvider::class);
    $this->alterInfo('llm_provider_info');
    $this->setCacheBackend($cache_backend, 'llm_provider_plugins');
  }
}
