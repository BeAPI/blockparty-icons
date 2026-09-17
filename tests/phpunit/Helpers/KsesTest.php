<?php
/**
 * The KSES allowances that keep saved block markup intact.
 *
 * These matter beyond tidiness: if KSES strips an attribute from post content, the
 * saved markup stops matching what the block's save() produced and the editor
 * reports a block validation error.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Helpers;

use Blockparty\Icons\Helpers\SvgKses;
use Blockparty\Icons\Tests\TestCase;

class KsesTest extends TestCase {

	/* ------------------------------------------------- wp_kses_allowed_html */

	private function allowed_post_tags(): array {
		return wp_kses_allowed_html( 'post' );
	}

	public function test_svg_is_allowed_in_post_content(): void {
		$tags = $this->allowed_post_tags();

		$this->assertArrayHasKey( 'svg', $tags );
	}

	/**
	 * @dataProvider provide_required_svg_attributes
	 */
	public function test_required_svg_attributes_are_allowed( string $attribute ): void {
		$tags = $this->allowed_post_tags();

		$this->assertArrayHasKey( $attribute, $tags['svg'] );
	}

	public function provide_required_svg_attributes(): array {
		return array_map(
			static fn( $a ) => [ $a ],
			[ 'aria-hidden', 'class', 'fill', 'focusable', 'height', 'style', 'viewbox', 'width', 'version', 'xmlns' ]
		);
	}

	public function test_path_and_its_geometry_are_allowed(): void {
		$tags = $this->allowed_post_tags();

		$this->assertArrayHasKey( 'path', $tags );
		$this->assertArrayHasKey( 'd', $tags['path'] );
		$this->assertArrayHasKey( 'fill', $tags['path'] );
		$this->assertArrayHasKey( 'transform', $tags['path'] );
	}

	public function test_use_and_its_references_are_allowed(): void {
		$tags = $this->allowed_post_tags();

		$this->assertArrayHasKey( 'use', $tags );
		$this->assertArrayHasKey( 'href', $tags['use'] );
		$this->assertArrayHasKey( 'xlink:href', $tags['use'] );
	}

	public function test_other_contexts_are_left_alone(): void {
		$this->assertArrayNotHasKey(
			'svg',
			wp_kses_allowed_html( 'strip' ),
			'The allowance is scoped to post content.'
		);
	}

	public function test_a_rendered_icon_survives_kses(): void {
		$html = '<svg class="ico" style="width:24px;height:24px;" fill="#ff0000" ' .
			'xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" aria-hidden="true" ' .
			'focusable="false"><path d="M0 0h24v24H0z" fill="#ff0000"/></svg>';

		$filtered = wp_kses_post( $html );

		$this->assertStringContainsString( '<svg', $filtered );
		$this->assertStringContainsString( 'd="M0 0h24v24H0z"', $filtered );
		$this->assertStringContainsString( 'viewbox="0 0 24 24"', $filtered );
		$this->assertStringContainsString( 'aria-hidden="true"', $filtered );
	}

	public function test_a_sprite_reference_survives_kses(): void {
		$html = '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' .
			'<use href="https://example.org/sprite.svg#star"></use></svg>';

		$filtered = wp_kses_post( $html );

		$this->assertStringContainsString( 'href="https://example.org/sprite.svg#star"', $filtered );
	}

	public function test_a_script_inside_an_svg_is_still_stripped(): void {
		$html = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>';

		$filtered = wp_kses_post( $html );

		$this->assertStringNotContainsString( '<script', $filtered );
		$this->assertStringContainsString( 'd="M0 0"', $filtered );
	}

	/* ------------------------------------------------------- safe_style_css */

	public function test_display_is_an_allowed_style_property(): void {
		$this->assertContains( 'display', apply_filters( 'safe_style_css', [] ) );
	}

	public function test_a_display_declaration_survives_kses(): void {
		$filtered = wp_kses_post( '<svg style="display:block;" xmlns="http://www.w3.org/2000/svg"></svg>' );

		$this->assertStringContainsString( 'display:block', $filtered );
	}

	/* ------------------------------------------------------------- SvgKses */

	public function test_svg_kses_lists_the_root_and_basic_shapes(): void {
		$allowed = SvgKses::get_allowed_svg_kses();

		foreach ( [ 'svg', 'path', 'circle', 'ellipse', 'line', 'rect', 'polygon', 'polyline' ] as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed );
		}
	}

	public function test_svg_kses_lists_structure_gradients_and_masks(): void {
		$allowed = SvgKses::get_allowed_svg_kses();

		foreach ( [ 'g', 'defs', 'symbol', 'use', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask' ] as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed );
		}
	}

	/**
	 * @dataProvider provide_dangerous_svg_elements
	 */
	public function test_svg_kses_excludes_dangerous_elements( string $tag ): void {
		$this->assertArrayNotHasKey( $tag, SvgKses::get_allowed_svg_kses() );
	}

	public function provide_dangerous_svg_elements(): array {
		return array_map(
			static fn( $t ) => [ $t ],
			[ 'script', 'foreignObject', 'iframe', 'image', 'animate', 'set', 'handler' ]
		);
	}

	public function test_svg_kses_allows_no_event_handlers(): void {
		foreach ( SvgKses::get_allowed_svg_kses() as $tag => $attributes ) {
			foreach ( array_keys( $attributes ) as $attribute ) {
				$this->assertStringStartsNotWith(
					'on',
					strtolower( (string) $attribute ),
					"Tag <{$tag}> must not allow the event attribute {$attribute}."
				);
			}
		}
	}

	public function test_svg_kses_is_filterable(): void {
		add_filter(
			'blockparty_icons_allowed_svg_kses',
			static function ( array $allowed ) {
				$allowed['marker'] = [ 'id' => true ];

				return $allowed;
			}
		);

		$this->assertArrayHasKey( 'marker', SvgKses::get_allowed_svg_kses() );
	}
}
