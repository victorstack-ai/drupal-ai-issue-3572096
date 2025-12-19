<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of automators tools.
 */
final class AutomatorsToolListBuilder extends ConfigEntityListBuilder {

  /**
   * Returns the header row for the automator chain list.
   *
   * @return array<string, string>
   *   The header row.
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * Returns a single row in the automator chain list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array<string, mixed>
   *   The row data.
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai_automators\AutomatorsToolInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
