<?php

namespace Blockparty\Icons;

use Blockparty\Icons\Icon\CollectionItem;

class BlockRenderer {

	/**
	 * Render icon block.
	 *
	 * @param array $attributes
	 * @param string $content
	 * @param \WP_Block $block
	 *
	 * @return string
	 */
	public static function render( $attributes ) {
		// Block attributes
		$icon_data  = $attributes['icon'] ?? [];
		$radius     = (string) ( $attributes['borderRadius'] ?? '0px' );
		$link_url   = (string) ( $attributes['url'] ?? '' );
		$link_label = (string) ( $attributes['label'] ?? '' );

		$collection_name = $icon_data['collection'] ?? null;
		$icon_name       = $icon_data['name'] ?? null;
		if ( empty( $collection_name ) || empty( $icon_name ) ) {
			return '<!-- missing collection or icon -->';
		}

		$icon = self::get_collection_item( $collection_name, $icon_name );
		if ( null === $icon ) {
			return '<!-- failed to load icon -->';
		}

		switch ( $icon->type() ) {
			case 'sprite':
				$icon_html = self::get_icon_sprite_html( $icon, $attributes );
				break;
			default:
				$icon_html = self::get_icon_raw_html( $icon, $attributes );
				break;
		}

		$html = '';

		// Start block wrapper
		$html .= sprintf(
			'<div %s>',
			get_block_wrapper_attributes( [ 'style' => sprintf( 'border-radius: %s;', $radius ) ] )
		);

		// Start block link
		if ( ! empty( $link_url ) ) {
			$html .= sprintf(
				'<a class="wp-block-blockparty-icon__link" href="%s"%s>',
				esc_url( $link_url ),
				! empty( $link_label ) ? sprintf( ' aria-label="%s"', esc_attr( $link_label ) ) : ''
			);
		}

		// Block icon
		$html .= sprintf(
			'<span class="wp-block-blockparty-icon__icon-container">%s</span>',
			$icon_html
		);

		// End block link
		if ( ! empty( $link_url ) ) {
			$html .= '</a>';
		}

		$html .= '</div>';

		return $html;
	}

	private static function get_collection_item( string $collection_name, string $icon_name ): ?CollectionItem {
		$collection = get_icon_collection( $collection_name );
		if ( ! $collection ) {
			return null;
		}

		return $collection->get( $icon_name );
	}

	private static function get_icon_raw_html( CollectionItem $icon, array $attributes ): string {
		$html_attributes = self::prepare_svg_html_attributes( $icon, $attributes );
		$html_content    = $icon->content();

		$processor = new \WP_HTML_Tag_Processor( $html_content );
		if ( $processor->next_tag( 'svg' ) ) {
			foreach ( $html_attributes as $attr => $value ) {
				if ( 'class' === $attr ) {
					array_map( [ $processor, 'add_class' ], $value );
					continue;
				}

				$processor->set_attribute( $attr, $value );
			}
		}

		return $processor->get_updated_html();
	}

	private static function get_icon_sprite_html( CollectionItem $icon, array $attributes ): string {
		$html_attributes = '';
		foreach ( self::prepare_svg_html_attributes( $icon, $attributes ) as $attr => $value ) {
			if ( 'class' === $attr ) {
				$html_attributes .= sprintf( ' class="%s"', esc_attr( implode( ' ', $value ) ) );
				continue;
			}

			$html_attributes .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" %s><use href="%s"></use></svg>',
			$html_attributes,
			esc_url( $icon->content() )
		);
	}

	/**
	 * @param CollectionItem $icon
	 * @param array $attributes
	 *
	 * @return array{class: array, style: string, fill: string}
	 */
	private static function prepare_svg_html_attributes( CollectionItem $icon, array $attributes ): array {
		$size       = (int) ( $attributes['size'] ?? 24 );
		$icon_color = (string) ( $attributes['iconColor'] ?? '' );

		$html_attributes = [
			'class' => [],
			'style' => '',
			'fill'  => '',
		];

		// CSS classes
		$css_classes              = [
			'wp-block-blockparty-icons__icon-component',
			sprintf( 'wp-block-blockparty-icons__icon-component--%s', $icon->type() ),
			$icon->name(),
		];
		$html_attributes['class'] = array_map( 'sanitize_html_class', $css_classes );

		// CSS style
		$css_style           = [];
		$css_style['width']  = sprintf( '%spx', $size );
		$css_style['height'] = sprintf( '%spx', $size );

		$sanitized_icon_color = self::sanitize_icon_color( $icon_color );
		if ( '' !== $sanitized_icon_color ) {
			$css_style['fill']       = $sanitized_icon_color;
			$html_attributes['fill'] = $sanitized_icon_color;
		}

		foreach ( $css_style as $property => $value ) {
			$html_attributes['style'] .= sprintf( '%s: %s;', $property, $value );
		}

		return $html_attributes;
	}

	/**
	 * Sanitize icon color value (hex, theme preset, or CSS custom property).
	 *
	 * @param string $color Raw color value from block attributes.
	 *
	 * @return string Sanitized color, or empty string when invalid.
	 */
	private static function sanitize_icon_color( string $color ): string {
		$color = trim( $color );

		if ( '' === $color ) {
			return '';
		}

		$hex_color = sanitize_hex_color( $color );
		if ( $hex_color ) {
			return $hex_color;
		}

		// Block editor theme preset format (e.g. var:preset|color|primary).
		if ( preg_match( '/^var:preset\|color\|([a-z0-9_-]+)$/i', $color, $matches ) ) {
			return sprintf(
				'var(--wp--preset--color--%s)',
				sanitize_key( $matches[1] )
			);
		}

		// CSS custom property with optional hex fallback (e.g. var(--wp--preset--color--primary, #000)).
		if ( preg_match( '/^var\(\s*(--[a-zA-Z0-9_-]+)\s*,\s*(#[0-9a-fA-F]{3,8})\s*\)$/', $color, $matches ) ) {
			$fallback = sanitize_hex_color( $matches[2] );

			return $fallback ? sprintf( 'var(%s, %s)', $matches[1], $fallback ) : '';
		}

		// CSS custom property (e.g. var(--wp--preset--color--primary)).
		if ( preg_match( '/^var\(\s*--[a-zA-Z0-9_-]+\s*\)$/', $color ) ) {
			return $color;
		}

		return '';
	}
}
