<?php
/**
 * Every way the plugin can be handed SVGs.
 *
 * These tests describe observable behaviour — how many items come back, what their
 * names, labels, types and contents are — and deliberately say nothing about when
 * or how often the files are read. That is what lets them keep guarding the
 * behaviour if loading ever becomes lazy.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Icon;

use Blockparty\Icons\Icon\CollectionCreationException;
use Blockparty\Icons\Icon\CollectionItem;
use Blockparty\Icons\Icon\CollectionItemsFactory;
use Blockparty\Icons\Tests\TestCase;

class CollectionItemsFactoryTest extends TestCase {

	/* ------------------------------------------------------------- folders */

	public function test_from_folder_returns_one_item_per_svg(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg( 'M1 1' ),
				'beta.svg'  => $this->svg( 'M2 2' ),
				'gamma.svg' => $this->svg( 'M3 3' ),
			]
		);

		$items = CollectionItemsFactory::from_folder( $folder );

		$this->assertCount( 3, $items );
		$this->assertContainsOnlyInstancesOf( CollectionItem::class, $items );
		$this->assertSame(
			[ 'alpha', 'beta', 'gamma' ],
			$this->names( $items ),
			'Item names come from the file name without its extension.'
		);
	}

	public function test_from_folder_exposes_each_file_contents(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg( 'M1 1' ),
				'beta.svg'  => $this->svg( 'M2 2' ),
			]
		);

		$items = $this->by_name( CollectionItemsFactory::from_folder( $folder ) );

		$this->assertSame( $this->svg( 'M1 1' ), $items['alpha']->content() );
		$this->assertSame( $this->svg( 'M2 2' ), $items['beta']->content() );
	}

	public function test_from_folder_items_are_raw_type(): void {
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg() ] );

		$items = CollectionItemsFactory::from_folder( $folder );

		$this->assertSame( 'raw', $items[0]->type() );
		$this->assertNull( $items[0]->version() );
	}

	public function test_from_folder_labels_default_to_the_file_name(): void {
		$folder = $this->make_icon_folder( [ 'arrow-left.svg' => $this->svg() ] );

		$items = CollectionItemsFactory::from_folder( $folder );

		$this->assertSame( 'arrow-left', $items[0]->label() );
	}

	public function test_from_folder_icon_map_overrides_labels(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg(),
				'beta.svg'  => $this->svg(),
			]
		);

		$items = $this->by_name(
			CollectionItemsFactory::from_folder( $folder, [ 'alpha' => 'First letter' ] )
		);

		$this->assertSame( 'First letter', $items['alpha']->label() );
		$this->assertSame( 'beta', $items['beta']->label(), 'Unmapped icons keep their file name.' );
	}

	public function test_from_folder_ignores_non_svg_files(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg'  => $this->svg(),
				'notes.txt'  => 'not an icon',
				'sprite.png' => 'not an icon either',
			]
		);

		$items = CollectionItemsFactory::from_folder( $folder );

		$this->assertSame( [ 'alpha' ], $this->names( $items ) );
	}

	public function test_from_folder_skips_empty_files(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg(),
				'empty.svg' => '',
			]
		);

		$items = CollectionItemsFactory::from_folder( $folder );

		$this->assertSame( [ 'alpha' ], $this->names( $items ) );
	}

	public function test_from_folder_on_empty_directory_returns_nothing(): void {
		$folder = $this->make_temp_dir( 'empty' );

		$this->assertSame( [], CollectionItemsFactory::from_folder( $folder ) );
	}

	public function test_from_folder_throws_on_unreadable_path(): void {
		$this->expectException( CollectionCreationException::class );

		CollectionItemsFactory::from_folder( '/definitely/not/a/folder' );
	}

	public function test_from_folder_is_stable_across_calls(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg( 'M1 1' ),
				'beta.svg'  => $this->svg( 'M2 2' ),
			]
		);

		$first  = $this->by_name( CollectionItemsFactory::from_folder( $folder ) );
		$second = $this->by_name( CollectionItemsFactory::from_folder( $folder ) );

		$this->assertSame( array_keys( $first ), array_keys( $second ) );
		$this->assertSame(
			$first['alpha']->content(),
			$second['alpha']->content(),
			'A second call — cached or not — must describe the same icons.'
		);
	}

	public function test_from_folder_caches_per_icon_map(): void {
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg() ] );

		$plain   = CollectionItemsFactory::from_folder( $folder );
		$labeled = CollectionItemsFactory::from_folder( $folder, [ 'alpha' => 'Renamed' ] );

		$this->assertSame( 'alpha', $plain[0]->label() );
		$this->assertSame(
			'Renamed',
			$labeled[0]->label(),
			'A different icon_map must not be served the previous cache entry.'
		);
	}

	/* ------------------------------------------------------------- sprites */

	public function test_from_sprite_returns_one_item_per_symbol(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha', 'ico-beta' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path );

		$this->assertCount( 2, $items );
		$this->assertSame( [ 'ico-alpha', 'ico-beta' ], $this->names( $items ) );
	}

	public function test_from_sprite_items_are_sprite_type(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path );

		$this->assertSame( 'sprite', $items[0]->type() );
	}

	public function test_from_sprite_content_is_a_public_url_fragment(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path );

		$expected = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $path ) . '#ico-alpha';

		$this->assertSame(
			$expected,
			$items[0]->content(),
			'A sprite icon is referenced by URL, not inlined.'
		);
		$this->assertStringStartsWith( WP_CONTENT_URL, $items[0]->content() );
	}

	public function test_from_sprite_version_is_added_to_the_url_and_the_item(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path, [], '2.4.1' );

		$this->assertStringContainsString( 'v=2.4.1', $items[0]->content() );
		$this->assertStringContainsString( '#ico-alpha', $items[0]->content() );
		$this->assertSame( '2.4.1', $items[0]->version() );
	}

	public function test_from_sprite_version_busts_the_cache(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$v1 = CollectionItemsFactory::from_sprite( $path, [], '1.0.0' );
		$v2 = CollectionItemsFactory::from_sprite( $path, [], '2.0.0' );

		$this->assertStringContainsString( 'v=1.0.0', $v1[0]->content() );
		$this->assertStringContainsString( 'v=2.0.0', $v2[0]->content() );
	}

	public function test_from_sprite_derives_a_readable_label_from_the_id(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-arrow-left' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path );

		$this->assertSame(
			'Arrow left',
			$items[0]->label(),
			'The first hyphen-separated segment is a prefix and is dropped.'
		);
	}

	public function test_from_sprite_label_falls_back_to_the_id_when_nothing_remains(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'solo' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path );

		$this->assertSame( 'solo', $items[0]->label() );
	}

	public function test_from_sprite_icon_map_overrides_labels(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$items = CollectionItemsFactory::from_sprite( $path, [ 'ico-alpha' => 'Custom' ] );

		$this->assertSame( 'Custom', $items[0]->label() );
	}

	public function test_from_sprite_without_symbols_returns_nothing(): void {
		$path = $this->make_file( 'sprite.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );

		$this->assertSame( [], CollectionItemsFactory::from_sprite( $path ) );
	}

	public function test_from_sprite_ignores_ids_outside_the_parsed_tags(): void {
		// Only <symbol> and <g> survive the strip_tags() pass, so a <path id> is
		// not an icon.
		$path = $this->make_file(
			'sprite.svg',
			'<svg xmlns="http://www.w3.org/2000/svg">' .
			'<symbol id="ico-real"><path id="ico-decoy" d="M0 0"/></symbol>' .
			'</svg>'
		);

		$items = CollectionItemsFactory::from_sprite( $path );

		$this->assertSame( [ 'ico-real' ], $this->names( $items ) );
	}

	public function test_from_sprite_parsed_tags_are_filterable(): void {
		$path = $this->make_file(
			'sprite.svg',
			'<svg xmlns="http://www.w3.org/2000/svg"><g id="ico-grouped"></g></svg>'
		);

		add_filter( 'blockparty_icons_svg_parse_tags', static fn() => '<symbol>' );

		$this->assertSame(
			[],
			CollectionItemsFactory::from_sprite( $path ),
			'Restricting the parsed tags to <symbol> must hide a <g> id.'
		);
	}

	public function test_from_sprite_throws_on_unreadable_path(): void {
		$this->expectException( CollectionCreationException::class );

		CollectionItemsFactory::from_sprite( '/definitely/not/a/sprite.svg' );
	}

	/* -------------------------------------------------------- single files */

	public function test_from_file_returns_one_item(): void {
		$path = $this->make_file( 'star.svg', $this->svg( 'M9 9' ) );

		$items = CollectionItemsFactory::from_file( $path, [ 'name' => 'star' ] );

		$this->assertCount( 1, $items );
		$this->assertSame( 'star', $items[0]->name() );
		$this->assertSame( 'raw', $items[0]->type() );
		$this->assertSame( $this->svg( 'M9 9' ), $items[0]->content() );
	}

	public function test_from_file_uses_the_provided_label(): void {
		$path = $this->make_file( 'star.svg', $this->svg() );

		$items = CollectionItemsFactory::from_file(
			$path,
			[
				'name'  => 'star',
				'label' => 'A shining star',
			]
		);

		$this->assertSame( 'A shining star', $items[0]->label() );
	}

	public function test_from_file_on_empty_file_returns_nothing(): void {
		$path = $this->make_file( 'empty.svg', '' );

		$this->assertSame( [], CollectionItemsFactory::from_file( $path, [ 'name' => 'empty' ] ) );
	}

	public function test_from_file_throws_on_unreadable_path(): void {
		$this->expectException( CollectionCreationException::class );

		CollectionItemsFactory::from_file( '/definitely/not/a/file.svg' );
	}

	/* ------------------------------------------------------------- helpers */

	/**
	 * @param CollectionItem[] $items
	 *
	 * @return string[]
	 */
	private function names( array $items ): array {
		return array_map( static fn( CollectionItem $item ) => $item->name(), $items );
	}

	/**
	 * @param CollectionItem[] $items
	 *
	 * @return array<string, CollectionItem>
	 */
	private function by_name( array $items ): array {
		$out = [];
		foreach ( $items as $item ) {
			$out[ $item->name() ] = $item;
		}

		return $out;
	}
}
