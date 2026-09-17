<?php
/**
 * PHPUnit bootstrap for the Blockparty Icons integration test suite.
 *
 * These are integration tests against a real WordPress install, not isolated unit
 * tests. The plugin leans on WP_Query, the object cache, WP_HTML_Tag_Processor,
 * WP_REST_Controller and a fistful of filters; mocking that surface would test the
 * mocks rather than the plugin.
 *
 * Two ways to run it:
 *
 *   npm run test:php        inside wp-env, which provides WordPress, the test
 *                           library and a database (WP_TESTS_DIR is set for us)
 *   composer test           anywhere else — uses the Composer copies of WordPress
 *                           and the test library, plus a wp-tests-config.php you
 *                           supply. This is the path CI takes.
 *
 * @package Blockparty\Icons
 */

/*
 * This file runs under the PHPUnit CLI, before WordPress exists. Diagnostics go to
 * STDERR, where WP_Filesystem has nothing to offer.
 */
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

declare( strict_types=1 );

$_bpi_root = dirname( __DIR__ );

require_once $_bpi_root . '/vendor/autoload.php';

/**
 * Locate the WordPress PHPUnit test library.
 *
 * @return array{0: string, 1: bool} The directory holding includes/functions.php,
 *                                   and whether it came from an explicit
 *                                   WP_TESTS_DIR (a managed environment such as
 *                                   wp-env, which brings its own configuration).
 */
function bpi_locate_wp_tests_dir(): array {
	/*
	 * wp-env sets WP_TESTS_DIR=/wordpress-phpunit on its containers, so this is
	 * populated whenever the suite runs inside one — including through
	 * `docker exec`, which inherits the container's environment. Finding it here is
	 * what keeps wp-env's WordPress, test library and database in play instead of
	 * the Composer fallback below.
	 */
	$from_env = getenv( 'WP_TESTS_DIR' );
	if ( ! empty( $from_env ) ) {
		$explicit = rtrim( $from_env, '/\\' );
		if ( file_exists( $explicit . '/includes/functions.php' ) ) {
			return [ $explicit, true ];
		}
	}

	$candidates = [];

	$develop = getenv( 'WP_DEVELOP_DIR' );
	if ( ! empty( $develop ) ) {
		$candidates[] = rtrim( $develop, '/\\' ) . '/tests/phpunit';
	}

	$candidates[] = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
	$candidates[] = '/tmp/wordpress-tests-lib';

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate . '/includes/functions.php' ) ) {
			return [ $candidate, false ];
		}
	}

	fwrite(
		STDERR,
		"Could not find the WordPress test library.\n\n" .
		"Looked in:\n  - " . implode( "\n  - ", $candidates ) . "\n\n" .
		"Run the suite through wp-env:\n  npm run test:php\n\n" .
		"or install dependencies with `composer install`.\n"
	);
	exit( 1 );
}

[ $_bpi_tests_dir, $_bpi_managed_env ] = bpi_locate_wp_tests_dir();

/*
 * Point the WordPress bootstrap at a wp-tests-config.php.
 *
 * It must be a constant holding a full file path: that is what
 * wp-phpunit/includes/bootstrap.php reads, and it takes precedence over the copy
 * shipped beside the test library.
 *
 * Precedence, highest first:
 *   1. WP_TESTS_CONFIG_FILE_PATH in the environment — an explicit instruction
 *   2. the configuration a managed environment (wp-env) provides for us
 *   3. wp-tests-config.php in the project root
 */
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	$_bpi_config = getenv( 'WP_TESTS_CONFIG_FILE_PATH' );

	if ( ! empty( $_bpi_config ) ) {
		define( 'WP_TESTS_CONFIG_FILE_PATH', $_bpi_config );
	} elseif ( ! $_bpi_managed_env ) {
		$_bpi_config = $_bpi_root . '/wp-tests-config.php';

		if ( ! file_exists( $_bpi_config ) ) {
			fwrite(
				STDERR,
				"No wp-tests-config.php found at {$_bpi_config}.\n\n" .
				"Either run the suite through wp-env:\n" .
				"  npm run test:php\n\n" .
				"or copy tests/wp-tests-config-sample.php to the project root as\n" .
				"wp-tests-config.php and fill in a throwaway database.\n"
			);
			exit( 1 );
		}

		define( 'WP_TESTS_CONFIG_FILE_PATH', $_bpi_config );
	}
}

/*
 * The plugin registers its block from build/block.json. Without it the block type
 * never registers, render_block() returns an empty string, and a couple of dozen
 * tests fail for a reason that has nothing to do with what they are testing.
 * build/ is git-ignored, so a fresh clone lands exactly there.
 */
if ( ! file_exists( $_bpi_root . '/build/block.json' ) ) {
	fwrite(
		STDERR,
		"build/block.json is missing, so the block cannot register and rendering\n" .
		"tests would fail for the wrong reason.\n\n" .
		"Compile the assets first:\n  npm ci && npm run build\n"
	);
	exit( 1 );
}

require_once $_bpi_tests_dir . '/includes/functions.php';

/**
 * Load the plugin into the test WordPress instance.
 *
 * muplugins_loaded fires before `init`, so the plugin registers its hooks exactly
 * as it would on a real site.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $_bpi_root ) {
		require $_bpi_root . '/blockparty-icons.php';
	}
);

require $_bpi_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/phpunit/TestCase.php';
