<?php

namespace Drupal\ai_ckeditor\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Returns responses for CKEditor integration routes.
 */
class Completion implements ContainerInjectionInterface {

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager;
   */
  protected $aiProvider;

  /**
   * The Completion controller constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $api
   *   The AI Provider.
   */
  public function __construct(AiProviderPluginManager $aiProvider) {
    $this->aiProvider = $aiProvider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * Builds the response.
   */
  public function generate(Request $request) {
    $data = json_decode($request->getContent());
    if (!isset($data->options->provider)) {
      throw new \Exception('No provider seems to be set for CKEditor AI Plugin.');
    }

    $provider = $this->aiProvider->loadProviderFromSimpleOption($data->options->provider);
    $provider->streamedOutput();

    $messages = new ChatInput([
      new chatMessage('system', 'You are an expert in content editing and an assistant to a user writing content for their website. Please return all answers without using first, second, or third person voice.'),
      new chatMessage('user', $data->prompt),
    ]);

    /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
    $message = $provider->chat($messages, $this->aiProvider->getModelNameFromSimpleOption($data->options->provider))->getNormalized();
    if ($message instanceof StreamedChatMessageIteratorInterface) {
      // This is a stream response.
      // You can loop through the response and output it as it comes in.
      $response = new StreamedResponse();
      $response->setCallback(function () use ($message): void {
        /* @var StreamedChatMessage $chat_message */
        foreach ($message as $chat_message) {
          $text = $chat_message->getText();
          echo $text;
          ob_flush();
          flush();
        }
      });
      $response->send();
    } else {
      // This is a normal response.
      $response = new Response(
        'Content',
        Response::HTTP_OK,
        ['content-type' => 'text/plain']
      );
      $response->setContent($message->getText());
      return $response;
    }
  }

}
