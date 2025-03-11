<?php

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface;

/**
 * This service helps with creating a form from function calling properties.
 */
class PropertyFormBuilder {

  /**
   * Constructor.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   */
  public function __construct(
    protected FunctionCallPluginManager $functionCallPluginManager,
  ) {
  }

  /**
   * Create the form elements for the function call properties.
   *
   * @param string $function_call_id
   *   The function call id.
   *
   * @return array
   *   The form elements.
   */
  public function createFormElements(string $function_call_id): array {
    $function_call = $this->functionCallPluginManager->createInstance($function_call_id);
    $normalized = $function_call->normalize();
    $form = [];
    foreach ($normalized->getProperties() as $property) {
      $form[$property->getName()] = $this->formElementFromProperty($property);
    }

    return $form;
  }

  /**
   * From element from property.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface $property
   *   The property.
   *
   * @return array
   *   The form element.
   */
  public function formElementFromProperty(ToolsPropertyInputInterface $property): array {
    $form_element = [
      '#title' => $property->getName(),
      '#description' => $property->getDescription(),
      '#default_value' => $property->getDefault(),
      '#required' => $property->isRequired(),
    ];
    switch ($property->getType()) {
      case 'string':
        if (!empty($property->getEnum())) {
          $form_element['#type'] = 'select';
          $form_element['#options'] = $property->getEnum();
        }
        else {
          // We don't know the size, so a 2 rows textarea is a good default.
          if ($property->getMaxLength() && $property->getMaxLength() < 255) {
            $form_element['#type'] = 'textfield';
          }
          else {
            $form_element['#type'] = 'textarea';
            $form_element['#rows'] = 1;
          }
        }
        if ($property->getExampleValue()) {
          $form_element['#attributes']['placeholder'] = $property->getExampleValue();
        }
        break;

      case 'bool':
      case 'boolean':
        $form_element['#type'] = 'checkbox';
        break;

      case 'number':
      case 'integer':
        $form_element['#type'] = 'number';
        if ($property->getExampleValue()) {
          $form_element['#attributes']['placeholder'] = $property->getExampleValue();
        }
        if (!empty($property->getEnum())) {
          $form_element['#type'] = 'select';
          $form_element['#options'] = $property->getEnum();
        }
        break;

      default:
        $form_element['#type'] = 'textfield';
        break;
    }
    return $form_element;
  }

}
