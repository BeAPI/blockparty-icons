<?php

namespace Blockparty\Icons\Helpers;

class Cache {

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
