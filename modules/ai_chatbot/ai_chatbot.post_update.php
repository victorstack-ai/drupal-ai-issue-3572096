<?php

/**
 * @file
 * Post update functions for AI Chatbot.
 */

/**
 * Remove deprecated ai_chatbot_block instances.
 */
function ai_chatbot_post_update_remove_chatbot_blocks(): void {
  $block_storage = \Drupal::entityTypeManager()->getStorage('block');
  $blocks = $block_storage->loadByProperties(['plugin' => 'ai_chatbot_block']);
  foreach ($blocks as $block) {
    $block->delete();
  }
}
