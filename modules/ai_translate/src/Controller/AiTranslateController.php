<?php

namespace Drupal\ai_translate\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Url;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines an AI Translate Controller.
 */
class AiTranslateController extends ControllerBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * Creates an ContentTranslationPreviewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  final public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
    $instance->languageManager = $container->get('language_manager');
    $instance->aiConfig = $container->get('config.factory')->get('ai.settings');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    return $instance;
  }

  /**
   * Add requested entity translation to the original content.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_from
   *   Source language code.
   * @param string $lang_to
   *   Target language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translation
   *   overview page by showing a success or error message.
   */
  public function translate(string $entity_type, string $entity_id, string $lang_from, string $lang_to) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $langFromName = $langNames[$lang_from]->getName();
    $langToName = $langNames[$lang_to]->getName();
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    // From UI, translation is always request from default entity language,
    // but nothing stops users from using different $lang_from.
    if ($entity->language()->getId() !== $lang_from
      && $entity->hasTranslation($lang_from)) {
      $entity = $entity->getTranslation($lang_from);
    }
    // @todo Check if content type has bundles.
    $bundle = $entity->bundle();
    $labelField = $entity->getEntityType()->getKey('label');
    $bundleFields[$labelField]['label'] = 'Title';

    $allowed_values = [
      'text',
      'text_with_summary',
      'text_long',
      'string',
      'string_long',
    ];
    foreach ($this->entityFieldManager->getFieldDefinitions(
      $entity_type, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle()) && in_array($field_definition->getType(), $allowed_values)) {
        $bundleFields[$field_name]['label'] = $field_definition->getLabel();
      }
    }

    foreach ($bundleFields as $field_name => $label) {
      $field = $entity->get($field_name);
      if ($field->isEmpty()) {
        continue;
      }

      // Fields may have multiple values.
      foreach ($field as $delta => $singleValue) {
        $content = $singleValue->getValue();
        if (empty($content['value'])) {
          continue;
        }
        $content['value'] = $this->translateContent($content['value'], $langNames[$lang_from], $langNames[$lang_to]);
        $bundleFields[$field_name][$delta] = $content;
      }
    }
    $insertTranslation = $this->insertTranslation($entity, $lang_to, $bundleFields);

    $response = new RedirectResponse(Url::fromRoute("entity.$entity_type.content_translation_overview",
      ['entity_type_id' => $entity_type, $entity_type => $entity_id])
      ->setAbsolute(TRUE)->toString());
    $response->send();
    $messenger = $this->messenger();

    if ($insertTranslation) {
      $messenger->addStatus($this->t('Content translated successfully.'));
    }
    else {
      $messenger->addError($this->t('There was some issue with content translation.'));
    }
    return $response;
  }

  /**
   * Get the text translated by AI API call.
   *
   * @param string $input_text
   *   Input prompt for the LLm.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   Source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language.
   *
   * @return string
   *   Translated content.
   */
  public function translateContent(string $input_text, $langFrom, $langTo) {
    static $provider;
    static $modelId;
    if (empty($provider)) {
      $default_providers = $this->aiConfig->get('default_providers') ?? [];
      $modelId = $default_providers['chat']['model_id'];
      try {
        $provider = $this->aiProviderManager->createInstance(
          $default_providers['chat']['provider_id']);
      }
      catch (InvalidPluginDefinitionException | PluginException) {
        return '';
      }
    }
    $prompt = $this->config('ai_translate.settings')->get('prompt');
    $promptText = $this->twig->renderInline($prompt, [
      'source_lang' => $langFrom->id(),
      'source_lang_name' => $langFrom->getName(),
      'dest_lang' => $langTo->id(),
      'dest_lang_name' => $langTo->getName(),
      'input_text' => $input_text,
    ]);
    try {
      $messages = new ChatInput([
        new chatMessage('system', 'You are helpful translator. '),
        new chatMessage('user', $promptText),
      ]);
      /** @var /Drupal\ai\OperationType\Chat\ChatOutput $message */
      $message = $provider->chat($messages, $modelId)->getNormalized();
    }
    catch (GuzzleException $exception) {
      // Error handling for the API call.
      return $exception->getMessage();
    }
    $cleaned = trim(trim($message->getText(), '```'), ' ');
    return trim($cleaned, '"');
  }

  /**
   * Adding the translation in database and linking it to the original entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string $target_language
   *   The target language.
   * @param array $bundleFields
   *   An array of field name and their translation.
   */
  public function insertTranslation(
    ContentEntityInterface $entity,
    string $target_language,
    array $bundleFields,
  ) {
    if ($entity->hasTranslation($target_language)) {
      return;
    }
    $translation = $entity->addTranslation($target_language, $bundleFields);
    $status = TRUE;

    foreach ($bundleFields as $field_name => $newValue) {
      unset($newValue['label']);
      $translation->set($field_name, $newValue);
    }

    try {
      $translation->save();
    }
    catch (EntityStorageException) {
      $status = FALSE;
    }
    return $status;
  }

}
