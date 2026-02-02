<?php
/**
 * Plugin Name:       Blockparty Icons
 * Description:       Provides blocks in WordPress editor to add custom SVG icons.
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Version:           1.0.0
 * Author:            Blockparty
 * Author URI:        https://blockparty.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blockparty-icons
 * Domain Path:       /languages
 */

namespace Blockparty\Icons;

use Blockparty\Icons\Icon\Collection;
use Blockparty\Icons\Icon\CollectionCreationException;
use Blockparty\Icons\Icon\CollectionItemsFactory;
use Blockparty\Icons\Rest\CollectionsController;
use Blockparty\Icons\Rest\IconsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include_once __DIR__ . '/vendor/autoload.php';
}

define( 'BLOCKPARTY_ICONS_VERSION', '1.0.0' );
define( 'BLOCKPARTY_ICONS_URL', plugin_dir_url( __FILE__ ) );
define( 'BLOCKPARTY_ICONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOCKPARTY_ICONS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

function init() {
	global $blockparty_icon_collections;
	$blockparty_icon_collections = [];

	// Load available translations.
	load_plugin_textdomain( 'blockparty-icons', false, dirname( BLOCKPARTY_ICONS_PLUGIN_BASENAME ) . '/languages' );

	register_block_type( __DIR__ . '/build/icon', [ 'render_callback' => __NAMESPACE__ . '\\render_callback' ] );

	// Load translations for JS
	wp_set_script_translations( 'blockparty-icon-editor-script', 'blockparty-icons', BLOCKPARTY_ICONS_DIR . '/languages' );

	// Expose sprite hashes to editor for cache busting in icon selector/modal.
	$sprite_hashes = get_sprite_hashes_for_script();
	if ( ! empty( $sprite_hashes ) ) {
		$config = [ 'spriteHashes' => $sprite_hashes ];
		wp_localize_script( 'blockparty-icon-editor-script', 'blockpartyIconsConfig', $config );
	}

	do_action( 'blockparty_icons_init' );
}

add_action( 'init', __NAMESPACE__ . '\\init' );

function rest() {
	$endpoint = new CollectionsController();
	$endpoint->register_routes();

	$collections = get_icon_collections();
	foreach ( $collections as $collection ) {
		$endpoint = new IconsController( $collection );
		$endpoint->register_routes();
	}
	unset( $endpoint );
}

add_action( 'rest_api_init', __NAMESPACE__ . '\\rest' );

/**
 * Register new icon collection.
 *
 * @param string|Collection $name
 * @param array             $args
 *
 * @return Collection|bool
 */
function register_icon_collection( $name, array $args = [] ) {
	/* @var Collection[] */
	global $blockparty_icon_collections;

	if ( $name instanceof Collection ) {
		if ( icon_collection_exists( $name->name() ) ) {
			return false;
		}

		$blockparty_icon_collections[ $name->name() ] = $name;

		return $name;
	}

	if ( icon_collection_exists( $name ) ) {
		return false;
	}

	$args = (array) wp_parse_args(
		$args,
		[
			'label'    => $name,
			'type'     => '',
			'source'   => '',
			'icon_map' => [],
		]
	);

	switch ( $args['type'] ) {
		case 'folder':
			try {
				$collection = Collection::from_folder( $name, $args['source'], $args );
			} catch ( CollectionCreationException $e ) {
				$collection = false;
			}
			break;
		case 'sprite':
			try {
				$collection = Collection::from_sprite( $name, $args['source'], $args );
			} catch ( CollectionCreationException $e ) {
				$collection = false;
			}
			break;
		/*case 'file':
			try {
				$collection = new Collection( $name, $args['label'] ?? null );
				$collection->add( CollectionItem::from_file( $args['source'], [] ) );
			} catch ( CollectionCreationException $e ) {
				$collection = false;
			}
			break;*/
		default:
			$collection = new Collection( $name, $args['label'] ?? null );
	}

	$blockparty_icon_collections[ $name ] = $collection;

	return $blockparty_icon_collections[ $name ];
}

/**
 * Add icons to an existing collection.
 *
 * @param string $collection_name
 * @param string $type
 * @param string $source
 * @param array  $args
 *
 * @return bool
 */
function add_icons( string $collection_name, string $type, string $source, array $args = [] ): bool {
	if ( ! icon_collection_exists( $collection_name ) ) {
		return false;
	}

	$args = (array) wp_parse_args(
		$args,
		[
			'label'    => '',
			'icon_map' => [],
		]
	);

	$items = [];
	switch ( $type ) {
		case 'folder':
			try {
				$items = CollectionItemsFactory::from_folder( $source, $args['icon_map'] );
			} catch ( CollectionCreationException $e ) {
				return false;
			}
			break;
		case 'sprite':
			try {
				$items = CollectionItemsFactory::from_sprite( $source, $args['icon_map'] );
			} catch ( CollectionCreationException $e ) {
				return false;
			}
			break;
		case 'file':
			try {
				$items = CollectionItemsFactory::from_file( $source, $args );
			} catch ( CollectionCreationException $e ) {
				return false;
			}
			break;
		default:
			return false;
	}

	$collection = get_icon_collection( $collection_name );
	array_map( [ $collection, 'add' ], $items );

	return true;
}

/**
 * Check if a collection exist with the provided name.
 *
 * @param string $name
 *
 * @return bool
 */
function icon_collection_exists( string $name ): bool {
	global $blockparty_icon_collections;

	return isset( $blockparty_icon_collections[ $name ] );
}

/**
 * Get a collection by name.
 *
 * @param string $name
 *
 * @return Collection|null
 */
function get_icon_collection( string $name ): ?Collection {
	global $blockparty_icon_collections;

	return $blockparty_icon_collections[ $name ] ?? null;
}

/**
 * Get all collections.
 *
 * @return Collection[]
 */
function get_icon_collections(): array {
	global $blockparty_icon_collections;

	return $blockparty_icon_collections ?? [];
}

/**
 * Allow additional HTML attributes in KSES.
 *
 * Without this, the attributes are stripped from the post content for users without the `unfiltered_html` capability.
 * This break the block in the editor since the saved content doesn't match the output of the block's `save` function.
 *
 * @param array  $tags
 * @param string $context
 *
 * @return array
 */
function allow_aria_attributes( $tags, $context ) {
	if ( 'post' !== $context ) {
		return $tags;
	}

	$tags['svg'] = [
		'aria-hidden' => true,
		'class'       => true,
		'fill'        => true,
		'focusable'   => true,
		'height'      => true,
		'style'       => true,
		'viewbox'     => true,
		'width'       => true,
		'version'     => true,
		'xmlns'       => true,
	];

	$tags['path'] = [
		'd'            => true,
		'clip-path'    => true,
		'clip-rule'    => true,
		'color'        => true,
		'cursor'       => true,
		'fill'         => true,
		'fill-opacity' => true,
		'fill-rule'    => true,
		'filter'       => true,
		'mask'         => true,
		'opacity'      => true,
		'stroke'       => true,
		'transform'    => true,
	];

	$tags['use'] = [
		'href'        => true,
		'xmlns:xlink' => true,
		'xlink:href'  => true,
	];

	return $tags;
}

add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\\allow_aria_attributes', 10, 2 );

/**
 * Allow additional CSS attributes in the `style` attribute.
 *
 * Without this, the CSS attributes are stripped from the `style` attribute for users without the `unfiltered_html` capability.
 * This break the block in the editor since the saved content doesn't match the output of the block's `save` function.
 *
 * @param array $attr
 *
 * @return array
 */
function allow_css_attributes( $attr ) {
	$attr[] = 'display';

	return $attr;
}

add_filter( 'safe_style_css', __NAMESPACE__ . '\\allow_css_attributes' );

/**
 * Get sprite hashes array for use in wp_localize_script (editor icon selector).
 *
 * @return array<string, string> Map of sprite path keys (e.g. "icons/social.svg") to hash values.
 */
function get_sprite_hashes_for_script(): array {
	$hashes_file = get_sprite_hashes_file_path();
	if ( '' === $hashes_file || ! is_readable( $hashes_file ) ) {
		return [];
	}

	$json = @file_get_contents( $hashes_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $json ) {
		return [];
	}

	$hashes = json_decode( $json, true );
	return is_array( $hashes ) ? $hashes : [];
}

/**
 * Get the path to the sprite hashes JSON file.
 *
 * @return string Path to sprite-hashes.json (empty if disabled or not set).
 */
function get_sprite_hashes_file_path(): string {
	$default = '';
	if ( function_exists( 'get_theme_file_path' ) ) {
		$default = get_theme_file_path( 'dist/sprite-hashes.json' );
	}

	/**
	 * Filter the path to the sprite hashes JSON file.
	 *
	 * @param string|false $path Absolute path to sprite-hashes.json. Return false or empty string to disable cache busting.
	 */
	$path = apply_filters( 'blockparty_icons_sprite_hashes_file', $default );
	if ( false === $path || '' === $path ) {
		return '';
	}
	return (string) $path;
}

/**
 * Append cache-busting hash to sprite URL when sprite-hashes.json exists and icon type is sprite.
 *
 * @param  string $sprite_url Full sprite URL (e.g. https://example.com/.../dist/icons/social.svg#icon-id).
 * @return string URL with ?v=hash query if hash found, unchanged otherwise.
 */
function get_sprite_url_with_hash( string $sprite_url ): string {
	$hashes_file = get_sprite_hashes_file_path();
	if ( '' === $hashes_file || ! is_readable( $hashes_file ) ) {
		return $sprite_url;
	}

	// Local file path from filter; file_get_contents is appropriate here.
	$json = @file_get_contents( $hashes_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $json ) {
		return $sprite_url;
	}

	$hashes = json_decode( $json, true );
	if ( ! is_array( $hashes ) ) {
		return $sprite_url;
	}

	$parsed   = wp_parse_url( $sprite_url );
	$path     = isset( $parsed['path'] ) ? $parsed['path'] : '';
	$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

	// Match JSON keys like "icons/social.svg" - path segment after "dist/".
	if ( preg_match( '#/dist/(.+)$#', $path, $m ) ) {
		$key = $m[1];
	} else {
		// Fallback: last two path segments (e.g. icons/social.svg).
		$parts = array_filter( explode( '/', trim( $path, '/' ) ) );
		$key   = implode( '/', array_slice( $parts, -2, 2 ) );
	}

	if ( empty( $hashes[ $key ] ) ) {
		return $sprite_url;
	}

	$hash   = $hashes[ $key ];
	$base   = $fragment ? strstr( $sprite_url, '#', true ) : $sprite_url;
	$with_v = add_query_arg( 'v', $hash, $base );

	return $with_v . $fragment;
}

/**
 * Render the icon item block.
 *
 * @param array    $attributes
 * @param string   $content
 * @param WP_Block $block
 *
 * @return string
 */
function render_callback( $attributes, $content, $block ) {
	/**
	 * Filter block's template slug.
	 *
	 * Template must be located in the theme as it will be loaded via `get_template_part`.
	 *
	 * @param string $template_slug block's template slug
	 * @param array $attributes block's attributes
	 * @param \WP_Block $block block's \WP_Block instance
	 */
	$template_slug = apply_filters( 'blockparty_icons_template_slug', 'components/gutenberg/dynamic-block', $attributes, $block );

	/**
	 * Filter block's template name.
	 *
	 * @param string $template_name block's template name
	 * @param string $template_slug block's template slug
	 * @param array $attributes block's attributes
	 * @param \WP_Block $block block's \WP_Block instance
	 */
	$template_name = apply_filters( 'blockparty_icons_template_name', '', $template_slug, $attributes, $block );

	/**
	 * Filter block's template args.
	 *
	 * @param string $template_name block's template name
	 * @param string $template_slug block's template slug
	 * @param array $attributes block's attributes
	 * @param \WP_Block $block block's \WP_Block instance
	 */
	$template_args = apply_filters(
		'blockparty_icons_template_args',
		[
			'block_attributes'         => $attributes,
			'block_wrapper_attributes' => get_block_wrapper_attributes(),
			'is_preview'               => isset( $_GET['is_block_editor'] ), //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		],
		$template_slug,
		$template_name,
		$attributes,
		$block
	);

	// Otherwise use default template.
	ob_start();
	load_template( plugin_dir_path( __FILE__ ) . 'views/icon-item.php', false, $template_args );

	return ob_get_clean();
}
