<?php

namespace Drupal\ai_ckeditor\PluginInterfaces;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Provides an interface for implementing ai_ckeditor plugins.
 */
interface AiCKEditorPluginInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return string
   *   The translated description.
   */
  public function description(): string;

  /**
   * Returns the built form for CKEditor.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $settings
   *   The settings array.
   *
   * @return array<mixed>
   *   The form array.
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array;

  /**
   * Validates the form for CKEditor.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $settings
   *   The settings array.
   *
   * @return array<mixed>
   *   The form array.
   */
  public function validateCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array;

  /**
   * Submits the form for CKEditor.
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<mixed>|\Drupal\Core\Ajax\AjaxResponse
   *   The form array.
   */
  public function submitCkEditorModalForm(array $form, FormStateInterface $form_state): array|AjaxResponse;

  /**
   * Returns available editors if the plugin provides many.
   *
   * @return array<string,string>
   *   The array of editors with id and label.
   */
  public function availableEditors(): array;

  /**
   * AJAX callback for AI generation.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|null
   *   The AJAX response, or NULL if not relevant.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state): ?AjaxResponse;

}
