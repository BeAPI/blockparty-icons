<?php

namespace Blockparty\Icons\Icon;

use Blockparty\Icons\Helpers\Cache;

class CollectionItemsFactory {

	private const CACHE_GROUP = 'blockparty-icons';

	/**
	 * Instantiate array of CollectionItem from a folder.
	 *
	 * @param string $folder
	 * @param array $icon_map
	 *
	 * @return CollectionItem[]
	 * @throws CollectionCreationException
	 */
	public static function from_folder( string $folder, array $icon_map = [] ): array {
		$cache_key = 'from_folder:' . $folder;
		$salt      = md5( wp_json_encode( $icon_map ) );

		$items = Cache::get_cache( $cache_key, self::CACHE_GROUP, $salt );
		if ( is_array( $items ) ) {
			return $items;
		}

		if ( ! is_readable( $folder ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( esc_html( $folder ) );
		}

		$items  = [];
		$folder = trailingslashit( $folder );
		foreach ( glob( $folder . '*.svg' ) as $svg ) {
			$item_content = file_get_contents( $svg ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read local file
			if ( ! $item_content ) {
				continue;
			}

			$name    = pathinfo( $svg, PATHINFO_FILENAME );
			$label   = $icon_map[ $name ] ?? $name;
			$items[] = new CollectionItem( $name, 'raw', $item_content, $label );
		}

		Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Instantiate array of CollectionItem from a sprite.
	 *
	 * @param string $path
	 * @param array $icon_map
	 * @param string|null $version
	 *
	 * @return CollectionItem[]
	 * @throws CollectionCreationException
	 */
	public static function from_sprite( string $path, array $icon_map = [], ?string $version = null ): array {
		$cache_key = 'from_sprite:' . $path;
		$salts     = [ md5( wp_json_encode( $icon_map ) ) ];
		if ( $version ) {
			$salts[] = $version;
		}

		$items = Cache::get_cache( $cache_key, self::CACHE_GROUP, $salts );
		if ( is_array( $items ) ) {
			return $items;
		}

		if ( ! is_readable( $path ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( esc_html( $path ) );
		}

		$contents     = file_get_contents( $path ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read local file
		$allowed_tags = apply_filters( 'blockparty_icons_svg_parse_tags', '<symbol><g>' );
		if ( ! preg_match_all( '/id="(\S+)"/m', strip_tags( $contents, $allowed_tags ), $svg ) ) {
			Cache::set_cache( $cache_key, [], self::CACHE_GROUP, $salts, DAY_IN_SECONDS );

			return [];
		}

		$items    = [];
		$base_url = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $path );
		foreach ( $svg[1] as $name ) {
			if ( empty( $name ) ) {
				continue;
			}

			$name         = sanitize_title( $name );
			$item_content = sprintf( '%s#%s', add_query_arg( [ 'v' => $version ], $base_url ), $name );

			$label   = $icon_map[ $name ] ?? self::format_svg_name( $name );
			$items[] = new CollectionItem( $name, 'sprite', $item_content, $label, $version );
		}

		Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salts, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Instantiate array of CollectionItem from a file.
	 *
	 * @param string $path
	 * @param array $args
	 *
	 * @return CollectionItem[]
	 * @throws CollectionCreationException
	 */
	public static function from_file( string $path, array $args = [] ): array {
		$cache_key = 'from_file:' . $path;
		$salt      = md5( wp_json_encode( $args ) );

		$items = Cache::get_cache( $cache_key, self::CACHE_GROUP, $salt );
		if ( is_array( $items ) ) {
			return $items;
		}

		if ( ! is_readable( $path ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( esc_html( $path ) );
		}

		$args = (array) wp_parse_args(
			$args,
			[
				'name'  => '',
				'label' => '',
			]
		);

		$items        = [];
		$item_content = file_get_contents( $path ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read local file
		if ( ! $item_content ) {
			Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

			return $items;
		}

		$name    = $args['name'] ?? pathinfo( $path, PATHINFO_FILENAME );
		$label   = $args['label'] ?? $name;
		$items[] = new CollectionItem( $name, 'raw', $item_content, $label );

		Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Format SVG name from its id attribute.
	 *
	 * @param $id
	 * @param bool $delete_suffix
	 *
	 * @return string
	 */
	private static function format_svg_name( $id, $delete_suffix = true ): string {
		// Split up the string based on the `-` character
		$ex = explode( '-', $id );
		if ( empty( $ex ) ) {
			return $id;
		}

		// Delete the first value, as it has no real value for the icon name.
		if ( $delete_suffix ) {
			unset( $ex[0] );
		}

		// Remix values into one with spaces
		$text = implode( ' ', $ex );

		// When result is empty (e.g. name is only digits or has no hyphen), use original id
		if ( '' === (string) $text ) {
			return $id;
		}

		// Add uppercase to the first word
		return ucfirst( $text );
	}
}
