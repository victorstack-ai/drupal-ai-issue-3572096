<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for image generators.
 */
class TextToMediaImage extends RuleBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'text_to_image';

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $fieldManager;


  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   */
  final public function __construct(
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    EntityTypeBundleInfo $entityTypeBundleInfo,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $fieldManager
  ) {
    parent::__construct($plugin_id, $plugin_definition, $pluginManager, $formHelper);
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldManager = $fieldManager;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate images from text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "{{ context }}, 50mm portrait photography, hard rim lighting photography-beta";
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    $options = [];
    $types = $this->entityTypeBundleInfo->getBundleInfo('media');
    foreach ($types as $key => $type) {
      $options[$key] = $type['label'];
    }

    $form['automator_llm_media_type'] = [
      '#type' => 'select',
      '#title' => 'Media Type',
      '#description' => $this->t('Media Type to create'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_llm_media_type'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    parent::validateConfigValues($form, $formState);
    $values = $formState->getValues();
    if (empty($values['llm_media_type'])) {
      $formState->setErrorByName('llm_media_type', 'Media Type is required.');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderTokenPrompt($automatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Generate the images.
    $images = [];
    $instance = $this->prepareLlmInstance('text_to_image', $automatorConfig);

    foreach ($prompts as $prompt) {
      // The image binary.
      $input = new TextToImageInput($prompt);
      $response = $instance->textToImage($input, $automatorConfig['ai_model'])->getNormalized();
      if (!empty($response)) {
        foreach ($response as $imageBinary) {
          $images[] = [
            'filename' => $this->getFileName($automatorConfig),
            'binary' => $imageBinary,
            'prompt' => $prompt,
          ];
        }
      }
    }
    return $images;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (!isset($value['filename'])) {
      return FALSE;
    }
    // Detect if binary.
    return preg_match('~[^\x20-\x7E\t\r\n]~', $value['binary']) > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $medias = [];

    // Prepare storage.
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $mediaType = $automatorConfig['llm_media_type'];
    $mediaTypeInterface = $this->entityTypeManager->getStorage('media_type')->load($mediaType);
    /** @var \Drupal\media\Entity\Media */
    $media = $mediaStorage->create([
      'name' => 'tmp',
      'bundle' => $mediaType,
    ]);
    $sourceField = $media->getSource()->getSourceFieldDefinition($mediaTypeInterface);
    $fileField = $sourceField->getName();
    $mediaFields = $this->fieldManager->getFieldDefinitions('media', $mediaType);

    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $mediaFields[$fileField], $entity);
      $imageData = $fileHelper->generateImageMetaDataFromBinary($value['binary'], $path);
      $media = $mediaStorage->create([
        'name' => substr($value['prompt'], 0, 250),
        'bundle' => $mediaType,
        $fileField => $imageData,
      ]);
      $media->save();

      $medias[] = $media->id();
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $medias);
    return TRUE;
  }

  /**
   * Gets the filename. Override this.
   *
   * @param array $args
   *   If arguments are needed to create the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFileName(array $args = []) {
    return 'ai_generated.jpg';
  }

  /**
   * Gets the file helper.
   *
   * @return \Drupal\ai_automator\Rulehelpers\FileHelper
   *   The file helper.
   */
  public function getFileHelper() {
    return \Drupal::service('ai_automator.rule_helper.file');
  }

}
