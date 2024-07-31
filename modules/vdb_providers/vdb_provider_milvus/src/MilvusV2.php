<?php

namespace Drupal\vdb_provider_milvus;

use GuzzleHttp\Client;

/**
 * Extends Milvus with extra calls.
 */
class MilvusV2 {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * API Token.
   *
   * @var string
   */
  private string $apiKey;

  /**
   * The base URL.
   *
   * @var string
   */
  private string $baseUrl;

  /**
   * The port.
   *
   * @var int
   */
  private int $port = 443;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\Client $client
   *   The http client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Set the API key.
   *
   * @param string $apiKey
   *   The API key.
   */
  public function setApiKey(string $apiKey) {
    $this->apiKey = $apiKey;
  }

  /**
   * Set the base URL.
   *
   * @param string $baseUrl
   *   The base URL.
   */
  public function setBaseUrl(string $baseUrl) {
    $this->baseUrl = $baseUrl;
  }

  /**
   * Set the port.
   *
   * @param int $port
   *   The port.
   */
  public function setPort(int $port) {
    $this->port = $port;
  }

  /**
   * Create collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param string $database_name
   *   The database.
   * @param int $dimension
   *   The dimension.
   * @param string $metric_type
   *   The metric type.
   * @param array $options
   *   Extra options.
   */
  public function createCollection(string $collection_name, string $database_name, int $dimension, string $metric_type, array $options = []) {
    $options['collectionName'] = $collection_name;
    $options['dimension'] = $dimension;
    $options['metricType'] = $metric_type;
    $options['databaseName'] = $database_name;
    $options['autoID'] = $options['autoID'] ?? TRUE;
    $options['schema']['autoID'] = TRUE;
    $options['schema']['enableDynamicField'] = FALSE;

    return json_decode($this->makeRequest('vectordb/collections/create', [], 'POST', $options), TRUE);
  }

  /**
   * Make Milvus call.
   *
   * @param string $path
   *   The path.
   * @param array $query_string
   *   The query string.
   * @param string $method
   *   The method.
   * @param string $body
   *   Data to attach if POST/PUT/PATCH.
   * @param array $options
   *   Extra headers.
   *
   * @return string|object
   *   The return response.
   */
  protected function makeRequest($path, array $query_string = [], $method = 'GET', $body = '', array $options = []) {
    if (!$this->baseUrl) {
      throw new \Exception('No base url set.');
    }
    // Don't wait to long.
    $options['connect_timeout'] = 120;
    $options['read_timeout'] = 120;
    $options['timeout'] = 120;

    // JSON unless its multipart.
    if (empty($options['multipart'])) {
      $options['headers']['Content-Type'] = 'application/json';
    }

    // Credentials.
    if ($this->apiKey) {
      $options['headers']['authorization'] = 'Bearer ' . $this->apiKey;
    }

    if ($body) {
      $options['body'] = json_encode($body);
    }

    $url = $this->baseUrl . ':' . $this->port;

    $new_url = rtrim($url, '/') . '/v2/' . $path;
    $new_url .= count($query_string) ? '?' . http_build_query($query_string) : '';

    $res = $this->client->request($method, $new_url, $options);

    return $res->getBody();
  }

}
