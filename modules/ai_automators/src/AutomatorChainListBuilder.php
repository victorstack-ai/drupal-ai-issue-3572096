<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the automator chain entity type.
 */
final class AutomatorChainListBuilder extends EntityListBuilder {

  /**
   * Returns the header row for the automator chain list.
   *
   * @return array<string, string>
   *   The header row.
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
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
    /** @var \Drupal\ai_automators\AutomatorChainInterface $entity */
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

  /**
   * A renderable array for this list builder.
   *
   * @return array<string,mixed>
   *   A renderable array.
   */
  public function render(): array {
    $build = parent::render();

    $build['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This is where you can view or amend your Disposable Automator Chain entities. <strong>This page is only available to assist with developer debugging</strong> and it is recommended that you disable access to it on production sites.'),
      '#weight' => -50,
    ];

    return $build;
  }

}
