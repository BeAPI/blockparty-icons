<?php
/**
 * Converts beapi/icon-block content to blockparty/icon.
 *
 * @package Blockparty\Icons
 */

namespace Blockparty\Icons\Migration;

/**
 * Turns old beapi/icon-block markup into blockparty/icon blocks.
 */
class IconBlockMigrator {

	/**
	 * How many icons were converted.
	 *
	 * @var int
	 */
	public $migrated = 0;

	/**
	 * How many icons could not be converted (missing name when an icon was expected).
	 *
	 * @var int
	 */
	public $skipped = 0;

	/**
	 * Missing icons encountered during migration: "collection/name" => count.
	 *
	 * @var array<string, int>
	 */
	public $missing_icons = [];

	/**
	 * Entry point: rewrite post content, or return null if nothing to do.
	 *
	 * @param string $content Post content.
	 * @return string|null
	 * @author Jules Fell
	 */
	public function migrate_content( string $content ): ?string {
		if ( false === strpos( $content, 'beapi/icon-block' ) && false === strpos( $content, 'beapi/icon-item' ) ) {
			return null;
		}

		// WP native: string → blocks → string.
		$new = serialize_blocks( $this->migrate_blocks( parse_blocks( $content ) ) );

		return $new === $content ? null : $new;
	}

	/**
	 * Walk every block. Replace old icon blocks. Recurse into groups/columns/etc.
	 *
	 * Important: keep parent innerContent HTML strings (group/columns wrappers).
	 * Only replace the null placeholders that point to inner blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array
	 * @author Jules Fell
	 */
	private function migrate_blocks( array $blocks ): array {
		$out = [];

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			// Old parent block → one or more blockparty/icon.
			if ( 'beapi/icon-block' === $name ) {
				$out = array_merge( $out, $this->convert_old_block( $block ) );
				continue;
			}

			// Rare: icon-item alone.
			if ( 'beapi/icon-item' === $name ) {
				$new   = $this->build_blockparty_block( $block['attrs'] ?? [], [], (string) ( $block['innerHTML'] ?? '' ) );
				$out[] = $new ?? $block;
				continue;
			}

			// Nested blocks (group, columns…): migrate children, keep wrapper HTML.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block = $this->migrate_inner_blocks( $block );
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Migrate innerBlocks while preserving innerContent HTML chunks.
	 *
	 * innerContent looks like: [ '<div class="wp-block-group">', null, '', null, '</div>' ]
	 * Strings = wrappers to keep. null = slot for one inner block.
	 *
	 * @param array $block Parent block with children.
	 * @return array
	 * @author Jules Fell
	 */
	private function migrate_inner_blocks( array $block ): array {
		$new_inners  = [];
		$new_content = [];
		$inner_index = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			// Keep HTML wrappers as-is (opening/closing divs, separators…).
			if ( is_string( $chunk ) ) {
				$new_content[] = $chunk;
				continue;
			}

			// null placeholder → migrate that child (may become 1..n blocks).
			$child        = $block['innerBlocks'][ $inner_index ] ?? null;
			++$inner_index;
			$replacements = null === $child ? [] : $this->migrate_blocks( [ $child ] );

			foreach ( $replacements as $i => $replacement ) {
				if ( $i > 0 ) {
					// Extra siblings need an empty separator between null slots.
					$new_content[] = '';
				}
				$new_content[] = null;
				$new_inners[]  = $replacement;
			}
		}

		$block['innerBlocks']  = $new_inners;
		$block['innerContent'] = $new_content;

		return $block;
	}

	/**
	 * Convert one beapi/icon-block.
	 *
	 * Case A — v3: has beapi/icon-item children → one new icon per child.
	 * Case B — legacy / empty: convert the parent itself.
	 *
	 * @param array $block Old parent block.
	 * @return array New blocks (or the original block if conversion failed).
	 * @author Jules Fell
	 */
	private function convert_old_block( array $block ): array {
		$parent = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		$items  = array_values(
			array_filter(
				$block['innerBlocks'] ?? [],
				static fn( $inner ) => ( $inner['blockName'] ?? '' ) === 'beapi/icon-item'
			)
		);

		// Case A: v3 children.
		if ( $items ) {
			$converted = [];
			foreach ( $items as $item ) {
				$new = $this->build_blockparty_block(
					is_array( $item['attrs'] ?? null ) ? $item['attrs'] : [],
					$parent,
					(string) ( $item['innerHTML'] ?? '' )
				);
				if ( null === $new ) {
					++$this->skipped;
					return [ $block ]; // Keep original if one child fails.
				}
				$converted[] = $new;
			}

			return $converted;
		}

		// Case B: legacy single icon or empty shell.
		$new = $this->build_blockparty_block( $parent, [], (string) ( $block['innerHTML'] ?? '' ) );
		if ( null === $new ) {
			++$this->skipped;
			return [ $block ];
		}

		return [ $new ];
	}

	/**
	 * Build a blockparty/icon from old attributes (+ HTML hints if needed).
	 *
	 * Markup only: always writes blockparty/icon when a name can be inferred.
	 * Missing assets are recorded, not used to abort the conversion.
	 *
	 * @param array  $attrs  Source attrs (item or legacy parent).
	 * @param array  $parent Parent attrs (for className / collection).
	 * @param string $html   Saved HTML of the old block.
	 * @return array|null Null when an icon was expected but name or collection is missing.
	 * @author Jules Fell
	 */
	private function build_blockparty_block( array $attrs, array $parent, string $html ): ?array {
		$old_icon = is_array( $attrs['icon'] ?? null ) ? $attrs['icon'] : [];
		$name     = (string) ( $old_icon['name'] ?? '' );

		// Legacy HTML fallback: class="icon-xxx".
		if ( '' === $name && preg_match( '/\bicon-([a-z0-9_-]+)\b/i', $html, $m ) ) {
			$name = $m[1];
		}

		$expects_icon = ( '' !== $name || $old_icon || false !== strpos( $html, 'icon-container' ) );

		// Collection from attrs only (no registry lookup, no project-specific default).
		$collection = (string) ( $old_icon['collection'] ?? '' );
		if ( '' === $collection ) {
			$raw        = $attrs['collection'] ?? $parent['collection'] ?? null;
			$collection = is_array( $raw ) ? (string) ( $raw['name'] ?? '' ) : (string) $raw;
		}

		// Abort when we expected an icon but name or collection is missing.
		if ( $expects_icon && ( '' === $name || '' === $collection ) ) {
			return null;
		}

		$new = [];

		if ( '' !== $name ) {
			$type = (string) ( $old_icon['type'] ?? ( false !== strpos( $html, '<use' ) ? 'sprite' : 'raw' ) );

			$new['icon'] = [
				'collection' => $collection,
				'name'       => $name,
				'type'       => $type,
				'label'      => (string) ( $old_icon['label'] ?? $name ),
			];

			$this->record_missing_icon( $collection, $name );
		}

		// Color: map attrs only (keep inherit/currentColor as stored; renderer may sanitize).
		$color = $attrs['iconColorValue'] ?? $attrs['iconColor'] ?? $parent['iconColor'] ?? null;
		if ( is_array( $color ) ) {
			$color = $color['color'] ?? null;
		}
		if ( is_string( $color ) && '' !== $color ) {
			$new['iconColor'] = $color;
		}

		// Size (attr or CSS width in HTML).
		$size = $attrs['size'] ?? null;
		if ( null === $size && preg_match( '/width:\s*(\d+)px/i', $html, $m ) ) {
			$size = (int) $m[1];
		}
		if ( is_numeric( $size ) ) {
			$new['size'] = (int) $size;
		}

		// Border radius: old int → "Npx".
		if ( isset( $attrs['borderRadius'] ) && is_numeric( $attrs['borderRadius'] ) ) {
			$new['borderRadius'] = ( (int) $attrs['borderRadius'] ) . 'px';
		} elseif ( ! empty( $attrs['borderRadius'] ) && is_string( $attrs['borderRadius'] ) ) {
			$new['borderRadius'] = $attrs['borderRadius'];
		}

		if ( ! empty( $attrs['url'] ) && is_string( $attrs['url'] ) ) {
			$new['url'] = $attrs['url'];
		}
		if ( ! empty( $attrs['label'] ) && is_string( $attrs['label'] ) ) {
			$new['label'] = $attrs['label'];
		}

		$class = $attrs['className'] ?? $parent['className'] ?? '';
		if ( is_string( $class ) && '' !== $class ) {
			$new['className'] = $class;
		}

		// Keep extra attrs (ex: sharedBlockId), drop old icon-only keys.
		foreach ( array_merge( $parent, $attrs ) as $key => $value ) {
			if ( ! isset( $new[ $key ] ) && ! in_array(
				$key,
				[
					'icon',
					'iconColor',
					'iconColorValue',
					'iconBackgroundColor',
					'iconBackgroundColorValue',
					'backgroundColor',
					'collection',
					'maxIcons',
					'size',
					'borderRadius',
					'padding',
					'url',
					'label',
					'rel',
					'text',
					'content',
					'className',
				],
				true
			) ) {
				$new[ $key ] = $value;
			}
		}

		++$this->migrated;

		return [
			'blockName'    => 'blockparty/icon',
			'attrs'        => $new,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Record a migrated icon that is not available in registered collections.
	 *
	 * @param string $collection Collection name.
	 * @param string $name       Icon name.
	 * @return void
	 * @author Jules Fell
	 */
	private function record_missing_icon( string $collection, string $name ): void {
		if ( '' === $collection || '' === $name ) {
			return;
		}

		if ( ! function_exists( '\\Blockparty\\Icons\\get_icon_collection' ) ) {
			return;
		}

		$col = \Blockparty\Icons\get_icon_collection( $collection );
		if ( $col && $col->get( $name ) ) {
			return;
		}

		$key = $collection . '/' . $name;
		$this->missing_icons[ $key ] = ( $this->missing_icons[ $key ] ?? 0 ) + 1;
	}
}
