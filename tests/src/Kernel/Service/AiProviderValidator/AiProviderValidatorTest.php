<?php

namespace Drupal\Tests\ai\Kernel\Service\AiProviderValidator;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderValidator\AiProviderValidatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Kernel tests for the "ai_provider_validator"-service.
 *
 * @group ai
 */
class AiProviderValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'system',
    'user',
  ];

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager|null
   */
  protected ?AiProviderPluginManager $aiProvider;

  /**
   * The AI Provider validator.
   *
   * @var \Drupal\ai\Service\AiProviderValidator\AiProviderValidatorInterface|null
   */
  protected ?AiProviderValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);

    $this->aiProvider = \Drupal::service('ai.provider');
    $this->validator = \Drupal::service('ai.provider_validator');
  }

  /**
   * Test config values.
   *
   * @param string $operationType
   *   The operation type.
   * @param array $values
   *   The values to validate.
   * @param array $expectedViolations
   *   The expected violations.
   * @param array $extraConstraints
   *   Optional extra constraints to be used.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @dataProvider providerValues
   */
  public function testValues(string $operationType, array $values, array $expectedViolations, array $extraConstraints = []) {
    $provider = $this->aiProvider->createInstance('echoai');
    $violations = $this->validator
      ->addConstraints($extraConstraints)
      ->validate($provider, 'ai', $operationType, $values);

    $this->assertEquals(count($expectedViolations), $violations->count());
    foreach (array_keys($expectedViolations) as $index => $path) {
      $this->assertEquals($path, $violations->get($index)->getPropertyPath());
      $this->assertEquals($expectedViolations[$path], $violations->get($index)->getMessage());
    }
  }

  /**
   * Provide values for the validator.
   *
   * @return \Generator
   *   Returns the necessary data to run the validator.
   */
  public function providerValues(): \Generator {
    yield [
      'chat',
      [],
      [],
    ];

    yield [
      'chat',
      [
        'max_tokens' => 2034,
        'temperature' => 0.5,
        'frequency_penalty' => 1,
        'presence_penalty' => -1,
        'top_p' => 0.46996323,
      ],
      [],
    ];

    yield [
      'chat',
      [
        'max_tokens' => -1,
        'temperature' => 0,
      ],
      [
        '[max_tokens]' => 'This value should be between 0 and 4096.',
        '[temperature]' => 'This value should be of type float.',
      ],
    ];

    yield [
      'chat',
      [],
      [
        '[system_name]' => 'This field is missing.',
      ],
      [
        'system_name' => new Assert\Required([
          'constraints' => [new Assert\Type('string')],
        ]),
      ],
    ];

    yield [
      'chat',
      [
        'system_name' => 123,
      ],
      [
        '[system_name]' => 'This value should be of type string.',
      ],
      [
        'system_name' => new Assert\Required([
          'constraints' => [new Assert\Type('string')],
        ]),
      ],
    ];

    yield [
      'text_to_image',
      [
        'response_format' => $this->randomMachineName(),
      ],
      [
        '[response_format]' => 'The value you selected is not a valid choice.',
      ],
    ];

    yield [
      'text_to_speech',
      [],
      [
        '[voice]' => 'This field is missing.',
      ],
    ];

    $key = $this->randomMachineName();
    yield [
      'speech_to_text',
      [
        $key => $this->randomString(),
      ],
      [
        sprintf('[%s]', $key) => 'This field was not expected.',
      ],
    ];

  }

}
