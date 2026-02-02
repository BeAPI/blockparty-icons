<?php

namespace Blockparty\Icons\Icon;

class CollectionItemsFactory {

	private const CACHE_GROUP = 'blockparty-icons';

	/**
	 * Add helper for enable or not object cache depends the context
	 *
	 * @return bool
	 */
	private static function is_cache_disabled() {
		if ( function_exists( 'wp_is_development_mode' ) ) {
			return wp_is_development_mode( 'all' );
		}

		if ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) === true ) {
			return true;
		}

		return false;
	}

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
		$cache_key = md5( 'from_folder' . $folder . serialize( $icon_map ) );

		if ( ! self::is_cache_disabled() ) {
			$found = null;
			$items = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );

			if ( $found && is_array( $items ) ) {
				return $items;
			}
		}

		if ( ! is_readable( $folder ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( $folder );
		}

		$items  = [];
		$folder = trailingslashit( $folder );
		foreach ( glob( $folder . '*.svg' ) as $svg ) {
			$item_content = file_get_contents( $svg );
			if ( ! $item_content ) {
				continue;
			}

			$name    = pathinfo( $svg, PATHINFO_FILENAME );
			$label   = $icon_map[ $name ] ?? $name;
			$items[] = new CollectionItem( $name, 'raw', $item_content, $label );
		}

		wp_cache_set( $cache_key, $items, self::CACHE_GROUP, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Instantiate array of CollectionItem from a sprite.
	 *
	 * @param string $path
	 * @param array $icon_map
	 *
	 * @return CollectionItem[]
	 * @throws CollectionCreationException
	 */
	public static function from_sprite( string $path, array $icon_map = [] ): array {
		$cache_key = md5( 'from_sprite' . $path . serialize( $icon_map ) );

		if ( ! self::is_cache_disabled() ) {
			$found = null;
			$items = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );

			if ( $found && is_array( $items ) ) {
				return $items;
			}
		}

		if ( ! is_readable( $path ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( $path );
		}

		$contents     = file_get_contents( $path );
		$allowed_tags = apply_filters( 'blockparty_icons_svg_parse_tags', '<symbol><g>' );
		if ( ! preg_match_all( '/id="(\S+)"/m', strip_tags( $contents, $allowed_tags ), $svg ) ) {
			wp_cache_set( $cache_key, [], self::CACHE_GROUP, DAY_IN_SECONDS );

			return [];
		}

		$items    = [];
		$base_url = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $path );
		foreach ( $svg[1] as $name ) {
			if ( empty( $name ) ) {
				continue;
			}

			$name         = sanitize_title( $name );
			$item_content = sprintf( '%s#%s', $base_url, $name );

			$label   = $icon_map[ $name ] ?? self::format_svg_name( $name );
			$items[] = new CollectionItem( $name, 'sprite', $item_content, $label );
		}

		wp_cache_set( $cache_key, $items, self::CACHE_GROUP, DAY_IN_SECONDS );

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
		$cache_key = md5( 'from_file' . $path . serialize( $args ) );

		if ( ! self::is_cache_disabled() ) {
			$found = null;
			$items = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );

			if ( $found && is_array( $items ) ) {
				return $items;
			}
		}

		if ( ! is_readable( $path ) ) {
			//TODO: create dedicated exception
			throw CollectionCreationException::unreadable_path( $path );
		}

		$args = (array) wp_parse_args(
			$args,
			[
				'name'  => '',
				'label' => '',
			]
		);

		$items        = [];
		$item_content = file_get_contents( $path );
		if ( ! $item_content ) {
			wp_cache_set( $cache_key, $items, self::CACHE_GROUP, DAY_IN_SECONDS );

			return $items;
		}

		$name    = $args['name'] ?? pathinfo( $path, PATHINFO_FILENAME );
		$label   = $args['label'] ?? $name;
		$items[] = new CollectionItem( $name, 'raw', $item_content, $label );

		wp_cache_set( $cache_key, $items, self::CACHE_GROUP, DAY_IN_SECONDS );

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

		// Add uppercase to the first word
		return ucfirst( $text );
	}
}
