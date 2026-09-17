<?php
/**
 * A single icon: what it exposes, and what survives a trip through the cache.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Icon;

use Blockparty\Icons\Icon\CollectionItem;
use Blockparty\Icons\Tests\TestCase;

class CollectionItemTest extends TestCase {

	public function test_it_exposes_everything_it_was_given(): void {
		$item = new CollectionItem( 'star', 'raw', '<svg/>', 'A star', '1.2.3' );

		$this->assertSame( 'star', $item->name() );
		$this->assertSame( 'raw', $item->type() );
		$this->assertSame( '<svg/>', $item->content() );
		$this->assertSame( 'A star', $item->label() );
		$this->assertSame( '1.2.3', $item->version() );
	}

	public function test_the_label_defaults_to_the_name(): void {
		$item = new CollectionItem( 'star', 'raw', '<svg/>' );

		$this->assertSame( 'star', $item->label() );
	}

	public function test_the_version_is_null_by_default(): void {
		$item = new CollectionItem( 'star', 'raw', '<svg/>' );

		$this->assertNull( $item->version() );
	}

	public function test_a_null_label_falls_back_to_the_name(): void {
		$item = new CollectionItem( 'star', 'raw', '<svg/>', null );

		$this->assertSame( 'star', $item->label() );
	}

	public function test_empty_content_is_allowed(): void {
		$item = new CollectionItem( 'star', 'raw', '' );

		$this->assertSame( '', $item->content() );
	}

	public function test_content_is_returned_verbatim(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z"/></svg>';

		$item = new CollectionItem( 'star', 'raw', $svg );

		$this->assertSame(
			$svg,
			$item->content(),
			'The plugin does not sanitize SVGs; content must pass through untouched.'
		);
	}

	public function test_it_survives_a_serialization_round_trip(): void {
		$item = new CollectionItem( 'star', 'sprite', 'https://example.org/s.svg#star', 'A star', '2.0' );

		// Serializing is the point of the test: this is how an item reaches the
		// object cache and comes back.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize,WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$restored = unserialize( serialize( $item ) );

		$this->assertInstanceOf( CollectionItem::class, $restored );
		$this->assertSame( 'star', $restored->name() );
		$this->assertSame( 'sprite', $restored->type() );
		$this->assertSame( 'https://example.org/s.svg#star', $restored->content() );
		$this->assertSame( 'A star', $restored->label() );
		$this->assertSame( '2.0', $restored->version() );
	}

	public function test_it_survives_a_trip_through_the_object_cache(): void {
		$item = new CollectionItem( 'star', 'raw', $this->svg( 'MARKER' ), 'A star' );

		wp_cache_set( 'item', [ $item ], 'blockparty-icons-test' );
		$restored = wp_cache_get( 'item', 'blockparty-icons-test' );

		$this->assertSame( 'star', $restored[0]->name() );
		$this->assertSame( $this->svg( 'MARKER' ), $restored[0]->content() );
	}
}
