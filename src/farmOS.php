<?php

namespace Drupal\farm_sync;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;

class farmOS {

  /**
   * Store authentication credentials.
   */
  private $hostname = '';
  private $username = '';
  private $password = '';

  /**
   * Store cookie jar and authentication token internally.
   */
  private $jar;
  private $token = '';

  /**
   * Create a new farmOS instance.
   *
   * @param string $hostname
   *   The farmOS hostname (without protocol).
   * @param string $username
   *   The farmOS user name.
   * @param string $password
   *   The farmOS user's password.
   */
  public function __construct($hostname, $username, $password) {
    $this->hostname = $hostname;
    $this->username = $username;
    $this->password = $password;
  }

  /**
   * Authenticates with the farmOS site.
   *
   * @return bool
   *   Returns TRUE or FALSE indicating whether or not the authentication was
   *   successful.
   */
  public function authenticate() {

    // If any of the authentication credentials are empty, bail.
    if (empty($this->hostname) || empty($this->username) || empty($this->password)) {
      $message = 'farmOS authentication failed: missing hostname, username, or password.';
      \Drupal::logger('farm_sync')->error($message);
      return FALSE;
    }

    // Create a cookie jar to store the session cookie.
    $this->jar = new CookieJar;

    // Clear any previously populated token.
    $this->token = '';

    // Login with the username and password to get a cookie.
    $options = [
      'form_params' => [
        'name' => $this->username,
        'pass' => $this->password,
        'form_id' => 'user_login',
      ],
    ];
    $response = $this->httpRequest('user/login', 'POST', $options);
    if (!empty($response)) {
      $code = $response->getStatusCode();
      if ($code != 200) {
        return FALSE;
      }
    }

    // Request a session token from the RESTful Web Services module.
    $response = $this->httpRequest('restws/session/token');
    if (!empty($response)) {
      $code = $response->getStatusCode();
      if ($code == 200) {
        $this->token = $response->getBody()->getContents();
      }
    }

    // Return TRUE if the token was populated.
    if (!empty($this->token)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieve all farm area records.
   *
   * Areas are a unique case in the farmOS API, because they are represented as
   * taxonomy terms. There isn't a built-in way of filtering taxonomy terms by
   * their vocabulary machine name (eg: 'farm_areas'). Instead you must filter
   * by the vocabulary ID, which may be different on each farmOS instance
   * (based on the order in which the vocabularies are created). So, we need to
   * make two requests: one to get a list of all vocabularies, so that we can
   * find the ID of the farm_areas vocabulary, and a second request to get all
   * terms in that vocabulary (all areas). This method abstracts those two
   * requests into a single method, using the normal getRecords()  method
   * internally.
   *
   * @param $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return array
   *   Returns an array of area records.
   */
  public function getAreas($filters = []) {

    // Start with an empty set of areas.
    $areas = [];

    // Get a list of all vocabularies.
    $vocabs = $this->getRecords('taxonomy_vocabulary');

    // If no vocabularies were found, bail.
    if (empty($vocabs)) {
      return $areas;
    }

    // Find the 'farm_areas' vocabulary ID.
    $vid = 0;
    foreach ($vocabs as $vocab) {
      if (!empty($vocab['machine_name']) && $vocab['machine_name'] == 'farm_areas') {
        $vid = $vocab['vid'];
        break;
      }
    }

    // If the vocabulary ID was not found, bail.
    if (empty($vid)) {
      return $areas;
    }

    // Get a list of areas (taxonomy terms in the 'farm_areas' vocabulary).
    $filters['vocabulary'] = $vid;
    $areas = $this->getRecords('taxonomy_term', $filters);

    // Return the areas.
    return $areas;
  }

  /**
   * Generic method for retrieving a list of records from farmOS.
   *
   * @param $entity_type
   *   The record entity type.
   * @param $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return array
   *   Returns an array of records, decoded from JSON.
   */
  public function getRecords($entity_type, $filters = []) {

    // Start with an empty set of records.
    $records = [];

    // The path is the entity type with '.json' on the end.
    $path = $entity_type . '.json';

    // Convert the list of filters into query string parameters.
    if (!empty($filters)) {
      $path .= '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    }

    // Request the records from farmOS.
    $response = $this->httpRequest($path);

    // If a response was received, and it has a status code of 200, parse it
    // as JSON into the records array.
    if (!empty($response)) {
      $code = $response->getStatusCode();
      if ($code == 200) {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, TRUE);
      }
      if (!empty($data['list'])) {
        $records = $data['list'];
      }
    }

    // Return the records.
    return $records;
  }

  /**
   * @param $path
   *   The API endpoint path (without hostname or leading/trailing slashes).
   * @param $method
   *   The HTTP method ('GET', 'POST', etc). Defaults to 'GET'.
   * @param $options
   *   Optional HTTP request options (via Guzzle). Defaults to empty array.
   *
   * @return \GuzzleHttp\Psr7\Response|bool
   *   Returns the response object, if available, FALSE otherwise.
   */
  protected function httpRequest($path, $method = 'GET', $options = []) {

    // Strip protocol, hostname, leading/trailing slashes, and whitespace from
    // the path.
    $remove = [
      'http://',
      'https://',
      $this->hostname,
    ];
    foreach ($remove as $search) {
      $path = str_ireplace($search, '', $path);
    }
    $path = trim($path, '/');
    $path = trim($path);

    // Assemble the URL.
    $url = 'http://' . $this->hostname . '/' . $path;

    // Create an HTTP client.
    $client = \Drupal::httpClient();

    // Automatically add the cookie jar to the request, if it exists.
    if (empty($options['cookies']) && !empty($this->jar)) {
      $options['cookies'] = $this->jar;
    }

    // Automatically add the token to the request, if it exists.
    if (empty($options['headers']['X-CSRF-Token']) && !empty($this->token)) {
      $options['headers']['X-CSRF-Token'] = $this->token;
    }

    // If allow_redirects is not configured in the options, add configuration
    // to use strict RFC compliant redirects (so that POST data is forwarded to
    // the new destination). This allows for HTTP to be redirected to HTTPS
    // automatically.
    if (empty($options['allow_redirects'])) {
      $options['allow_redirects'] = [
        'strict' => TRUE,
      ];
    }

    // Perform the request.
    try {
      $response = $client->request($method, $url, $options);
      return $response;
    }
    catch (RequestException $e) {
      watchdog_exception('farm_sync', $e);
      if ($e->hasResponse()) {
        $response = $e->getResponse();
        return $response;
      }
    }
    return FALSE;
  }
}
