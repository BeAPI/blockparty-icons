<?php
/**
 * Plugin Name:       Blockparty Icons
 * Description:       Provides blocks in WordPress editor to add custom SVG icons.
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Version:           1.1.2
 * Author:            Be API Technical Team
 * Author URI:        https://beapi.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blockparty-icons
 * Domain Path:       /languages
 */

namespace Blockparty\Icons;

use Blockparty\Icons\Cli\MigrateFromIconBlockCommand;
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

define( 'BLOCKPARTY_ICONS_VERSION', '1.1.2' );
define( 'BLOCKPARTY_ICONS_URL', plugin_dir_url( __FILE__ ) );
define( 'BLOCKPARTY_ICONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOCKPARTY_ICONS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

function init() {
	global $blockparty_icon_collections;
	$blockparty_icon_collections = [];

	// Load available translations.
	load_plugin_textdomain( 'blockparty-icons', false, dirname( BLOCKPARTY_ICONS_PLUGIN_BASENAME ) . '/languages' );

	register_block_type( __DIR__ . '/build/', [ 'render_callback' => [ BlockRenderer::class, 'render' ] ] );

	// Load translations for JS
	wp_set_script_translations( 'blockparty-icon-editor-script', 'blockparty-icons', BLOCKPARTY_ICONS_DIR . '/languages' );

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
 * Preload collections and icons endpoint.
 *
 * @param array $paths
 *
 * @return array
 */
function preload_rest_endpoints( $paths ) {
	$paths[] = '/icons/v1/collections?context=edit';
	foreach ( get_icon_collections() as $collection ) {
		$paths[] = sprintf( '/icons/v1/%s?context=edit', $collection->name() );
	}

	return $paths;
}

add_filter( 'block_editor_rest_api_preload_paths', __NAMESPACE__ . '\\preload_rest_endpoints' );

/**
 * Register new icon collection.
 *
 * @param string|Collection $name
 * @param array $args {
 *   Optional. An array of additional arguments. Default empty array.
 *
 *   @type string      $label    Optional. A human friendly name for the collection.
 *   @type string      $type     Optional. The type of icons. Supported values are 'folder', 'sprite' or 'raw'.
 *   @type string      $source   Optional. The path to load the collection's icons. Depending on the 'type' can be a path to a folder or a SVG file.
 *   @type array       $icon_map Optional. Use an array to override default labels for the icons.
 *   @type string|null $version  Optional. The collection version, use for cache busting in the sprite URL.
 * }
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
			'version'  => null,
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
 * @param string $collection_name The collection to add the icons to.
 * @param string $type            The type of icons. Supported values are 'folder', 'sprite' or 'raw'.
 * @param string $source          The path to load the collection's icons. Depending on the 'type' can be a path to a folder or a SVG file.
 * @param array  $args {
 *   Optional. An array of additional arguments. Default empty array.
 *
 *   @type string      $label    Optional. A human friendly name for the icon(s).
 *   @type array       $icon_map Optional. Use an array to override default labels for the icons.
 *   @type string|null $version  Optional. The collection version, use for cache busting in the sprite URL.
 * }
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
			'version'  => null,
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
				$items = CollectionItemsFactory::from_sprite( $source, $args['icon_map'], $args['version'] );
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

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'blockparty-icons migrate-from-icon-block', MigrateFromIconBlockCommand::class );
}
