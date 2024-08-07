<?php

namespace Drupal\ai_assistant_api;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Render\RendererInterface;

/**
 * The runner for the AI assistant.
 */
class AiAssistantApiRunner {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The assistant.
   *
   * @var \Drupal\ai_assistant_api\Entity\AiAssistant
   */
  protected AiAssistant $assistant;

  /**
   * The AI provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The message to send to the assistant.
   *
   * @var \Drupal\ai_assistant_api\Data\UserMessage
   */
  protected UserMessage $userMessage;

  /**
   * The Drupal renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected RendererInterface $renderer;

  /**
   * If it should be a streaming result.
   *
   * @var bool
   */
  protected bool $streaming = FALSE;

  /**
   * The context for the assistant.
   *
   * @var array
   */
  protected array $context = [];

  /**
   * Boolean to keep track if the context was used.
   *
   * @var bool
   */
  protected bool $contextUsed = FALSE;

  /**
   * Set token replacements.
   *
   * @var array
   */
  protected array $tokens = [];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Drupal renderer.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AiProviderPluginManager $aiProvider, Renderer $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiProvider = $aiProvider;
    $this->renderer = $renderer;
  }

  /**
   * Set the assistant.
   *
   * @param \Drupal\ai_assistant_api\Entity\AiAssistant $assistant
   *   The assistant.
   */
  public function setAssistant(AiAssistant $assistant) {
    // This is immutable once set.
    if (!isset($this->assistant)) {
      $this->assistant = $assistant;
    }
    else {
      throw new \Exception('Assistant is immutable once set.');
    }
  }

  /**
   * Set the context.
   *
   * @param array $context
   *   The context to set.
   */
  public function setContext($context) {
    $this->context = $context;
  }

  /**
   * Set streaming.
   *
   * @param bool $streaming
   *   If the output should be streamed.
   */
  public function streamedOutput(bool $streaming) {
    $this->streaming = $streaming;
  }

  /**
   * Set a message to the assistant.
   *
   * @param \Drupal\ai_assistant_api\Data\UserMessage $userMessage
   *   The message to set.
   */
  public function setUserMessage(UserMessage $userMessage) {
    $this->userMessage = $userMessage;
    $this->tokens['question'] = $userMessage->getMessage();
  }

  /**
   * Start processing the assistant synchrounously.
   */
  public function process() {
    if (!$this->assistant) {
      throw new \Exception('Assistant is required to process.');
    }
    if (!$this->userMessage) {
      throw new \Exception('Message is required to process.');
    }

    // Process rag if its installed.
    if ($this->assistant->get('rag_enabled')) {
      $rag_databases = $this->assistant->get('rag_databases');
      foreach ($rag_databases as $rag_database) {
        $results = $this->getRagResults($rag_database);
        // If we don't have any results, we send the error message if wanted.
        if ((empty($results) || $results->getResultCount() === 0) &&
          $this->assistant->get('no_results_message')
        ) {
          return new ChatOutput(
            new ChatMessage('assistant', $this->assistant->get('no_results_message')),
            [$this->assistant->get('no_results_message')],
            [],
          );
        }
        // Get the results we are interested in as a string.
        $this->tokens['rag_context'] = $this->renderRagResponseAsString($results, $rag_database);
      }
    }
    // Run the response to the final assistants message.
    return $this->assistantMessage();
  }

  /**
   * Run the final assistants message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the assistant.
   */
  protected function assistantMessage() {
    $provider = $this->aiProvider->createInstance($this->assistant->get('llm_provider'));
    $message = $this->assistant->get('assistant_message');
    foreach ($this->tokens as $key => $value) {
      $message = str_replace('[' . $key . ']', $value, $message);
    }
    $config = [];
    foreach ($this->assistant->get('llm_configuration') as $key => $val) {
      $config[$key] = $val;
    }
    $provider->setConfiguration($config);
    if ($this->streaming) {
      $provider->streamedOutput(TRUE);
    }
    $input = new ChatInput([
      new ChatMessage('user', $message),
    ]);
    return $provider->chat($input, $this->assistant->get('llm_model'));
  }

  /**
   * Render the RAG response as string.
   *
   * @param \Drupal\search_api\Query\ResultSet $results
   *   The RAG results.
   * @param array $rag_database
   *   The RAG database array data.
   *
   * @return string
   *   The RAG response.
   */
  protected function renderRagResponseAsString($results, array $rag_database) {
    $response = '';
    foreach ($results as $result) {
      // Filter the results.
      if (!$this->contextUsed && $this->$rag_database['score_threshold'] > $result->getScore()) {
        continue;
      }
      if ($this->contextUsed && $this->$rag_database['context_threshold'] > $result->getScore()) {
        continue;
      }
      // Chunked mode is easy.
      if ($rag_database['output_mode'] == 'chunks') {
        $response .= $result->getExtraData('content') . "\n\n";
        $response .= '----------------------------------------' . "\n\n";
      }
      else {
        // LLM checking results.
        $response .= $this->fullEntityCheck($result, $rag_database);
      }
    }
    return $response;
  }

  /**
   * Process RAG.
   *
   * @param array $rag_database
   *   The RAG database array data.
   *
   * @return \Drupal\search_api\Query\ResultSet
   *   The RAG response.
   */
  protected function getRagResults(array $rag_database) {
    /** @var \Drupal\search_api\Entity\Index */
    $rag_storage = $this->entityTypeManager->getStorage('search_api_index');
    // Get the index.
    $index = $rag_storage->load($rag_database['database']);
    if (!$index) {
      throw new \Exception('RAG database not found.');
    }

    // Check if we should use context and if there is context.
    $context_match = FALSE;
    if ($rag_database['use_context'] && !empty($this->context)) {
      $context_match = $this->checkContentContextMatches($index);
      $this->contextUsed = TRUE;
    }

    // Then we try to search.
    try {
      $query = $index->query([
        'limit' => $rag_database['max_results'],
      ]);
      // If we have context, we filter on that.
      if ($context_match) {
        $query->addCondition('drupal_entity_id', $context_match, '==');
      }
      $query->setOption('search_api_bypass_access', FALSE);
      $query->setOption('search_api_ai_get_chunks_result', $rag_database['output_mode'] == 'chunks');
      $query->keys([$this->userMessage->getMessage()]);
      $results = $query->execute();
    }
    catch (\Exception $e) {
      throw new \Exception('Failed to search: ' . $e->getMessage());
    }
    return $results;
  }

  /**
   * Full entity check with a LLM checking the rendered entity.
   *
   * @param \Drupal\search_api\Query\Result $result
   *   The result to check.
   * @param array $rag_database
   *   The RAG database array data.
   *
   * @return string
   *   The response.
   */
  protected function fullEntityCheck($result, array $rag_database) {
    $entity_string = $result->getExtraData('drupal_entity_id');
    // Load the entity from search api key.
    // @todo probably exists a function for this.
    [, $entity_parts, $lang] = explode(':', $entity_string);
    [$entity_type, $entity_id] = explode('/', $entity_parts);
    /** @var \Drupal\Core\Entity\ContentEntityBase */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    // Get translated if possible.
    if (method_exists($entity, 'hasTranslation')) {
      if ($entity->hasTranslation($lang)) {
        $entity = $entity->getTranslation($lang);
      }
    }
    // Render the entity in default view mode.
    $pre_render_entity = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity);
    $rendered_entity = nl2br(trim(strip_tags($this->renderer->render($pre_render_entity))));
    $message = str_replace([
      '[question]',
      '[entity]',
    ], [
      $this->userMessage->getMessage(),
      $rendered_entity,
    ], nl2br($rag_database['aggregated_llm']));

    // Now we have the entity, we can check it with the LLM.
    $provider = $this->aiProvider->createInstance($this->assistant->get('llm_provider'));
    $config = [];
    foreach ($rag_database['llm_configuration'] as $key => $val) {
      $config[$key] = $val;
    }
    $provider->setConfiguration($config);
    $input = new ChatInput([
      new ChatMessage('user', $message),
    ]);
    $output = $provider->chat($input, $this->assistant->get('llm_model'));
    $response = $output->getNormalized()->getText() . "\n";
    $response .= '----------------------------------------' . "\n\n";
    return $response;
  }

  /**
   * Check for context matches.
   *
   * @param \Drupal\search_api\Entity\Index $index
   *   The index to check.
   *
   * @return string
   *   If the context matches.
   */
  protected function checkContentContextMatches($index) {
    // Check for context.
    $keys = array_keys($this->context);
    // Check if any of the keys are content entities.
    foreach ($keys as $key) {
      $possible_entity = $this->context[$key];
      if (is_object($possible_entity) && $possible_entity instanceof ContentEntityInterface) {
        // Check if the entity type is in the index.
        if ($index->isValidDatasource('entity:' . $possible_entity->getEntityTypeId())) {
          // Get the bundles for the indfex.
          $bundles = $index->getDatasource('entity:' . $possible_entity->getEntityTypeId())->getBundles();
          // Check if the bundle is in the index.
          if (in_array($possible_entity->bundle(), array_keys($bundles))) {
            return 'entity:' . $possible_entity->getEntityTypeId() . '/' . $possible_entity->id() . ':' . $possible_entity->language()->getId();
          }
        }
      }
    }
    return "";
  }

}
