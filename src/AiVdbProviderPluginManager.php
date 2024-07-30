<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Vector DB plugin manager.
 */
final class AiVdbProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ContainerInterface $container) {
    parent::__construct('Plugin/VdbProvider', $namespaces, $module_handler, AiVdbProviderInterface::class, AiVdbProvider::class);
    $this->alterInfo('ai_vdb_provider_info');
  }

  /**
   * Creates a plugin instance of a Vector Database Provider.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return AiVdbProviderInterface
   *   A fully configured vector database plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): AiVdbProviderInterface {
    /** @var \Drupal\ai\AiVdbProviderInterface $providerInstance */
    $providerInstance = parent::createInstance($plugin_id, $configuration);
    return $providerInstance;
  }

  /**
   * Gets all the available Vector DB providers.
   *
   * @return array
   *   The providers.
   */
  public function getProviders(): array {
    $plugins = [];
    foreach ($this->getDefinitions() as $definition) {
      $plugins[$definition['id']] = $definition['label']->__toString();
    }
    return $plugins;
  }

}
