<?php
/**
 * WordPress Playground bootstrap for Blockparty Icons.
 *
 * @package Blockparty\Icons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_dir = WP_PLUGIN_DIR . '/blockparty-icons';
$mu_src     = $plugin_dir . '/.wordpress-org/blueprints/mu-plugin/blockparty-icons-playground.php';
$mu_dest    = WP_CONTENT_DIR . '/mu-plugins/blockparty-icons-playground.php';

if ( ! is_dir( WP_CONTENT_DIR . '/mu-plugins' ) ) {
	wp_mkdir_p( WP_CONTENT_DIR . '/mu-plugins' );
}

if ( is_readable( $mu_src ) ) {
	copy( $mu_src, $mu_dest );
}

$content_file = $plugin_dir . '/.wordpress-org/blueprints/demo-page-content.html';
$page_content = is_readable( $content_file ) ? file_get_contents( $content_file ) : '';

if ( '' === $page_content ) {
	echo 'Demo page content file missing.';
	return;
}

$page_id = wp_insert_post(
	[
		'post_title'   => 'Blockparty Icons',
		'post_name'    => 'blockparty-icons-demo',
		'post_content' => $page_content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	]
);

echo 'Page created with ID: ' . (int) $page_id;
