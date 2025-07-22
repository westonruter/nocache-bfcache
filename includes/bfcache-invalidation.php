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

/**
 * Sends the Clear-Site-Data header with the 'cache' directive when logging out.
 *
 * This only works in Chrome, and it only works in a secure context (HTTPS).
 *
 * @since n.e.x.t
 * @access private
 */
function send_clear_site_data_upon_logout(): void {
	if ( ! headers_sent() ) {
		header( 'Clear-Site-Data: "cache"' );
	}
}
add_action( 'wp_logout', __NAMESPACE__ . '\send_clear_site_data_upon_logout' ); // TODO: Would the clear_auth_cookie action be better?
