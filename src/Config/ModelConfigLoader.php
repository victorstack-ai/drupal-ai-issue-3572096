<?php

namespace Drupal\ai\Config;

use Drupal\ai\Enum\Bundles;
use Drupal\ai\Utility\StringUtility;

/**
 * Loader of LLM configuration settings.
 */
class ModelConfigLoader {

  /**
   * Returns LLM-specific settings.
   *
   * @return array[]
   *   Configurations.
   */
  public static function loadConfigSettings(): array {
    return [
      'huggingface' => [
        'bundles' => [
          StringUtility::pascalToSnake(Bundles::Chat->name) => [
            'settings_key' => 'known_providers.huggingface.chat.configuration.api',
          ],
          StringUtility::pascalToSnake(Bundles::Summarization->name) => [
            'settings_key' => 'known_providers.huggingface.summarization.configuration.api',
          ],
        ],
        'title' => 'Hugging Face',
      ],
    ];
  }

  /**
   * Returns LLM-specific settings.
   *
   * @return array[]
   *   Configurations.
   */
  public static function loadSettings(): array {
    $settings = \Drupal::service('config.factory')->get('ai.settings');
    $chat_title = $settings->get('known_providers.huggingface.chat.configuration.api.name.default');
    $chat_endpoint = $settings->get('known_providers.huggingface.chat.configuration.api.namespace.default');
    $summarization_title = $settings->get('known_providers.huggingface.summarization.configuration.api.name.default');
    $summarization_endpoint = $settings->get('known_providers.huggingface.summarization.configuration.api.namespace.default');
    return [
      StringUtility::pascalToSnake(Bundles::Chat->name) => [
        'endpoint' => $chat_endpoint,
        'llms' => [
          'huggingface_chat' => [
            'title' => $chat_title,
          ],
        ],
      ],
      StringUtility::pascalToSnake(Bundles::Summarization->name) => [
        'endpoint' => $summarization_endpoint,
        'llms' => [
          'huggingface_summarization' => [
            'title' => $summarization_title,
          ],
        ],
      ],
    ];
  }

}
