<?php

namespace Drupal\farm_sync;

class farmOS {

  /**
   * Store authentication credentials.
   */
  private $hostname = '';
  private $username = '';
  private $password = '';

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
}
