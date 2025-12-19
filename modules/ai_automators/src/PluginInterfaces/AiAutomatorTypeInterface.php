<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_automators\AiAutomatorInterface;

/**
 * Interface for automator type modifiers.
 */
interface AiAutomatorTypeInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Does it need a prompt.
   *
   * @return bool
   *   If it needs a prompt or not.
   */
  public function needsPrompt();

  /**
   * Advanced mode.
   *
   * @return bool
   *   If tokens are available or not.
   */
  public function advancedMode();

  /**
   * Help text.
   *
   * @return string
   *   Help text to show.
   */
  public function helpText();

  /**
   * Allowed inputs.
   *
   * @return array<mixed>
   *   The array of field inputs to allow.
   */
  public function allowedInputs();

  /**
   * Returns the text that will be placed as placeholder in the textarea.
   *
   * @return string
   *   The text.
   */
  public function placeholderText();

  /**
   * Return the Tokens.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   *
   * @return array<string,mixed>
   *   Token with replacement as key and description as value.
   */
  public function tokens(ContentEntityInterface $entity);

  /**
   * Checks if the value is empty on complex field types.
   *
   * @param array<mixed> $value
   *   The value response.
   * @param array<string,mixed> $automatorConfig
   *   The automator config.
   *
   * @return mixed
   *   Return empty array if empty.
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []);

  /**
   * Check if the rule is allowed based on config.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return bool
   *   If its allowed or not.
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

  /**
   * Generate the Tokens.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array<mixed> $automatorConfig
   *   The automator config.
   * @param int $delta
   *   The delta in the values.
   *
   * @return array<mixed>
   *   Token key and token value.
   */
  public function generateTokens(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
    $delta,
  );

  /**
   * Generates a response.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array<string,mixed> $automatorConfig
   *   The automator config.
   *
   * @return array<mixed>
   *   An array of values.
   */
  public function generate(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  );

  /**
   * Verifies a value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param mixed $value
   *   The value returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array<string,mixed> $automatorConfig
   *   The automator config.
   *
   * @return bool
   *   True if verified, otherwise false.
   */
  public function verifyValue(
    ContentEntityInterface $entity,
    $value,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  );

  /**
   * Stores one or many values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param array<mixed> $values
   *   The array of mixed value(s) returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array<string,mixed> $automatorConfig
   *   The automator config.
   *
   * @return bool|void
   *   True if verified, otherwise false.
   */
  public function storeValues(
    ContentEntityInterface $entity,
    array $values,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  );

  /**
   * Returns the label of the automator type.
   *
   * @return string
   *   The label of the automator type.
   */
  public function label(): string;

  /**
   * Returns the unique ID representing the automator type.
   *
   * @return string
   *   The automator type ID.
   */
  public function getUuid();

  /**
   * Returns the weight of the automator type.
   *
   * @return int|string
   *   Either the integer weight of the automator type, or an empty string.
   */
  public function getWeight();

  /**
   * Sets the weight for this automator type.
   *
   * @param int $weight
   *   The weight for this automator type.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Build the base settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   *
   * @return array<string,mixed>
   *   The base configuration form array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, AiAutomatorInterface $automator): array;

  /**
   * Validate the base settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state, AiAutomatorInterface $automator): void;

  /**
   * Submit the base settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, AiAutomatorInterface $automator): void;

  /**
   * Build the advanced settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   *
   * @return array<string,mixed>
   *   The advanced configuration form array.
   */
  public function buildAdvancedConfigurationForm(array $form, FormStateInterface $form_state, AiAutomatorInterface $automator): array;

  /**
   * Validate the advanced settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   */
  public function validateAdvancedConfigurationForm(array &$form, FormStateInterface $form_state, AiAutomatorInterface $automator): void;

  /**
   * Submit the advanced settings section.
   *
   * @param array<string,mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator instance.
   */
  public function submitAdvancedConfigurationForm(array &$form, FormStateInterface $form_state, AiAutomatorInterface $automator): void;

  /**
   * Legacy extra advanced form fields.
   *
   * @todo move this to the automator type form and refactor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array<mixed> $defaultValues
   *   The default values.
   *
   * @return array<mixed>
   *   Form array with key starting with automator_{type}.
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []): array;

}
