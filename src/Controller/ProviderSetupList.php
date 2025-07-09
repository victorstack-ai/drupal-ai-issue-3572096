<?php

namespace Drupal\ai\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the provider setup list.
 */
class ProviderSetupList extends ControllerBase {

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The AI VDB Provider.
   *
   * @var \Drupal\ai\AiVdbProviderPluginManager
   */
  protected AiVdbProviderPluginManager $vdbProviderManager;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Constructs a new ProviderSetupList object.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $ai_vdb_provider
   *   The AI vector database provider service.
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider_manager
   *   The AI provider service.
   * @param \Drupal\system\SystemManager $system_manager
   *   The system manager service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   */
  public function __construct(AiVdbProviderPluginManager $ai_vdb_provider, AiProviderPluginManager $ai_provider_manager, SystemManager $system_manager, CurrentPathStack $current_path) {
    $this->vdbProviderManager = $ai_vdb_provider;
    $this->aiProviderManager = $ai_provider_manager;
    $this->systemManager = $system_manager;
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.vdb_provider'),
      $container->get('ai.provider'),
      $container->get('system.manager'),
      $container->get('path.current')
    );
  }

  /**
   * Displays the list of Vector Database Providers.
   *
   * @return array
   *   A render array suitable for rendering the admin interface.
   */
  public function list() {
    // Fetch available VDB providers.
    $vdb_providers = $this->vdbProviderManager->getProviders();
    $ai_providers = $this->aiProviderManager->getDefinitions();

    // Get the current path.
    $current_path = $this->currentPath->getPath();

    // If VDB providers exist and we are on the VDB page.
    if ($current_path === '/admin/config/ai/vdb_providers' && !empty($vdb_providers)) {
      return $this->systemManager->getBlockContents();
    }

    // If AI providers exist and we are on the AI providers page.
    if ($current_path === '/admin/config/ai/providers' && !empty($ai_providers)) {
      return $this->systemManager->getBlockContents();
    }

    // If no VDB providers and on VDB page, show the VDB setup message.
    if ($current_path === '/admin/config/ai/vdb_providers' && empty($vdb_providers)) {
      return [
        '#markup' => $this->t('No vector database provider is configured. Please <a href=":link" target="_blank">configure a provider</a> to use this feature.', [
          ':link' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
        ]),
        '#allowed_tags' => ['a'],
      ];
    }

    // If no AI providers and on AI Providers page, setup message.
    if ($current_path === '/admin/config/ai/providers' && empty($ai_providers)) {
      return [
        '#markup' => $this->t('No AI provider is configured. Please <a href=":link" target="_blank">configure a provider</a> to use this feature.', [
          ':link' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
        ]),
        '#allowed_tags' => ['a'],
      ];
    }

    // Default case: Return an empty render array.
    return [];
  }

}
