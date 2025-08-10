<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Component\Serialization\Json;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Traits\OperationType\EventDispatcherTrait;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  use EventDispatcherTrait;

  /**
   * The iterator.
   *
   * @var \Traversable
   */
  protected $iterator;

  /**
   * The messages.
   *
   * @var array
   *   The stream chat messages.
   */
  protected $messages = [];

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The finish reason.
   *
   * @var string
   */
  protected $finishReason;

  /**
   * The tool calls used.
   *
   * @var array
   */
  protected $toolCalls = [];

  /**
   * Total token usage.
   *
   * @var int|null
   */
  protected $totalTokenUsage = NULL;

  /**
   * Input token usage.
   *
   * @var int|null
   */
  protected $inputTokenUsage = NULL;

  /**
   * Output token usage.
   *
   * @var int|null
   */
  protected $outputTokenUsage = NULL;

  /**
   * Reasoning token usage.
   *
   * @var int|null
   */
  protected $reasoningTokenUsage = NULL;

  /**
   * Cached token usage.
   *
   * @var int|null
   */
  protected $cachedTokenUsage = NULL;

  /**
   * The created chat output after iteration.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatOutput|null
   */
  protected $chatOutput = NULL;

  /**
   * Constructor.
   */
  public function __construct(\Traversable $iterator) {
    $this->iterator = $iterator;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    foreach ($this->doIterate() as $data) {
      yield $data;
    }
    $this->reconstructChatOutput();
    $this->triggerEvent();
  }

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    // We keep this empty, so anyone extending from 1.2.0 can implement
    // their own logic.
    yield $this->createStreamedChatMessage(
      'assistant',
      'Please implement the doIterate method in your provider.',
      [],
    );
  }

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void {
    // Dispatch the event.
    $event = new PostStreamingResponseEvent($this->requestThreadId ?? '', $this->chatOutput, []);
    $event = $this->setTokenUsageOnEvent($event);
    $this->getEventDispatcher()->dispatch($event, PostStreamingResponseEvent::EVENT_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestThreadId(string $request_thread_id): void {
    $this->requestThreadId = $request_thread_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestThreadId(): string {
    return $this->requestThreadId;
  }

  /**
   * {@inheritdoc}
   */
  public function createStreamedChatMessage(
    string $role,
    string $message,
    array $metadata,
    ?array $tools = NULL,
    ?array $raw = NULL,
  ): StreamedChatMessageInterface {
    $message = new StreamedChatMessage($role, $message, $metadata, $tools, $raw);
    $this->messages[] = $message;
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamChatMessages(): array {
    return $this->messages;
  }

  /**
   * {@inheritdoc}
   */
  public function setStreamChatMessages(array $messages): void {
    $this->messages = $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function reconstructChatOutput(): ChatOutput {
    // Create a ChatMessage out of it all.
    $role = '';
    $message_text = '';
    $raw = [];
    foreach ($this->messages as $message) {
      // The role only needs to be set once, so we check if it's empty.
      if (!empty($message->getRole()) && empty($role)) {
        $role = $message->getRole();
      }
      // Just accumulate the text.
      if (!empty($message->getText())) {
        $message_text .= $message->getText();
      }
      // We assume that we can combine, any external provider can override this
      // if needed.
      if (!empty($message->getRaw())) {
        $raw = array_merge($raw, $message->getRaw());
      }

      // Set the total usage, if it exists.
      $this->setTokenUsageFromChunk($message);
    }

    $message = new ChatMessage($role, $message_text);
    $message->setTools($this->assembleToolCalls());

    $output = new ChatOutput($message, $raw, []);
    // Set the token usage on the output.
    $output = $this->setTokenUsageOnChatOutput($output);

    $this->chatOutput = $output;
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return $this->assembleToolCalls();
  }

  /**
   * Assembled the tools calls used - OpenAI style, can be overriden.
   *
   * @return array
   *   The tool calls used.
   */
  private function assembleToolCalls(): array {
    $tools = [];
    $key = 0;
    $current_tool = NULL;
    foreach ($this->messages as $message) {
      if ($message->getTools()) {
        foreach ($message->getTools() as $tool) {
          $array_tool = $tool->toArray();
          // If it has a new id, it means its a new tool call.
          if (!empty($array_tool['id'])) {
            // If the current tool is not empty, we need to save it.
            if (!empty($current_tool)) {
              $arguments = Json::decode($current_tool['function']['arguments']);
              $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
              $output->setName($current_tool['function']['name']);
              $tools[$key] = $output;
              $key++;
            }
            // Reset the current tool.
            $current_tool = $array_tool;
          }
          else {
            // Otherwise we just add to the argument of the current tool.
            $current_tool['function']['arguments'] .= $array_tool['function']['arguments'] ?? '';
          }
        }
      }
    }
    // Save the last tool if it exists.
    if (!empty($current_tool)) {
      $arguments = Json::decode($current_tool['function']['arguments']);
      $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
      $output->setName($current_tool['function']['name']);
      $tools[$key] = $output;
    }

    return $tools;
  }

  /**
   * Helper function to set the token usage on the event.
   *
   * @param \Drupal\ai\Event\PostStreamingResponseEvent $event
   *   The event to set the token usage on.
   *
   * @return \Drupal\ai\Event\PostStreamingResponseEvent
   *   The event with the token usage set.
   */
  protected function setTokenUsageOnEvent(PostStreamingResponseEvent $event): PostStreamingResponseEvent {
    if ($this->totalTokenUsage !== NULL) {
      $event->setTotalTokenUsage($this->totalTokenUsage);
    }
    if ($this->inputTokenUsage !== NULL) {
      $event->setInputTokenUsage($this->inputTokenUsage);
    }
    if ($this->outputTokenUsage !== NULL) {
      $event->setOutputTokenUsage($this->outputTokenUsage);
    }
    if ($this->reasoningTokenUsage !== NULL) {
      $event->setReasoningTokenUsage($this->reasoningTokenUsage);
    }
    if ($this->cachedTokenUsage !== NULL) {
      $event->setCachedTokenUsage($this->cachedTokenUsage);
    }

    return $event;
  }

  /**
   * Set the token usage from each chunk.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface $message
   *   The streamed chat message to set the token usage on.
   */
  protected function setTokenUsageFromChunk(StreamedChatMessageInterface $message): void {
    if ($message->getTotalTokenUsage() !== NULL) {
      $this->totalTokenUsage = $message->getTotalTokenUsage();
    }
    if ($message->getInputTokenUsage() !== NULL) {
      $this->inputTokenUsage = $message->getInputTokenUsage();
    }
    if ($message->getOutputTokenUsage() !== NULL) {
      $this->outputTokenUsage = $message->getOutputTokenUsage();
    }
    if ($message->getReasoningTokenUsage() !== NULL) {
      $this->reasoningTokenUsage = $message->getReasoningTokenUsage();
    }
    if ($message->getCachedTokenUsage() !== NULL) {
      $this->cachedTokenUsage = $message->getCachedTokenUsage();
    }
  }

  /**
   * Set the token usage on the chat output.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $output
   *   The chat output to set the token usage on.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output with the token usage set.
   */
  protected function setTokenUsageOnChatOutput(ChatOutput $output): ChatOutput {
    if ($this->totalTokenUsage !== NULL) {
      $output->setTotalTokenUsage($this->totalTokenUsage);
    }
    if ($this->inputTokenUsage !== NULL) {
      $output->setInputTokenUsage($this->inputTokenUsage);
    }
    if ($this->outputTokenUsage !== NULL) {
      $output->setOutputTokenUsage($this->outputTokenUsage);
    }
    if ($this->reasoningTokenUsage !== NULL) {
      $output->setReasoningTokenUsage($this->reasoningTokenUsage);
    }
    if ($this->cachedTokenUsage !== NULL) {
      $output->setCachedTokenUsage($this->cachedTokenUsage);
    }

    return $output;
  }

}
