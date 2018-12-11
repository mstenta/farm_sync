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
