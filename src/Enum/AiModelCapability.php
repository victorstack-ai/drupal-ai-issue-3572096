<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * Enum of AI provider model capabilities, which aren't shared across.
 */
enum AiModelCapability: string {
  // Allows the chat model to include image vision in chat.
  case ChatWithImageVision = 'chat_with_image_vision';
  // Allows the chat model to include a system role.
  case ChatSystemRole = 'chat_system_role';
  // Allow the chat model that can do flawless complex JSON output.
  case ChatJsonOutput = 'chat_json_output';

}
