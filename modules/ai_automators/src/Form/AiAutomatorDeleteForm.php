<?php

namespace Drupal\ai_automators\Form;

use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirm form to delete an automator type from an AI Automator.
 */
class AiAutomatorDeleteForm extends ConfirmFormBase {

  /**
   * The AI Automator entity.
   */
  protected ?AiAutomatorInterface $aiAutomator = NULL;

  /**
   * The automator type plugin instance.
   */
  protected ?AiAutomatorTypeInterface $aiAutomatorType = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_automator_type_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = $this->aiAutomatorType?->label() ?? $this->t('this automator type');
    return $this->t("Are you sure you want to delete '@label'?", ['@label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->aiAutomator?->toUrl('edit-form') ?? Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AiAutomatorInterface $ai_automator = NULL, $ai_automator_type = NULL) {
    $this->aiAutomator = $ai_automator;

    if (!$this->aiAutomator) {
      throw new NotFoundHttpException();
    }

    $collection = $this->aiAutomator->getAutomatorTypes();
    if (!$collection->has($ai_automator_type)) {
      throw new NotFoundHttpException();
    }
    $this->aiAutomatorType = $collection->get($ai_automator_type);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->aiAutomator && $this->aiAutomatorType) {
      $uuid = $this->aiAutomatorType->getUuid();
      $this->aiAutomator->deleteAutomatorType($uuid);
      $this->aiAutomator->save();
      $this->messenger()->addStatus($this->t("The automator type '@label' has been deleted.", ['@label' => $this->aiAutomatorType->label()]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
