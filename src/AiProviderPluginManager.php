<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;
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
   * Cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ContainerInterface $container) {
    parent::__construct('Plugin/AiProvider', $namespaces, $module_handler, AiProviderInterface::class, AiProvider::class);
    $this->alterInfo('ai_provider_info');
    $this->setCacheBackend($cache_backend, 'ai_provider_plugins');
    $this->eventDispatcher = $container->get('event_dispatcher');
    $this->loggerFactory = $container->get('logger.factory');
    $this->cacheBackend = $cache_backend;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin = parent::createInstance($plugin_id, $configuration);
    return new ProviderProxy($plugin, $this->eventDispatcher, $this->loggerFactory);
  }

  /**
   * Helper function if providers exists and are setup per operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $setup
   *   If the provider should be required to be setup.
   *
   * @return bool
   *   If providers exist.
   */
  public function hasProvidersForOperationType(string $operation_type, bool $setup = TRUE): bool {
    $providers = $this->getProvidersForOperationType($operation_type, $setup);
    return !empty($providers);
  }

  /**
   * Gets all providers for an operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $setup
   *   If the provider should be required to be setup.
   *
   * @return array
   *   The providers.
   */
  public function getProvidersForOperationType(string $operation_type, bool $setup = TRUE): array {
    $providers = [];
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      $provider_entity = $this->createInstance($id);
      if (in_array($operation_type, $provider_entity->getSupportedOperationTypes())) {
        if (!$setup || $provider_entity->isUsable($id)) {
          $providers[$id] = $definition;
        }
      }
    }
    return $providers;
  }

  /**
   * Get operation types.
   *
   * @return array
   *   The list of operation types.
   */
  public function getOperationTypes(): array {
    // Load from cache.
    $data = $this->cacheBackend->get('ai_operation_types');
    if (!empty($data->data)) {
      return $data->data;
    }
    // Look in the OperationType/** directories.
    $operation_types = [];
    $base_path = $this->moduleHandler->getModule('ai')->getPath() . '/src/OperationType';
    $directories = new \RecursiveDirectoryIterator($base_path);
    $iterator = new \RecursiveIteratorIterator($directories);
    $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $file) {
      $interface = $this->getInterfaceFromFile($file[0]);
      if ($interface && $this->doesInterfaceExtend($interface, OperationTypeInterface::class)) {
        $reflection = new \ReflectionClass($interface);
        $attributes = $reflection->getAttributes(OperationType::class);
        foreach ($attributes as $attribute) {
          $operation_types[] = [
            'id' => $attribute->newInstance()->id,
            'label' => $attribute->newInstance()->label->render(),
          ];
        }
      }
    }

    // Save to cache.
    $this->cacheBackend->set('ai_operation_types', $operation_types);

    return $operation_types;
  }

  /**
   * Extracts the fully qualified interface name from a file.
   *
   * @param string $file
   *   The file path.
   *
   * @return string|null
   *   The fully qualified interface name, or NULL if not found.
   */
  protected function getInterfaceFromFile($file) {
    $contents = file_get_contents($file);

    // Match namespace and interface declarations.
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $matches)) {
      $namespace = $matches[1];
    }

    // Match on starts with interface and has extends in it.
    if (preg_match('/interface\s+([^ ]+)\s+extends\s+([^ ]+)/i', $contents, $matches)) {
      $interface = $matches[1];
      return $namespace . '\\' . $interface;
    }

    return NULL;
  }

  /**
   * Checks if an interface extends another interface.
   *
   * @param string $interface
   *   The interface name.
   * @param string $baseInterface
   *   The base interface name.
   *
   * @return bool
   *   TRUE if the interface extends the base interface, FALSE otherwise.
   */
  protected function doesInterfaceExtend($interface, $baseInterface) {
    try {
      $reflection = new \ReflectionClass($interface);

      if ($reflection->isInterface() && in_array($baseInterface, $reflection->getInterfaceNames())) {
        return TRUE;
      }
    }
    catch (\ReflectionException $e) {
      // Ignore.
    }

    return FALSE;
  }

}
