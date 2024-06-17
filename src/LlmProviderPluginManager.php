<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Attribute\LlmProvider;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Large Language Model plugin manager.
 */
final class LlmProviderPluginManager extends DefaultPluginManager {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ContainerInterface $container) {
    parent::__construct('Plugin/LlmProvider', $namespaces, $module_handler, LlmProviderInterface::class, LlmProvider::class);
    $this->alterInfo('llm_provider_info');
    $this->setCacheBackend($cache_backend, 'llm_provider_plugins');
    $this->eventDispatcher = $container->get('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin = parent::createInstance($plugin_id, $configuration);
    return new ProviderProxy($plugin, $this->eventDispatcher);
  }

}
