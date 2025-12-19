<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The batch processor.
 */
#[AiAutomatorProcessRule(
  id: 'batch',
  title: new TranslatableMarkup('Batch'),
  description: new TranslatableMarkup('Uses JavaScript batch queue (not recommended), will not work on programmatical saving.'),
)]
class BatchProcessing implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * The batch.
   *
   * @var array<mixed>
   */
  protected array $batch;

  /**
   * AI Runner.
   */
  protected AiAutomatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructor.
   */
  final public function __construct(AiAutomatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_automator.rule_runner'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType) {
    $automatorTypeConfig = $automatorType->getConfiguration();
    $entry = [
      'entity' => $entity,
      'fieldDefinition' => $fieldDefinition,
      'automatorConfig' => $automatorTypeConfig,
    ];

    $this->batch[] = [
      'Drupal\ai_automators\Batch\ProcessField::saveField',
      [$entry],
    ];
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(ContentEntityInterface $entity) {
    // @phpstan-ignore-next-line
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_PROCESSING;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(ContentEntityInterface $entity) {
    if (!empty($this->batch)) {
      $batch = [
        'operations' => $this->batch,
        'title' => 'AI Automator',
        'init_message' => 'Processing AI fields.',
        'progress_message' => 'Processed @current out of @total.',
        'error_message' => 'Something went wrong.',
      ];
      \batch_set($batch);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
