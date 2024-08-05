<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Entity;

use Drupal\ai_logging\AiLogInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the ai log entity class.
 *
 * @ContentEntityType(
 *   id = "ai_log",
 *   label = @Translation("AI Log"),
 *   label_collection = @Translation("AI Logs"),
 *   label_singular = @Translation("ai log"),
 *   label_plural = @Translation("ai logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai logs",
 *     plural = "@count ai logs",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_logging\AiLogListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ai_logging\Form\AiLogForm",
 *       "edit" = "Drupal\ai_logging\Form\AiLogForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "ai_log",
 *   admin_permission = "administer ai_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ai-log",
 *     "add-form" = "/ai-log/add",
 *     "canonical" = "/ai-log/{ai_log}",
 *     "edit-form" = "/ai-log/{ai_log}/edit",
 *     "delete-form" = "/ai-log/{ai_log}/delete",
 *     "delete-multiple-form" = "/admin/content/ai-log/delete-multiple",
 *   },
 * )
 */
final class AiLog extends ContentEntityBase implements AiLogInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the ai log was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Long string for prompt.
    $fields['prompt'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Prompt'))
      ->setDescription(t('The prompt for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['operation_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Type'))
      ->setDescription(t('The operation type.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setDescription(t('The provider.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['model'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Model'))
      ->setDescription(t('Model.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tags'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tags'))
      ->setDescription(t('The tags for the ai log.'))
      ->setCardinality(-1)
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['output_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Response'))
      ->setDescription(t('The output text for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['configuration'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Configuration'))
      ->setDescription(t('The configuration for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
