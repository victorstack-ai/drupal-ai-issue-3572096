<?php

namespace Drupal\ai_automator\Plugin\AiAutomatorProcess;

use Drupal\ai_automator\AiAutomatorRuleRunner;
use Drupal\ai_automator\AiAutomatorStatusField;
use Drupal\ai_automator\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automator\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * {@inheritDoc}
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
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $entry = [
      'entity' => $entity,
      'fieldDefinition' => $fieldDefinition,
      'automatorConfig' => $automatorConfig,
    ];

    $this->batch[] = [
      'Drupal\ai_automator\Batch\ProcessField::saveField',
      [$entry],
    ];
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_PROCESSING;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
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
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
