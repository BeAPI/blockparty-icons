<?php

use function Blockparty\Icons\register_icon_collection;

add_filter( 'upload_mimes', 'wpc_mime_types' );

function wpc_mime_types( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}

add_action( 'blockparty_icons_init', function () {
	register_icon_collection(
		'icon-bootstrap',
		[
			'label' => 'Bootstrap Icons',
			'type' => 'sprite',
			'source' => get_theme_file_path( '/icons/bootstrap-icons.svg' ),
		]
	);

	register_icon_collection(
		'icon-social',
		[
			'label' => 'Social Icons',
			'type' => 'sprite',
			'source' => get_theme_file_path( '/icons/social.svg' ),
		]
	);

	register_icon_collection(
		'icon-user',
		[
			'label' => 'User Icons',
			'type' => 'folder',
			'source' => get_theme_file_path( '/icons/user' ),
		]
	);

	// Register collections with icons from the media library.
	//
	// `attachments` runs a single query and caches one small index; the SVG payload
	// of an icon is read only when that icon is actually rendered or listed.
	register_icon_collection(
		'mediatheque',
		[
			'label' => __( 'Media library', 'beapi-frontend-framework' ),
			'type'  => 'attachments',
		]
	);
} );
