<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Large Language Model plugin manager.
 */
final class AiProviderPluginManager extends DefaultPluginManager {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ContainerInterface $container) {
    parent::__construct('Plugin/AiProvider', $namespaces, $module_handler, AiProviderInterface::class, AiProvider::class);
    $this->alterInfo('ai_provider_info');
    $this->setCacheBackend($cache_backend, 'ai_provider_plugins');
    $this->eventDispatcher = $container->get('event_dispatcher');
    $this->loggerFactory = $container->get('logger.factory');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin = parent::createInstance($plugin_id, $configuration);
    return new ProviderProxy($plugin, $this->eventDispatcher, $this->loggerFactory);
  }

}
