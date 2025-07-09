<?php

declare(strict_types=1);

namespace Drupal\ai\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai\AiVdbProviderPluginManager;

/**
 * Checks if user has permission and vdb providers exist.
 */
final class VdbProvidersAccessChecker implements AccessInterface {

  /**
   * Constructs a VdbProvidersAccessChecker object.
   */
  public function __construct(
    private readonly AiVdbProviderPluginManager $aiVdbProvider,
  ) {}

  /**
   * Checks whether the user has the correct permission and providers exist.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   the access check result.
   */
  public function access(AccountInterface $account): AccessResult {
    // Fetch the list of providers.
    $providers = $this->aiVdbProvider->getProviders();

    // Allow access for admins even if no providers exist.
    if ($account->hasPermission('administer ai')) {
      return AccessResult::allowed()
        ->addCacheableDependency($account)
        ->addCacheContexts(['ai_providers', 'user.permissions']);
    }

    // Allow access only if providers exist.
    if (!empty($providers)) {
      return AccessResult::allowed()
        ->addCacheableDependency($account)
        ->addCacheContexts(['ai_providers', 'user.permissions']);
    }

    return AccessResult::forbidden();
  }

}
