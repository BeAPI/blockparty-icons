<?php
/**
 * The object-cache wrapper every icon source goes through.
 *
 * Not covered: the development-mode bypass in Cache::is_cache_disabled(). WordPress
 * resolves it from the WP_DEVELOPMENT_MODE constant behind a static cache, and only
 * lets its own core suite vary it (via WP_RUN_CORE_TESTS plus a global). Defining
 * that constant in a plugin suite would change how the WordPress test bootstrap
 * installs, which is a poor trade for one assertion. These tests therefore run with
 * caching active, which is the production path anyway.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Helpers;

use Blockparty\Icons\Helpers\Cache;
use Blockparty\Icons\Tests\TestCase;

class CacheTest extends TestCase {

	private const GROUP = 'blockparty-icons-test';

	public function test_a_value_survives_a_round_trip(): void {
		Cache::set_cache( 'key', [ 'a', 'b' ], self::GROUP, 'salt' );

		$this->assertSame( [ 'a', 'b' ], Cache::get_cache( 'key', self::GROUP, 'salt' ) );
	}

	public function test_a_missing_key_returns_false(): void {
		$this->assertFalse( Cache::get_cache( 'never-written', self::GROUP, 'salt' ) );
	}

	public function test_a_different_salt_misses(): void {
		Cache::set_cache( 'key', 'value', self::GROUP, 'salt-one' );

		$this->assertFalse(
			Cache::get_cache( 'key', self::GROUP, 'salt-two' ),
			'A changed salt must behave as a miss, never serve the stale value.'
		);
	}

	public function test_a_different_group_misses(): void {
		Cache::set_cache( 'key', 'value', self::GROUP, 'salt' );

		$this->assertFalse( Cache::get_cache( 'key', 'another-group', 'salt' ) );
	}

	public function test_salts_can_be_given_as_an_array(): void {
		Cache::set_cache( 'key', 'value', self::GROUP, [ 'one', 'two' ] );

		$this->assertSame( 'value', Cache::get_cache( 'key', self::GROUP, [ 'one', 'two' ] ) );
	}

	public function test_an_array_salt_is_order_sensitive(): void {
		Cache::set_cache( 'key', 'value', self::GROUP, [ 'one', 'two' ] );

		$this->assertFalse( Cache::get_cache( 'key', self::GROUP, [ 'two', 'one' ] ) );
	}

	public function test_an_empty_array_round_trips_as_a_hit(): void {
		Cache::set_cache( 'key', [], self::GROUP, 'salt' );

		$this->assertSame(
			[],
			Cache::get_cache( 'key', self::GROUP, 'salt' ),
			'An empty result is a real answer and must not be mistaken for a miss.'
		);
	}

	public function test_a_later_write_replaces_the_earlier_one(): void {
		Cache::set_cache( 'key', 'first', self::GROUP, 'salt' );
		Cache::set_cache( 'key', 'second', self::GROUP, 'salt' );

		$this->assertSame( 'second', Cache::get_cache( 'key', self::GROUP, 'salt' ) );
	}
}
