<?php
/**
 * SVG allowed elements and attributes for wp_kses.
 *
 * Based on MDN SVG Element reference: https://developer.mozilla.org/en-US/docs/Web/SVG/Element
 * Excludes elements that can execute script or embed untrusted content (script, foreignObject, etc.).
 *
 * @package Blockparty\Icons
 */

namespace Blockparty\Icons\Helper;

/**
 * Helper for SVG sanitization with wp_kses.
 *
 * @since 1.0.0
 */
class SvgKses {

	/**
	 * Allowed SVG elements and attributes for wp_kses (raw inline SVG).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, bool>> Map of tag name => [ attribute => true ].
	 */
	public static function get_allowed_svg_kses() {
		$allowed = self::get_default_allowed_svg_kses();

		return apply_filters( 'blockparty_icons_allowed_svg_kses', $allowed );
	}

	/**
	 * Default allowed SVG elements and attributes (before filters).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, bool>> Map of tag name => [ attribute => true ].
	 */
	private static function get_default_allowed_svg_kses() {
		$common_presentation = [
			'class'     => true,
			'style'     => true,
			'fill'      => true,
			'stroke'    => true,
			'opacity'   => true,
			'transform' => true,
		];

		$common_core = array_merge(
			$common_presentation,
			[
				'id' => true,
			]
		);

		return [
			// Root / container (MDN: svg)
			'svg'            => array_merge(
				$common_core,
				[
					'xmlns'       => true,
					'xmlns:xlink' => true,
					'width'       => true,
					'height'      => true,
					'viewbox'     => true,
					'viewBox'     => true,
					'aria-hidden' => true,
					'focusable'   => true,
					'role'        => true,
				]
			),
			// Basic shapes (MDN: circle, ellipse, line, path, polygon, polyline, rect)
			'path'           => array_merge( $common_presentation, [ 'd' => true ] ),
			'circle'         => array_merge(
				$common_presentation,
				[
					'cx' => true,
					'cy' => true,
					'r'  => true,
				]
			),
			'ellipse'        => array_merge(
				$common_presentation,
				[
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				]
			),
			'line'           => array_merge(
				$common_presentation,
				[
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				]
			),
			'rect'           => array_merge(
				$common_presentation,
				[
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				]
			),
			'polygon'        => array_merge( $common_presentation, [ 'points' => true ] ),
			'polyline'       => array_merge( $common_presentation, [ 'points' => true ] ),
			// Container / structure (MDN: defs, g, symbol, use)
			'g'              => [
				'class'     => true,
				'id'        => true,
				'transform' => true,
				'fill'      => true,
				'stroke'    => true,
			],
			'defs'           => [],
			'symbol'         => [
				'id'      => true,
				'viewbox' => true,
				'viewBox' => true,
				'class'   => true,
			],
			'use'            => [
				'href'       => true,
				'xlink:href' => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
				'class'      => true,
			],
			// Descriptive (MDN: title, desc) – safe text content
			'title'          => [],
			'desc'           => [],
			// Gradient (MDN: linearGradient, radialGradient, stop)
			'linearGradient' => [
				'id'                => true,
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
				'gradientUnits'     => true,
				'gradientTransform' => true,
			],
			'radialGradient' => [
				'id'            => true,
				'cx'            => true,
				'cy'            => true,
				'r'             => true,
				'fx'            => true,
				'fy'            => true,
				'gradientUnits' => true,
			],
			'stop'           => [
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			],
			// Clip / mask (MDN: clipPath, mask)
			'clipPath'       => [
				'id'            => true,
				'clipPathUnits' => true,
				'transform'     => true,
			],
			'mask'           => [
				'id'     => true,
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
			],
			// Link (MDN: a)
			'a'              => [
				'href'       => true,
				'xlink:href' => true,
				'target'     => true,
				'rel'        => true,
				'class'      => true,
				'transform'  => true,
			],
		];
	}
}
