<?php
/**
 * The public registration API a theme talks to.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests;

use Blockparty\Icons\Icon\Collection;
use Blockparty\Icons\Icon\CollectionItem;

use function Blockparty\Icons\add_icons;
use function Blockparty\Icons\get_icon_collection;
use function Blockparty\Icons\get_icon_collections;
use function Blockparty\Icons\icon_collection_exists;
use function Blockparty\Icons\register_icon_collection;

class RegistrationTest extends TestCase {

	/* ----------------------------------------------- register_icon_collection */

	public function test_registering_by_name_creates_an_empty_collection(): void {
		$collection = register_icon_collection( 'plain' );

		$this->assertInstanceOf( Collection::class, $collection );
		$this->assertSame( 'plain', $collection->name() );
		$this->assertSame( 0, $collection->count() );
	}

	public function test_registering_uses_the_given_label(): void {
		$collection = register_icon_collection( 'plain', [ 'label' => 'Plain icons' ] );

		$this->assertSame( 'Plain icons', $collection->label() );
	}

	public function test_registering_a_folder_loads_its_icons(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg( 'M1 1' ),
				'beta.svg'  => $this->svg( 'M2 2' ),
			]
		);

		$collection = register_icon_collection(
			'folder-icons',
			[
				'type'   => 'folder',
				'source' => $folder,
			]
		);

		$this->assertSame( 2, $collection->count() );
		$this->assertSame( $this->svg( 'M1 1' ), $collection->get( 'alpha' )->content() );
	}

	public function test_registering_a_sprite_loads_its_symbols(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha', 'ico-beta' ] ) );

		$collection = register_icon_collection(
			'sprite-icons',
			[
				'type'   => 'sprite',
				'source' => $path,
			]
		);

		$this->assertSame( 2, $collection->count() );
		$this->assertSame( 'sprite', $collection->get( 'ico-alpha' )->type() );
	}

	public function test_registering_an_unknown_type_yields_an_empty_collection(): void {
		$collection = register_icon_collection( 'weird', [ 'type' => 'carrier-pigeon' ] );

		$this->assertInstanceOf( Collection::class, $collection );
		$this->assertSame( 0, $collection->count() );
	}

	public function test_registering_a_folder_that_does_not_exist_stores_false(): void {
		$result = register_icon_collection(
			'broken',
			[
				'type'   => 'folder',
				'source' => '/definitely/not/a/folder',
			]
		);

		$this->assertFalse( $result, 'A source that cannot be read must not throw out of the API.' );
	}

	public function test_registering_a_prebuilt_collection_instance(): void {
		$collection = new Collection( 'prebuilt', 'Prebuilt' );
		$collection->add( new CollectionItem( 'alpha', 'raw', '<svg/>' ) );

		$returned = register_icon_collection( $collection );

		$this->assertSame( $collection, $returned );
		$this->assertSame( $collection, get_icon_collection( 'prebuilt' ) );
		$this->assertSame( 1, get_icon_collection( 'prebuilt' )->count() );
	}

	public function test_registering_a_duplicate_name_is_refused(): void {
		register_icon_collection( 'once', [ 'label' => 'First' ] );

		$second = register_icon_collection( 'once', [ 'label' => 'Second' ] );

		$this->assertFalse( $second );
		$this->assertSame( 'First', get_icon_collection( 'once' )->label(), 'The first registration wins.' );
	}

	public function test_registering_a_duplicate_instance_is_refused(): void {
		register_icon_collection( new Collection( 'once', 'First' ) );

		$this->assertFalse( register_icon_collection( new Collection( 'once', 'Second' ) ) );
		$this->assertSame( 'First', get_icon_collection( 'once' )->label() );
	}

	/* ------------------------------------------------------------ add_icons */

	public function test_add_icons_from_a_folder(): void {
		register_icon_collection( 'target' );
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg(),
				'beta.svg'  => $this->svg(),
			]
		);

		$this->assertTrue( add_icons( 'target', 'folder', $folder ) );
		$this->assertSame( 2, get_icon_collection( 'target' )->count() );
	}

	public function test_add_icons_from_a_sprite(): void {
		register_icon_collection( 'target' );
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$this->assertTrue( add_icons( 'target', 'sprite', $path ) );
		$this->assertSame( 'sprite', get_icon_collection( 'target' )->get( 'ico-alpha' )->type() );
	}

	public function test_add_icons_from_a_single_file(): void {
		register_icon_collection( 'target' );
		$path = $this->make_file( 'star.svg', $this->svg( 'M9 9' ) );

		$added = add_icons(
			'target',
			'file',
			$path,
			[
				'name'  => 'star',
				'label' => 'A star',
			]
		);

		$this->assertTrue( $added );
		$this->assertSame( 'A star', get_icon_collection( 'target' )->get( 'star' )->label() );
		$this->assertSame( $this->svg( 'M9 9' ), get_icon_collection( 'target' )->get( 'star' )->content() );
	}

	public function test_add_icons_accumulates_across_sources(): void {
		register_icon_collection( 'target' );
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg() ] );
		$sprite = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-beta' ] ) );

		add_icons( 'target', 'folder', $folder );
		add_icons( 'target', 'sprite', $sprite );

		$collection = get_icon_collection( 'target' );

		$this->assertSame( 2, $collection->count() );
		$this->assertSame( 'raw', $collection->get( 'alpha' )->type() );
		$this->assertSame( 'sprite', $collection->get( 'ico-beta' )->type() );
	}

	public function test_add_icons_to_an_unknown_collection_fails(): void {
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg() ] );

		$this->assertFalse( add_icons( 'nope', 'folder', $folder ) );
	}

	public function test_add_icons_with_an_unknown_type_fails(): void {
		register_icon_collection( 'target' );

		$this->assertFalse( add_icons( 'target', 'carrier-pigeon', '/tmp' ) );
		$this->assertSame( 0, get_icon_collection( 'target' )->count() );
	}

	public function test_add_icons_from_an_unreadable_source_fails(): void {
		register_icon_collection( 'target' );

		$this->assertFalse( add_icons( 'target', 'folder', '/definitely/not/a/folder' ) );
		$this->assertSame( 0, get_icon_collection( 'target' )->count() );
	}

	/* -------------------------------------------------------------- lookups */

	public function test_icon_collection_exists(): void {
		$this->assertFalse( icon_collection_exists( 'ghost' ) );

		register_icon_collection( 'ghost' );

		$this->assertTrue( icon_collection_exists( 'ghost' ) );
	}

	public function test_get_icon_collection_returns_null_when_unknown(): void {
		$this->assertNull( get_icon_collection( 'ghost' ) );
	}

	public function test_get_icon_collections_lists_everything_by_name(): void {
		register_icon_collection( 'one' );
		register_icon_collection( 'two' );

		$all = get_icon_collections();

		$this->assertSame( [ 'one', 'two' ], array_keys( $all ) );
		$this->assertContainsOnlyInstancesOf( Collection::class, $all );
	}

	public function test_get_icon_collections_is_empty_by_default(): void {
		$this->assertSame( [], get_icon_collections() );
	}

	/* ------------------------------------------------------------- editor */

	public function test_preload_paths_cover_the_collections_endpoint(): void {
		$paths = apply_filters( 'block_editor_rest_api_preload_paths', [], null );

		$this->assertContains( '/icons/v1/collections?context=edit', $paths );
	}

	public function test_preload_paths_include_one_entry_per_collection(): void {
		register_icon_collection( 'one' );
		register_icon_collection( 'two' );

		$paths = apply_filters( 'block_editor_rest_api_preload_paths', [], null );

		$this->assertContains( '/icons/v1/one?context=edit', $paths );
		$this->assertContains( '/icons/v1/two?context=edit', $paths );
	}

	public function test_preload_paths_keep_existing_entries(): void {
		$paths = apply_filters( 'block_editor_rest_api_preload_paths', [ '/wp/v2/types' ], null );

		$this->assertContains( '/wp/v2/types', $paths );
	}

	public function test_init_action_fires_for_third_parties(): void {
		$fired = false;
		add_action(
			'blockparty_icons_init',
			static function () use ( &$fired ) {
				$fired = true;
			}
		);

		do_action( 'blockparty_icons_init' );

		$this->assertTrue( $fired );
	}

	public function test_the_block_type_is_registered(): void {
		$this->assertTrue(
			\WP_Block_Type_Registry::get_instance()->is_registered( 'blockparty/icon' ),
			'The plugin registers its block on init.'
		);
	}
}
