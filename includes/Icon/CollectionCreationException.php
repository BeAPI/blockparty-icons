<?php

namespace Blockparty\Icons\Icon;

class CollectionCreationException extends CollectionException {

	public static function unreadable_path( string $path ): self {
		$path = wp_normalize_path( $path );
		$path = str_replace( WP_CONTENT_DIR, '', $path );

		return new self( sprintf( 'Fail to create icon collection, path %s is not readable', $path ) );
	}
}
