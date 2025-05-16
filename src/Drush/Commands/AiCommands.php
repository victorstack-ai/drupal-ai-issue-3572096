<?php

declare(strict_types=1);

namespace Drupal\ai\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Boot\DrupalBootLevels;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

class AiCommands extends DrushCommands
{
  const DEFAULT_SYSTEM_PROMPT = <<<EOD
    You are a Drupal assistant, capable of answer questions about Drupal and Drush. When responding, make sure...

    1. To keep all answers brief, and in ANSI
    2. When highlighting text, wrap it in <info></info> tags
    3. When output errors, use <error></error> tags
    4. When writing optional text, use <comment></comment> tags
    5. When posing questions, use <question></question> tags
    6. When outputing a link, write the link in this format: <href=https://drupal.org>Drupal</>
    EOD;

  /**
   * Send a message through to an AI provider.
   */
  #[CLI\Command(name: 'ai:chat', aliases: ['chat', 'ai'])]
  #[CLI\Argument(name: 'input', description: 'The message to send to the chat.')]
  #[CLI\Option(name: 'provider', description: 'Indicates a provider to use other than the default.')]
  #[CLI\Option(name: 'model', description: 'Indicates which model to use, other than the default.')]
  #[CLI\Option(name: 'system', description: 'Indicates the system message to use, other than the default.')]
  #[CLI\Usage(name: 'drush ai:chat "Hello, how are you?"', description: 'Sends a message to your chat provider.')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function chat(string $input, array $options = ['provider' => self::OPT, 'model' => self::OPT, 'system' => self::OPT]): void
  {
    // Make sure an AI Provider exists.
    if (!\Drupal::hasService('ai.provider')) {
      $this->logger()->error(dt('No AI Provider Service found'));
      return;
    }

    // Retrieve the AI Provider and the options.
    $service = \Drupal::service('ai.provider');
    $defaults = $service->getDefaultProviderForOperationType('chat');
    $provider_id = empty($options['provider']) ? $defaults['provider_id'] : $options['provider'];
    $model_id = empty($options['model']) ? $defaults['model_id'] : $options['model'];
    $system_message = !empty($optionts['system']) ? $option['system'] : self::DEFAULT_SYSTEM_PROMPT;

    // Validate the provider ID and model ID.
    if (empty($provider_id) || empty($model_id)) {
      $this->logger()->error(dt('No default AI provider or model set'));
      return;
    }

    // Build the provider and the chat message.
    $provider = $service->createInstance($provider_id);
    $messages = new ChatInput([
      new chatMessage('system', $system_message),
      new chatMessage('user', $input),
    ]);

    // Output the message.
    $message = $provider->chat($messages, $model_id)->getNormalized();
    $lines = explode("\n", $message->getText());
    foreach ($lines as $line) {
      $this->output()->writeln($line);
    }
  }
}
