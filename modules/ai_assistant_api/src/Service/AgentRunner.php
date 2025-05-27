<?php

namespace Drupal\ai_assistant_api\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;

/**
 * Class AgentRunner, runs agents as assistants.
 */
class AgentRunner {

  /**
   * Constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider.
   */
  public function __construct(
    public AiProviderPluginManager $aiProvider,
  ) {
  }

  /**
   * The assistant.
   *
   * @var string $assistant_id
   *   The assistant id.
   * @var array $chat_history
   *   The chat history.
   */
  public function runAsAgent(string $assistant_id, array $chat_history, array $defaults) {
    /** @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent */
    // @phpstan-ignore-next-line
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance($assistant_id);
    // Remove the last message from the chat history.
    $new_messages = [];
    foreach ($chat_history as $key => $message) {
      $new_messages[] = new ChatMessage($message['role'], $message['content']);
    }
    $agent->setChatInput(new ChatInput($new_messages));
    $agent->setAiProvider($this->aiProvider->createInstance($defaults['provider_id']));
    $agent->setModelName($defaults['model_id']);
    $agent->setCreateDirectly(TRUE);
    $solvability = $agent->determineSolvability();
    if ($solvability == AiAgentInterface::JOB_NEEDS_ANSWERS) {
      $questions = $agent->askQuestion();
      if ($questions && is_array($questions)) {
        return new ChatOutput(
          new ChatMessage('assistant', implode("\n", $questions)),
          [implode("\n", $questions)],
          [],
        );
      }
    }
    elseif ($solvability == AiAgentInterface::JOB_NOT_SOLVABLE) {
      return new ChatOutput(
        new ChatMessage('assistant', "Could not solve this task."),
        ["Could not solve this task"],
        [],
      );
    }
    elseif ($solvability == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
      return new ChatOutput(
        new ChatMessage('assistant', $agent->answerQuestion()),
        [$agent->answerQuestion()],
        [],
      );
    }
    elseif ($solvability == AiAgentInterface::JOB_INFORMS) {
      return new ChatOutput(
        new ChatMessage('assistant', $agent->inform()),
        [$agent->inform()],
        [],
      );
    }
    else {
      $response = $agent->solve();
      return new ChatOutput(
        new ChatMessage('assistant', $response),
        [$response],
        [],
      );
    }
  }

}
