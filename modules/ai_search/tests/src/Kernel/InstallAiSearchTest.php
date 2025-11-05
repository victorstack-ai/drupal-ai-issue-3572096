<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_search and its dependencies.
 *
 * @group ai_search
 */
class InstallAiSearchTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Install Search API module first so its hook install also runs. Adding
      // to static $modules above does not run it and so the search_api_item
      // table will not be created.
      \Drupal::service('module_installer')->install(['search_api']);

      // Now try to enable the module.
      \Drupal::service('module_installer')->install(['ai_search']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_search'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
