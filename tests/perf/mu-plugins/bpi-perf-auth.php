<?php
/**
 * Plugin Name: Blockparty Icons — Perf Auth Shim
 * Description: Test-only authentication bypass so the bench runner can hit editor and REST endpoints. NEVER ship this.
 *
 * The icons REST endpoints require `edit_posts`, and the block editor requires a
 * logged-in user. Rather than juggling cookies or application passwords in the bench
 * script, this shim authenticates a request that presents the shared token.
 *
 * The token comes from the BPI_PERF_TOKEN constant, set in .wp-env.perf.json. With no
 * constant defined the shim is inert, so a stray copy of this file cannot open anything up.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'determine_current_user',
	function ( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		if ( ! defined( 'BPI_PERF_TOKEN' ) ) {
			return $user_id;
		}

		$expected = (string) constant( 'BPI_PERF_TOKEN' );
		if ( '' === $expected ) {
			return $user_id;
		}

		$provided = '';
		if ( isset( $_SERVER['HTTP_X_BPI_PERF_TOKEN'] ) ) {
			$provided = (string) $_SERVER['HTTP_X_BPI_PERF_TOKEN'];
		}

		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return $user_id;
		}

		$admin = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'ASC',
			]
		);

		return empty( $admin ) ? $user_id : (int) $admin[0];
	},
	20
);

/**
 * The REST cookie check fails without a nonce even when the user is resolved.
 * Accept the resolved user for token-authenticated requests.
 */
add_filter(
	'rest_authentication_errors',
	function ( $result ) {
		if ( ! empty( $_SERVER['HTTP_X_BPI_PERF_TOKEN'] ) && is_user_logged_in() ) {
			return true;
		}

		return $result;
	},
	20
);
