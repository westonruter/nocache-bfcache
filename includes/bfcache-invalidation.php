<?php
/**
 * Invalidating pages from bfcache.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

/**
 * Enqueues script modules to invalidate bfcache.
 *
 * These script modules are only enqueued when the user is logged in and had opted in to bfcache via electing to
 * "Remember Me" and having JavaScript enabled.
 *
 * @since 1.0.0
 * @access private
 */
function enqueue_bfcache_invalidation_script_modules(): void {
	if ( null === get_user_bfcache_session_token() ) {
		return;
	}

	wp_enqueue_script_module( BFCACHE_INVALIDATION_SCRIPT_MODULE_ID );
	export_script_module_data(
		BFCACHE_INVALIDATION_SCRIPT_MODULE_ID,
		array(
			'cookieName' => get_bfcache_session_token_cookie_name(),
			'debug'      => WP_DEBUG,
		)
	);
}

foreach ( array( 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'customize_controls_enqueue_scripts' ) as $_action ) {
	add_action( $_action, __NAMESPACE__ . '\enqueue_bfcache_invalidation_script_modules' );
}
