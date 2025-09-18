<?php

namespace Drupal\ai\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * A custom data type for YAML as string for AI Providers.
 */
#[DataType(
  id: "yaml_as_string",
  label: new TranslatableMarkup("YAML as String"),
)]
class Yaml extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    return $this->getString();
  }

}
