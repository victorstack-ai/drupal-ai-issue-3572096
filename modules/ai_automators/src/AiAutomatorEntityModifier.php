<?php

namespace Drupal\ai_automators;

use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Event\AutomatorConfigEvent;
use Drupal\ai_automators\Event\ProcessFieldEvent;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorDirectProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper for entity saving logic.
 */
class AiAutomatorEntityModifier {

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The process manager.
   */
  protected AiAutomatorFieldProcessManager $processes;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an entity modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\ai_automators\AiFieldRules $aiFieldRules
   *   The field rules.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiAutomatorFieldProcessManager $processes, AiFieldRules $aiFieldRules, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager) {
    $this->fieldManager = $fieldManager;
    $this->processes = $processes;
    $this->fieldRules = $aiFieldRules;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Field form should have form altered to allow automatic content generation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param bool $isInsert
   *   Is it an insert.
   * @param string|null $specificField
   *   If a specific field should be processed, this is the field name.
   * @param bool $isAutomated
   *   If this is an automated process or not.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity or NULL if no automator fields are found.
   */
  public function saveEntity(EntityInterface $entity, $isInsert = FALSE, $specificField = NULL, $isAutomated = TRUE) {
    // Only run on Content Interfaces.
    if (!($entity instanceof ContentEntityInterface)) {
      return NULL;
    }
    // Get and check so field configs exists.
    $automators = $this->entityHasAutomators($entity, $specificField);
    if (!count($automators)) {
      return NULL;
    }

    // Sort automators by weight to ensure proper execution order.
    uasort($automators, function ($a, $b) {
      return $a->get('weight') <=> $b->get('weight');
    });

    // Get process for this entity.
    /** @var array<string, \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface> $processes */
    $processes = $this->getProcesses($automators);

    // Preprocess.
    foreach ($processes as $process) {
      $process->preProcessing($entity);
    }

    // Walk through the automators and mark fields for processing.
    /** @var \Drupal\ai_automators\AiAutomatorInterface $automator */
    foreach ($automators as $automator) {
      $automatorTypes = $automator->getAutomatorTypes();
      if (!count($automatorTypes)) {
        continue;
      }
      $fieldDefinition = $automator->getFieldDefinition();

      /** @var \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType */
      foreach ($automatorTypes as $automatorType) {
        $automatorConfig = $automatorType->getConfiguration();
        // Event where you can change the field configs when something exists.
        if (!empty($automatorConfig['settings'])) {
          $event = new AutomatorConfigEvent($entity, $automatorConfig['settings']);
          $this->eventDispatcher->dispatch($event, AutomatorConfigEvent::EVENT_NAME);
          $automatorConfig['settings'] = $event->getAutomatorConfig();
        }
        // Load the processor or load direct.
        $processor = $processes[$automator->get('worker_type')] ?? $processes['direct'];
        // If the processor is a dynamic process and its automatic, we skip it.
        if ($isAutomated && $processor instanceof AiAutomatorDirectProcessInterface) {
          continue;
        }

        if (method_exists($processor, 'isImport') && $isInsert) {
          $this->markFieldForProcessing($entity, $fieldDefinition, $automatorType, $processor);
        }
        if (!method_exists($processor, 'isImport') && !$isInsert) {
          $this->markFieldForProcessing($entity, $fieldDefinition, $automatorType, $processor);
        }
      }
    }

    // Postprocess.
    foreach ($processes as $process) {
      $process->postProcessing($entity);
    }
    return $entity;
  }

  /**
   * Checks if an entity has fields with automator enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param string|null $specificField
   *   If a specific field should be processed, this is the field name.
   *
   * @return array<\Drupal\ai_automators\AiAutomatorInterface>
   *   An array of Automators for this entity.
   */
  public function entityHasAutomators(EntityInterface $entity, $specificField = NULL): array {
    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $properties = [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ];
    if ($specificField) {
      $properties['field_name'] = $specificField;
    }
    /** @var \Drupal\ai_automators\AiAutomatorInterface[] $automators */
    $automators = $storage->loadByProperties($properties);
    return $automators;
  }

  /**
   * Gets the processes available.
   *
   * @param array<\Drupal\ai_automators\AiAutomatorInterface> $automators
   *   The enabled automators.
   *
   * @return array<string,\Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface>
   *   Array of processes keyed by id.
   */
  public function getProcesses(array $automators): array {
    // Get possible processes.
    $processes = [];
    foreach ($automators as $automator) {
      $definition = $this->processes->getDefinition($automator->get('worker_type'));
      /** @var \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface $process */
      $process = $this->processes->createInstance($definition['id']);
      $processes[$definition['id']] = $process;
    }
    return $processes;
  }

  /**
   * Checks if a field should be saved and saves it appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The automator type.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface $processor
   *   The processor.
   *
   * @return bool
   *   If the saving was successful or not.
   */
  protected function markFieldForProcessing(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType, AiAutomatorFieldProcessInterface $processor) {
    $automatorTypeConfig = $automatorType->getConfiguration();
    $automatorTypeSettings = $automatorTypeConfig['settings'] ?? [];

    // Event to modify if the field should be processed.
    $event = new ProcessFieldEvent($entity, $fieldDefinition, $automatorTypeSettings);
    $this->eventDispatcher->dispatch($event, ProcessFieldEvent::EVENT_NAME);
    // If a force reject or force process exists, we do that.
    if (in_array(ProcessFieldEvent::FIELD_FORCE_SKIP, $event->actions)) {
      return FALSE;
    }
    elseif (in_array(ProcessFieldEvent::FIELD_FORCE_PROCESS, $event->actions)) {
      return $processor->modify($entity, $fieldDefinition, $automatorType);
    }

    // If the type is of AiAutomatorDirectProcessInterface, it checks first.
    if ($processor instanceof AiAutomatorDirectProcessInterface && $processor->shouldProcessDirectly($entity, $fieldDefinition, $automatorTypeSettings)) {
      // If the processor wants to process directly, we do that.
      return $processor->modify($entity, $fieldDefinition, $automatorType);
    }

    // Otherwise continue as normal.
    if ((!isset($automatorTypeSettings['mode']) || $automatorTypeSettings['mode'] == 'base') && !$this->baseShouldSave($entity, $fieldDefinition, $automatorType)) {
      return FALSE;
    }
    elseif (isset($automatorTypeSettings['mode']) && $automatorTypeSettings['mode'] == 'token' && !$this->tokenShouldSave($entity, $fieldDefinition, $automatorType)) {
      return FALSE;
    }

    return $processor->modify($entity, $fieldDefinition, $automatorType);
  }

  /**
   * If token mode, check if it should run.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The automator type.
   *
   * @return bool
   *   If it should save or not.
   */
  private function tokenShouldSave(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType): bool {
    // @todo this could be simplified in automator type.
    $automatorConfig = $automatorType->getConfiguration();
    $fieldName = $fieldDefinition->getName();
    $value = $entity->get($fieldName)->getValue();
    $value = $automatorType->checkIfEmpty($value, $automatorConfig['settings']);

    // Get prompt.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * If base mode, check if it should run.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The automator type.
   *
   * @return bool
   *   If it should save or not.
   */
  private function baseShouldSave(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType): bool {
    // Check if a value exists.
    $fieldName = $fieldDefinition->getName();
    $value = $entity->get($fieldName)->getValue();

    // Get automator config.
    $automatorConfig = $automatorType->getConfiguration();
    $settings = $automatorConfig['settings'] ?? [];

    $original = isset($entity->original) && json_encode($entity->original->get($settings['base_field'])->getValue());
    $change = json_encode($entity->get($settings['base_field'])->getValue()) !== $original;

    // Get the rule to check the value.
    $value = $automatorType->checkIfEmpty($value, $settings);

    // If the base field is not filled out.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    // If the value exists and we don't have edit mode, we do nothing.
    if (!empty($value) && !empty($value[0]) && !$settings['edit_mode']) {
      return FALSE;
    }
    // Otherwise look for a change.
    if ($settings['edit_mode'] && !$change && !empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

}
