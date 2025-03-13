<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The function for tools calling.
 */
class ToolsFunctionInput implements ToolsFunctionInputInterface {

  /**
   * The name of the function.
   *
   * @var string
   */
  private string $name = '';

  /**
   * The description of the function.
   *
   * @var string
   */
  private string $description = "";

  /**
   * The properties of the function.
   *
   * @var \Drupal\ai\OperationType\Chat\ToolsPropertyInputInterface[]
   */
  private array $properties = [];

  /**
   * The required properties of the function.
   *
   * @var \Drupal\ai\OperationType\Chat\ToolsPropertyInputInterface[]
   */
  private array $requiredProperties = [];

  /**
   * The instructions.
   */
  public function __construct(string $name = "", array $function = []) {
    if (!empty($name) && !empty($function)) {
      $this->setFromArray($name, $function);
    }
    elseif (!empty($name)) {
      $this->setName($name);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritDoc}
   */
  public function setName(string $name) {
    $this->name = $name;
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritDoc}
   */
  public function setDescription(string $description) {
    $this->description = $description;
  }

  /**
   * {@inheritDoc}
   */
  public function getProperties(): array {
    return $this->properties;
  }

  /**
   * {@inheritDoc}
   */
  public function getPropertyByName(string $name): ?ToolsPropertyInputInterface {
    return $this->properties[$name] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setProperty(ToolsPropertyInputInterface $property) {
    $this->properties[$property->getName()] = $property;
    if ($property->isRequired()) {
      $this->setRequiredProperty($property);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function setProperties(array $properties) {
    foreach ($properties as $property) {
      $this->properties[$property->getName()] = $property;
      if ($property->isRequired()) {
        $this->setRequiredProperty($property);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getRequiredProperties(): array {
    return $this->requiredProperties;
  }

  /**
   * {@inheritDoc}
   */
  public function setRequiredProperty(ToolsPropertyInputInterface $requiredProperty) {
    $this->requiredProperties[$requiredProperty->getName()] = $requiredProperty;
  }

  /**
   * {@inheritDoc}
   */
  public function setRequiredProperties(array $requiredProperties) {
    foreach ($requiredProperties as $property) {
      $this->requiredProperties[$property->getName()] = $property;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function setFromArray(string $name, array $function) {
    $this->name = $name;
    if (isset($function['description'])) {
      $this->description = $function['description'];
    }
    if (isset($function['properties'])) {
      $this->setProperties($function['properties']);
    }
    if (isset($function['required'])) {
      $this->setRequiredProperties($function['required']);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function renderFunctionArray(): array {
    $properties = [];
    foreach ($this->properties as $property) {
      $properties[$property->getName()] = $property->renderPropertyArray();
    }
    $function = [
      'name' => $this->name,
      'description' => $this->description,
    ];
    if (!empty($properties)) {
      $function['parameters'] = [
        'type' => 'object',
        'properties' => $properties,
      ];
    }
    if (!empty($this->requiredProperties)) {
      $requiredProperties = [];
      foreach ($this->requiredProperties as $requiredProperty) {
        $requiredProperties[] = $requiredProperty->getName();
      }
      $function['parameters']['required'] = $requiredProperties;
    }
    return $function;
  }

}
