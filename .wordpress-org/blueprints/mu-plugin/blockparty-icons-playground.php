<?php
/**
 * Playground demo: register the WordPress (@wordpress/icons) SVG library.
 *
 * Icon files live in .wordpress-org/blueprints/icons/gutenberg/ (from the Gutenberg icon library).
 *
 * @package Blockparty\Icons
 */

add_action(
	'blockparty_icons_init',
	static function () {
		if ( ! function_exists( 'Blockparty\\Icons\\register_icon_collection' ) ) {
			return;
		}

		$source = WP_PLUGIN_DIR . '/blockparty-icons/.wordpress-org/blueprints/icons/gutenberg';
		if ( ! is_dir( $source ) || ! is_readable( $source ) ) {
			return;
		}

		\Blockparty\Icons\register_icon_collection(
			'gutenberg-icons',
			[
				'label'  => 'WordPress icons',
				'type'   => 'folder',
				'source' => $source,
			]
		);
	}
);
