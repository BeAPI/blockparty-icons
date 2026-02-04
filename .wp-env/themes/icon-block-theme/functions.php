<?php

use function Blockparty\Icons\register_icon_collection;
use Blockparty\Icons\Icon\Collection;
use Blockparty\Icons\Icon\CollectionItemsFactory;

add_filter( 'upload_mimes', 'wpc_mime_types' );

function wpc_mime_types( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}

add_action( 'blockparty_icons_init', function () {
	register_icon_collection(
		'Bootstrap',
		[
			'type' => 'sprite',
			'source' => get_theme_file_path( '/icons/bootstrap-icons.svg' ),
		]
	);

	register_icon_collection(
		'Social',
		[
			'type' => 'sprite',
			'source' => get_theme_file_path( '/icons/social.svg' ),
		]
	);

	register_icon_collection(
		'Folder',
		[
			'type' => 'folder',
			'source' => get_theme_file_path( '/icons/user' ),
		]
	);

	// Register collections with icons from media library.
	$query = new \WP_Query(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/svg+xml',
			'posts_per_page' => 500, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'no_found_rows'  => true,
		]
	);

	if ( $query->have_posts() ) {
		$media_collection = new Collection( 'mediatheque', __( 'Media library', 'beapi-frontend-framework' ) );
		foreach ( $query->posts as $svg ) {
			$path = get_attached_file( $svg->ID );

			if ( empty( $path ) ) {
				continue;
			}

			try {
				$items = CollectionItemsFactory::from_file(
					$path,
					[
						'name'  => $svg->post_name,
						'label' => get_the_title( $svg ),
					]
				);
				array_map( [ $media_collection, 'add' ], $items );
			} catch ( \Exception $e ) { // phpcs:ignore
			}
		}
		register_icon_collection( $media_collection );
	}
} );
