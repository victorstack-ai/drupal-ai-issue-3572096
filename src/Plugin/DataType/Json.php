<?php

namespace Drupal\ai\Plugin\DataType;

use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * A custom data type for JSON as string for AI Providers.
 */
#[DataType(
  id: "json_as_string",
  label: new TranslatableMarkup("JSON as String"),
)]
class Json extends TypedData {

  /**
   * {@inheritdoc}
   */
  #[JsonSchema(['type' => 'string'])]
  public function getCastedValue() {
    return $this->getString();
  }

}
