<?php
/**
 * Script and style registration.
 *
 * No scripts or styles are actually enqueued in this file. They are merely registered here.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

/**
 * Style handle for the bfcache opt-in.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_OPT_IN_STYLE_HANDLE = 'nocache-bfcache-opt-in';

/**
 * Registers script modules.
 *
 * @since 1.1.0
 * @access private
 */
function register_script_modules(): void {

	wp_register_script_module(
		Script_Module_Ids::BFCACHE_OPT_IN,
		plugins_url( 'js/bfcache-opt-in.js', PLUGIN_FILE ),
		array(),
		VERSION
	);

	// This is used by Chrome and Firefox.
	wp_register_script_module(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_EMITTER,
		plugins_url( 'js/bfcache-invalidation-via-broadcast-channel-emitter.js', PLUGIN_FILE ),
		array(),
		VERSION
	);
	wp_register_script_module(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_LISTENER,
		plugins_url( 'js/bfcache-invalidation-via-broadcast-channel-listener.js', PLUGIN_FILE ),
		array(),
		VERSION
	);

	// This is only needed by Safari.
	wp_register_script_module(
		Script_Module_Ids::BFCACHE_INVALIDATION_VIA_PAGESHOW,
		plugins_url( 'js/bfcache-invalidation-via-pageshow.js', PLUGIN_FILE ),
		array(),
		VERSION
	);
}

add_action( 'init', __NAMESPACE__ . '\register_script_modules' );

/**
 * Registers styles.
 *
 * @since 1.1.0
 * @access private
 */
function register_styles(): void {

	wp_register_style(
		BFCACHE_OPT_IN_STYLE_HANDLE,
		plugins_url( 'css/bfcache-opt-in.css', PLUGIN_FILE ),
		array(),
		VERSION
	);
}

add_action( 'init', __NAMESPACE__ . '\register_styles' );

/**
 * Exports script module data.
 *
 * This helper function provides an interface similar to `wp_script_add_data()` for exporting data from PHP to JS. See
 * [prior discussion](https://github.com/WordPress/wordpress-develop/pull/6682#discussion_r1624822067).
 *
 * @since 1.1.0
 * @access private
 *
 * @param non-empty-string                         $module_id   Module ID.
 * @param non-empty-array<non-empty-string, mixed> $module_data Module data.
 */
function export_script_module_data( string $module_id, array $module_data ): void {
	add_filter(
		"script_module_data_{$module_id}",
		static function () use ( $module_data ): array {
			return $module_data;
		}
	);
}

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
