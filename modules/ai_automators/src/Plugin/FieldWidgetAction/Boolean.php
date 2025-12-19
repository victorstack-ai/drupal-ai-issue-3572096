<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Boolean action.
 */
#[FieldWidgetAction(
  id: 'automator_boolean',
  label: new TranslatableMarkup('Automator Boolean'),
  widget_types: ['boolean_checkbox'],
  field_types: ['boolean'],
)]
class Boolean extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'value';

  /**
   * Ajax handler for Automators.
   *
   * @param array<mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<mixed>
   *   The updated form element.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $key = $array_parents[2] ?? 0;

    // Cast to integer to ensure proper type.
    $key = is_numeric($key) ? (int) $key : 0;

    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * Save form values.
   *
   * @param array<mixed> $form
   *   The form.
   * @param string $form_key
   *   The form key.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param int|null $key
   *   The key.
   *
   * @return array<mixed>
   *   The form values.
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // Get the first (and only) item from the boolean field.
    if (isset($entity->get($form_key)->getValue()[0]) && $entity->get($form_key)->getValue()[0]->get($this->formElementProperty) != NULL) {
      $value = $entity->get($form_key)->getValue()[0]->get($this->formElementProperty)->getValue();
      // For boolean fields, set both value and checked state.
      $form[$form_key]['widget'][$this->formElementProperty]['#value'] = $value ? 1 : 0;
      $form[$form_key]['widget'][$this->formElementProperty]['#checked'] = (bool) $value;
      $form[$form_key]['widget'][$this->formElementProperty]['#default_value'] = $value ? 1 : 0;
    }

    return $form[$form_key];
  }

}
