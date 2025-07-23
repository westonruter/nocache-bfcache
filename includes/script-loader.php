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
 * Script module ID for the bfcache opt-in.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_OPT_IN_SCRIPT_MODULE_ID = '@nocache-bfcache/bfcache-opt-in';

/**
 * Script module ID for the script to detect whether scripting is enabled at login.
 *
 * @since 1.2.0
 * @access private
 * @var string
 */
const DETECT_SCRIPTING_ENABLED_AT_LOGIN_SCRIPT_MODULE_ID = '@nocache-bfcache/detect-scripting-enabled-at-login';

/**
 * Script module ID for bfcache invalidation.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_INVALIDATION_SCRIPT_MODULE_ID = '@nocache-bfcache/bfcache-invalidation';

/**
 * Registers script modules.
 *
 * @since 1.1.0
 * @access private
 */
function register_script_modules(): void {

	wp_register_script_module(
		BFCACHE_OPT_IN_SCRIPT_MODULE_ID,
		plugins_url( 'js/bfcache-opt-in.js', PLUGIN_FILE ),
		array(),
		VERSION
	);

	wp_register_script_module(
		DETECT_SCRIPTING_ENABLED_AT_LOGIN_SCRIPT_MODULE_ID,
		plugins_url( 'js/detect-scripting-enabled-at-login.js', PLUGIN_FILE ),
		array(),
		VERSION
	);

	/*
	 * Ideally this script would load in the HEAD with async. This would execute it as early as possible before the DOM
	 * has been fully constructed when a page is restored from the HTTP cache. This would give the script the chance to
	 * invalidate the page even earlier. Instead, this is currently this is being printed at the end of the BODY in
	 * classic themes and in the WP Admin, and the associated script data is printed afterward anyway, meaning async
	 * loading is not yet suitable. See <https://core.trac.wordpress.org/ticket/63486> for what may allow for the API
	 * to specify whether script modules are printed in the HEAD or in the footer.
	 */
	wp_register_script_module(
		BFCACHE_INVALIDATION_SCRIPT_MODULE_ID,
		plugins_url( 'js/bfcache-invalidation.js', PLUGIN_FILE ),
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
	$relative_path = 'css/bfcache-opt-in.css';
	wp_register_style(
		BFCACHE_OPT_IN_STYLE_HANDLE,
		plugins_url( $relative_path, PLUGIN_FILE ),
		array(),
		VERSION
	);
	wp_style_add_data(
		BFCACHE_OPT_IN_STYLE_HANDLE,
		'path',
		plugin_dir_path( PLUGIN_FILE ) . $relative_path
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
 * Adds low fetchpriority to SCRIPT tags for script modules.
 *
 * @since 1.2.0
 * @access private
 *
 * @link https://core.trac.wordpress.org/ticket/63486
 * @link https://weston.ruter.net/2025/05/26/improve-lcp-by-deprioritizing-interactivity-api-script-modules/
 *
 * @param array<string, string>|mixed $attributes Script attributes.
 * @return array<string, string> Modified attributes.
 */
function filter_script_tag_attributes( $attributes ): array {
	if ( ! is_array( $attributes ) ) {
		$attributes = array();
	}
	/**
	 * Because plugins do bad things.
	 *
	 * @var array<string, string> $attributes
	 */

	if (
		isset( $attributes['id'], $attributes['src'], $attributes['type'] ) &&
		'module' === $attributes['type'] &&
		is_string( $attributes['id'] ) &&
		1 === preg_match( '/^(?P<script_module_id>.+)-js-module$/', $attributes['id'], $matches ) &&
		in_array(
			$matches['script_module_id'],
			array(
				BFCACHE_OPT_IN_SCRIPT_MODULE_ID,
				DETECT_SCRIPTING_ENABLED_AT_LOGIN_SCRIPT_MODULE_ID,
				BFCACHE_INVALIDATION_SCRIPT_MODULE_ID,
			),
			true
		)
	) {
		$attributes['fetchpriority'] = 'low';
	}

	return $attributes;
}

add_filter( 'wp_script_attributes', __NAMESPACE__ . '\filter_script_tag_attributes' );

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
