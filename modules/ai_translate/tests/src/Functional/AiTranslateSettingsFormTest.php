<?php

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the AI Translate settings form.
 *
 * @group ai_translate
 * @covers \Drupal\ai_translate\Form\AiTranslateSettingsForm
 */
class AiTranslateSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_translate',
    'ai',
    'node',
    'language',
    'content_translation',
    'help',
  ];

  /**
   * An administrative user with extra permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add a second language to test language-specific settings.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Create a user with permission to manage ai translation prompts.
    $this->adminUser = $this->drupalCreateUser(['manage ai translation prompts']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the settings form functionality.
   */
  public function testSettingsForm() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $form_url = Url::fromRoute('ai_translate.settings_form');

    // 1. Visit the form and check for default values.
    $this->drupalGet($form_url);
    $session->statusCodeEquals(200);
    $session->pageTextContains('AI Translate Settings');

    $session->fieldExists('use_ai_translate');
    $session->fieldExists('prompt');
    $session->fieldExists('reference_defaults[node]');
    $session->fieldExists('entity_reference_depth');
    $session->pageTextContains('Translate to English');
    $session->fieldExists('language_settings[en][prompt]');
    $session->pageTextContains('Translate to French');
    $session->fieldExists('language_settings[fr][prompt]');
    $session->checkboxChecked('use_ai_translate');

    // 2. Test form validation.
    // 2a. Test default prompt is too short.
    $this->submitForm(['prompt' => 'This is too short.'], 'Save configuration');
    $session->pageTextContains('Prompt cannot be shorter than 50 characters');

    // 2b. Test language-specific prompt is too short.
    $edit = [
      'prompt' => 'Translate the following text from {{ source_lang_name }} to {{ dest_lang_name }}. The text to translate is: {{ input_text }}',
      'language_settings[fr][prompt]' => 'Translate to French.',
    ];
    $this->submitForm($edit, 'Save configuration');
    $session->pageTextContains('Prompt cannot be shorter than 50 characters');
    $this->assertNotNull($page->find('css', '#edit-language-settings-fr-prompt.error'));

    // 3. Test successful form submission and config saving.
    $default_prompt = 'This is the new default prompt for translation. It must be over 50 characters long to pass validation. Source: {{ source_lang_name }}, Destination: {{ dest_lang_name }}. Text: {{ input_text }}';
    $french_prompt = 'Ceci est le prompt spécifique pour le français. Il doit également faire plus de 50 caractères. Source: {{ source_lang_name }}, Destination: {{ dest_lang_name }}. Texte: {{ input_text }}';

    $edit = [
      'use_ai_translate' => FALSE,
      'prompt' => $default_prompt,
      'language_settings[fr][prompt]' => $french_prompt,
      'language_settings[en][prompt]' => '',
      'reference_defaults[node]' => TRUE,
      'reference_defaults[user]' => FALSE,
      'entity_reference_depth' => '5',
    ];
    $this->submitForm($edit, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');

    // 4. Verify that the configuration was saved correctly.
    $config = $this->config('ai_translate.settings');
    $this->assertFalse($config->get('use_ai_translate'), 'The "use_ai_translate" setting was saved correctly.');
    $this->assertEquals($default_prompt, $config->get('prompt'), 'The default prompt was saved correctly.');
    $this->assertEquals($french_prompt, $config->get('language_settings.fr.prompt'), 'The French-specific prompt was saved correctly.');
    $this->assertEmpty($config->get('language_settings.en.prompt'), 'The empty English-specific prompt was saved correctly.');
    $this->assertEquals(['node'], $config->get('reference_defaults'), 'The entity reference defaults were saved correctly.');
    $this->assertEquals('5', $config->get('entity_reference_depth'), 'The entity reference depth was saved correctly.');
  }

}
