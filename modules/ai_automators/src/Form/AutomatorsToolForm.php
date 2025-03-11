<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_automators\Entity\AutomatorsTool;

/**
 * Automators Tool form.
 */
final class AutomatorsToolForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AutomatorsTool::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $workflow = $form_state->getValue('workflow') ?? $this->entity->get('workflow');

    $form['workflow'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Workflow'),
      '#default_value' => $workflow,
      '#required' => TRUE,
      '#description' => $this->t('This is the AI Interpolator workflow that will be used for this agent.'),
      '#ajax' => [
        'callback' => '::getWorkflow',
        'wrapper' => 'field-connections-wrapper',
        'event' => 'change',
      ],
      '#autocomplete_route_name' => 'ai_automators.autocomplete.workflows',
    ];

    $form['remove_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Garbage Collect'),
      '#default_value' => $this->entity->get('remove_entity'),
      '#description' => $this->t('Remove the entity from the database when the agent has successfully completed the task or a task that was closed for other reasons. <strong>Obviously do not enable this for workflows where this is the end product and end storage.</strong>'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
      '#description' => $this->t('If you disable an agent that has worker tasks connected to them, they will be assigned to the fallback user.'),
    ];

    $form['field_connections'] = [
      '#type' => 'details',
      '#title' => $workflow ? $this->t('Field Connection %workflow', [
        '%workflow' => $workflow,
      ]) : $this->t('Choose workflow first'),
      '#attributes' => [
        'id' => 'field-connections-wrapper',
      ],
      '#open' => $workflow,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Ajax callback for the workflow field.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function getWorkflow(array $form, FormStateInterface $form_state): array {
    return $form['field_connections'];
  }

}
