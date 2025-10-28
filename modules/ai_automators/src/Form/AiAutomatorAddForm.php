<?php

namespace Drupal\ai_automators\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for adding AI automators.
 */
class AiAutomatorAddForm extends AiAutomatorFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // Redirect to the edit form of the newly created entity.
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    $this->messenger()->addStatus($this->t('Field Automator %name was created.', ['%name' => $this->entity->label()]));
  }

}
