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
 * Broadcast channel name for when an unauthenticated user lands on the login screen.
 *
 * This also applies to the interim login (auth check) modal.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const LOGIN_BROADCAST_CHANNEL_NAME = 'nocache_bfcache_login';

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

	wp_enqueue_script_module( Script_Module_Ids::BFCACHE_INVALIDATION_VIA_PAGESHOW );
	export_script_module_data(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_PAGESHOW,
		array(
			'cookieName'                => get_bfcache_session_token_cookie_name(),
			'loginBroadcastChannelName' => LOGIN_BROADCAST_CHANNEL_NAME,
			'debug'                     => WP_DEBUG,
		)
	);

	wp_enqueue_script_module( Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_LISTENER );
	export_script_module_data(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_LISTENER,
		array(
			'channelName' => LOGIN_BROADCAST_CHANNEL_NAME,
		)
	);
}

foreach ( array( 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'customize_controls_enqueue_scripts' ) as $_action ) {
	add_action( $_action, __NAMESPACE__ . '\enqueue_bfcache_invalidation_script_modules' );
}

/**
 * Enqueues script module for bfcache invalidation via Broadcast Channel which emits the message.
 *
 * @since 1.1.0
 * @access private
 */
function enqueue_bfcache_invalidation_via_broadcast_channel_emitter_script_module(): void {
	wp_enqueue_script_module( Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_EMITTER );
	export_script_module_data(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_EMITTER,
		array(
			'channelName' => LOGIN_BROADCAST_CHANNEL_NAME,
		)
	);
}
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_bfcache_invalidation_via_broadcast_channel_emitter_script_module' );
