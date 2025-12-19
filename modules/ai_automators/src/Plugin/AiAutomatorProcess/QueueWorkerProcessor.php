<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The queue processor.
 */
#[AiAutomatorProcessRule(
  id: 'queue',
  title: new TranslatableMarkup('Queue/Cron'),
  description: new TranslatableMarkup('Saves as a queue worker and runs on cron.'),
)]
class QueueWorkerProcessor implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * A queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructor.
   */
  final public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
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
      $container->get('queue'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, AiAutomatorTypeInterface $automatorType) {
    $queue = $this->queueFactory->get('ai_automator_field_modifier');
    $automatorTypeConfig = $automatorType->getConfiguration();
    $queue->createItem([
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'fieldDefinition' => $fieldDefinition,
      'automatorConfig' => $automatorTypeConfig,
    ]);
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(ContentEntityInterface $entity) {
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(ContentEntityInterface $entity) {
  }

  /**
   * Should run on import.
   *
   * @return bool
   *   TRUE if should run on import, FALSE otherwise.
   */
  public function isImport() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
