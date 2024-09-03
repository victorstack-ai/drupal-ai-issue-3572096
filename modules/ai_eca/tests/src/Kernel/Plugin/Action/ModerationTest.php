<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

/**
 * Kernel tests for the "ai_eca_execute_moderation"-action plugin.
 *
 * @group ai
 */
class ModerationTest extends AiActionTestBase {

  /**
   * Test action-plugin with all options provided.
   */
  public function testAllOptions(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Token input name.
    $tokenInputName = $this->randomMachineName();
    $tokenInputValue = [$this->randomMachineName() => $this->randomString()];
    $this->tokenService->addTokenData($tokenInputName, $tokenInputValue);

    /** @var \Drupal\ai_eca\Plugin\Action\Moderation $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_moderation', [
      'token_result' => $tokenResultName,
      'token_input' => $tokenInputName,
      'model' => 'echoai__ai',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replace(sprintf('[%s:information:input]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the given data is present.
    $this->assertStringContainsString(reset($tokenInputValue), $output);
    // Assert that the input token name is not present.
    $this->assertStringNotContainsString($tokenInputName, $output);
    // Assert that the input is not flagged.
    $this->assertEquals('1', $this->tokenService->replace(sprintf('[%s:flagged]', $tokenResultName)));
    // Assert that the test-module as added as a dependency.
    $this->assertContains('ai_test', $action->calculateDependencies()['module']);
  }

}
