<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Form;

use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * AI Assistant form.
 */
final class AiAssistantForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Article finder assistant'),
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [AiAssistant::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
      '#description' => $this->t('A 1-2 sentence description of the AI assistant and what it does.'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('An assistant that can find old articles and also publish and unpublish them.'),
      ],
    ];

    $form['allow_history'] = [
      '#type' => 'select',
      '#title' => $this->t('Allow History'),
      '#default_value' => $entity->get('allow_history'),
      '#description' => $this->t('If enabled, the AI Assistant will try store the questions and answers in history during a session. This makes it possible to ask follow-up questions to the Assistant. Note that this raises the price and size of AI calls, and might not be needed for all assistants. Sessions means that it will be stored in the session until the page is reloaded. (coming) Database means that it will be stored in the database with an ID and can be continued later in multiple threads.'),
      '#options' => [
        'none' => $this->t('None'),
        'session' => $this->t('Session'),
      ],
    ];

    $form['system_role'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System role'),
      '#default_value' => $entity->get('system_role'),
      '#description' => $this->t('The system role that this AI assistant should have. This is used to determine how and with what the AI Assistant should act.'),
      '#required' => TRUE,
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('You are an assistant helping people find old articles in the archive using natural language. Answer in a professional and neutral tone. Be short and concise. You may use the following HTML tags - a, em, strong, ul, ol, li, pre. Link to the article in question using its title.'),
      ],
    ];

    $form['preprompt_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre-prompt Instructions'),
      '#default_value' => $entity->get('preprompt_instructions'),
      '#description' => $this->t('Extra instruction that should be run before each message from the user. This can be used to control the content of the prompt.'),
      '#required' => FALSE,
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('If the user asks questions about unpublished articles, make sure to add status unpulished somewhere in the lookup.'),
      ],
    ];

    // phpcs:ignore
    $actions = \Drupal::service('ai_assistant_api.action_plugin.manager');
    foreach ($actions->getDefinitions() as $definition) {
      $form['action_plugin_' . $definition['id']] = [
        '#type' => 'details',
        '#title' => $definition['label'],
        '#open' => TRUE,
        '#description' => $this->t('Configure the ' . $definition['label'] . ' settings for this AI assistant.'),
      ];

      $form['action_plugin_' . $definition['id']]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %label', ['%label' => $definition['label']]),
        '#default_value' => isset($entity->get('actions_enabled')[$definition['id']]),
      ];

      $form['action_plugin_' . $definition['id']]['plugin_id'] = [
        '#type' => 'hidden',
        '#value' => $definition['id'],
      ];

      $form['action_plugin_' . $definition['id']]['configuration'] = [
        '#type' => 'details',
        '#title' => $this->t('%label settings', [
          '%label' => $definition['label'],
        ]),
        '#open' => TRUE,
        'states' => [
          'visible' => [
            ':input[name="' . $definition['id'] . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
        '#description' => $this->t('Configure the ' . $definition['label'] . ' settings for this AI assistant.'),
      ];

      $instance = $actions->createInstance($definition['id'], $entity->get('actions_enabled')[$definition['id']] ?? []);
      $subform = $form['action_plugin_' . $definition['id']]['configuration'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);

      if (isset($query_parameters['selected_text'])) {
        $subform_state->setStorage(['selected_text' => $query_parameters['selected_text']]);
      }

      $form['action_plugin_' . $definition['id']]['configuration'] = $instance->buildConfigurationForm([], $subform_state);
      $form['action_plugin_' . $definition['id']]['#tree'] = TRUE;
    }

    $form['rag']['no_results_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('RAG No results message'),
      '#description' => $this->t('This is a hard coded message you can answer if the threshold of RAG is not enough, this will bypass the assistant message and send it out right away. Can be left empty if you want a natural answer.'),
      '#default_value' => $entity->get('no_results_message') ?? $this->t('I am sorry, but I could not find any relevant information in the archives. Please try to ask the question in a different way or try to ask a different question.'),
      '#states' => [
        'visible' => [
          ':input[name="rag_enabled"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, but I could not find any relevant information in the archives. Please try to ask the question in a different way or try to ask a different question.'),
        'rows' => 2,
      ],
    ];

    $form['assistant_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Assistant message'),
      '#description' => $this->t('Use the token [rag_context] for providing the snippets or full rendered entities. Use the token [question] for providing the question from the end-user.<br />The assistant message is for responses created by the LLM.'),
      '#default_value' => $entity->get('assistant_message'),
      '#attributes' => [
        'placeholder' => $this->t("Based on the following results that were fetched from an article database and the following question, check if you can answer the question truthfully. If you can not answer the question, please respond that you do not have enough information to do so. Do NOT make up information. Answer in a professional and concise manner. If a link is provided with the article, use HTML to link to the article using the articles title.

The question is:
-----------------------
[question]
-----------------------

The following articles were found:
-----------------------
[rag_context]
-----------------------"),
        'rows' => 15,
      ],
    ];

    $form['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('This is the answer if we run into any error on the way. You may use the token [error_message] to get the error message from the backend in your message, but it might be a security concern to show this to none escalated users.'),
      '#default_value' => $entity->get('error_message') ?? $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
        'rows' => 2,
      ],
    ];

    // Set form state if empty.
    if ($form_state->getValue('llm_ai_provider') == NULL) {
      $form_state->setValue('llm_ai_provider', $entity->get('llm_provider'));
    }
    if ($form_state->getValue('llm_ai_model') == NULL) {
      $form_state->setValue('llm_ai_model', $entity->get('llm_model'));
    }
    // phpcs:ignore
    $form_helper = \Drupal::service('ai.form_helper');
    $form_helper->generateAiProvidersForm($form, $form_state, 'chat', 'llm', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 0, '', $this->t('Advanced LLM'), $this->t('The AI Provider to use for the advanced interactions.'), TRUE);

    // Set default values.
    $llm_configs = $entity->get('llm_configuration');
    if ($llm_configs && count($llm_configs)) {
      foreach ($llm_configs as $key => $value) {
        $form['llm_ajax_prefix']['llm_ajax_prefix_configuration_' . $key]['#default_value'] = $value;
      }
    }
    // phpcs:ignore
    $pre_action_prompt = file_get_contents(\Drupal::service('extension.path.resolver')->getPath('module', 'ai_assistant_api') . '/resources/pre_action_prompt.txt');

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['pre_action_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre Action Prompt'),
      '#default_value' => $entity->get('pre_action_prompt') ?? $pre_action_prompt,
      '#description' => $this->t('The pre prompts gets a list of actions that it can take, including RAG databases and either gives back actions that the Assistant can take or an outputted answer. You may use [list_of_actions] to list the actions that the Assistant can take. You can only change this via manual config change. DO NOT CHANGE THIS UNLESS YOU KNOW WHAT YOU ARE DOING.'),
      '#required' => TRUE,
      '#disabled' => TRUE,
      '#attributes' => [
        'rows' => 75,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // phpcs:ignore
    \Drupal::service('ai.form_helper')->validateAiProvidersConfig($form, $form_state, 'chat', 'llm');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;

    // RAG settings.
    $action_plugins = [];
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, 'action_plugin_') === 0) {
        if ($val['enabled']) {
          $action_plugins[$val['plugin_id']] = $val['configuration'] ?? [];
        }
      }
    }
    $entity->set('actions_enabled', $action_plugins);
    // LLM provider.
    $entity->set('llm_provider', $form_state->getValue('llm_ai_provider'));
    // If its default, we don't set the last.
    if ($form_state->getValue('llm_ai_provider') !== '__default__') {
      $entity->set('llm_model', $form_state->getValue('llm_ai_model'));
      $llm_config = [];
      // phpcs:ignore
      $provider = \Drupal::service('ai.provider')->createInstance($form_state->getValue('llm_ai_provider'));
      $schema = $provider->getAvailableConfiguration('chat', $form_state->getValue('llm_ai_model'));
      foreach ($form_state->getValues() as $key => $val) {
        if (strpos($key, 'llm_') === 0 && $key !== 'llm_ai_provider' && $key !== 'llm_ai_model') {

          $real_key = str_replace('llm_ajax_prefix_configuration_', '', $key);
          $type = $schema[$real_key]['type'] ?? 'string';
          $llm_config[$real_key] = CastUtility::typeCast($type, $val);
        }
      }
      $entity->set('llm_configuration', $llm_config);
    }
    else {
      $entity->set('llm_configuration', []);
      $entity->set('llm_model', '');
    }
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

}
