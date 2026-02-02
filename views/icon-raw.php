<?php
/**
 * Icon Raw template
 *
 * Displays inline SVG content with accessibility and style attributes.
 *
 * @var array $args {
 *      @type \WP_Block $block
 *      @type array $block_attributes
 *      @type bool $is_preview
 * }
 */

$block_attributes = $args['block_attributes'];
$size             = $block_attributes['size'] ?? 24;
$content          = $block_attributes['content'] ?? '';
$icon             = $block_attributes['icon'] ?? [];
$icon_color       = $block_attributes['iconColorValue'] ?? '';
$icon_style       = '';
$extra_attrs      = '';

if ( ! empty( $icon_color ) ) {
	$icon_style  .= 'fill:' . $icon_color . ';';
	$extra_attrs .= ' fill="' . esc_attr( $icon_color ) . '"';
}

if ( ! empty( $size ) ) {
	$icon_style .= 'width:' . (string) $size . 'px;height:' . (string) $size . 'px;';
}

if ( empty( $content ) || ! is_string( $content ) ) {
	return;
}

$allowed_svg = \Blockparty\Icons\Helper\SvgKses::get_allowed_svg_kses();
$content_safe = wp_kses( $content, $allowed_svg );

// Merge our class with existing class on the opening <svg> to avoid duplicate attribute.
$block_class = 'wp-block-blockparty-icons__icon-component wp-block-blockparty-icons__icon-component--raw';
if ( ! empty( $icon['name'] ) ) {
	$block_class .= ' ' . $icon['name'];
}
if ( preg_match( '/<svg\s[^>]*\bclass=(["\'])([^"\']*)\1/', $content_safe, $class_match ) ) {
	$content_safe = preg_replace( '/<svg\s([^>]*)\bclass=(["\'])([^"\']*)\2/', '<svg $1class=$2' . esc_attr( $class_match[2] . ' ' . $block_class ) . '$2', $content_safe, 1 );
} else {
	$block_class = ' class="' . esc_attr( $block_class ) . '"';
}

// Inject aria-hidden, focusable, style (and class if not already present) into the opening <svg> tag.
if ( ! empty( $icon_style ) ) {
	$extra_attrs .= ' style="' . esc_attr( $icon_style ) . '"';
}

if ( is_string( $block_class ) && strpos( $block_class, '=' ) !== false ) {
	$extra_attrs .= $block_class;
}
$content_safe = preg_replace( '/<svg\s/', '<svg' . $extra_attrs . ' ', $content_safe, 1 );

echo wp_kses( $content_safe, $allowed_svg );
