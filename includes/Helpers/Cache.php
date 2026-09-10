<?php

namespace Blockparty\Icons\Helpers;

class Cache {

	/**
	 * Largest payload worth handing to the object cache, in bytes.
	 *
	 * Memcached — what WordPress VIP and most managed hosts run — refuses any item
	 * over 1 MB and reports it only through a `false` return that nothing checks.
	 * The effect is a cache that never warms: the entry is rebuilt, refused, and
	 * rebuilt again on the next request, forever.
	 *
	 * We stay under the limit so the refusal never happens, leaving headroom for
	 * the key and the backend's own framing. Redis and APCu tolerate far more;
	 * raise it with the `blockparty_icons_cache_max_item_bytes` filter, or set 0
	 * to disable the guard entirely.
	 */
	private const DEFAULT_MAX_ITEM_BYTES = 900000;

	/**
	 * Retrieves cached data if valid and unchanged.
	 *
	 * Polyfill for WordPress function `wp_cache_get_salted`.
	 *
	 * @param string $cache_key The cache key used for storage and retrieval.
	 * @param string $group The cache group used for organizing data.
	 * @param string|string[] $salt The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 *
	 * @return mixed|false The cached data if valid, or false if the cache does not exist or is outdated.
	 */
	public static function get_cache( $cache_key, $group, $salt ) {
		if ( self::is_cache_disabled() ) {
			return false;
		}

		if ( function_exists( 'wp_cache_get_salted' ) ) {
			return wp_cache_get_salted( $cache_key, $group, $salt );
		}

		$salt  = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		$cache = wp_cache_get( $cache_key, $group );

		if ( ! is_array( $cache ) ) {
			return false;
		}

		if ( ! isset( $cache['salt'] ) || ! isset( $cache['data'] ) || $salt !== $cache['salt'] ) {
			return false;
		}

		return $cache['data'];
	}

	/**
	 * Stores salted data in the cache.
	 *
	 * Polyfill for WordPress function `wp_cache_set_salted`.
	 *
	 * @param string $cache_key The cache key under which to store the data.
	 * @param mixed $data The data to be cached.
	 * @param string $group The cache group to which the data belongs.
	 * @param string|string[] $salt The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 * @param int $expire Optional. When to expire the cache contents, in seconds.
	 *                                    Default 0 (no expiration).
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set_cache( $cache_key, $data, $group, $salt, $expire = 0 ) {
		if ( self::is_cache_disabled() ) {
			return true;
		}

		// Do not pay for a write the backend is going to refuse.
		if ( self::exceeds_max_item_bytes( $data ) ) {
			return false;
		}

		if ( function_exists( 'wp_cache_set_salted' ) ) {
			return wp_cache_set_salted( $cache_key, $data, $group, $salt, $expire );
		}

		$salt = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		return wp_cache_set(
			$cache_key,
			array(
				'data' => $data,
				'salt' => $salt,
			),
			$group,
			$expire
		);
	}

	/**
	 * The size ceiling in force for this site.
	 *
	 * @return int Bytes, or 0 when the guard is disabled.
	 */
	public static function max_item_bytes(): int {
		/**
		 * Filters the largest payload the plugin will hand to the object cache.
		 *
		 * Set to 0 to store items of any size — appropriate on Redis or APCu, where
		 * there is no 1 MB per-item limit.
		 *
		 * @param int $bytes Default 900000.
		 */
		return max( 0, (int) apply_filters( 'blockparty_icons_cache_max_item_bytes', self::DEFAULT_MAX_ITEM_BYTES ) );
	}

	/**
	 * Whether a payload is too large to be worth caching.
	 *
	 * @param mixed $data
	 *
	 * @return bool
	 */
	private static function exceeds_max_item_bytes( $data ): bool {
		$max = self::max_item_bytes();
		if ( 0 === $max ) {
			return false;
		}

		// Icon payloads are strings, and measuring one is free.
		if ( is_string( $data ) ) {
			return strlen( $data ) > $max;
		}

		if ( is_scalar( $data ) || null === $data ) {
			return false;
		}

		// Anything else has to be measured. The extra serialize() costs far less
		// than rebuilding an uncacheable entry on every request.
		return strlen( serialize( $data ) ) > $max; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	private static function is_cache_disabled() {
		if ( function_exists( 'wp_is_development_mode' ) ) {
			return wp_is_development_mode( 'all' );
		}

		if ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) === true ) {
			return true;
		}

		return false;
	}
}
