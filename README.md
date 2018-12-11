# FARM SYNC

This is a module for Drupal 8 that provides features for synchronizing records
from a [farmOS](https://drupal.org/project/farm) site into the local Drupal 8
database.

It comes with a PHP class that provides general methods for connecting,
authenticating, and retrieving records via the farmOS API.

Currently it provides a simple form at /farmOS/sync that can be used to pull
farm area records and merge them into a {farm_sync_areas} database table.

## REQUIREMENTS

No special requirements.

## INSTALLATION

* Install as you would normally install a contributed Drupal module. Visit:
  https://www.drupal.org/documentation/install/modules-themes/modules-7
  for further information.

* Add your farmOS hostname, username, and password to your `settings.php` file
  (or to a `settings.local.php` that is included via `settings.php`). Be sure
  to protect access to this file in the same way that you would protect your
  database credentials.

## MAINTAINERS

Current maintainers:
* Michael Stenta (m.stenta) - https://drupal.org/user/581414

This project has been sponsored by:
 * [The United States Forest Service - International Programs](https://www.fs.fed.us/about-agency/international-programs)
 * [The National Forestry Authority of Uganda](https://www.nfa.org.ug/)
 * [Farmier](https://farmier.com/)
