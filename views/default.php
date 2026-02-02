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

$icon      = $block_attributes['icon'];
$icon_type = $icon['type'];

if ( empty( $icon_type ) ) {
	return;
}

$url             = $block_attributes['url'] ?? '';
$link_aria_label = $block_attributes['label'] ?? '';
$text            = $block_attributes['text'] ?? '';
$radius          = $block_attributes['borderRadius'] ?? 0;

if ( ! empty( $link_aria_label ) && ! empty( $url ) ) {
	$link_aria_label = 'aria-label="' . $link_aria_label . '"';
}

$has_link_aria_label = ! empty( $link_aria_label );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( [ 'style' => 'border-radius: ' . $radius . '%;' ] ) ); ?>>
	<?php if ( ! empty( $url ) ) : ?>
	<a class="wp-block-blockparty-icon__link" href="<?php echo esc_url( $url ); ?>" <?php echo wp_kses_data( $has_link_aria_label ? $link_aria_label : '' ); ?>>
	<?php endif; ?>
		<?php
		if ( 'sprite' === $icon_type ) {
			load_template( plugin_dir_path( __FILE__ ) . 'icon-sprite.php', false, $args );
		} else {
			load_template( plugin_dir_path( __FILE__ ) . 'icon-raw.php', false, $args );
		}

		if ( ! empty( $text ) ) :
			?>
		<p class="wp-block-blockparty-icon__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>
	<?php if ( ! empty( $url ) ) : ?>
	</a>
	<?php endif; ?>
</div>
