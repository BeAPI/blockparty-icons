<?php
/**
 * The collection container: building, lookup, counting, searching, iteration.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Icon;

use Blockparty\Icons\Icon\Collection;
use Blockparty\Icons\Icon\CollectionCreationException;
use Blockparty\Icons\Icon\CollectionItem;
use Blockparty\Icons\Tests\TestCase;

class CollectionTest extends TestCase {

	private function item( string $name, string $label = null, string $content = '<svg/>' ): CollectionItem {
		return new CollectionItem( $name, 'raw', $content, $label );
	}

	/* --------------------------------------------------------- constructor */

	public function test_label_defaults_to_the_name(): void {
		$collection = new Collection( 'my-icons' );

		$this->assertSame( 'my-icons', $collection->name() );
		$this->assertSame( 'my-icons', $collection->label() );
	}

	public function test_label_can_be_given(): void {
		$collection = new Collection( 'my-icons', 'My icons' );

		$this->assertSame( 'My icons', $collection->label() );
	}

	/* ---------------------------------------------------------- membership */

	public function test_a_new_collection_is_empty(): void {
		$collection = new Collection( 'empty' );

		$this->assertSame( 0, $collection->count() );
		$this->assertSame( [], $collection->all() );
		$this->assertNull( $collection->get( 'anything' ) );
	}

	public function test_items_are_retrievable_by_name(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha' ) );
		$collection->add( $this->item( 'beta' ) );

		$this->assertSame( 2, $collection->count() );
		$this->assertSame( 'alpha', $collection->get( 'alpha' )->name() );
		$this->assertSame( 'beta', $collection->get( 'beta' )->name() );
	}

	public function test_get_returns_null_for_an_unknown_name(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha' ) );

		$this->assertNull( $collection->get( 'nope' ) );
	}

	public function test_adding_the_same_name_twice_replaces_the_first(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha', 'First' ) );
		$collection->add( $this->item( 'alpha', 'Second' ) );

		$this->assertSame( 1, $collection->count() );
		$this->assertSame( 'Second', $collection->get( 'alpha' )->label() );
	}

	public function test_a_dotted_name_is_keyed_on_the_part_before_the_dot(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha.svg' ) );

		$this->assertSame(
			'alpha.svg',
			$collection->get( 'alpha' )->name(),
			'Lookup uses the truncated key while the item keeps its full name.'
		);
	}

	public function test_all_is_keyed_by_lookup_name(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha' ) );
		$collection->add( $this->item( 'beta' ) );

		$this->assertSame( [ 'alpha', 'beta' ], array_keys( $collection->all() ) );
	}

	/* -------------------------------------------------------------- search */

	public function test_search_matches_on_name(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'arrow-left' ) );
		$collection->add( $this->item( 'arrow-right' ) );
		$collection->add( $this->item( 'star' ) );

		$found = $collection->search( 'arrow' );

		$this->assertSame( 2, $found->count() );
		$this->assertSame( [ 'arrow-left', 'arrow-right' ], array_keys( $found->all() ) );
	}

	public function test_search_matches_on_label(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'ico-1', 'Shopping cart' ) );
		$collection->add( $this->item( 'ico-2', 'Star' ) );

		$found = $collection->search( 'cart' );

		$this->assertSame( [ 'ico-1' ], array_keys( $found->all() ) );
	}

	public function test_search_is_case_insensitive(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'Arrow-Left' ) );

		$this->assertSame( 1, $collection->search( 'arrow' )->count() );
		$this->assertSame( 1, $collection->search( 'ARROW' )->count() );
	}

	public function test_search_returns_an_empty_collection_when_nothing_matches(): void {
		$collection = new Collection( 'icons', 'Icons' );
		$collection->add( $this->item( 'alpha' ) );

		$found = $collection->search( 'zzz' );

		$this->assertSame( 0, $found->count() );
		$this->assertSame( 'icons', $found->name(), 'The filtered collection keeps its identity.' );
		$this->assertSame( 'Icons', $found->label() );
	}

	public function test_search_does_not_modify_the_original(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha' ) );
		$collection->add( $this->item( 'beta' ) );

		$collection->search( 'alpha' );

		$this->assertSame( 2, $collection->count() );
	}

	/* -------------------------------------------------------- named ctors */

	public function test_from_folder_builds_a_populated_collection(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg( 'M1 1' ),
				'beta.svg'  => $this->svg( 'M2 2' ),
			]
		);

		$collection = Collection::from_folder( 'folder-icons', $folder, [ 'label' => 'Folder icons' ] );

		$this->assertSame( 'folder-icons', $collection->name() );
		$this->assertSame( 'Folder icons', $collection->label() );
		$this->assertSame( 2, $collection->count() );
		$this->assertSame( $this->svg( 'M1 1' ), $collection->get( 'alpha' )->content() );
	}

	public function test_from_folder_applies_the_icon_map(): void {
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg() ] );

		$collection = Collection::from_folder(
			'folder-icons',
			$folder,
			[ 'icon_map' => [ 'alpha' => 'The first one' ] ]
		);

		$this->assertSame( 'The first one', $collection->get( 'alpha' )->label() );
	}

	public function test_from_folder_throws_on_unreadable_path(): void {
		$this->expectException( CollectionCreationException::class );

		Collection::from_folder( 'nope', '/definitely/not/a/folder' );
	}

	public function test_from_sprite_builds_a_populated_collection(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha', 'ico-beta' ] ) );

		$collection = Collection::from_sprite( 'sprite-icons', $path, [ 'label' => 'Sprite icons' ] );

		$this->assertSame( 'Sprite icons', $collection->label() );
		$this->assertSame( 2, $collection->count() );
		$this->assertSame( 'sprite', $collection->get( 'ico-alpha' )->type() );
	}

	public function test_from_sprite_passes_the_version_through(): void {
		$path = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-alpha' ] ) );

		$collection = Collection::from_sprite( 'sprite-icons', $path, [ 'version' => '3.1' ] );

		$this->assertSame( '3.1', $collection->get( 'ico-alpha' )->version() );
		$this->assertStringContainsString( 'v=3.1', $collection->get( 'ico-alpha' )->content() );
	}

	public function test_from_sprite_throws_on_unreadable_path(): void {
		$this->expectException( CollectionCreationException::class );

		Collection::from_sprite( 'nope', '/definitely/not/a/sprite.svg' );
	}

	/* ------------------------------------------------------------ iterator */

	public function test_collection_implements_iterator(): void {
		$this->assertInstanceOf( \Iterator::class, new Collection( 'icons' ) );
	}

	public function test_iteration_over_an_empty_collection_yields_nothing(): void {
		$seen = [];
		foreach ( new Collection( 'icons' ) as $item ) {
			$seen[] = $item;
		}

		$this->assertSame( [], $seen );
	}

	/**
	 * Records a defect, not a specification.
	 *
	 * The Iterator implementation walks an integer $position, while add() keys items
	 * by name. valid() therefore asks for $items[0], which never exists, and foreach
	 * over a populated collection yields nothing at all.
	 *
	 * Nothing in the plugin iterates a Collection — every caller uses all() — so this
	 * is latent rather than broken in production. The test is here so that whoever
	 * fixes it sees this expectation fail and knows to update it deliberately.
	 *
	 * @see Collection::current()
	 * @see Collection::valid()
	 */
	public function test_known_defect_iteration_yields_nothing_even_when_populated(): void {
		$collection = new Collection( 'icons' );
		$collection->add( $this->item( 'alpha' ) );
		$collection->add( $this->item( 'beta' ) );

		$seen = [];
		foreach ( $collection as $item ) {
			$seen[] = $item->name();
		}

		$this->assertSame( 2, $collection->count(), 'The items really are in there.' );
		$this->assertSame(
			[],
			$seen,
			'Iteration is broken: positions are integers, keys are names. ' .
			'If this assertion starts failing, the Iterator was fixed — update this test.'
		);
	}
}
