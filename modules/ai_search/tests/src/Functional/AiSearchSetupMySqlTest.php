<?php

namespace Drupal\Tests\ai_search\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Contains AI Search UI setup functional tests.
 *
 * @group ai_search_functional
 */
class AiSearchSetupMySqlTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_search',
    'test_ai_provider_mysql',
    'test_ai_vdb_provider_mysql',
    'node',
    'taxonomy',
    'user',
    'system',
    'field_ui',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with restricted access for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $restrictedUser;

  /**
   * Nodes for testing the indexing.
   *
   * @var array
   *   An array of nodes for testing.
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    // Allow skipping the test if FFI is not loaded AND PHP version is not
    // 8.3. We must update gitlab-ci.yml and this with 8.4, etc as versions
    // change. See docs/modules/ai_search/index.md for details.
    if (
      (!in_array('FFI', get_loaded_extensions()) || !class_exists('FFI'))
      && !str_starts_with(phpversion(), '8.3')
      && !str_starts_with(phpversion(), '8.4')
    ) {
      $this->markTestSkipped('FFI extension is not loaded.');
    }
    parent::setUp();

    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'access content overview',
      'administer nodes',
      'administer node fields',
      'bypass node access',
      'administer ai',
      'administer ai providers',
      'administer search_api',
      'administer views',
    ]);

    // Create a role and user for access control testing.
    $restricted_role = Role::create([
      'id' => 'restricted_user',
      'label' => 'Restricted User',
    ]);
    $restricted_role->grantPermission('access content');
    $restricted_role->save();

    $this->restrictedUser = User::create([
      'name' => 'restricted',
      'pass' => 'restricted',
      'status' => 1,
    ]);
    $this->restrictedUser->addRole($restricted_role->id());
    $this->restrictedUser->save();

    $this->setupServerAndIndex();
    $this->createSampleContent();
    $this->indexContent();
  }

  /**
   * Set up server and index.
   *
   * This both tests the setup and provides a default setup for other tests.
   */
  public function setupServerAndIndex(): void {
    $this->drupalLogin($this->adminUser);

    // Keep the indexed content minimal since the MySQL Vector embedding has
    // low accuracy in the tests.
    $this->drupalGet('admin/structure/types/manage/article');
    $this->submitForm([
      'display_submitted' => FALSE,
    ], 'Save');

    // Set the embedding default provider as the test MySQL one.
    $this->drupalGet('admin/config/ai/settings');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
    ], 'Choose Model');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
      'model__embeddings' => 'mysql',
    ], 'Save configuration');

    // Set up Search API Server.
    $this->drupalGet('admin/config/search/search-api/add-server');
    $this->submitForm([
      'name' => 'Test MySQL AI Vector Database',
      'id' => 'test_mysql_vdb',
      'backend' => 'search_api_ai_search',
      'status' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[embeddings_engine]' => 'test_mysql_provider__mysql',
      'backend_config[database]' => 'test_mysql',
      'backend_config[embeddings_engine_configuration][dimensions]' => 384,
      'backend_config[embeddings_engine_configuration][set_dimensions]' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[database_settings][database_name]' => 'test_mysql_database',
      'backend_config[database_settings][collection]' => 'test_mysql_collection',
    ], 'Save');

    // Set up index.
    $this->drupalGet('admin/config/search/search-api/add-index');
    $this->submitForm([
      'name' => 'Test MySQL VDB Index',
      'id' => 'test_mysql_vdb_index',
      'datasources[entity:node]' => TRUE,
      'server' => 'test_mysql_vdb',
      'options[cron_limit]' => 5,
    ], 'Save and add fields');
    $this->submitForm([], 'Save and add fields');

    // Add fields.
    $page = $this->getSession()->getPage();
    // Rendered html.
    $page->pressButton('rendered_item');
    $this->submitForm([
      'view_mode[entity:node][:default]' => 'default',
      // Render the content items as admin so unpublished content gets shown.
      'roles' => [$this->adminUser->getRoles()[0]],
    ], 'Save');
    // Title.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields/add/nojs');
    $page->pressButton('entity:node/title');
    // Done.
    $page->clickLink('edit-done');

    // Selecting indexing options on fields page.
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');

    // Check indexing options have been configured.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->pageTextContains('Indexing options have been configured.');
  }

  /**
   * Create sample content to check index.
   */
  public function createSampleContent(): void {

    // Unpublished items to be able to test iterative retrieval.
    $this->nodes['strawberry_cake'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Strawberry Cheese Cake',
      'body' => [
        'value' => 'A sweet cheese based dessert make with strawberries on a pie-like crust.',
        'format' => 'plain_text',
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->nodes['blueberry_cream_roll'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Blueberry Cream Roll',
      'body' => [
        'value' => 'Filled homemade sponge cake with a whipped cream and blueberry filling.',
        'format' => 'plain_text',
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->nodes['cornmeal_cake'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Cornmeal Cake',
      'body' => [
        'value' => 'A gluten free cake alternative.',
        'format' => 'plain_text',
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    // Published items.
    $this->nodes['chocolate_cake'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Chocolate Cake',
      'body' => [
        'value' => 'A delicious chocolate dessert made with cocoa powder and dark chocolate.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes['vanilla_ice_cream'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Vanilla Ice Cream',
      'body' => [
        'value' => 'A creamy vanilla dessert made with milk, cream, and vanilla extract.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes['tomato_soup'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Tomato Soup',
      'body' => [
        'value' => 'A warm starter made with fresh tomatoes, garlic, and basil.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes['grilled_chicken_breast'] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Grilled Chicken Breast',
      'body' => [
        'value' => 'A savory main course made with marinated chicken breast, grilled to perfection.',
        'format' => 'plain_text',
      ],
    ]);
  }

  /**
   * Index content.
   */
  public function indexContent(): void {
    $cron_service = \Drupal::service('cron');

    // Run cron twice since we are in batches of 5 per run.
    $cron_service->run();
    $cron_service->run();
  }

  /**
   * Test the content indexing has completed.
   */
  public function testContentIndexingCompleted(): void {
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->elementTextContains('css', '.progress__percentage', '100%');
  }

  /**
   * Test the field main and contextual indexing options.
   */
  public function testFieldIndexingOptions() {
    // Test Case 1: Title NOT in contextual content + exclude_title FALSE.
    // Expected: Title IS auto-added as "# CHOCOLATE CAKE".
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'ignore',
    ], 'Save changes');

    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'checker[entity]' => $this->nodes['chocolate_cake']->label() . ' (' . $this->nodes['chocolate_cake']->id() . ')',
    ], 'Save changes');

    $page_text = $this->getSession()->getPage()->getText();
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // When CommonMark is available, markdown is converted to HTML.
      // Just check that the title appears somewhere on the page.
      $this->assertSession()->pageTextContains('Chocolate Cake');
    }
    else {
      // When CommonMark is NOT available, check for raw markdown syntax.
      $has_markdown_link = str_contains($page_text, '[Chocolate Cake](' . $this->nodes['chocolate_cake']->toUrl()->toString() . ')');
      $has_markdown_title = str_contains($page_text, '# CHOCOLATE CAKE');
      $this->assertTrue($has_markdown_link || $has_markdown_title, sprintf(
        "Expected markdown link '[Chocolate Cake](%s)' OR markdown title '# CHOCOLATE CAKE'. Neither found. CommonMark class exists: %s. Page text preview: %s",
        $this->nodes['chocolate_cake']->toUrl()->toString(),
        class_exists('League\CommonMark\CommonMarkConverter') ? 'YES' : 'NO',
        substr($page_text, 0, 2000)
      ));
    }
    // Title should NOT appear in contextual content format.
    $this->assertSession()->pageTextNotContains('Title: Chocolate Cake');

    // Test Case 2: Title IS in contextual content + exclude_title FALSE.
    // Expected: Title NOT auto-added (to avoid duplication).
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'checker[entity]' => $this->nodes['chocolate_cake']->label() . ' (' . $this->nodes['chocolate_cake']->id() . ')',
    ], 'Save changes');
    // Title should appear in contextual content format.
    $this->assertSession()->pageTextContains('Title: Chocolate Cake');
    // Title should NOT be auto-added as header.
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // Content may still contain the title in other forms, so we check for
      // the specific uppercase header format.
      $this->assertSession()->pageTextNotContains('# CHOCOLATE CAKE');
    }
    else {
      $this->assertSession()->pageTextNotContains('# CHOCOLATE CAKE');
    }

    // Test Case 3: Title NOT in contextual content + exclude_title TRUE.
    // Expected: Title NOT auto-added.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'ignore',
      'advanced[exclude_title]' => TRUE,
    ], 'Save changes');
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'checker[entity]' => $this->nodes['chocolate_cake']->label() . ' (' . $this->nodes['chocolate_cake']->id() . ')',
    ], 'Save changes');
    // Title should NOT appear at all.
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $this->assertSession()->elementNotContains('css', 'h1', 'CHOCOLATE CAKE');
    }
    else {
      $page_text = $this->getSession()->getPage()->getText();
      $has_markdown_link = str_contains($page_text, '[Chocolate Cake](' . $this->nodes['chocolate_cake']->toUrl()->toString() . ')');
      $has_markdown_title = str_contains($page_text, '# CHOCOLATE CAKE');
      $this->assertTrue($has_markdown_link, 'Expected markdown link to be present.');
      $this->assertFalse($has_markdown_title, 'Expected markdown title NOT to be present.');
    }
    $this->assertSession()->pageTextNotContains('Title: Chocolate Cake');

    // Reset to default state for parallel test runs.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
      'advanced[exclude_title]' => FALSE,
    ], 'Save changes');
  }

  /**
   * Test searching via a search view.
   */
  public function testSearchView() {
    // Create the view using our index.
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test search view',
      'id' => 'test_search_view',
      'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
      'page[title]' => 'Test Search View',
      'page[create]' => 1,
      'page[path]' => 'test-search-view',
    ], 'Save and edit');

    // Set quantity to 3.
    $this->drupalGet('admin/structure/views/nojs/display/test_search_view/default/pager_options');
    $this->submitForm([
      'pager_options[items_per_page]' => 3,
    ], 'Apply');

    // Add a search exposed filter.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/filter');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
    ], 'Add and configure filter criteria');

    // Expose the filter then save it.
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
    ];
    $this->submitForm($edit, 'Expose filter');
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
      'options[group_button][radios][radios]' => 0,
    ];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');

    // Sort by relevance.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/sort');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_relevance]' => 'search_api_index_test_mysql_vdb_index.search_api_relevance',
    ], 'Add and configure sort criteria');
    $this->submitForm([
      'options[order]' => 'DESC',
    ], 'Apply');
    $this->submitForm([], 'Save');

    // Remove sort by authored on.
    $this->drupalGet('admin/structure/views/nojs/handler/test_search_view/default/sort/created');
    $this->submitForm([], 'Remove');
    $this->submitForm([], 'Save');

    // Check results when logged in as admin (can see unpublished content).
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry Cheese',
    ], 'Apply');
    $this->assertSession()->pageTextContains('Strawberry Cheese Cake');

    // Now logged out: Ensure the unpublished item does not exist.
    $this->drupalLogout();
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry Cheese',
    ], 'Apply');
    $this->assertSession()->pageTextNotContains('Strawberry Cheese Cake');

    // Iterative should retrieve 3 rows.
    $rows = $this->cssSelect('.views-row');
    $this->assertCount(3, $rows);

    // Test the iterative search logic with a logged-in but restricted user.
    $this->drupalLogin($this->restrictedUser);
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry Cheese',
    ], 'Apply');
    $this->assertSession()->pageTextNotContains('Strawberry Cheese Cake');

    // Iterative check again, and in this case, other nodes would have been
    // so the iteration should have occurred as unpublished items were dropped
    // from the results.
    $rows = $this->cssSelect('.views-row');
    $this->assertCount(3, $rows);
  }

  /**
   * Tests that raw embedding vector is included in results when enabled.
   */
  public function testRawEmbeddingVectorInResults() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/search-api/server/test_mysql_vdb/edit');
    $this->submitForm([
      'backend_config[include_raw_embedding_vector]' => TRUE,
    ], 'Save');

    $this->drupalGet('admin/structure/views');
    if (!$this->getSession()->getPage()->hasLink('Test raw vector view')) {
      $this->drupalGet('admin/structure/views/add');
      $this->submitForm([
        'label' => 'Test raw vector view',
        'id' => 'test_raw_vector_view',
        'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
        'page[title]' => 'Test Raw Vector View',
        'page[create]' => 1,
        'page[path]' => 'test-raw-vector-view',
      ], 'Save and edit');

      // Add a search exposed filter.
      $this->drupalGet('admin/structure/views/nojs/add-handler/test_raw_vector_view/default/filter');
      $this->submitForm([
        'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
      ], 'Add and configure filter criteria');

      // Expose the filter then save it.
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
      ];
      $this->submitForm($edit, 'Expose filter');
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
        'options[group_button][radios][radios]' => 0,
      ];
      $this->submitForm($edit, 'Apply');
      $this->submitForm([], 'Save');
    }

    // Go to the search view page.
    $this->drupalGet('test-raw-vector-view');
    $this->submitForm([
      'search_api_fulltext' => 'chocolate',
    ], 'Apply');

    // Programmatically fetch Search API results for deeper inspection.
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('test_mysql_vdb_index');
    $query = $index->query();
    $query->keys('chocolate');
    $results = $query->execute();

    foreach ($results->getResultItems() as $item) {
      $extra_data = $item->getExtraData();
      $this->assertArrayHasKey('raw_vector', $extra_data, 'raw_vector is present in extra data');
      $this->assertIsArray($extra_data['raw_vector'], 'raw_vector is an array');
    }
  }

  /**
   * Tests searching by passing a raw vector directly to the query.
   */
  public function testSearchByVectorInputOption(): void {
    $this->drupalLogin($this->adminUser);

    // First, we need a known vector to test with. We'll get the vector for
    // the "Chocolate Cake" node by performing a regular search for it and
    // extracting the vector from the results.
    $index_storage = $this->container->get('entity_type.manager')->getStorage('search_api_index');
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $index_storage->load('test_mysql_vdb_index');

    // Enable the setting to include the raw vector in results and re-index.
    $server = $index->getServerInstance();
    $backend_config = $server->getBackendConfig();
    $backend_config['include_raw_embedding_vector'] = TRUE;
    $server->setBackendConfig($backend_config);
    $server->save();
    $index->reindex();
    $index->indexItems();

    // Execute a query to get the "Chocolate Cake" vector.
    $query_for_vector = $index->query();
    $query_for_vector->keys('Chocolate Cake');
    $query_for_vector->range(0, 1);
    $results_for_vector = $query_for_vector->execute();

    $result_items = $results_for_vector->getResultItems();
    $this->assertNotEmpty($result_items, 'Successfully retrieved an item to get a source vector.');
    $chocolate_cake_item = reset($result_items);
    $this->assertSame('entity:node/' . $this->nodes['chocolate_cake']->id() . ':en', $chocolate_cake_item->getExtraData('drupal_entity_id'), 'Item found is sample item 1 "Chocolate Cake".');
    $chocolate_cake_vector = $chocolate_cake_item->getExtraData('normalized_vector');
    $this->assertIsArray($chocolate_cake_vector, 'Successfully extracted a known vector for "Chocolate Cake".');
    $this->assertNotEmpty($chocolate_cake_vector, 'The extracted vector is not empty.');

    // Now, perform a new search, passing the known vector directly via the
    // 'vector_input' query option.
    $query_by_vector = $index->query();
    $query_by_vector->setOption('vector_input', $chocolate_cake_vector);
    $results_by_vector = $query_by_vector->execute();
    $result_items = $results_by_vector->getResultItems();

    // The most similar item should be itself (in real-world scenario, a
    // condition to exclude itself should be used e.g. for a similarity search).
    $this->assertNotEmpty($result_items, 'Search by vector option returned results.');
    $top_result = array_shift($result_items);
    $this->assertSame('entity:node/' . $this->nodes['chocolate_cake']->id() . ':en', $top_result->getExtraData('drupal_entity_id'), 'Item found is sample item 1 "Chocolate Cake".');
    if (!empty($result_items)) {
      $second_result = array_shift($result_items);
      $this->assertNotSame('entity:node/' . $this->nodes['chocolate_cake']->id() . ':en', $second_result->getExtraData('drupal_entity_id'), 'Item found is not sample item 1 "Chocolate Cake".');
    }
  }

}
