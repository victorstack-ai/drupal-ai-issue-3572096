<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Entity;

use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\AutomatorTypePluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the ai automator entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_automator",
 *   label = @Translation("AI Automator"),
 *   label_collection = @Translation("AI Automators"),
 *   label_singular = @Translation("ai automator"),
 *   label_plural = @Translation("ai automators"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai automator",
 *     plural = "@count ai automators",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_automators\AiAutomatorListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_automators\Form\AiAutomatorAddForm",
 *       "edit" = "Drupal\ai_automators\Form\AiAutomatorEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_automator",
 *   admin_permission = "administer ai_automator",
 *   links = {
 *     "collection" = "/admin/config/ai/ai-automators/fields",
 *     "add-form" = "/admin/config/ai/ai-automators/fields/add",
 *     "edit-form" = "/admin/config/ai/ai-automators/fields/{ai_automator}",
 *     "delete-form" = "/admin/config/ai/ai-automators/fields/{ai_automator}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "entity_type",
 *     "bundle",
 *     "field_name",
 *     "worker_type",
 *     "weight",
 *     "automator_types",
 *   },
 * )
 */
final class AiAutomator extends ConfigEntityBase implements AiAutomatorInterface, EntityWithPluginCollectionInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The AI Automator entity type.
   */
  protected string $entity_type;

  /**
   * The AI Automator bundle.
   */
  protected string $bundle;

  /**
   * The AI Automator field name.
   */
  protected string $field_name;

  /**
   * The worker type.
   *
   * @var string
   *
   * This is the plugin ID of the worker type plugin.
   */
  protected $worker_type = 'direct';

  /**
   * The weight of this automator.
   *
   * @var int
   */
  protected int $weight = 0;

  /**
   * The automator type plugins configuration.
   *
   * This must match the key used in getPluginCollections() and config_export.
   *
   * @var array
   */
  protected $automator_types = [];

  /**
   * Holds the collection of automator types that are used by this automator.
   *
   * @var \Drupal\ai_automators\AutomatorTypePluginCollection
   */
  protected $automatorTypesCollection;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // Set the dependencies its connected to.
    $this->addDependency('config', 'field.field.' . $this->entity_type . '.' . $this->bundle . '.' . $this->field_name);
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    // Get the field definition for the specified entity type and bundle.
    return $this->getEntityFieldManager()
      ->getFieldDefinitions($this->entity_type, $this->bundle)[$this->field_name] ?? NULL;
  }

  /**
   * Get a dummy entity of the type and bundle this automator is for.
   *
   * @todo we shouldn't need this.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A dummy entity of the type and bundle this automator is for.
   */
  public function getDummyEntity() {
    $entityDefinition = $this->entityTypeManager()->getDefinition($this->entity_type);
    return $this->entityTypeManager()->getStorage($this->entity_type)->create([
      $entityDefinition->getKey('bundle') => $this->bundle,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutomatorType($automator_type): AiAutomatorTypeInterface {
    return $this->getAutomatorTypes()->get($automator_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutomatorTypes(): AutomatorTypePluginCollection {
    if (!$this->automatorTypesCollection) {
      $this->automatorTypesCollection = new AutomatorTypePluginCollection($this->getAutomatorTypePluginManager(), $this->automator_types);
      $this->automatorTypesCollection->sort();
    }
    return $this->automatorTypesCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function addAutomatorType(array $configuration): string {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getAutomatorTypes()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    // The key must match the config property name: $automator_types.
    return ['automator_types' => $this->getAutomatorTypes()];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAutomatorType(string $uuid): void {
    // Remove from the plugin collection if supported.
    if ($this->automatorTypesCollection) {
      if ($this->automatorTypesCollection->has($uuid)) {
        $this->automatorTypesCollection->removeInstanceId($uuid);
      }
    }
    // Ensure the underlying config is in sync.
    if (isset($this->automator_types[$uuid])) {
      unset($this->automator_types[$uuid]);
    }
    // Rebuild the collection to reflect the updated configuration.
    if ($this->automatorTypesCollection) {
      $this->automatorTypesCollection = new AutomatorTypePluginCollection($this->getAutomatorTypePluginManager(), $this->automator_types);
      $this->automatorTypesCollection->sort();
    }
  }

  /**
   * Returns the automator type plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The automator type plugin manager.
   */
  protected function getAutomatorTypePluginManager() {
    return \Drupal::service('plugin.manager.ai_automator');
  }

  /**
   * Returns the entity field manager service.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager service.
   */
  protected function getEntityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

}
