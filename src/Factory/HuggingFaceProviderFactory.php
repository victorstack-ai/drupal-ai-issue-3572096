<?php

namespace Drupal\ai\Factory;

use Drupal\ai\Provider\HuggingFaceProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Factory for HuggingFace Provider plugin.
 */
class HuggingFaceProviderFactory {

  /**
   * Provide plugin if HuggingFace module is enabled.
   */
  public static function create(ContainerInterface $container) {
    $http_client = $container->get('http_client');
    $config_factory = $container->get('config.factory');
    $logger_factory = $container->get('logger.factory');

    try {
      // Attempt to load the huggingface.api service.
      $huggingface_api = $container->get('huggingface.api');
    }
    catch (ServiceNotFoundException $e) {
      // Handle the case where the huggingface.api service is not available.
      return NULL;
    }

    return new HuggingFaceProvider(
      $http_client,
      $config_factory,
      $logger_factory,
      $huggingface_api
    );
  }

}
