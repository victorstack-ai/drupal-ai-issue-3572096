## You want to spoof a provider call?
One of the problems when working with testing of the AI module is that the provider calls are not always deterministic. This means that the same input can yield different results, which makes it hard to test. It would also cost a lot of money to test the AI module with real provider calls, so we need to spoof the provider calls.

We have added a provider called AI Test, that you can find under tests/modules/ai_test, that is a spoof provider. It has the functionality to look for matching requests and return a predefined response. These requests can be defined in YAML and be added to your contrib module, meaning we have a standardized way to run kernel and functional tests with the AI module.

### Where should I put the YAML files?
The YAML files should be placed under the tests/resources/ai_test/requests/{operation_type}. Currently only the operation type `chat` is supported, but we will add more in the future. You create one YAML file per request, the name doesn't matter as long as it ends with `.yaml` or `.yml`.

### What is the structure of the YAML files?
The YAML files should contain the following structure:
```yaml
request: [The request that should be matched]
response: [The response that should be returned]
wait: [Optional, time in milliseconds to wait before returning the response - can be good to test js loading/race conditions]
```

### How do I collect example requests and responses?
All normalized inputs and outputs now has an `toArray()` method that returns the data in a format that can be used in the YAML files. You can use this method to collect example requests and responses from your tests and just YAML encode them.

For example if you do something like this in your test:
```php
$input = new ChatInput([
  new ChatMessage([
    'role' => 'user',
    'content' => 'Hello, how are you?',
  ]),
]);
$provider = \Drupal::service('ai.provider_manager')->getProvider('a_real_provider');
$response = $provider->chat($input);
$request_yaml = $input->toArray();
$response_yaml = $response->toArray();
file_put_contents('request_file.yaml', Yaml::encode([
  'request' => $request_yaml,
  'response' => $response_yaml,
]));
```

### How do I use the AI Test provider in my tests?
Just load the AI Test provider, data name `echoai` and model `default` in your test and make sure that the input matches the request in the YAML file. The AI Test provider will then return the response defined in the YAML file.

### Example of a test using the AI Test provider
Check out the module ai_content_suggestions where its has tests/resources/ai_test/requests/chat/Summarize.yml for the YAML file and tests/src/Kernel/TestApiEndpointTest.php for the test that uses the AI Test provider.
