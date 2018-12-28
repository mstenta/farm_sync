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
      /**
       * @todo
       * Remove Drupal-specific code from this class.
       * @see https://github.com/mstenta/farm_sync/issues/3
       */
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
   * taxonomy terms. So this simply wraps the getTerms() method.
   *
   * @param $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return array
   *   Returns an array of area records.
   */
  public function getAreas($filters = []) {
    return $this->getTerms('farm_areas', $filters);
  }

  /**
   * Generic method for retrieving terms from a given vocabulary.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param array $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return array
   *   Returns an array of taxonomy term records.
   */
  public function getTerms($vocabulary, $filters = []) {

    // Get a list of areas (taxonomy terms in the vocabulary).
    $filters['bundle'] = $vocabulary;
    $terms = $this->getRecords('taxonomy_term', $filters);

    // Return the terms.
    return $terms;
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

    // Get record data from the farmOS API.
    $data = $this->getRecordData($entity_type, $filters);

    // If the list of records is not empty, return it.
    if (!empty($data['list'])) {
      return $data['list'];
    }

    // Otherwise, return an empty array.
    return [];
  }

  /**
   * Determines how many pages of records are available for a given entity type
   * and filter(s).
   *
   * @param $entity_type
   *   The record entity type.
   * @param $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return int
   *   Returns an integer page count.
   */
  public function pageCount($entity_type, $filters = []) {

    // Start with an empty page count.
    $pages = 0;

    // Get record data from the farmOS API.
    $data = $this->getRecordData($entity_type, $filters);

    // If the 'last' page is not set, bail.
    if (empty($data['last'])) {
      return $pages;
    }

    // Parse the last page number.
    $last_page = 0;
    $query = [];
    $parts = parse_url($data['last']);
    parse_str($parts['query'], $query);
    if (isset($query['page'])) {
      $last_page = $query['page'];
    }

    // The number of pages is the last page number plus one.
    $pages = $last_page + 1;

    // Return the page count.
    return $pages;
  }

  /**
   * Retrieve raw record data from the farmOS API.
   *
   * Note that this will only perform a single request. The farmOS API will
   * only provide a limited set of results per request, along with information
   * about how many pages are available. It is the responsibility of the code
   * calling this function to add a 'page' filter and make multiple requests to
   * get all pages of records.
   *
   * @param $entity_type
   *   The record entity type.
   * @param $filters
   *   Additional filters to apply to the request. These will be added as
   *   query parameters to the URL.
   *
   * @return array
   *   Returns raw data from the farmOS API, decoded from JSON.
   */
  public function getRecordData($entity_type, $filters = []) {

    // Start with an empty set of data.
    $data = [];

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
    }

    // Return the data.
    return $data;
  }

  /**
   * Raw HTTP request helper function.
   *
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
    /**
     * @todo
     * Remove Drupal-specific code from this class.
     * @see https://github.com/mstenta/farm_sync/issues/3
     */
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
      /**
       * @todo
       * Remove Drupal-specific code from this class.
       * @see https://github.com/mstenta/farm_sync/issues/3
       */
      watchdog_exception('farm_sync', $e);
      if ($e->hasResponse()) {
        $response = $e->getResponse();
        return $response;
      }
    }
    return FALSE;
  }
}
