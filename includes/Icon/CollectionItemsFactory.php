<?php

namespace Blockparty\Icons\Icon;

use Blockparty\Icons\Helpers\Cache;

class CollectionItemsFactory {

	private const CACHE_GROUP = 'blockparty-icons';

	/**
	 * Format revision of the cached index.
	 *
	 * Cached indexes hold serialized CollectionItem objects. Bump this whenever
	 * their shape changes so that entries written by an earlier release are never
	 * read back — the salt cannot do this job, because `wp_cache_get_salted()`
	 * unserializes the stored value *before* it compares salts. Only a different
	 * key keeps the two formats apart.
	 */
	private const CACHE_FORMAT = 'v3';

	/**
	 * Build a format-scoped cache key.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private static function cache_key( string $key ): string {
		return self::CACHE_FORMAT . ':' . $key;
	}

	/**
	 * Salt component that invalidates cached indexes when the plugin is updated.
	 *
	 * @return string
	 */
	private static function cache_version(): string {
		return defined( 'BLOCKPARTY_ICONS_VERSION' ) ? BLOCKPARTY_ICONS_VERSION : '0';
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
		$cache_key = self::cache_key( 'from_folder:' . $folder );
		$salt      = [ md5( wp_json_encode( $icon_map ) ), self::cache_version() ];

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
			// Skip empty files as before, but without reading their contents:
			// the payload is loaded only if the icon is actually used.
			if ( ! filesize( $svg ) ) {
				continue;
			}

			$name    = pathinfo( $svg, PATHINFO_FILENAME );
			$label   = $icon_map[ $name ] ?? $name;
			$items[] = CollectionItem::from_source(
				$name,
				'raw',
				[
					'type' => 'file',
					'path' => $svg,
				],
				$label
			);
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
		$cache_key = self::cache_key( 'from_sprite:' . $path );
		$salts     = [ md5( wp_json_encode( $icon_map ) ), self::cache_version() ];
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
		$cache_key = self::cache_key( 'from_file:' . $path );
		$salt      = [ md5( wp_json_encode( $args ) ), self::cache_version() ];

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

		$items = [];
		if ( ! filesize( $path ) ) {
			Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

			return $items;
		}

		$name    = $args['name'] ?? pathinfo( $path, PATHINFO_FILENAME );
		$label   = $args['label'] ?? $name;
		$items[] = CollectionItem::from_source(
			$name,
			'raw',
			[
				'type' => 'file',
				'path' => $path,
			],
			$label
		);

		Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Instantiate array of CollectionItem from SVG attachments in the media library.
	 *
	 * Registering media-library icons one file at a time — a WP_Query followed by a
	 * `from_file()` call per attachment — costs a database query and one cache
	 * round trip per icon on every request. This builds the whole collection from a
	 * single query and stores it as one small index, keyed on the posts cache's
	 * `last_changed` value so contributing a new SVG in the back office invalidates
	 * it immediately.
	 *
	 * @param array $args {
	 *   Optional. An array of additional arguments. Default empty array.
	 *
	 *   @type array $icon_map Optional. Override labels, keyed by attachment slug.
	 *   @type array $query    Optional. Extra WP_Query arguments, merged over the defaults.
	 * }
	 *
	 * @return CollectionItem[]
	 */
	public static function from_attachments( array $args = [] ): array {
		$args = (array) wp_parse_args(
			$args,
			[
				'icon_map' => [],
				'query'    => [],
			]
		);

		$query_args = (array) wp_parse_args(
			(array) $args['query'],
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/svg+xml',
				'posts_per_page' => 500, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		$cache_key = self::cache_key( 'from_attachments:' . md5( wp_json_encode( $query_args ) ) );
		$salts     = [
			md5( wp_json_encode( $args['icon_map'] ) ),
			self::cache_version(),
			// Any change to a post or attachment bumps this, so the index cannot go stale.
			(string) wp_cache_get_last_changed( 'posts' ),
		];

		$items = Cache::get_cache( $cache_key, self::CACHE_GROUP, $salts );
		if ( is_array( $items ) ) {
			return $items;
		}

		// Only the fields needed to build the index.
		$query_args['fields'] = 'all';

		$query = new \WP_Query( $query_args );

		$items = [];
		foreach ( $query->posts as $attachment ) {
			$name  = $attachment->post_name;
			$label = $args['icon_map'][ $name ] ?? get_the_title( $attachment );

			$items[] = CollectionItem::from_source(
				$name,
				'raw',
				[
					'type' => 'attachment',
					'id'   => (int) $attachment->ID,
				],
				$label
			);
		}

		Cache::set_cache( $cache_key, $items, self::CACHE_GROUP, $salts, DAY_IN_SECONDS );

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
