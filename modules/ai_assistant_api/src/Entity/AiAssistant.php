<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Entity;

use Drupal\ai_assistant_api\AiAssistantInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the ai assistant entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_assistant",
 *   label = @Translation("AI Assistant"),
 *   label_collection = @Translation("AI Assistants"),
 *   label_singular = @Translation("AI Assistant"),
 *   label_plural = @Translation("AI Assistants"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI assistant",
 *     plural = "@count AI Assistants",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_assistant_api\AiAssistantListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "edit" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_assistant",
 *   admin_permission = "administer ai_assistant",
 *   links = {
 *     "collection" = "/admin/structure/ai-assistant",
 *     "add-form" = "/admin/structure/ai-assistant/add",
 *     "edit-form" = "/admin/structure/ai-assistant/{ai_assistant}",
 *     "delete-form" = "/admin/structure/ai-assistant/{ai_assistant}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "system_role",
 *     "rag_enabled",
 *     "rag_databases",
 *     "assistant_message",
 *     "no_results_message",
 *     "error_message",
 *     "llm_provider",
 *     "llm_model",
 *     "llm_configuration",
 *   },
 * )
 */
final class AiAssistant extends ConfigEntityBase implements AiAssistantInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The system role.
   */
  protected string $system_role;

  /**
   * The RAG enabled.
   */
  protected bool $rag_enabled;

  /**
   * The RAG databases.
   */
  protected array $rag_databases = [];

  /**
   * The assistant message.
   */
  protected string $assistant_message;

  /**
   * The no results message.
   */
  protected string $no_results_message;

  /**
   * The error message.
   */
  protected string $error_message;

  /**
   * The LLM provider.
   */
  protected string $llm_provider;

  /**
   * The LLM model.
   */
  protected string $llm_model;

  /**
   * The LLM configuration.
   */
  protected array $llm_configuration;

}
