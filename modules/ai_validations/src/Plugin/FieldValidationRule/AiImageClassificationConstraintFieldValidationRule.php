<?php

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides functionality for AI Image Classification.
 *
 * @FieldValidationRule(
 *   id = "ai_image_classification constraint_rule",
 *   label = @Translation("AI image classification constraint"),
 *   description = @Translation("Uses Image classification AI to validate the field.")
 * )
 */
class AiImageClassificationConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {


  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * {@inheritdoc}
   */
  final public function __construct($configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, Token $token_service, AiProviderPluginManager $aiProvider) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $token_service);
    $this->aiProvider = $aiProvider;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('field_validation'),
      $container->get('token'),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return "AiImageClassification";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'classification_tag' => NULL,
      'finder' => 'exact',
      'model' => NULL,
      'min_confidence' => 0.8,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $options = $this->aiProvider->getSimpleProviderModelOptions('image_classification');
    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Classification Model'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $this->configuration['model'] ?? '',
    ];

    $form['classification_tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classification Tag'),
      '#description' => $this->t('The tag that the image should be classified as.'),
      '#default_value' => $this->configuration['classification_tag'] ?? '',
      '#required' => TRUE,
    ];

    $form['finder'] = [
      '#type' => 'select',
      '#title' => $this->t('Finder'),
      '#options' => [
        'exact' => $this->t('Exact'),
        'contains' => $this->t('Contains'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['finder'] ?? 'exact',
    ];

    $form['min_confidence'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Confidence'),
      '#description' => $this->t('The minimum confidence level required for the classification to trigger.'),
      '#default_value' => $this->configuration['min_confidence'] ?? 0.8,
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.001,
    ];

    $form['model_not_available'] = [
      'type' => 'select',
      '#title' => $this->t('If model is not available'),
      '#options' => [
        'skip' => $this->t('Skip validation'),
        'fail' => $this->t('Fail validation'),
      ],
      '#default_value' => $this->configuration['model_not_available'] ?? 'skip',
      '#description' => $this->t('What to do if the model is not available.'),
    ];

    $message = 'This value is not valid.';
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['classification_tag'] = $form_state->getValue('classification_tag');
    $this->configuration['finder'] = $form_state->getValue('finder');
    $this->configuration['model'] = $form_state->getValue('model');
    $this->configuration['min_confidence'] = $form_state->getValue('min_confidence');
    $this->configuration['model_not_available'] = $form_state->getValue('model_not_available');
  }

}
