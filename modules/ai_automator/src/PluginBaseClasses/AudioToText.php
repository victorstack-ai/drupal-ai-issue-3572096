<?php

namespace Drupal\ai_automator\PluginBaseClasses;

use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_automator\PluginBaseClasses\RuleBase;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Helper function for audio to text.
 */
class AudioToText extends RuleBase implements AiAutomatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM Audio To Text';

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'speech_to_text';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $values = [];
    $instance = $this->aiPluginManager->createInstance($automatorConfig['ai_provider']);

    // Get configuration.
    $config = [];
    $configCast = $instance->getAvailableConfiguration('speech_to_text', $automatorConfig['ai_model']);
    foreach ($automatorConfig as $key => $val) {
      if (strpos($key, 'configuration_') === 0 && $val) {
        $configKey = str_replace('configuration_', '', $key);
        $config[$configKey] = CastUtility::typeCast($configCast[$configKey]['type'], $val);
      }
    }
    foreach ($entity->{$automatorConfig['base_field']} as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'audio/mpeg',
          'audio/aac',
          'audio/wav',
        ])) {
          $input = new SpeechToTextInput(file_get_contents($fileEntity->getFileUri()));
          $response = $instance->speechToText($input, $automatorConfig['ai_model'], ['ai_automator_speech_to_text']);
          $values[] = $response->getNormalized();
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Should be a string.
    if (!is_string($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }
}
