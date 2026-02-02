<?php
/**
 * Icon Item Sprite template
 *
 * @var array $args {
 *      @type \WP_Block $block
 *      @type array $block_attributes
 *      @type bool $is_preview
 * }
 */

$block_attributes = $args['block_attributes'];
$size             = $block_attributes['size'] ?? 24;
$content          = $block_attributes['content'];
$icon             = $block_attributes['icon'];
$icon_color       = $block_attributes['iconColorValue'] ?? '';
$icon_style       = '';

if ( ! empty( $icon_color ) ) {
	$icon_style .= 'fill:' . $icon_color . ';';
}

if ( ! empty( $size ) ) {
	$icon_style .= 'width:' . (string) $size . 'px;height:' . (string) $size . 'px;';
}

$has_icon_style = ! empty( $icon_style );

// Add cache-busting hash to sprite URL when sprite-hashes.json exists.
$sprite_href = \Blockparty\Icons\get_sprite_url_with_hash( $content );
?>
<svg
	aria-hidden="true"
	focusable="false"
	version="1.1"
	xmlns="http://www.w3.org/2000/svg"
	<?php echo $has_icon_style ? wp_kses_data( sprintf( 'style="%s"', $icon_style ) ) : ''; ?>
	class="wp-block-blockparty-icons__icon-component wp-block-blockparty-icons__icon-component--sprite <?php echo esc_attr( $icon['name'] ); ?>"
>
	<use href="<?php echo esc_url( $sprite_href ); ?>"></use>
</svg>
