<?php
/**
 * Icon Item template
 *
 * @var array $args {
 *      @type \WP_Block $block
 *      @type array $block_attributes
 *      @type bool $is_preview
 * }
 */

$block_attributes = $args['block_attributes'];

if ( ! isset( $block_attributes['icon'] ) ) {
	return;
}

$icon             = $block_attributes['icon'];
$icon_type        = $icon['type'];

if ( empty( $icon_type ) ) {
	return;
}

$url              = $block_attributes['url'] ?? '';
$link_aria_label  = $block_attributes['label'] ?? '';
$text             = $block_attributes['text'] ?? '';

if ( ! empty( $link_aria_label ) && ! empty( $url ) ) {
	$link_aria_label = 'aria-label="' . $link_aria_label . '"';
}

$has_link_aria_label = ! empty( $link_aria_label );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php if ( ! empty( $url ) ) : ?>
	<a href="<?php echo esc_url( $url ); ?>" <?php echo wp_kses_data( $has_link_aria_label ? $link_aria_label : '' ); ?>>
	<?php endif; ?>
		<?php
		if ( 'raw' === $icon_type ) {
			load_template( plugin_dir_path( __FILE__ ) . 'icon-item-raw.php', false, $args );
		}

		if ( 'sprite' === $icon_type ) {
			load_template( plugin_dir_path( __FILE__ ) . 'icon-item-sprite.php', false, $args );
		}
		?>
	<?php if ( ! empty( $url ) ) : ?>
	</a>
	<?php endif; ?>
	<?php if ( ! empty( $text ) ) : ?>
	<p class="wp-block-blockparty-icon__text"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
</div>
