<?php

namespace Drupal\ai_content;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * AI Content form.
 */
class AiContentForm {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The AI Content config, immutable config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The Renederer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * List of all valid field options.
   *
   * @var array
   */
  protected $options;

  /**
   * Constructor.
   */
  public function __construct(AccountProxyInterface $account, ConfigFactoryInterface $configFactory, AiProviderPluginManager $aiProvider, RendererInterface $renderer) {
    $this->account = $account;
    $this->configFactory = $configFactory;
    $this->aiProvider = $aiProvider;
    $this->renderer = $renderer;
  }

  /**
   * Get the config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  protected function getConfig() {
    if (!$this->config) {
      $this->config = $this->configFactory->get('ai_content.settings');
    }
    return $this->config;
  }

  /**
   * Apply AI content form elements to a form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function applyContentForm(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface */
    $form_object = $form_state->getFormObject();

    if ($this->account->hasPermission('access ai content tools')) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_object->getEntity();
      // Prepare.
      $this->options = $this->getAllTextFields($entity, $form);

      // The forms.
      $this->moderationForm($form, $form_state);
      $this->toneAdjustForm($form, $form_state);
      $this->summarizeForm($form, $form_state);
      $this->suggestTitleForm($form, $form_state);
      $this->suggestTaxForm($form, $form_state);
    }
  }

  /**
   * Moderation form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function moderationForm(&$form, FormStateInterface $form_state) {
    if ($this->getConfig()->get('analyse_policies_enabled')) {
      $form['ai_moderate'] = [
        '#type' => 'details',
        '#title' => $this->t('Analyze text'),
        '#group' => 'advanced',
        '#tree' => TRUE,
      ];

      $form['ai_moderate']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose field'),
        '#description' => $this->t('Select what field you would like to analyze.'),
        '#options' => $this->options,
      ];

      $form['ai_moderate']['response'] = [
        '#type' => 'markup',
        '#markup' => $this->t('AI can analyze content and tell you what content policies it may violate for a provider. This is beneficial if your audience are certain demographics and sensitive to certain categories. Note that this is only a useful guide.'),
        '#prefix' => '<div id="ai-moderate-response">',
        '#suffix' => '</div>',
      ];

      $form['ai_moderate']['do_moderate'] = [
        '#type' => 'button',
        '#value' => $this->t('Analyze'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'analyzeContentResponse'],
          'wrapper' => 'ai-moderate-response',
        ],
      ];
    }
  }

  /**
   * Tone adjust form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function toneAdjustForm(&$form, FormStateInterface $form_state) {
    if ($this->getConfig()->get('tone_adjust_enabled')) {
      $form['ai_tone_edit'] = [
        '#type' => 'details',
        '#title' => $this->t('Adjust content tone'),
        '#group' => 'advanced',
        '#tree' => TRUE,
      ];

      $form['ai_tone_edit']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose field'),
        '#description' => $this->t('Select what field you would like to change the tone of.'),
        '#options' => $this->options,
      ];

      // @todo these values should be configurable options
      $form['ai_tone_edit']['tone'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose tone'),
        '#description' => $this->t('Selecting one of the options will adjust/reword the body content to be appropriate for the target audience.'),
        '#options' => [
          'friendly' => $this->t('Friendly'),
          'professional' => $this->t('Professional'),
          'helpful' => $this->t('Helpful'),
          'easier for a high school educated reader' => $this->t('High school level reader'),
          'easier for a college educated reader' => $this->t('College level reader'),
          'explained to a five year old' => $this->t("Explain like I'm 5"),
        ],
      ];

      $form['ai_tone_edit']['response'] = [
        '#prefix' => '<br /><div id="ai-tone-edit-response">',
        '#suffix' => '</div>',
      ];

      $form['ai_tone_edit']['edit'] = [
        '#type' => 'button',
        '#value' => $this->t('Adjust tone'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'adjustToneResponse'],
          'wrapper' => 'ai-tone-edit-response',
        ],
      ];
    }
  }

  /**
   * Summarize form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function summarizeForm(&$form, FormStateInterface $form_state) {
    if ($this->getConfig()->get('summarise_enabled')) {
      $form['ai_summarize'] = [
        '#type' => 'details',
        '#title' => $this->t('Summarize text'),
        '#group' => 'advanced',
        '#tree' => TRUE,
      ];

      $form['ai_summarize']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose field'),
        '#description' => $this->t('Select what field you would like to create a summary for.'),
        '#options' => $this->options,
      ];

      $form['ai_summarize']['response'] = [
        '#type' => 'markup',
        '#prefix' => '<div id="ai-summarize-response">',
        '#suffix' => '</div>',
      ];

      $form['ai_summarize']['do_summarize'] = [
        '#type' => 'button',
        '#value' => $this->t('Summarize'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'fieldSummarizeResponse'],
          'wrapper' => 'ai-summarize-response',
        ],
      ];
    }
  }

  /**
   * Summarize title form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function suggestTitleForm(&$form, FormStateInterface $form_state) {
    if ($this->getConfig()->get('suggest_title_enabled')) {
      $form['ai_suggest_title'] = [
        '#type' => 'details',
        '#title' => $this->t('Suggest content title'),
        '#group' => 'advanced',
        '#tree' => TRUE,
      ];

      $form['ai_suggest_title']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose field'),
        '#description' => $this->t('Select what field you would like to use to suggest an SEO friendly title.'),
        '#options' => $this->options,
      ];

      $form['ai_suggest_title']['response'] = [
        '#type' => 'markup',
        '#prefix' => '<div id="ai-suggest-title-response">',
        '#suffix' => '</div>',
      ];

      $form['ai_suggest_title']['do_suggest_title'] = [
        '#type' => 'button',
        '#value' => $this->t('Suggest title'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'suggestTitleResponse'],
          'wrapper' => 'ai-suggest-title-response',
        ],
      ];
    }
  }

  /**
   * Suggest taxonomy form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function suggestTaxForm(&$form, FormStateInterface $form_state) {
    if ($this->getConfig()->get('suggest_tax_enabled')) {
      $form['ai_suggest'] = [
        '#type' => 'details',
        '#title' => $this->t('Suggest taxonomy'),
        '#group' => 'advanced',
        '#tree' => TRUE,
      ];

      $form['ai_suggest']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose field'),
        '#description' => $this->t('Select what field you would like to suggest taxonomy terms for.'),
        '#options' => $this->options,
      ];

      $form['ai_suggest']['response'] = [
        '#type' => 'markup',
        '#prefix' => '<div id="ai-suggest-response">',
        '#suffix' => '</div>',
      ];

      $form['ai_suggest']['do_suggest'] = [
        '#type' => 'button',
        '#value' => $this->t('Suggest taxonomy'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'suggestTaxonomy'],
          'wrapper' => 'ai-suggest-response',
        ],
      ];
    }
  }

  /**
   * Get a list of all string and text fields on the current node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity on the form.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   List of all valid field options.
   */
  public function getAllTextFields(ContentEntityInterface $entity, $form) {
    $fields = $entity->getFieldDefinitions();
    $options = [];

    foreach ($fields as $field) {
      if (in_array($field->getType(), ['text_with_summary', 'text_long', 'string', 'string_long'])) {
        // @todo How to skip special fields?
        if (in_array($field->getName(), ['revision_log', 'revision_log_message'])) {
          continue;
        }

        $label = $field->getLabel();

        if ($label instanceof TranslatableMarkup) {
          $label = $label->render();
        }

        $options[$field->getName()] = $label;
      }
    }

    asort($options);
    $options = array_intersect_key($options, $form);
    return $options;
  }

  /**
   * The AJAX callback for analyzing content.
   *
   * @param array $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The HTML response.
   */
  public function analyzeContentResponse(array &$form, FormStateInterface $form_state) {
    $ai_analyze = $form_state->getValue('ai_moderate');
    $target_field = $ai_analyze['target_field'];
    $target_field_value = $form_state->getValue($target_field)[0]['value'];
    $output = $this->t('The @field field has no text. Please supply content to the @field field.', ['@field' => $target_field]);
    if (!empty($target_field_value)) {
      // Load the chosen provider/model.
      $ai_settings = explode('__', $this->getConfig()->get('analyse_policies_enabled'));
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0]);

      /** @var \Drupal\ai\OperationType\Moderation $response */
      $response = $ai_provider->moderation($target_field_value, $ai_settings[1])->getNormalized();
      $content = [];
      if ($response->isFlagged()) {
        $categories = $response->getInformation();

        $content['heading'] = [
          '#markup' => '<p>' . $this->t('Violation(s) found for these categories:') . '</p>',
        ];

        $violations = [];
        foreach ($categories as $category => $did_violate) {
          $violations[] = Unicode::ucfirst($category);
        }
        $content['results'] = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $violations,
          '#empty' => $this->t('The text does not violate any content policies noted by OpenAI/ChatGPT.'),
        ];
      }
      else {
        $content['results'] = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => [],
          '#empty' => $this->t('The text does not violate any content policies noted by OpenAI/ChatGPT.'),
        ];
      }
      asort($content);
      $output = $this->renderer->render($content);
    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ai-moderate-response', $output));
    return $response;
  }

  /**
   * The AJAX callback for adjusting the tone of body content.
   *
   * @param array $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The HTML response.
   */
  public function adjustToneResponse(array &$form, FormStateInterface $form_state) {
    $ai_tone_edit = $form_state->getValue('ai_tone_edit');
    $target_field = $ai_tone_edit['target_field'];
    $target_field_value = $form_state->getValue($target_field)[0]['value'];
    $tone = $ai_tone_edit['tone'];
    $text = $this->t('The @field field has no text. Please supply content to the @field field.', ['@field' => $target_field]);
    if (!empty($target_field_value)) {
      // Load the chosen provider/model.
      $ai_settings = explode('__', $this->getConfig()->get('tone_adjust_enabled'));
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0]);
      $truncated_value = $target_field_value;
      $prompt = 'Change the tone of the following text to be ' . $tone . ' using the same language as the following text:\r\n"' . $truncated_value . '"';
      $messages = new ChatInput([
        new ChatMessage('system', 'You are helpful assistant.'),
        new chatMessage('user', $prompt),
      ]);
      $message = $ai_provider->chat($messages, $ai_settings[1])->getNormalized();
      $text = trim($message->getText()) ?? $this->t('No result could be generated.');
    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ai-tone-edit-response', $text));
    return $response;
  }

  /**
   * The AJAX callback for summarizing a field.
   *
   * @param array $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The HTML response.
   */
  public function fieldSummarizeResponse(array &$form, FormStateInterface $form_state) {
    $ai_summarize = $form_state->getValue('ai_summarize');
    $target_field = $ai_summarize['target_field'];
    $target_field_value = $form_state->getValue($target_field)[0]['value'];
    $text = $this->t('The @field field has no text. Please supply content to the @field field.', ['@field' => $target_field]);
    if (!empty($target_field_value)) {
      // Load the chosen provider/model.
      $ai_settings = explode('__', $this->getConfig()->get('summarise_enabled'));
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0]);
      $prompt = 'Create a detailed summary of the following text in less than 130 words using the same language as the following text:\r\n"' . $target_field_value . '"';
      $messages = new ChatInput([
        new chatMessage('system', 'You are helpful assistant.'),
        new chatMessage('user', $prompt),
      ]);
      $message = $ai_provider->chat($messages, $ai_settings[1])->getNormalized();
      $text = trim($message->getText()) ?? $this->t('No result could be generated.');
    }

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ai-summarize-response', $text));
    return $response;
  }

  /**
   * The AJAX callback for suggesting a title.
   *
   * @param array $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The HTML response.
   */
  public function suggestTitleResponse(array &$form, FormStateInterface $form_state) {
    $ai_suggest = $form_state->getValue('ai_suggest_title');
    $target_field = $ai_suggest['target_field'];
    $target_field_value = $form_state->getValue($target_field)[0]['value'];
    $text = $this->t('The @field field has no text. Please supply content to the @field field.', ['@field' => $target_field]);
    if (!empty($target_field_value)) {
      $ai_settings = explode('__', $this->getConfig()->get('summarise_enabled'));
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0]);
      $prompt = 'Suggest an SEO friendly title for this page based off of the following content in 10 words or less, in the same language as the input:\r\n"' . $target_field_value . '"';
      $messages = new ChatInput([
        new chatMessage('system', 'You are helpful assistant.'),
        new chatMessage('user', $prompt),
      ]);
      $message = $ai_provider->chat($messages, $ai_settings[1])->getNormalized();
      $text = trim($message->getText()) ?? $this->t('No result could be generated.');
    }

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ai-suggest-title-response', $text));
    return $response;
  }

  /**
   * The AJAX callback for suggesting taxonomy.
   *
   * @param array $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The HTML response.
   */
  public function suggestTaxonomy(array &$form, FormStateInterface $form_state) {
    $ai_suggest = $form_state->getValue('ai_suggest');
    $target_field = $ai_suggest['target_field'];
    $target_field_value = $form_state->getValue($target_field)[0]['value'];
    $text = $this->t('The @field field has no text. Please supply content to the @field field.', ['@field' => $target_field]);
    if (!empty($target_field_value)) {
      $ai_settings = explode('__', $this->getConfig()->get('summarise_enabled'));
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0]);
      $prompt = 'Suggest five words to classify the following text using the same language as the input text. The words must be nouns or adjectives in a comma delimited list:\r\n"' . $target_field_value . '"';
      $messages = new ChatInput([
        new chatMessage('system', 'You are helpful assistant.'),
        new chatMessage('user', $prompt),
      ]);
      $message = $ai_provider->chat($messages, $ai_settings[1])->getNormalized();
      $text = trim($message->getText()) ?? $this->t('No result could be generated.');
    }

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ai-suggest-response', $text));
    return $response;
  }

}
