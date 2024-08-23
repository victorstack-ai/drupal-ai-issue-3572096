<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * Enum of AI provider model capabilities, which aren't shared across all of these.
 */
enum AiModelCapability: string {
  case ChatWithImageVision = 'chat_with_image_vision';
}
