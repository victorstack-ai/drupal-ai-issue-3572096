<?php

namespace Drupal\ai_search\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\search_api\Form\IndexFieldsForm;

/**
 * Override the Search API Index Fields Form.
 */
class AiSearchIndexFieldsForm extends IndexFieldsForm {

  /**
   * The indexing options with labels and descriptions.
   *
   * @var array[] {
   *   @type string $label The label for the indexing option.
   *   @type string $description The description for the indexing option.
   * }
   */
  public array $options = [];

  /**
   * Build the select indexing options.
   *
   * @return array
   *   The select options for indexing options.
   */
  protected function buildSelectIndexingOptions(): array {
    $return = [];
    foreach (EmbeddingStrategyIndexingOptions::cases() as $option) {
      $return[$option->getKey()] = $option->getLabel();
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $index_config = $this->config('search_api.index.' . $this->entity->id())->getRawData();

    // Advance controls.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Field Indexing Options'),
      '#open' => FALSE,
    ];
    $form['advanced']['control_field_max_length'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced usage: Set maximum lengths for each string "Filterable attribute".'),
      '#description' => $this->t('Vector Databases allow attaching of metadata to the vectorized content; however, they typically have limits to how much metadata can be attached. If you set very long fields as "Filterable attributes" you may wish to control the maximum length per field. Disabling this checkbox will reset the maximum lengths to no restriction.'),
      '#default_value' => $index_config['control_field_max_length'] ?? FALSE,
    ];
    $form['advanced']['exclude_chunk_from_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced usage: Exclude the "Chunk" of the "Main Content" from the metadata.'),
      '#description' => $this->t('By default the metadata contains a "content" attribute attached to it. This may be used by some tools when a chunk is returned such as an AI Assistant. If you however ensure that the returned results are used to load the full entity (also an option in AI Assistants and the default for Views) then the "content" attribute in the metadata is not needed and can save space.'),
      '#default_value' => $index_config['exclude_chunk_from_metadata'] ?? FALSE,
    ];
    if (
      $form['advanced']['control_field_max_length']['#default_value']
      || $form['advanced']['exclude_chunk_from_metadata']['#default_value']
    ) {
      $form['advanced']['#open'] = TRUE;
    }

    // Add the options to the introductory description.
    $form['description']['indexing_options_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Vector Database indexing options'),
    ];
    $rows = [];
    foreach (EmbeddingStrategyIndexingOptions::cases() as $option) {
      $rows[] = [
        'label' => $option->getLabel(),
        'description' => $option->getDescription(),
      ];
    }
    $form['description']['indexing_options_table'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('Indexing option')],
        ['data' => $this->t('Description')],
      ],
      '#rows' => $rows,
    ];

    foreach ($form as $key => &$field_group) {
      if ($key !== '_general' && !str_starts_with($key, 'entity:')) {
        continue;
      }

      // Add the header row for target type.
      if (!empty($field_group['#header'])) {
        $operations_header = array_pop($field_group['#header']);
        $field_group['#header'][] = $this->t('Indexing option');
        if ($index_config['control_field_max_length']) {
          $field_group['#header'][] = $this->t('Maximum length');
        }
        $field_group['#header'][] = $operations_header;

        // Update the rows.
        if (!empty($field_group['fields'])) {
          foreach ($field_group['fields'] as $field_id => &$row) {
            $field_id = (string) $field_id;
            $edit_row = array_pop($row);
            $remove_row = array_pop($row);
            $row['indexing_option'] = [
              '#type' => 'select',
              '#options' => $this->buildSelectIndexingOptions(),
              '#empty_option' => $this->t('- Select -'),
              '#default_value' => '',
            ];
            if (!empty($index_config['indexing_options'][$field_id]['indexing_option'])) {
              $row['indexing_option']['#default_value'] = $index_config['indexing_options'][$field_id]['indexing_option'];
            }

            if ($index_config['control_field_max_length']) {
              if (
                isset($row['type']['#default_value'])
                && $row['type']['#default_value'] === 'string'
                && $row['indexing_option']['#default_value'] === EmbeddingStrategyIndexingOptions::ATTRIBUTES->getKey()
              ) {
                $row['max'] = [
                  '#type' => 'number',
                  '#step' => 1,
                  '#maxlength' => 5,
                  '#default_value' => '',
                ];
                if (
                  !empty($index_config['indexing_options'][$field_id]['max'])
                  && $index_config['indexing_options'][$field_id]['max'] > 0
                ) {
                  $row['max']['#default_value'] = (int) $index_config['indexing_options'][$field_id]['max'];
                }
              }
              else {
                $row['max'] = ['#markup' => 'N/A'];
              }
            }
            $row[] = $remove_row;
            $row[] = $edit_row;
          }
        }
      }

    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check if the embedding strategy does not support multiple 'Main Content'
    // fields.
    $server_backend_config = $this->entity->getServerInstance()->getBackendConfig();

    // Ignore static Drupal Service call: we do this to make it easier to keep
    // this compatible with Search API as changes are expected here.
    // @phpstan-ignore-next-line
    $embedding_strategy_provider = \Drupal::service('ai_search.embedding_strategy');
    /** @var \Drupal\ai_search\EmbeddingStrategyInterface $embedding_strategy */
    $embedding_strategy = $embedding_strategy_provider->createInstance($server_backend_config['embedding_strategy']);
    if (!$embedding_strategy->supports(EmbeddingStrategyCapability::MultipleMainContent)) {
      $values = $form_state->getValues();

      // Determine the selected indexing options by looping through all fields.
      $count = 0;
      if (!empty($values['fields'])) {
        $message = $this->t('Only one "Main Content" field is supported by the Embedding Strategy selected in the Search API Server configuration.');
        foreach ($values['fields'] as $id => $field) {
          if (!isset($field['indexing_option'])) {
            continue;
          }

          // If there is more than one, set a validation error.
          if ($field['indexing_option'] === EmbeddingStrategyIndexingOptions::MAIN_CONTENT->getKey()) {
            $count++;
          }
          if ($count > 1) {
            $form_state->setErrorByName('fields[' . $id . '][indexing_option', $message);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    $index_config = $this->configFactory()->getEditable('search_api.index.' . $this->entity->id());
    $values = $form_state->getValues();

    // Determine the selected indexing options by looping through all fields.
    $indexing_options = [];
    if (!empty($values['fields'])) {
      foreach ($values['fields'] as $id => $field) {
        if (!isset($field['indexing_option'])) {
          continue;
        }
        $id = (string) $id;

        // Store the selected indexing option for the field.
        $indexing_options[$id] = [
          'indexing_option' => $field['indexing_option'],
        ];

        // Store maximum length if set, otherwise set to -1 for unlimited.
        if (isset($field['max']) && is_numeric($field['max']) && $field['max'] > 0) {
          $indexing_options[$id]['max'] = $field['max'];
        }
        else {
          $indexing_options[$id]['max'] = -1;
        }
      }
    }
    $index_config->set('indexing_options', $indexing_options);

    // Advanced options.
    $advanced = $form_state->getValue('advanced');
    $index_config->set('control_field_max_length', (bool) $advanced['control_field_max_length']);
    $index_config->set('exclude_chunk_from_metadata', (bool) $advanced['exclude_chunk_from_metadata']);

    // Save the changes.
    $index_config->save();
    return $return;
  }

}
