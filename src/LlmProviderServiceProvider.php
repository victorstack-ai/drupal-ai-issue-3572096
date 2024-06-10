<?php

namespace Drupal\ai;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Find and add all the service providers tagged with ai.provider.
 */
class LlmProviderServiceProvider extends ServiceProviderBase {

  /**
   * Registers services to the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The Container Builder.
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has('ai.manager')) {
      $taggedServices = $container->findTaggedServiceIds('ai.provider');
      $definition = $container->getDefinition('ai.manager');
      foreach ($taggedServices as $id => $tags) {
        $definition->addMethodCall('addProvider', [new Reference($id), $tags[0]['id']]);
      }
    }
  }

}
