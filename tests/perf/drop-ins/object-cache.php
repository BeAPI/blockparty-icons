<?php
/**
 * Instrumented persistent object cache drop-in for the Blockparty Icons perf protocol.
 *
 * Purpose: reproduce the WordPress VIP failure mode locally.
 *
 * VIP runs memcached, which silently refuses any item larger than 1 MB. WordPress'
 * cache API gives no feedback for that: `wp_cache_set()` returns false and every
 * caller in this plugin ignores the return value. The result is a cache that never
 * warms up -- each request pays the full cost of rebuilding the collection.
 *
 * This drop-in is file-backed rather than memcached-backed. That is deliberate:
 *   - it needs no extra container or PHP extension in wp-env
 *   - it reproduces the *semantics* exactly (persistence, 1 MB cap, silent failure)
 *
 * It does NOT reproduce memcached's latency. Local file I/O is much faster than a
 * network round trip. Therefore the protocol treats **operation counts** as the
 * primary metric and wall time as indicative only. See tests/perf/README.md.
 *
 * Tunables (define in wp-config.php or via the probe):
 *   BPI_OC_MAX_ITEM_BYTES  per-item ceiling in bytes. Default 1048576 (memcached default).
 *   BPI_OC_DIR             storage directory.
 *   BPI_OC_DISABLED        set true to behave as a non-persistent cache.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BPI_OC_MAX_ITEM_BYTES' ) ) {
	define( 'BPI_OC_MAX_ITEM_BYTES', 1048576 );
}

if ( ! defined( 'BPI_OC_DIR' ) ) {
	define( 'BPI_OC_DIR', WP_CONTENT_DIR . '/bpi-object-cache' );
}

/**
 * Per-request counters. Read by the perf probe on shutdown.
 *
 * @var array
 */
$GLOBALS['bpi_oc_stats'] = [
	'get'             => 0,
	'get_hit'         => 0,
	'get_miss'        => 0,
	'get_runtime_hit' => 0,
	'set'             => 0,
	'set_ok'          => 0,
	'set_rejected'    => 0,
	'delete'          => 0,
	'bytes_read'      => 0,
	'bytes_written'   => 0,
	'bytes_rejected'  => 0,
	'largest_reject'  => 0,
	'rejects'         => [], // group:key => bytes
	'by_group'        => [], // group => [get, hit, miss, set_ok, set_rejected]
];

class BPI_Perf_Object_Cache {

	/** @var array Runtime (single request) cache. */
	private $runtime = [];

	/** @var array Groups that must never be written to disk. */
	private $non_persistent = [];

	/** @var array Groups shared across sites. */
	private $global_groups = [];

	/** @var int */
	private $blog_id = 1;

	/** @var bool */
	private $persistent = true;

	public function __construct() {
		$this->persistent = ! ( defined( 'BPI_OC_DISABLED' ) && BPI_OC_DISABLED );
		$this->blog_id    = function_exists( 'get_current_blog_id' ) ? 1 : 1;

		if ( $this->persistent && ! is_dir( BPI_OC_DIR ) ) {
			@mkdir( BPI_OC_DIR, 0777, true );
		}
	}

	/* ---------------------------------------------------------------- keys */

	private function full_key( $key, $group ) {
		$group = $group ?: 'default';
		$scope = isset( $this->global_groups[ $group ] ) ? 'global' : $this->blog_id;

		return $scope . ':' . $group . ':' . $key;
	}

	private function path( $full_key ) {
		return BPI_OC_DIR . '/' . md5( $full_key ) . '.cache';
	}

	private function &group_stats( $group ) {
		$group = $group ?: 'default';
		if ( ! isset( $GLOBALS['bpi_oc_stats']['by_group'][ $group ] ) ) {
			$GLOBALS['bpi_oc_stats']['by_group'][ $group ] = [
				'get'          => 0,
				'hit'          => 0,
				'miss'         => 0,
				'set_ok'       => 0,
				'set_rejected' => 0,
				'bytes_read'   => 0,
			];
		}

		return $GLOBALS['bpi_oc_stats']['by_group'][ $group ];
	}

	/* --------------------------------------------------------------- reads */

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$group    = $group ?: 'default';
		$full_key = $this->full_key( $key, $group );

		$GLOBALS['bpi_oc_stats']['get']++;
		$gs = &$this->group_stats( $group );
		$gs['get']++;

		if ( ! $force && array_key_exists( $full_key, $this->runtime ) ) {
			$found = true;
			$GLOBALS['bpi_oc_stats']['get_hit']++;
			$GLOBALS['bpi_oc_stats']['get_runtime_hit']++;
			$gs['hit']++;

			return $this->clone_value( $this->runtime[ $full_key ] );
		}

		if ( ! $this->persistent || isset( $this->non_persistent[ $group ] ) ) {
			$found = false;
			$GLOBALS['bpi_oc_stats']['get_miss']++;
			$gs['miss']++;

			return false;
		}

		$path = $this->path( $full_key );
		if ( ! is_readable( $path ) ) {
			$found = false;
			$GLOBALS['bpi_oc_stats']['get_miss']++;
			$gs['miss']++;

			return false;
		}

		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			$found = false;
			$GLOBALS['bpi_oc_stats']['get_miss']++;
			$gs['miss']++;

			return false;
		}

		$GLOBALS['bpi_oc_stats']['bytes_read'] += strlen( $raw );
		$gs['bytes_read']                      += strlen( $raw );

		$envelope = @unserialize( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_array( $envelope ) || ! array_key_exists( 'data', $envelope ) ) {
			$found = false;
			$GLOBALS['bpi_oc_stats']['get_miss']++;
			$gs['miss']++;

			return false;
		}

		if ( ! empty( $envelope['expire'] ) && $envelope['expire'] < time() ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$found = false;
			$GLOBALS['bpi_oc_stats']['get_miss']++;
			$gs['miss']++;

			return false;
		}

		$this->runtime[ $full_key ] = $envelope['data'];

		$found = true;
		$GLOBALS['bpi_oc_stats']['get_hit']++;
		$gs['hit']++;

		return $this->clone_value( $envelope['data'] );
	}

	public function get_multiple( $keys, $group = 'default', $force = false ) {
		$out = [];
		foreach ( (array) $keys as $key ) {
			$out[ $key ] = $this->get( $key, $group, $force );
		}

		return $out;
	}

	/* -------------------------------------------------------------- writes */

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$group    = $group ?: 'default';
		$full_key = $this->full_key( $key, $group );

		$GLOBALS['bpi_oc_stats']['set']++;
		$gs = &$this->group_stats( $group );

		// Runtime cache always accepts, exactly like memcached-backed drop-ins.
		$this->runtime[ $full_key ] = $data;

		if ( ! $this->persistent || isset( $this->non_persistent[ $group ] ) ) {
			$GLOBALS['bpi_oc_stats']['set_ok']++;
			$gs['set_ok']++;

			return true;
		}

		$payload = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			[
				'data'   => $data,
				'expire' => $expire ? time() + (int) $expire : 0,
			]
		);
		$size    = strlen( $payload );

		// This is the VIP behaviour we are reproducing: memcached refuses the item,
		// wp_cache_set() returns false, and nothing in the calling code notices.
		if ( $size > BPI_OC_MAX_ITEM_BYTES ) {
			$GLOBALS['bpi_oc_stats']['set_rejected']++;
			$GLOBALS['bpi_oc_stats']['bytes_rejected'] += $size;
			$GLOBALS['bpi_oc_stats']['largest_reject']  = max(
				$GLOBALS['bpi_oc_stats']['largest_reject'],
				$size
			);
			$GLOBALS['bpi_oc_stats']['rejects'][ $group . ':' . $key ] = $size;
			$gs['set_rejected']++;

			return false;
		}

		$ok = false !== file_put_contents( $this->path( $full_key ), $payload, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $ok ) {
			$GLOBALS['bpi_oc_stats']['set_ok']++;
			$GLOBALS['bpi_oc_stats']['bytes_written'] += $size;
			$gs['set_ok']++;
		}

		return $ok;
	}

	public function set_multiple( $data, $group = 'default', $expire = 0 ) {
		$out = [];
		foreach ( (array) $data as $key => $value ) {
			$out[ $key ] = $this->set( $key, $value, $group, $expire );
		}

		return $out;
	}

	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}

		$found = null;
		$this->get( $key, $group, false, $found );
		if ( $found ) {
			return false;
		}

		return $this->set( $key, $data, $group, $expire );
	}

	public function add_multiple( array $data, $group = 'default', $expire = 0 ) {
		$out = [];
		foreach ( $data as $key => $value ) {
			$out[ $key ] = $this->add( $key, $value, $group, $expire );
		}

		return $out;
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$found = null;
		$this->get( $key, $group, false, $found );
		if ( ! $found ) {
			return false;
		}

		return $this->set( $key, $data, $group, $expire );
	}

	public function delete( $key, $group = 'default' ) {
		$group    = $group ?: 'default';
		$full_key = $this->full_key( $key, $group );

		$GLOBALS['bpi_oc_stats']['delete']++;
		unset( $this->runtime[ $full_key ] );

		if ( ! $this->persistent ) {
			return true;
		}

		$path = $this->path( $full_key );

		return is_file( $path ) ? @unlink( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	public function delete_multiple( array $keys, $group = 'default' ) {
		$out = [];
		foreach ( $keys as $key ) {
			$out[ $key ] = $this->delete( $key, $group );
		}

		return $out;
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
		$found = null;
		$value = $this->get( $key, $group, false, $found );
		if ( ! $found || ! is_numeric( $value ) ) {
			return false;
		}

		$value = max( 0, (int) $value + (int) $offset );
		$this->set( $key, $value, $group );

		return $value;
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
		return $this->incr( $key, -abs( (int) $offset ), $group );
	}

	/* --------------------------------------------------------------- admin */

	public function flush() {
		$this->runtime = [];

		if ( ! $this->persistent || ! is_dir( BPI_OC_DIR ) ) {
			return true;
		}

		foreach ( (array) glob( BPI_OC_DIR . '/*.cache' ) as $file ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return true;
	}

	public function flush_runtime() {
		$this->runtime = [];

		return true;
	}

	public function flush_group( $group ) {
		// Coarse but sufficient for the protocol: we never need surgical group flushes.
		return $this->flush();
	}

	public function add_global_groups( $groups ) {
		foreach ( (array) $groups as $group ) {
			$this->global_groups[ $group ] = true;
		}
	}

	public function add_non_persistent_groups( $groups ) {
		foreach ( (array) $groups as $group ) {
			$this->non_persistent[ $group ] = true;
		}
	}

	public function switch_to_blog( $blog_id ) {
		$this->blog_id = (int) $blog_id;
	}

	public function close() {
		return true;
	}

	/**
	 * WordPress expects values to be returned by value, not by reference.
	 */
	private function clone_value( $value ) {
		if ( is_object( $value ) ) {
			return clone $value;
		}

		return $value;
	}
}

/* ------------------------------------------------------------- API shims */

function wp_cache_init() {
	$GLOBALS['wp_object_cache'] = new BPI_Perf_Object_Cache();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add( $key, $data, $group, (int) $expire );
}

function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add_multiple( $data, $group, (int) $expire );
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, (int) $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set( $key, $data, $group, (int) $expire );
}

function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set_multiple( $data, $group, (int) $expire );
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found );
}

function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	return $GLOBALS['wp_object_cache']->get_multiple( $keys, $group, $force );
}

function wp_cache_delete( $key, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete( $key, $group );
}

function wp_cache_delete_multiple( array $keys, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete_multiple( $keys, $group );
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group );
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->decr( $key, $offset, $group );
}

function wp_cache_flush() {
	return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_flush_runtime() {
	return $GLOBALS['wp_object_cache']->flush_runtime();
}

function wp_cache_flush_group( $group ) {
	return $GLOBALS['wp_object_cache']->flush_group( $group );
}

function wp_cache_close() {
	return $GLOBALS['wp_object_cache']->close();
}

function wp_cache_add_global_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups );
}

function wp_cache_switch_to_blog( $blog_id ) {
	$GLOBALS['wp_object_cache']->switch_to_blog( $blog_id );
}

function wp_cache_supports( $feature ) {
	return in_array(
		$feature,
		[ 'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group' ],
		true
	);
}
