<?php

namespace Blockparty\Icons\Icon;

use Blockparty\Icons\Helpers\Cache;

/**
 * Resolves an icon's SVG payload on demand.
 *
 * Collections hold a lightweight index — name, label, type and a small descriptor
 * saying where the bytes live. The bytes themselves are only read when something
 * actually asks for them: one icon when rendering a block, one page's worth when
 * the editor lists a collection.
 *
 * Payloads are cached individually rather than as part of the collection, so a
 * single oversized icon cannot make a whole collection uncacheable.
 */
final class ContentLoader {

	private const CACHE_GROUP = 'blockparty-icons-content';

	/**
	 * Load the SVG payload described by a source descriptor.
	 *
	 * @param array $source Descriptor produced by CollectionItemsFactory.
	 *
	 * @return string SVG markup, or an empty string when it cannot be read.
	 */
	public static function load( array $source ): string {
		$path = self::resolve_path( $source );
		if ( null === $path ) {
			return '';
		}

		$cache_key = 'content:' . md5( $path );
		$salt      = defined( 'BLOCKPARTY_ICONS_VERSION' ) ? BLOCKPARTY_ICONS_VERSION : '0';

		$cached = Cache::get_cache( $cache_key, self::CACHE_GROUP, $salt );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read local file
		if ( ! is_string( $content ) ) {
			return '';
		}

		/*
		 * Cache::set_cache() declines payloads the object cache would refuse anyway,
		 * so an icon larger than the backend's item limit simply stays uncached
		 * instead of failing a write on every single request.
		 */
		Cache::set_cache( $cache_key, $content, self::CACHE_GROUP, $salt, DAY_IN_SECONDS );

		return $content;
	}

	/**
	 * Turn a source descriptor into a readable path.
	 *
	 * Descriptors are plain arrays so they survive serialization into the object
	 * cache; a closure would not.
	 *
	 * @param array $source
	 *
	 * @return string|null
	 */
	private static function resolve_path( array $source ): ?string {
		switch ( $source['type'] ?? '' ) {
			case 'file':
				$path = (string) ( $source['path'] ?? '' );

				return '' === $path ? null : $path;

			case 'attachment':
				$id = (int) ( $source['id'] ?? 0 );
				if ( ! $id ) {
					return null;
				}

				$path = get_attached_file( $id );

				return is_string( $path ) && '' !== $path ? $path : null;
		}

		return null;
	}
}
