<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorFieldRules;

use Drupal\ai_automator\Attribute\AiAutomatorFieldRule;
use Drupal\ai_automator\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text field.
 */
#[AiAutomatorFieldRule(
  id: 'llm_text',
  label: new TranslatableMarkup('LLM Text'),
  field_rule: 'text',
  target: '',
)]
class LlmText extends ComplexTextChat implements AiAutomatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Text';

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Get text format.
    $textFormat = $this->getGeneralHelper()->getTextFormat($fieldDefinition);

    // Then set the value.
    $cleanedValues = [];
    foreach ($values as $value) {
      $cleanedValues[] = [
        'value' => $value,
        'format' => $textFormat,
      ];
    }
    $entity->set($fieldDefinition->getName(), $cleanedValues);
  }

}
