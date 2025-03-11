<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat\Tools;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput
 */
class ToolsFunctionInputTest extends TestCase {

  /**
   * Test the constructor.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::__construct
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getName
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setName
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getDescription
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setDescription
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getProperties
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setProperties
   * @group ai
   */
  public function testConstructor() {
    $function = new ToolsFunctionInput();
    $this->assertEquals('', $function->getName());
    $this->assertEquals('', $function->getDescription());
    $this->assertEquals([], $function->getProperties());
    $this->assertEquals([], $function->getRequiredProperties());

    $property = new ToolsPropertyInput('test', [
      'description' => 'Test description',
      'type' => 'string',
      'default' => 'test',
    ]);
    $property2 = new ToolsPropertyInput('test2', [
      'description' => 'Test description',
      'type' => 'string',
      'default' => 'test',
    ]);
    $function = new ToolsFunctionInput('test', [
      'description' => 'Test description',
      'properties' => [$property, $property2],
      'required' => [$property],
    ]);
    $this->assertEquals('test', $function->getName());
    $this->assertEquals('Test description', $function->getDescription());
    $this->assertEquals(2, count($function->getProperties()));
    $this->assertEquals(1, count($function->getRequiredProperties()));
    $this->assertEquals($property, $function->getProperties()['test']);
    $this->assertEquals($property2, $function->getProperties()['test2']);
    $this->assertEquals($property, $function->getRequiredProperties()['test']);
  }

}
