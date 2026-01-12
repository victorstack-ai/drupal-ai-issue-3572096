<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The File action.
 */
#[FieldWidgetAction(
  id: 'automator_file',
  label: new TranslatableMarkup('Automator File'),
  widget_types: ['file_generic', 'file_default'],
  field_types: ['file'],
)]
class File extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = '';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $key = $array_parents[2] ?? 0;
    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    if (is_null($key)) {
      // If no key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->target_id) {
          $form[$form_key]['widget'][$index]['#value'] = ['fids' => [$item->target_id]];
        }
      }
    }
    else {
      // Handle specific key/index.
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        if ($item && $item->target_id) {
          $form[$form_key]['widget'][$key]['#value'] = ['fids' => [$item->target_id]];
        }
      }
    }

    return $form[$form_key];
  }

}
