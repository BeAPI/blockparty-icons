<?php
/**
 * Fixture theme for the Blockparty Icons performance protocol.
 *
 * Reproduces the topology of the WordPress VIP site that exhibits the problem:
 *
 *   - `perf-theme`  : 200 icons in a folder shipped with the theme. On VIP theme
 *                     files are local, so this collection reads the local disk.
 *   - `perf-media`  : 300 icons contributed through the back office (media library).
 *                     On VIP these live on VIP Files, a remote store. The perf probe
 *                     routes get_attached_file() through bpifs:// so every read is
 *                     counted and can be given realistic latency.
 *
 * The media-library registration below is copied from the pattern the plugin's own
 * README and demo theme encourage. It is the code path point 5 of the audit targets.
 */

defined( 'ABSPATH' ) || exit;

const BPI_PERF_FIXTURES = WP_CONTENT_DIR . '/bpi-fixtures';

/**
 * Allow SVG uploads so the 300 media-library icons can be contributed through the BO.
 */
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';

		return $mimes;
	}
);

/**
 * WordPress refuses SVG uploads on a MIME/extension mismatch. Trust our own fixtures.
 */
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( '.svg' === strtolower( substr( $filename, -4 ) ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	},
	10,
	3
);

add_action(
	'blockparty_icons_init',
	function () {
		/*
		 * Collection 1 — 200 icons from a theme folder (local filesystem).
		 */
		\Blockparty\Icons\register_icon_collection(
			'perf-theme',
			[
				'label'  => 'Perf — theme folder',
				'type'   => 'folder',
				'source' => BPI_PERF_FIXTURES . '/theme-icons',
			]
		);

		/*
		 * Collection 2 — 300 icons contributed in the back office (media library).
		 *
		 * Two implementations, so the protocol can measure both:
		 *
		 *   attachments : the supported `type => attachments` collection. One query,
		 *                 one cached index, content resolved on demand.
		 *   legacy      : the pattern the plugin's README and demo theme used to
		 *                 recommend — a WP_Query on every request, then one
		 *                 from_file() call per attachment, each with its own cache key.
		 *
		 * Switch with:  wp option update bpi_perf_media_mode legacy
		 */
		if ( 'legacy' !== get_option( 'bpi_perf_media_mode', 'attachments' ) ) {
			\Blockparty\Icons\register_icon_collection(
				'perf-media',
				[
					'label' => 'Perf - media library',
					'type'  => 'attachments',
				]
			);

			return;
		}

		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/svg+xml',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'no_found_rows'  => true,
			]
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		$media_collection = new \Blockparty\Icons\Icon\Collection(
			'perf-media',
			'Perf — media library'
		);

		foreach ( $query->posts as $svg ) {
			$path = get_attached_file( $svg->ID );

			if ( empty( $path ) ) {
				continue;
			}

			try {
				$items = \Blockparty\Icons\Icon\CollectionItemsFactory::from_file(
					$path,
					[
						'name'  => $svg->post_name,
						'label' => get_the_title( $svg ),
					]
				);
				array_map( [ $media_collection, 'add' ], $items );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// Skip unreadable fixtures.
			}
		}

		\Blockparty\Icons\register_icon_collection( $media_collection );
	}
);
