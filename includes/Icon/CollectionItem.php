<?php

namespace Blockparty\Icons\Icon;

class CollectionItem {

	private string $name;
	private ?string $label;
	private string $type;
	private ?string $version;

	/**
	 * Resolved SVG payload, or null while the item is still unresolved.
	 *
	 * @var string|null
	 */
	private ?string $content = null;

	/**
	 * Serializable descriptor saying where the payload can be read from.
	 *
	 * Null for items created with literal content. A plain array rather than a
	 * closure so the item survives a round trip through the object cache.
	 *
	 * @var array|null
	 */
	private ?array $source = null;

	/**
	 * Create an item that already holds its content.
	 *
	 * @param string      $name
	 * @param string      $type
	 * @param string      $content
	 * @param string|null $label
	 * @param string|null $version
	 */
	public function __construct( string $name, string $type, string $content, ?string $label = null, ?string $version = null ) {
		$this->name    = $name;
		$this->type    = $type;
		$this->content = $content;
		$this->label   = $label ?? $name;
		$this->version = $version;
		$this->source  = null;
	}

	/**
	 * Create an item whose content is read only when it is first needed.
	 *
	 * @param string      $name
	 * @param string      $type
	 * @param array       $source  Descriptor understood by ContentLoader.
	 * @param string|null $label
	 * @param string|null $version
	 *
	 * @return self
	 */
	public static function from_source( string $name, string $type, array $source, ?string $label = null, ?string $version = null ): self {
		$item = new self( $name, $type, '', $label, $version );

		$item->content = null;
		$item->source  = $source;

		return $item;
	}

	/**
	 * Get icon's name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Get icon's label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Get icon's type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Get icon's content.
	 *
	 * For a deferred item this is where the SVG is actually read. The result is
	 * memoized, so repeated calls within a request cost nothing.
	 *
	 * @return string
	 */
	public function content(): string {
		if ( null === $this->content ) {
			$this->content = null === $this->source
				? ''
				: ContentLoader::load( $this->source );
		}

		return $this->content;
	}

	/**
	 * Whether the content is already in memory.
	 *
	 * Lets callers avoid triggering a read they do not need.
	 *
	 * @return bool
	 */
	public function is_resolved(): bool {
		return null !== $this->content;
	}

	/**
	 * Get icon's version.
	 *
	 * @return string|null
	 */
	public function version(): ?string {
		return $this->version;
	}

	/**
	 * Keep resolved payloads out of the cached collection index.
	 *
	 * Without this, caching a collection after rendering a page would grow the
	 * index by however many icons happened to be used, and eventually push it past
	 * the object cache's per-item limit.
	 *
	 * @return array
	 */
	public function __serialize(): array {
		return [
			'name'    => $this->name,
			'label'   => $this->label,
			'type'    => $this->type,
			'version' => $this->version,
			'source'  => $this->source,
			// Items with a source can always reload; only literal content is stored.
			'content' => null === $this->source ? $this->content : null,
		];
	}

	/**
	 * @param array $data
	 */
	public function __unserialize( array $data ): void {
		$this->name    = (string) ( $data['name'] ?? '' );
		$this->label   = isset( $data['label'] ) ? (string) $data['label'] : $this->name;
		$this->type    = (string) ( $data['type'] ?? 'raw' );
		$this->version = $data['version'] ?? null;
		$this->source  = $data['source'] ?? null;
		$this->content = $data['content'] ?? null;

		// An item with neither content nor a source would otherwise re-resolve forever.
		if ( null === $this->content && null === $this->source ) {
			$this->content = '';
		}
	}
}
