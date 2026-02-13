<?php

namespace Blockparty\Icons\Icon;

class CollectionItem {

	private $name;
	private $label;
	private $type;
	private $version;
	private $content;

	public function __construct( string $name, string $type, string $content, string $label = null, string $version = null ) {
		$this->name    = $name;
		$this->type    = $type;
		$this->content = $content;
		$this->label   = $label ?? $name;
		$this->version = $version;
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
	 * @return string
	 */
	public function content(): string {
		return $this->content;
	}

	/**
	 * Get icon's version.
	 *
	 * @return string|null
	 */
	public function version(): ?string {
		return $this->version;
	}
}
