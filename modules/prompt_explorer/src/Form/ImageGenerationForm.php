<?php

declare(strict_types=1);

namespace Drupal\prompt_explorer\Form;

use Drupal\ai\Enum\Bundles;
use Drupal\ai\Service\LlmFormProviderHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for images.
 */
class ImageGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\LlmProviderHelper
   */
  protected $llmProviderHelper;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'prompt_explorer_image_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->llmProviderHelper = $container->get('ai.form_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['response'] = [
      '#type' => 'inline_template',
      '#template' => '{{ images|raw }}',
      '#context' => [
        'images' => '<div id="ai-image-response"><h2>Image will appear here.</h2></div>',
      ],
    ];

    // Load the LLM configurations.
    $this->llmProviderHelper->generateLlmProvidersForm($form, $form_state, Bundles::TextToImage, 'image_generator', LlmFormProviderHelper::FORM_CONFIGURATION_FULL);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Image'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-image-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->llmProviderHelper->generateLlmProviderFromFormSubmit($form, $form_state, Bundles::TextToImage, 'image_generator');
    $images = $provider->invokeModelResponse(Bundles::TextToImage, $form_state->getValue('image_generator_ai_model'), $form_state->getValue('prompt'), TRUE);
    $response = '';
    foreach ($images as $image) {
      $response .= '<img src="data:image/png;charset=utf-8;base64,' . $image . '" />';
    }

    $form['response']['#context'] = [
      'images' => '<div id="ai-image-response"><h2>Image will appear here.</h2>' . $response . '</div>',
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
