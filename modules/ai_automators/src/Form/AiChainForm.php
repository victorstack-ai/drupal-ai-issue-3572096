<?php

namespace Drupal\ai_automators\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\ai_automators\Traits\AutomatorInstructionTrait;
use Drupal\Core\Url;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\token\TreeBuilder;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The AI chain form.
 */
class AiChainForm extends FormBase {

  use AutomatorInstructionTrait;

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  protected string $bundle;

  /**
   * AiChainForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager $automatorTypeManager
   *   The automator type manager.
   * @param \Drupal\token\TokenEntityMapperInterface $tokenEntityMapper
   *   The token entity mapper.
   * @param \Drupal\token\TreeBuilder $tokenTreeBuilder
   *   The token tree builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AiAutomatorTypeManager $automatorTypeManager,
    protected TokenEntityMapperInterface $tokenEntityMapper,
    protected TreeBuilder $tokenTreeBuilder,
    RouteMatchInterface $routeMatch,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.ai_automator'),
      $container->get('token.entity_mapper'),
      $container->get('token.tree_builder'),
      $container->get('current_route_match'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_chain_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity = NULL;

    // This is our route, so we know the only parameter is the entity. We have
    // to get it this way, as the parameter is named for the entity's id in
    // these routes so we cannot know what it will be until we have the entity.
    if ($route_params = $this->routeMatch->getParameters()->all()) {
      $entity = reset($route_params);

      // The route params have been upscaled to actual entities, which can cause
      // issues for some field types so we'll switch back to the raw params from
      // now on.
      $route_params = $this->routeMatch->getRawParameters()->all();
    }

    $entity_type = $entity->getEntityType()->getBundleOf();

    // But if there is some unexpected problem, abort trying to build the form.
    if (empty($entity_type)) {
      throw new NotFoundException('Entity type and bundle are required.');
    }

    // Set the required variables.
    $this->entityType = $entity_type;
    $bundle = $this->bundle = $entity->id();

    // Get all fields, including base fields for the entity type and bundle.
    try {
      $definitions = $this->getAutomatorInstructions($entity_type, $bundle);
    }
    catch (\Exception $e) {
      throw new NotFoundException('Entity type and bundle are required.');
    }

    $form['introduction'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This form shows you all the AI Automators currently set up on this :entity_type and allows you to alter the order in which they run.', [
        ':entity_type' => ucwords($entity_type),
      ]),
    ];

    if (!$this->moduleHandler->moduleExists('field_ui')) {
      $form['warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You do not currently have the Field UI module enabled. <strong>AI Automators are configured through the settings of the field they are attached to</strong> so to add or edit them you will need Field UI enabled. This can then be disabled again once you have finished altering them.'),
      ];
    }

    // Resort definitions on weight.
    uasort($definitions, function ($a, $b) {
      return $a->get('weight') <=> $b->get('weight');
    });

    $header = [
      'label' => $this->t('Automator'),
      'field' => $this->t('Field'),
      'automator_type' => $this->t('Automator Types'),
      'worker' => $this->t('Worker'),
      'source_type' => $this->t('Source Type'),
      'inputs' => $this->t('Source Field(s)'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];

    $form['items'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No fields found'),
      '#rows' => [],
      '#attributes' => [
        'id' => 'fields-table',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'item-order-weight',
        ],
      ],
    ];

    foreach ($definitions as $definition) {
      // Get automator types for this definition.
      $automatorTypes = $definition->getAutomatorTypes();

      // Common worker value.
      $worker_type_value = $definition->get('worker_type') ?? '-';
      if ($automatorTypes->count() === 0) {
        $form['items'][$definition->id()]['#attributes']['class'][] = 'draggable';
        $form['items'][$definition->id()]['#weight'] = 0;

        $form['items'][$definition->id()]['label'] = [
          '#plain_text' => $definition->label(),
        ];

        $form['items'][$definition->id()]['field'] = [
          '#plain_text' => $this->fieldNameToLabel($definition->get('field_name')),
        ];

        $form['items'][$definition->id()]['automator_type'] = [
          '#plain_text' => $this->t('No automator types configured'),
        ];

        $form['items'][$definition->id()]['worker'] = [
          '#plain_text' => $worker_type_value ?: '-',
        ];

        $form['items'][$definition->id()]['source_type'] = [
          '#plain_text' => '-',
        ];

        $form['items'][$definition->id()]['inputs'] = [
          '#plain_text' => '-',
        ];

        $form['items'][$definition->id()]['weight'] = [
          '#type' => 'weight',
          '#delta' => 1000,
          '#default_value' => 0,
          '#title' => $this->t('Weight for @label', ['@label' => $definition->label()]),
          '#title_display' => 'invisible',
          '#attributes' => ['class' => ['item-order-weight']],
        ];

        // Provide operations even when there are no automator types.
        $links = [];
        $links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $definition->toUrl('edit-form'),
          'query' => [
            'destination' => Url::fromRoute('<current>')->toString(),
          ],
        ];
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $definition->toUrl('delete-form'),
        ];

        $form['items'][$definition->id()]['operations'] = [
          '#type' => 'operations',
          '#links' => $links,
        ];
      }
      else {
        // Get the highest weighted automator type for display purposes.
        $displayAutomatorType = NULL;
        $highestWeight = -1000;

        foreach ($automatorTypes as $automatorType) {
          if ($automatorType->getWeight() > $highestWeight) {
            $highestWeight = $automatorType->getWeight();
            $displayAutomatorType = $automatorType;
          }
        }

        $form['items'][$definition->id()]['#attributes']['class'][] = 'draggable';
        $form['items'][$definition->id()]['#weight'] = $highestWeight;

        $form['items'][$definition->id()]['label'] = [
          '#plain_text' => $definition->label() . ($automatorTypes->count() > 1 ? ' (' . $automatorTypes->count() . ' types)' : ''),
        ];

        $form['items'][$definition->id()]['field'] = [
          '#plain_text' => $this->fieldNameToLabel($definition->get('field_name')),
        ];

        $typeLabels = [];
        foreach ($automatorTypes as $automatorType) {
          $typeLabels[] = $this->automatorTypeManager->getDefinition($automatorType->getPluginId())['label'];
        }

        $form['items'][$definition->id()]['automator_type'] = [
          '#plain_text' => implode(', ', $typeLabels),
        ];

        $form['items'][$definition->id()]['worker'] = [
          '#plain_text' => $worker_type_value ?: '-',
        ];

        $form['items'][$definition->id()]['source_type'] = [
          '#plain_text' => $this->calculateSourceType($displayAutomatorType),
        ];

        $form['items'][$definition->id()]['inputs'] = [
          '#plain_text' => $this->calculateInputFromAutomatorType($displayAutomatorType),
        ];

        $form['items'][$definition->id()]['weight'] = [
          '#type' => 'weight',
          '#delta' => 1000,
          '#default_value' => $highestWeight,
          '#title' => $this->t('Weight for @label', ['@label' => $definition->label()]),
          '#title_display' => 'invisible',
          '#attributes' => ['class' => ['item-order-weight']],
        ];

        $links = [];
        $links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $definition->toUrl('edit-form'),
          'query' => [
            'destination' => Url::fromRoute('<current>')->toString(),
          ],
        ];
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $definition->toUrl('delete-form'),
        ];

        $form['items'][$definition->id()]['operations'] = [
          '#type' => 'operations',
          '#links' => $links,
        ];
      }
    }

    // Add the "Add new automator" button with query parameters.
    $form['add_automator'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new automator'),
      '#url' => Url::fromRoute('entity.ai_automator.add_form', [], [
        'query' => [
          'entity_type' => $entity_type,
          'bundle' => $bundle,
        ],
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#weight' => -1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Re-sort'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get items safely.
    $items = $form_state->getValue('items', []);

    // Ensure $items is an array.
    if (!is_array($items)) {
      $items = [];
    }

    if (empty($items)) {
      $this->messenger()->addWarning($this->t('No instructions to process.'));
      return;
    }

    // First, get the lowest weight value.
    $weight = NULL;
    foreach ($items as $instruction => $new_weight) {
      if (is_null($weight) || (isset($new_weight['weight']) && $new_weight['weight'] < $weight)) {
        $weight = $new_weight['weight'];
      }
    }

    // Now loop through the instructions and update the weight.
    foreach ($items as $instruction => $new_weight) {
      /** @var \Drupal\ai_automators\Entity\AiAutomator $definition */
      $definition = $this->entityTypeManager->getStorage('ai_automator')->load($instruction);

      if ($definition) {
        $definition->set('weight', (int) $weight);
        $definition->save();
        $weight++;
      }
    }

    // Set a message.
    $this->messenger()->addMessage($this->t('Instructions have been resorted.'));
  }

  /**
   * Calculate the input.
   *
   * @param \Drupal\ai_automators\Entity\AiAutomator $definition
   *   The definition.
   *
   * @return string
   *   The input.
   */
  protected function calculateInput(AiAutomator $definition): string {
    $input = $definition->get('input_mode');
    if ($input == 'base') {
      return $this->fieldNameToLabel($definition->get('base_field'));
    }
    elseif ($input == 'token') {
      // Extract all the tokens from the prompt.
      $tokens = [];
      // Extra everything within [].
      preg_match_all('/\[(.*?)\]/', $definition->get('token'), $tokens);
      if (!empty($tokens[1])) {
        $tokens = $tokens[1];
        $labels = [];
        foreach ($tokens as $token) {
          $labels[] = $this->tokenToLabel($token);
        }
        return implode(', ', $labels);
      }
      return '';
    }
    return '';
  }

  /**
   * Calculate the source type from an automator type plugin.
   *
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The automator type plugin.
   *
   * @return string
   *   The source type.
   */
  protected function calculateSourceType($automatorType): string {
    if (!$automatorType) {
      return '-';
    }

    $settings = $automatorType->getConfiguration()['settings'] ?? [];
    $mode = $settings['mode'] ?? '';

    return $mode === 'base' ? 'Base Field' : ($mode === 'token' ? 'Token' : 'Other');
  }

  /**
   * Calculate the input from an automator type plugin.
   *
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $automatorType
   *   The automator type plugin.
   *
   * @return string
   *   The input description.
   */
  protected function calculateInputFromAutomatorType($automatorType): string {
    if (!$automatorType) {
      return '-';
    }

    $settings = $automatorType->getConfiguration()['settings'] ?? [];
    $mode = $settings['mode'] ?? '';

    if ($mode === 'base') {
      $baseField = $settings['base_field'] ?? '';
      return $baseField ? $this->fieldNameToLabel($baseField) : 'Unknown Base Field';
    }
    elseif ($mode === 'token') {
      $token = $settings['token'] ?? '';
      // Extract all the tokens from the token string.
      $tokens = [];
      preg_match_all('/\[(.*?)\]/', $token, $tokens);
      if (!empty($tokens[1])) {
        $tokens = $tokens[1];
        $labels = [];
        foreach ($tokens as $tokenName) {
          $labels[] = $this->tokenToLabel($tokenName);
        }
        return implode(', ', $labels);
      }
      return $token ?: 'No tokens';
    }

    return 'Other';
  }

  /**
   * Field name to field label.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The field label.
   */
  protected function fieldNameToLabel(string $field_name): string {
    // Load the field name from the entity type.
    $field_data = $this->entityFieldManager->getFieldDefinitions($this->entityType, $this->bundle);
    return isset($field_data[$field_name]) ? $field_data[$field_name]->getLabel() : 'Unknown Field';
  }

  /**
   * Token to token label.
   *
   * @param string $token
   *   The token.
   *
   * @return string
   *   The token label.
   */
  protected function tokenToLabel(string $token): string {
    [$entity_type, $info] = explode(':', $token);
    // Current user.
    $info = $this->tokenTreeBuilder->buildTree('current-user');
    if (isset($info['[' . $token . ']'])) {
      return $info['[' . $token . ']']['name'];
    }

    $info = $this->tokenTreeBuilder->buildTree($entity_type);
    if (isset($info['[' . $token . ']'])) {
      return $info['[' . $token . ']']['name'];
    }

    return 'Unknown Token';
  }

}
