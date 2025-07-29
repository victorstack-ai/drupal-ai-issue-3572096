<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiDataTypeConverter;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * This tests yaml converter.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiDataTypeConverter\YamlDeserializer
 *
 * @group ai
 */
class YamlDeserializerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'key',
    'file',
    'system',
    'node',
    'media',
    'comment',
    'user',
  ];

  /**
   * Test the applicability.
   */
  public function testYamlConverterApplicability(): void {
    $converter = $this->container->get('plugin.manager.ai_data_type_converter')->createInstance('yaml_deserializer');
    // Check so it applied to data type.
    $this->assertTrue($converter->appliesToDataType('yaml_as_string')->applies());
    $converter_result = $converter->appliesToDataType('string');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The data type is not YAML.');
    $converter_result = $converter->appliesToDataType('array');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The data type is not YAML.');

    // Check so it applied to value.
    $this->assertTrue($converter->appliesToValue('yaml_as_string', Yaml::dump(['test']))->applies());
    $this->assertTrue($converter->appliesToValue('yaml_as_string', Yaml::dump([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]))->applies());
    $converter_result = $converter->appliesToValue('yaml_as_string', 'string');
    $this->assertTrue($converter_result->valid());
    $converter_result = $converter->appliesToValue('string', Yaml::dump(['test']));
    $this->assertTrue($converter_result->valid());

    // Check so it converts.
    $array = $converter->convert('yaml_as_string', Yaml::dump(['test']));
    $this->assertIsArray($array);
    $this->assertEquals($array, ['test']);
    $object = $converter->convert('yaml_as_string', Yaml::dump([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]));
    $this->assertIsArray($object);
    $this->assertEquals($object['hello'], 'there');
    $this->assertEquals($object['obi-wan'], 'kenobi');
  }

}
