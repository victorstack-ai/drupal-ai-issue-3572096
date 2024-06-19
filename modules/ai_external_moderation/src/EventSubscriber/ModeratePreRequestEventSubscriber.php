<?php

namespace Drupal\ai_external_moderation\EventSubscriber;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai_external_moderation\EventSubscriber
 */
class ModeratePreRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(AiProviderPluginManager $ai, ConfigFactoryInterface $config_factory) {
    $this->aiProvider = $ai;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'moderatePreRequest',
    ];
  }

  /**
   * Check if we should stop a request due to OpenAI moderation.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to log.
   */
  public function moderatePreRequest(PreGenerateResponseEvent $event) {
    // Check the config if we should moderate the provider and type.
    $config = $this->getConfig()->get('moderations');
    $key = $event->getProviderId() . '__' . $event->getOperationType();
    if (empty($config) || !isset($config[$key])) {
      return;
    }
    // Get the openai provider.
    [$moderator, $model_id] = explode('__', $config[$key]);
    try {
      $provider = $this->aiProvider->createInstance($moderator);
    }
    catch (\Exception $e) {
      throw new AiUnsafePromptException($moderator . ' moderation is wanted on a request of type ' . $event->getOperationType() . ' for the provider ' . $event->getProviderId() . ', but it is not installed.');
    }
    // Check that its configured and model id exists.
    if (!$provider->isUsable('moderation') || !$model_id) {
      throw new AiUnsafePromptException($moderator . ' moderation is wanted on a request of type ' . $event->getOperationType() . ' for the provider ' . $event->getProviderId() . ', but it is not configured.');
    }

    // Get the input and json_encode it since it might be complex.
    $input = '';
    if ($event->getInput() instanceof InputInterface) {
      $input = $event->getInput()->toString();
    }
    else {
      // If its raw data, lets json encode it into a string.
      $input = json_encode($event->getInput());
    }
    // Test it against the provider and fail if its not safe.
    if ($provider->moderation($input, $model_id)->getNormalized()->isFlagged()) {
      throw new AiUnsafePromptException($moderator . ' moderation endpoint flagged and stopped this prompt.');
    }
  }

  /**
   * Get the config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  protected function getConfig() {
    return $this->configFactory->get('ai_external_moderation.settings');
  }

}
