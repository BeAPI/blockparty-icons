<?php

namespace Blockparty\Icons\Icon;

class Collection implements \Iterator {

	private $name;
	private $label;
	private $items    = [];
	private $position = 0;

	/**
	 * Create a new collection and load icons from a folder.
	 *
	 * @param string $name collection's name.
	 * @param string $folder path to icons folder.
	 * @param array $args collection's args.
	 *
	 * @return static
	 * @throws CollectionCreationException
	 */
	public static function from_folder( string $name, string $folder, array $args = [] ): self {
		if ( ! is_readable( $folder ) ) {
			throw CollectionCreationException::unreadable_path( esc_html( $folder ) );
		}

		$collection = new self( $name, $args['label'] ?? null );

		try {
			$items = CollectionItemsFactory::from_folder( $folder, $args['icon_map'] ?? [] );
		} catch ( CollectionCreationException $e ) {
			return $collection;
		}

		array_map( [ $collection, 'add' ], $items );

		return $collection;
	}

	/**
	 * Create a new collection and load icons from a sprite.
	 *
	 * @param string $name collection's name.
	 * @param string $path path to sprite.
	 * @param array $args collection's args.
	 *
	 * @return static
	 * @throws CollectionCreationException
	 */
	public static function from_sprite( string $name, string $path, array $args = [] ): self {
		if ( ! is_readable( $path ) ) {
			throw CollectionCreationException::unreadable_path( esc_html( $path ) );
		}

		$collection = new self( $name, $args['label'] ?? null );

		try {
			$items = CollectionItemsFactory::from_sprite( $path, $args['icon_map'] ?? [], $args['version'] ?? null );
		} catch ( CollectionCreationException $e ) {
			return $collection;
		}

		array_map( [ $collection, 'add' ], $items );

		return $collection;
	}

	/**
	 * Create a new collection from the SVG attachments in the media library.
	 *
	 * @param string $name collection's name.
	 * @param array $args collection's args. See CollectionItemsFactory::from_attachments().
	 *
	 * @return static
	 */
	public static function from_attachments( string $name, array $args = [] ): self {
		$collection = new self( $name, $args['label'] ?? null );

		$items = CollectionItemsFactory::from_attachments( $args );
		array_map( [ $collection, 'add' ], $items );

		return $collection;
	}

	public function __construct( string $name, $label = null ) {
		$this->name  = $name;
		$this->label = $label ?? $name;
	}

	/**
	 * Get callection's name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Get collection's label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Add a new icon to the collection.
	 *
	 * @param CollectionItem $item
	 */
	public function add( CollectionItem $item ): void {
		$this->items[ strtok( $item->name(), '.' ) ] = $item;
	}

	/**
	 * Get a icon.
	 *
	 * @param string $name icon's name.
	 *
	 * @return CollectionItem|null
	 */
	public function get( string $name ): ?CollectionItem {
		return $this->items[ $name ] ?? null;
	}

	/**
	 * Get all icons in the collection.
	 *
	 * @return array
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Get icon count in the collection.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Filter the collection.
	 *
	 * @param string $query search query.
	 *
	 * @return self
	 */
	public function search( string $query ): self {
		$filtered_items = array_filter(
			$this->items,
			function ( CollectionItem $item ) use ( $query ) {
				// Search in both label and name
				$label_match = stripos( $item->label(), $query ) !== false;
				$name_match  = stripos( $item->name(), $query ) !== false;

				return $label_match || $name_match;
			}
		);

		$collection = new self( $this->name, $this->label );
		foreach ( $filtered_items as $filtered_item ) {
			$collection->add( $filtered_item );
		}

		return $collection;
	}

	/**
	 * @inerhitDoc
	 */
	#[\ReturnTypeWillChange]
	public function current() {
		return $this->items[ $this->position ];
	}

	/**
	 * @inerhitDoc
	 */
	#[\ReturnTypeWillChange]
	public function next() {
		++$this->position;
	}

	/**
	 * @inerhitDoc
	 */
	#[\ReturnTypeWillChange]
	public function key() {
		return $this->position;
	}

	/**
	 * @inerhitDoc
	 */
	#[\ReturnTypeWillChange]
	public function valid() {
		return isset( $this->items[ $this->position ] );
	}

	/**
	 * @inerhitDoc
	 */
	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->position = 0;
	}
}
