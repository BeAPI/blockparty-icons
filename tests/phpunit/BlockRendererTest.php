<?php
/**
 * Front-end markup produced by the icon block.
 *
 * This is the contract a visitor sees, and the one most worth protecting: it is
 * the only place an icon's payload reaches a page.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests;

use function Blockparty\Icons\register_icon_collection;

class BlockRendererTest extends TestCase {

	private string $folder;
	private string $sprite;

	public function set_up() {
		parent::set_up();

		$this->folder = $this->make_icon_folder(
			[
				'alpha.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1"/></svg>',
			]
		);
		$this->sprite = $this->make_file( 'sprite.svg', $this->sprite( [ 'ico-star' ] ) );

		register_icon_collection(
			'raw-icons',
			[
				'type'   => 'folder',
				'source' => $this->folder,
			]
		);
		register_icon_collection(
			'sprite-icons',
			[
				'type'   => 'sprite',
				'source' => $this->sprite,
			]
		);
	}

	/**
	 * Render the block the way WordPress does.
	 *
	 * Going through render_block() rather than calling BlockRenderer::render()
	 * directly matters: the renderer calls get_block_wrapper_attributes(), which
	 * reads WP_Block_Supports::$block_to_render. Outside a real render pass that is
	 * null, and PHP 8.4 warns on it — a bug in the test, not the plugin.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	private function render( array $attributes ): string {
		return render_block(
			[
				'blockName'    => 'blockparty/icon',
				'attrs'        => $attributes,
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			]
		);
	}

	/**
	 * @param string $collection
	 * @param string $name
	 *
	 * @return array
	 */
	private function icon( string $collection = 'raw-icons', string $name = 'alpha' ): array {
		return [
			'collection' => $collection,
			'name'       => $name,
		];
	}

	/* ------------------------------------------------------- missing input */

	public function test_no_icon_attribute_renders_a_comment(): void {
		$this->assertSame( '<!-- missing collection or icon -->', $this->render( [] ) );
	}

	public function test_missing_collection_renders_a_comment(): void {
		$html = $this->render( [ 'icon' => [ 'name' => 'alpha' ] ] );

		$this->assertSame( '<!-- missing collection or icon -->', $html );
	}

	public function test_missing_icon_name_renders_a_comment(): void {
		$html = $this->render( [ 'icon' => [ 'collection' => 'raw-icons' ] ] );

		$this->assertSame( '<!-- missing collection or icon -->', $html );
	}

	public function test_unknown_collection_renders_a_comment(): void {
		$html = $this->render( [ 'icon' => $this->icon( 'nope', 'alpha' ) ] );

		$this->assertSame( '<!-- failed to load icon -->', $html );
	}

	public function test_unknown_icon_renders_a_comment(): void {
		$html = $this->render( [ 'icon' => $this->icon( 'raw-icons', 'nope' ) ] );

		$this->assertSame( '<!-- failed to load icon -->', $html );
	}

	/* ----------------------------------------------------------- raw icons */

	public function test_raw_icon_inlines_the_svg(): void {
		$html = $this->render( [ 'icon' => $this->icon() ] );

		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( '<path d="M1 1"', $html, 'The file contents reach the page.' );
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $html );
	}

	public function test_raw_icon_carries_component_classes(): void {
		$html = $this->render( [ 'icon' => $this->icon() ] );

		$this->assertStringContainsString( 'wp-block-blockparty-icons__icon-component', $html );
		$this->assertStringContainsString( 'wp-block-blockparty-icons__icon-component--raw', $html );
		$this->assertStringContainsString( 'alpha', $html, 'The icon name is used as a class.' );
	}

	public function test_raw_icon_is_wrapped_in_the_block_and_a_container_span(): void {
		$html = $this->render( [ 'icon' => $this->icon() ] );

		$this->assertStringContainsString( 'wp-block-blockparty-icon__icon-container', $html );
		$this->assertStringStartsWith( '<div ', $html );
		$this->assertStringEndsWith( '</div>', $html );
	}

	public function test_size_becomes_width_and_height(): void {
		$html = $this->render(
			[
				'icon' => $this->icon(),
				'size' => 64,
			]
		);

		$this->assertStringContainsString( 'width:64px', $html );
		$this->assertStringContainsString( 'height:64px', $html );
	}

	public function test_size_defaults_to_24(): void {
		$html = $this->render( [ 'icon' => $this->icon() ] );

		$this->assertStringContainsString( 'width:24px', $html );
		$this->assertStringContainsString( 'height:24px', $html );
	}

	public function test_border_radius_is_applied_to_the_wrapper(): void {
		$html = $this->render(
			[
				'icon'         => $this->icon(),
				'borderRadius' => '12px',
			]
		);

		$this->assertStringContainsString( 'border-radius: 12px', $html );
	}

	/* -------------------------------------------------------- sprite icons */

	public function test_sprite_icon_references_the_symbol_by_url(): void {
		$html = $this->render( [ 'icon' => $this->icon( 'sprite-icons', 'ico-star' ) ] );

		$this->assertStringContainsString( '<use href="', $html );
		$this->assertStringContainsString( '#ico-star', $html );
		$this->assertStringNotContainsString(
			'<path',
			$html,
			'A sprite icon is referenced, never inlined.'
		);
	}

	public function test_sprite_icon_carries_its_own_modifier_class(): void {
		$html = $this->render( [ 'icon' => $this->icon( 'sprite-icons', 'ico-star' ) ] );

		$this->assertStringContainsString( 'wp-block-blockparty-icons__icon-component--sprite', $html );
	}

	public function test_sprite_icon_is_hidden_from_assistive_technology(): void {
		$html = $this->render( [ 'icon' => $this->icon( 'sprite-icons', 'ico-star' ) ] );

		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'focusable="false"', $html );
	}

	/* ---------------------------------------------------------------- link */

	public function test_url_wraps_the_icon_in_a_link(): void {
		$html = $this->render(
			[
				'icon' => $this->icon(),
				'url'  => 'https://example.org/somewhere',
			]
		);

		$this->assertStringContainsString( '<a class="wp-block-blockparty-icon__link"', $html );
		$this->assertStringContainsString( 'href="https://example.org/somewhere"', $html );
		$this->assertStringContainsString( '</a>', $html );
	}

	public function test_label_becomes_the_link_aria_label(): void {
		$html = $this->render(
			[
				'icon'  => $this->icon(),
				'url'   => 'https://example.org',
				'label' => 'Go somewhere',
			]
		);

		$this->assertStringContainsString( 'aria-label="Go somewhere"', $html );
	}

	public function test_label_without_a_url_produces_no_link(): void {
		$html = $this->render(
			[
				'icon'  => $this->icon(),
				'label' => 'Orphan label',
			]
		);

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( 'aria-label', $html );
	}

	public function test_link_without_a_label_has_no_aria_label(): void {
		$html = $this->render(
			[
				'icon' => $this->icon(),
				'url'  => 'https://example.org',
			]
		);

		$this->assertStringContainsString( '<a ', $html );
		$this->assertStringNotContainsString( 'aria-label', $html );
	}

	/* --------------------------------------------------------------- color */

	/**
	 * @dataProvider provide_accepted_colors
	 */
	public function test_accepted_icon_colors( string $input, string $expected ): void {
		$html = $this->render(
			[
				'icon'      => $this->icon(),
				'iconColor' => $input,
			]
		);

		$this->assertStringContainsString( 'color:' . $expected, $html );
		$this->assertStringContainsString( 'fill:' . $expected, $html );
	}

	public function provide_accepted_colors(): array {
		return [
			'hex'                  => [ '#ff0000', '#ff0000' ],
			'short hex'            => [ '#f00', '#f00' ],
			'editor preset'        => [ 'var:preset|color|primary', 'var(--wp--preset--color--primary)' ],
			'css variable'         => [ 'var(--brand-accent)', 'var(--brand-accent)' ],
			'variable w/ fallback' => [ 'var(--brand-accent, #123456)', 'var(--brand-accent, #123456)' ],
		];
	}

	/**
	 * @dataProvider provide_rejected_colors
	 */
	public function test_rejected_icon_colors( string $input ): void {
		$html = $this->render(
			[
				'icon'      => $this->icon(),
				'iconColor' => $input,
			]
		);

		$this->assertStringNotContainsString( 'color:', $html );
		$this->assertStringNotContainsString( 'fill:', $html );
	}

	public function provide_rejected_colors(): array {
		return [
			'empty'          => [ '' ],
			'named color'    => [ 'red' ],
			'rgb function'   => [ 'rgb(255, 0, 0)' ],
			'javascript url' => [ 'javascript:alert(1)' ],
			'expression'     => [ 'expression(alert(1))' ],
			'quote break'    => [ '#fff;" onload="alert(1)' ],
		];
	}

	public function test_no_color_leaves_the_svg_fill_untouched(): void {
		$html = $this->render( [ 'icon' => $this->icon() ] );

		$this->assertStringNotContainsString( 'color:', $html );
	}

	/* ------------------------------------------------------------ escaping */

	public function test_a_malicious_url_is_escaped(): void {
		$html = $this->render(
			[
				'icon' => $this->icon(),
				'url'  => 'javascript:alert(1)',
			]
		);

		$this->assertStringNotContainsString( 'href="javascript:alert(1)"', $html );
	}

	public function test_a_malicious_label_is_escaped(): void {
		$html = $this->render(
			[
				'icon'  => $this->icon(),
				'url'   => 'https://example.org',
				'label' => '" onmouseover="alert(1)',
			]
		);

		$this->assertStringNotContainsString( 'onmouseover="alert(1)"', $html );
	}
}
