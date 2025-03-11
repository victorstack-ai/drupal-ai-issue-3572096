<?php

namespace Drupal\ai\Plugin\AiFunctionCall;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Action\ActionPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Plugin\AiFunctionCall\Derivative\ActionPluginDeriver;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the action plugin function.
 */
#[FunctionCall(
  id: 'action_plugin',
  function_name: 'action_plugin',
  name: new TranslatableMarkup('Action Plugin Wrapper'),
  description: '',
  deriver: ActionPluginDeriver::class
)]
class ActionPluginBase extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The action plugin.
   *
   * @var \Drupal\Core\Action\ActionInterface
   */
  protected $pluginCollection;

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new ContextDefinitionNormalizer(),
    );
    $instance->actionManager = $container->get('plugin.manager.action');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $action_plugin = $this->getPluginCollection()->get($this->getDerivativeId());
    if ($action_plugin instanceof ConfigurableInterface) {
      $params = [];
      $configuration = $action_plugin->getConfiguration();
      // If context keys exist, not in configuration, set to execute.
      foreach ($this->getContexts() as $key => $context) {
        if (isset($configuration[$key])) {
          $configuration[$key] = $context->getContextValue();
        }
        else {
          $params[$key] = $context;
        }
      }
    }
    else {
      $params = $this->getContexts();
    }

    $action_plugin->execute(...$params);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return '';
  }

  /**
   * Encapsulates the creation of the action's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new ActionPluginCollection($this->actionManager, $this->getDerivativeId(), []);
    }
    return $this->pluginCollection;
  }

}
