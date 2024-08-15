<?php

namespace Drupal\ai_automator\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 */
class AutomatorRouteSubscriber {

  /**
   * Provides dynamic routes.
   */
  public function routes() {
    $routes = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        continue;
      }
      if (!$entity_type->getBundleOf()) {
        continue;
      }
      $route = new Route(
        '/admin/structure/types/manage/automator_chain/' . $entity_type->getBundleOf(). '/{' . $entity_type_id . '}',
        [
          '_form' => '\Drupal\ai_automator\Form\AiChainForm',
          '_title' => 'AI Automator Chain Configuration',
        ],
        [
          '_permission'  => 'administer ai_automator',
        ]
      );
      $routes['ai_automator.config_chain.' . $entity_type->getBundleOf()] = $route;
    }
    return $routes;
  }

}
