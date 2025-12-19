<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of ai automators.
 */
final class AiAutomatorListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array<string, string>
   *   A render array structure of header strings.
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['entity_type'] = $this->t('Entity type');
    $header['bundle'] = $this->t('Bundle');
    $header['field_name'] = $this->t('Field');
    $header['worker_type'] = $this->t('Worker type');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for the entity listing.
   *
   * @return array<string, string>
   *   A render array structure of row strings.
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai_automators\AiAutomatorInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['entity_type'] = $entity->get('entity_type') ?? '';
    $row['bundle'] = $entity->get('bundle') ?? '';
    $row['field_name'] = $entity->get('field_name') ?? '';
    $row['worker_type'] = $entity->get('worker_type') ?? '';
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
