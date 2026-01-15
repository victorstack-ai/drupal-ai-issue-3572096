<?php

namespace Drupal\ai_observability\EventSubscriber;

use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai_observability\EventSubscriber
 */
class AiEventsSubscriber implements EventSubscriberInterface {

  /**
   * The logger channel name.
   */
  const LOGGER_NAME = 'ai_observability';

  const SUPPORTED_EVENTS = [
    PreGenerateResponseEvent::class,
    PostStreamingResponseEvent::class,
    PostGenerateResponseEvent::class,
    ProviderDisabledEvent::class,
  ];

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_observability.settings';

  /**
   * Configuration key for event types to log.
   */
  const CONFIG_KEY_LOG_EVENT_TYPES = 'log_event_types';

  /**
   * Configuration key for logging input.
   */
  const CONFIG_KEY_LOG_INPUT = 'log_input';

  /**
   * Configuration key for logging output.
   */
  const CONFIG_KEY_LOG_OUTPUT = 'log_output';

  /**
   * Configuration key for logging tags.
   */
  const CONFIG_KEY_LOG_TAGS = 'log_tags';

  /**
   * Configuration key for the log fallback mode.
   */
  const CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE = 'fallback_log_message_mode';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * UUID to log for streaming.
   *
   * @var array<string>
   */
  protected $streamingUuids = [];

  /**
   * The watchdog logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $logger) {
    $this->config = $configFactory->get(SettingsForm::CONFIG_NAME);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    // We can't read the configuration in this static function, because the
    // Drupal Container is not initialized yet. So, have to subscribe to all
    // supported events.
    $events = [];
    foreach (self::SUPPORTED_EVENTS as $eventClass) {
      $eventName = $eventClass::EVENT_NAME;
      $events[$eventName] = 'logEvent';
    }
    return $events;
  }

  /**
   * Log if needed after running an AI request.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent|\Drupal\ai\Event\ProviderDisabledEvent $event
   *   The event to log.
   *
   * @return void
   *   Does not return a value.
   */
  public function logEvent(AiProviderRequestBaseEvent|ProviderDisabledEvent $event): void {
    if (!in_array(get_class($event), $this->config->get(self::CONFIG_KEY_LOG_EVENT_TYPES))) {
      return;
    }
    if ($event instanceof ProviderDisabledEvent) {
      $this->logger->get(self::LOGGER_NAME)->info('Provider @provider disabled.', [
        '@provider' => $event->getProviderId(),
      ]);
      return;
    }

    $logTags = $this->config->get(self::CONFIG_KEY_LOG_TAGS);
    $tags = $event->getTags();
    if (!empty($logTags) && !array_intersect($logTags, $tags)) {
      return;
    }

    // @todo Generate the context by the event type.
    $context = [
      'metadata' => [
        // As we checking the definition of the constant and have a fallback,
        // the "classConstant.notFound" is not actual.
        // @phpstan-ignore classConstant.notFound
        'event_name' => defined($event::class . '::EVENT_NAME') ? $event::EVENT_NAME : $event::class,
        'provider' => $event->getProviderId(),
        'operation_type' => $event->getOperationType(),
        'model' => $event->getModelId(),
        'provider_request_id' => $event->getRequestThreadId(),
        'provider_request_parent_id' => $event->getRequestParentId(),
        'configuration' => $event->getConfiguration(),
        'tags' => $tags,
      ],
    ];

    if (($input = $event->getInput()) && $input instanceof InputInterface) {
      $context['metadata']['guardrails'] = array_map(function (GuardrailResultInterface $result) {
        return [
          'guardrail' => $result->getGuardrailLabel(),
          'type' => get_class($result),
          'context' => $result->getContext(),
          'message' => $result->getMessage(),
        ];
      }, $input->getGuardrailsResults());
    }

    if ($event instanceof AiProviderResponseBaseEvent) {
      $output = $event->getOutput();
      // Not every AI output type provides the token usage info yet, therefore
      // we need to check if the method exists before calling it.
      // @todo Remove the method_exists check when all output types implement
      // the getTokenUsage method and it is added to the OutputInterface.
      if (method_exists($output, 'getTokenUsage')) {
        $context['metadata']['token_usage'] = $output->getTokenUsage()->toArray();
      }
    }

    if ($this->config->get(self::CONFIG_KEY_LOG_INPUT)) {
      $context['metadata']['input'] = $event->getInput()->toArray();
    }
    if (
      $this->config->get(self::CONFIG_KEY_LOG_OUTPUT)
      && method_exists($event, 'getOutput')
    ) {
      $context['metadata']['output'] = $event->getOutput()->toArray();
    }

    $message = $this->prepareLogMessage($event, $context);

    $this->logger->get(self::LOGGER_NAME)->info($message, $context);
  }

  /**
   * Composes a user-friendly log message with placeholders.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to log.
   * @param mixed $context
   *   The context for the log message. New placeholders will be added there
   *   if the fallback mode is enabled.
   *
   * @return string
   *   The log message string with placeholders
   */
  protected function prepareLogMessage(AiProviderRequestBaseEvent $event, &$context): string {
    if ($event instanceof AiProviderResponseBaseEvent) {
      $messagePrefix = 'Response from provider {metadata.provider}';
    }
    else {
      $messagePrefix = 'Call provider {metadata.provider}';
    }

    $messageParts = [
      'model' => '{metadata.model}',
      'operation type' => '{metadata.operation_type}',
    ];
    if ($context['metadata']['token_usage']['total'] ?? NULL) {
      $messageParts['token usage'] = '{metadata.token_usage.total}';
    }

    $messageItems = [];
    foreach ($messageParts as $key => $value) {
      $messageItems[] = "$key: $value";
    }

    $message = $messagePrefix . ': ' . implode(', ', $messageItems) . '.';

    if ($this->config->get(self::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE)) {
      $messagePlaceholders = [];
      preg_match_all('/\{([^\}]+)\}/', $message, $matches);
      if (!empty($matches[1])) {
        foreach ($matches[1] as $placeholder) {
          $messagePlaceholders[] = $placeholder;
        }
      }
      $replacements = [];
      foreach ($messagePlaceholders as $placeholder) {
        $path = explode('.', $placeholder);
        $value = (string) NestedArray::getValue($context, $path);
        $fallbackPlaceholder = '@' . implode('_', $path);
        $placeholderFull = '{' . $placeholder . '}';
        $replacements[$placeholderFull] = $fallbackPlaceholder;
        $context[$fallbackPlaceholder] = $value;
      }
      if (!empty($replacements)) {
        $message = strtr($message, $replacements);
      }
    }

    return $message;
  }

}
