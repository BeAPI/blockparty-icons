<?php
/**
 * Sample configuration for running the test suite outside wp-env.
 *
 * Copy this to the project root as wp-tests-config.php and fill in your database
 * credentials. Running the suite through wp-env (`npm run test:php`) needs none of
 * this: wp-env provides its own configuration.
 *
 * WARNING: the suite DROPS AND RECREATES the tables in this database. Point it at a
 * throwaway database, never at one holding anything you want to keep.
 *
 * @package Blockparty\Icons
 */

// WordPress core, as installed by Composer (roots/wordpress-no-content).
define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );

define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- required by the test suite.
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_PHP_BINARY', 'php' );
