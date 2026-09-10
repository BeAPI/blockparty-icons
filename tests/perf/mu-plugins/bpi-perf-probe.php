<?php
/**
 * Plugin Name: Blockparty Icons — Perf Probe
 * Description: Instrumentation for the Blockparty Icons performance protocol. Not for production.
 *
 * Records, per request:
 *   - peak memory, and memory consumed by the `blockparty_icons_init` hook alone
 *   - bytes of SVG content held resident in the registered collections
 *   - object cache operation counts (gets / hits / misses / rejected sets)
 *   - media-library file reads and bytes, via the bpifs:// stream wrapper
 *
 * Metrics land as one JSON object per line in wp-content/bpi-perf.log.
 *
 * Tag a request with ?bpi_perf_label=my-scenario so the bench runner can group runs.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BPI_PERF_LOG' ) ) {
	define( 'BPI_PERF_LOG', WP_CONTENT_DIR . '/bpi-perf.log' );
}

/**
 * Microseconds of artificial latency charged on every media-library file open, to
 * emulate VIP Files (a network filesystem) rather than a local disk.
 *
 * Defaults to 0 so the baseline stays honest about what was actually measured.
 * Override per request with ?bpi_perf_latency_us=2000 — 2 ms is a conservative
 * stand-in for a VIP Files round trip.
 */
if ( ! defined( 'BPI_PERF_REMOTE_LATENCY_US' ) ) {
	define( 'BPI_PERF_REMOTE_LATENCY_US', 0 );
}

/**
 * Route media-library files through the counting stream wrapper.
 * On VIP, get_attached_file() returns a vip:// path backed by remote storage.
 */
if ( ! defined( 'BPI_PERF_REMOTE_MEDIA' ) ) {
	define( 'BPI_PERF_REMOTE_MEDIA', true );
}

/**
 * Effective latency for this request: query override, else the constant.
 */
function bpi_perf_latency_us() {
	static $us = null;

	if ( null !== $us ) {
		return $us;
	}

	$us = (int) BPI_PERF_REMOTE_LATENCY_US;

	// phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_GET['bpi_perf_latency_us'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$us = max( 0, min( 100000, (int) $_GET['bpi_perf_latency_us'] ) );
	}

	return $us;
}

/* ------------------------------------------------------------------ state */

$GLOBALS['bpi_perf'] = [
	'label'            => isset( $_GET['bpi_perf_label'] ) // phpcs:ignore WordPress.Security.NonceVerification
		? sanitize_text_field( wp_unslash( $_GET['bpi_perf_label'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
		: 'unlabelled',
	'fs_reads'         => 0,
	'fs_bytes'         => 0,
	'fs_time_us'       => 0,
	'hook_time_ms'     => null,
	'hook_mem_bytes'   => null,
	'mem_before_hook'  => null,
];

/* --------------------------------------------------- bpifs:// stream wrapper */

/**
 * Pass-through stream wrapper that counts reads and can add latency.
 *
 * Models VIP Files: the bytes live somewhere else and every open costs a round trip.
 */
class BPI_Perf_Stream_Wrapper {

	/** @var resource|null */
	public $context;

	/** @var resource|false */
	private $handle = false;

	private function real_path( $path ) {
		return substr( $path, strlen( 'bpifs://' ) );
	}

	private function charge_latency() {
		$us = bpi_perf_latency_us();

		if ( $us > 0 ) {
			usleep( $us );
			$GLOBALS['bpi_perf']['fs_time_us'] += $us;
		}
	}

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$real = $this->real_path( $path );

		$this->charge_latency();
		$GLOBALS['bpi_perf']['fs_reads']++;

		$this->handle = @fopen( $real, $mode ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors

		return false !== $this->handle;
	}

	public function stream_read( $count ) {
		$data = fread( $this->handle, $count ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( is_string( $data ) ) {
			$GLOBALS['bpi_perf']['fs_bytes'] += strlen( $data );
		}

		return $data;
	}

	public function stream_write( $data ) {
		return fwrite( $this->handle, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function stream_tell() {
		return ftell( $this->handle );
	}

	public function stream_eof() {
		return feof( $this->handle );
	}

	public function stream_seek( $offset, $whence = SEEK_SET ) {
		return 0 === fseek( $this->handle, $offset, $whence );
	}

	public function stream_stat() {
		return fstat( $this->handle );
	}

	public function stream_close() {
		if ( is_resource( $this->handle ) ) {
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return true;
	}

	public function stream_flush() {
		return is_resource( $this->handle ) ? fflush( $this->handle ) : true;
	}

	public function unlink( $path ) {
		return @unlink( $this->real_path( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	public function url_stat( $path, $flags ) {
		$real = $this->real_path( $path );

		// is_readable() lands here. On a remote FS this is a round trip too.
		$this->charge_latency();

		$stat = ( $flags & STREAM_URL_STAT_QUIET )
			? @stat( $real ) // phpcs:ignore WordPress.PHP.NoSilencedErrors
			: stat( $real );

		return false === $stat ? false : $stat;
	}

	public function dir_opendir( $path, $options ) {
		return false;
	}
}

if ( ! in_array( 'bpifs', stream_get_wrappers(), true ) ) {
	stream_wrapper_register( 'bpifs', 'BPI_Perf_Stream_Wrapper' );
}

/**
 * Make media-library SVGs behave like VIP Files: remote, counted, optionally slow.
 */
add_filter(
	'get_attached_file',
	function ( $file ) {
		if ( ! BPI_PERF_REMOTE_MEDIA || ! is_string( $file ) || '' === $file ) {
			return $file;
		}

		if ( 0 === strpos( $file, 'bpifs://' ) ) {
			return $file;
		}

		if ( '.svg' !== strtolower( substr( $file, -4 ) ) ) {
			return $file;
		}

		return 'bpifs://' . $file;
	},
	// Late, so the real path is resolved first.
	9999
);

/* ----------------------------------------------------- hook timing/memory */

add_action(
	'blockparty_icons_init',
	function () {
		$GLOBALS['bpi_perf']['_hook_start_t'] = microtime( true );
		$GLOBALS['bpi_perf']['_hook_start_m'] = memory_get_usage( true );
		$GLOBALS['bpi_perf']['mem_before_hook'] = memory_get_usage( true );
	},
	-PHP_INT_MAX
);

add_action(
	'blockparty_icons_init',
	function () {
		if ( ! isset( $GLOBALS['bpi_perf']['_hook_start_t'] ) ) {
			return;
		}

		$GLOBALS['bpi_perf']['hook_time_ms'] = round(
			( microtime( true ) - $GLOBALS['bpi_perf']['_hook_start_t'] ) * 1000,
			2
		);
		$GLOBALS['bpi_perf']['hook_mem_bytes'] = memory_get_usage( true ) - $GLOBALS['bpi_perf']['_hook_start_m'];
	},
	PHP_INT_MAX
);

/* ------------------------------------------------- resident content bytes */

/**
 * Sum the SVG payload currently held in memory by the registered collections.
 *
 * Uses reflection on CollectionItem's private properties rather than the content()
 * getter on purpose: once content loading is made lazy, calling content() would
 * force every icon to load and destroy the very thing we are measuring.
 *
 * @return array{collections:int, items:int, resident_bytes:int}
 */
function bpi_perf_collect_resident() {
	$out = [
		'collections'    => 0,
		'items'          => 0,
		'resident_bytes' => 0,
	];

	if ( ! function_exists( 'Blockparty\Icons\get_icon_collections' ) ) {
		return $out;
	}

	$collections = \Blockparty\Icons\get_icon_collections();
	$out['collections'] = count( $collections );

	foreach ( $collections as $collection ) {
		if ( ! is_object( $collection ) || ! method_exists( $collection, 'all' ) ) {
			continue;
		}

		foreach ( $collection->all() as $item ) {
			$out['items']++;

			if ( ! is_object( $item ) ) {
				continue;
			}

			try {
				$ref = new ReflectionObject( $item );
				foreach ( $ref->getProperties() as $prop ) {
					$prop->setAccessible( true );

					if ( ! $prop->isInitialized( $item ) ) {
						continue;
					}

					$value = $prop->getValue( $item );
					if ( is_string( $value ) ) {
						$out['resident_bytes'] += strlen( $value );
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// Ignore: measurement must never break the request.
			}
		}
	}

	return $out;
}

/* ------------------------------------------------------------- reporting */

add_action(
	'shutdown',
	function () {
		// WP-CLI bootstraps WordPress too (the bench flushes the cache that way).
		// Those invocations are not requests under test.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$perf     = $GLOBALS['bpi_perf'];
		$oc       = isset( $GLOBALS['bpi_oc_stats'] ) ? $GLOBALS['bpi_oc_stats'] : [];
		$resident = bpi_perf_collect_resident();

		$start = defined( 'WP_START_TIMESTAMP' )
			? WP_START_TIMESTAMP
			: ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );

		$icons_group = $oc['by_group']['blockparty-icons'] ?? [];

		$record = [
			'ts'    => gmdate( 'c' ),
			'label' => $perf['label'],
			'uri'   => $_SERVER['REQUEST_URI'] ?? '',
			'ctx'   => ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? 'rest' : ( is_admin() ? 'admin' : 'front' ),

			// Headline numbers.
			'wall_ms'        => round( ( microtime( true ) - $start ) * 1000, 2 ),
			'peak_mem_mb'    => round( memory_get_peak_usage( true ) / 1048576, 2 ),
			'hook_ms'        => $perf['hook_time_ms'],
			'hook_mem_mb'    => null === $perf['hook_mem_bytes']
				? null
				: round( $perf['hook_mem_bytes'] / 1048576, 2 ),

			// The direct measure of "how much SVG are we holding for nothing".
			'collections'    => $resident['collections'],
			'items'          => $resident['items'],
			'resident_mb'    => round( $resident['resident_bytes'] / 1048576, 3 ),

			// Remote (media library) filesystem cost.
			'fs_reads'       => $perf['fs_reads'],
			'fs_mb'          => round( $perf['fs_bytes'] / 1048576, 2 ),
			'fs_latency_ms'  => round( $perf['fs_time_us'] / 1000, 1 ),

			// Object cache behaviour.
			'oc_get'         => $oc['get'] ?? 0,
			'oc_hit'         => $oc['get_hit'] ?? 0,
			'oc_miss'        => $oc['get_miss'] ?? 0,
			'oc_set_ok'      => $oc['set_ok'] ?? 0,
			'oc_rejected'    => $oc['set_rejected'] ?? 0,
			'oc_reject_mb'   => round( ( $oc['bytes_rejected'] ?? 0 ) / 1048576, 2 ),
			'oc_biggest_mb'  => round( ( $oc['largest_reject'] ?? 0 ) / 1048576, 2 ),

			// Same, scoped to the plugin's own cache group.
			'icons_oc_get'      => $icons_group['get'] ?? 0,
			'icons_oc_hit'      => $icons_group['hit'] ?? 0,
			'icons_oc_miss'     => $icons_group['miss'] ?? 0,
			'icons_oc_set_ok'   => $icons_group['set_ok'] ?? 0,
			'icons_oc_rejected' => $icons_group['set_rejected'] ?? 0,
		];

		// Keep the rejected-key list: it names exactly which cache entries are too big.
		if ( ! empty( $oc['rejects'] ) ) {
			$record['rejected_keys'] = $oc['rejects'];
		}

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions
			BPI_PERF_LOG,
			wp_json_encode( $record ) . "\n",
			FILE_APPEND | LOCK_EX
		);
	},
	PHP_INT_MAX
);
