<?php
/**
 * Script loader for Nocache BFCache.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

/**
 * Script module ID for the bfcache opt-in.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_OPT_IN_SCRIPT_MODULE_ID = '@nocache-bfcache/bfcache-opt-in';

/**
 * Script module ID for bfcache invalidation via the pageshow event.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_INVALIDATION_VIA_PAGESHOW_SCRIPT_MODULE_ID = '@nocache-bfcache/bfcache-invalidation-via-pageshow';

/**
 * Script module ID for bfcache invalidation via Broadcast Channel.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_SCRIPT_MODULE_ID = '@nocache-bfcache/bfcache-invalidation-via-broadcast-channel';

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
 * Registers script modules.
 *
 * @since 1.1.0
 * @access private
 */
function register_script_modules(): void {

	wp_register_script_module(
		BFCACHE_OPT_IN_SCRIPT_MODULE_ID,
		plugins_url( 'js/bfcache-opt-in.js', __FILE__ ),
		array(),
		VERSION
	);

	// This is used by Chrome and Firefox. TODO: Actually, this doesn't seem to be the case!
	wp_register_script_module(
		BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_SCRIPT_MODULE_ID,
		plugins_url( 'js/bfcache-invalidation-via-broadcast-channel.js', __FILE__ ),
		array(),
		VERSION
	);

	// This is only needed by Safari.
	wp_register_script_module(
		BFCACHE_INVALIDATION_VIA_PAGESHOW_SCRIPT_MODULE_ID,
		plugins_url( 'js/bfcache-invalidation-via-pageshow.js', __FILE__ ),
		array(),
		VERSION
	);
}

add_action( 'init', __NAMESPACE__ . '\register_script_modules' );

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

	wp_enqueue_script_module( BFCACHE_INVALIDATION_VIA_PAGESHOW_SCRIPT_MODULE_ID );
	wp_enqueue_script_module( BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_SCRIPT_MODULE_ID );
}

foreach ( array( 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'customize_controls_enqueue_scripts' ) as $_action ) {
	add_action( $_action, __NAMESPACE__ . '\enqueue_bfcache_invalidation_script_modules' );
}

/**
 * Exports script module data.
 *
 * @since 1.0.0
 * @access private
 */
function export_script_module_data(): void {
	$data = array(
		BFCACHE_INVALIDATION_VIA_PAGESHOW_SCRIPT_MODULE_ID => array(
			'cookieName'                => get_bfcache_session_token_cookie_name(),
			'loginBroadcastChannelName' => LOGIN_BROADCAST_CHANNEL_NAME,
			'debug'                     => WP_DEBUG,
		),
		BFCACHE_OPT_IN_SCRIPT_MODULE_ID                    => array(
			'cookieName'       => JAVASCRIPT_ENABLED_COOKIE_NAME,
			'buttonTemplateId' => BUTTON_TEMPLATE_ID,
			'cookiePath'       => COOKIEPATH,
			'siteCookiePath'   => SITECOOKIEPATH,
		),
		BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_SCRIPT_MODULE_ID => array(
			'channelName' => LOGIN_BROADCAST_CHANNEL_NAME,
		),
	);

	foreach ( $data as $module_id => $module_data ) {
		add_filter(
			"script_module_data_{$module_id}",
			static function () use ( $module_data ): array {
				return $module_data;
			}
		);
	}
}

add_action( 'init', __NAMESPACE__ . '\export_script_module_data' );

/**
 * Enqueues bfcache opt-in script module and style.
 *
 * @since 1.1.0
 * @access private
 */
function enqueue_bfcache_opt_in_script_module_and_style(): void {
	wp_enqueue_script_module( BFCACHE_OPT_IN_SCRIPT_MODULE_ID );

	wp_enqueue_style(
		'nocache-bfcache-opt-in',
		plugins_url( 'css/bfcache-opt-in.css', __FILE__ ),
		array(),
		VERSION
	);
}
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_bfcache_opt_in_script_module_and_style' );

/**
 * Adds missing hooks to print script modules in the Customizer and login screen if they are enqueued.
 *
 * @since 1.1.0
 * @access private
 * @see \WP_Script_Modules::add_hooks()
 */
function add_missing_script_modules_hooks(): void {
	$actions = array(
		'login_footer',
		'customize_controls_print_footer_scripts',
	);
	$methods = array(
		'print_import_map',
		'print_enqueued_script_modules',
		'print_script_module_preloads',
		'print_script_module_data',
	);
	foreach ( $actions as $action ) {
		foreach ( $methods as $method ) {
			if ( false === has_action( $action, array( wp_script_modules(), $method ) ) ) {
				add_action( $action, array( wp_script_modules(), $method ) );
			}
		}
	}
}

add_action(
	'after_setup_theme',
	__NAMESPACE__ . '\add_missing_script_modules_hooks',
	100 // Core does this at priority 10.
);
