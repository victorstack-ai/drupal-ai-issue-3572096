<?php

namespace Drupal\ai_automators\FormAlter;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_automators\AiFieldRules;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Drupal\Core\Url;

/**
 * A helper to store configs for fields.
 */
class AiAutomatorFieldConfig {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Constructs a field config modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\AiFieldRules $fieldRules
   *   The field rule manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match interface.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityFieldManagerInterface $fieldManager,
    protected AiFieldRules $fieldRules,
    protected RouteMatchInterface $routeMatch,
    protected AiAutomatorFieldProcessManager $processes,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Alter the form with field config if applicable.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function alterForm(array &$form, FormStateInterface $formState): void {
    // Get the entity and the field name.
    $entity = $form['#entity'];

    // Try different ways to get the field name.
    $fieldName = NULL;
    $routeParameters = $this->routeMatch->getParameters()->all();
    if (!empty($routeParameters['field_name'])) {
      $fieldName = $routeParameters['field_name'];
    }
    elseif (!empty($routeParameters['field_config'])) {
      $fieldName = $routeParameters['field_config']->getName();
    }
    elseif (!empty($routeParameters['base_field_override'])) {
      $fieldName = $routeParameters['base_field_override']->getName();
    }

    // If no field name it is not for us.
    if (!$fieldName) {
      return;
    }

    // Get the field config.
    $fields = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    /** @var \Drupal\field\Entity\FieldConfig $fieldInfo */
    $fieldInfo = $fields[$fieldName] ?? NULL;

    // The info might not have been saved yet.
    if (!$fieldInfo) {
      return;
    }

    // Find the rules. If not found don't do anything.
    $rules = $this->fieldRules->findRuleCandidates($entity, $fieldInfo);
    if (empty($rules)) {
      return;
    }

    // Get the default config if it exists.
    $aiConfig = $this->entityTypeManager->getStorage('ai_automator')
      ->loadByProperties([
        'entity_type' => $form['#entity']->getEntityTypeId(),
        'bundle' => $form['#entity']->bundle(),
        'field_name' => $fieldInfo->getName(),
      ]);

    $add_url = Url::fromRoute('entity.ai_automator.add_form', [], [
      'query' => [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'field_name' => $fieldInfo->getName(),
      ],
    ]);

    $form['automators'] = [
      '#type' => 'link',
      '#title' => $aiConfig ? $this->t('Edit AI Automator') : $this->t('Add AI Automator'),
      '#url' => $aiConfig ? reset($aiConfig)->toUrl('edit-form') : $add_url,
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'target' => '_blank',
      ],
      '#weight' => 15,
    ];
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  public function getEntityTokenType(string $entityTypeId): string {
    return ($entityTypeId == 'taxonomy_term') ? 'term' : $entityTypeId;
  }

}
