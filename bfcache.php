<?php
/**
 * Plugin Name: Back/Forward Cache (bfcache)
 * Plugin URI: https://github.com/westonruter/bfcache
 * Description: Enables back/forward cache (bfcache) for instant history navigations even when "nocache" headers are sent.
 * Requires at least: 6.8
 * Requires PHP: 7.2
 * Version: 1.0.0
 * Author: Weston Ruter
 * Author URI: https://weston.ruter.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Update URI: https://github.com/westonruter/bfcache
 * GitHub Plugin URI: https://github.com/westonruter/bfcache
 * Primary Branch: main
 *
 * @package WestonRuter\Bfcache
 */

namespace WestonRuter\Bfcache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

// Abort plugin if the core patch has been applied.
if ( defined( 'BFCACHE_SESSION_TOKEN_COOKIE' ) || function_exists( 'wp_enqueue_bfcache_script_module' ) ) {
	return;
}

/**
 * Version.
 *
 * @var string
 */
const VERSION = '1.0.0';

/**
 * Gets the name for the cookie which contains a session token for the bfcache.
 *
 * This incorporates the `COOKIEHASH` to prevent cookie collisions on multisite subdirectory installs.
 *
 * @since 1.0.0
 *
 * @link https://core.trac.wordpress.org/ticket/29095
 * @return non-empty-string Cookie name.
 */
function get_bfcache_session_token_cookie_name(): string {
	return 'wordpress_bfcache_session_' . COOKIEHASH;
}

/**
 * Filters nocache_headers to remove the no-store directive.
 *
 * @since 1.0.0
 *
 * @link https://core.trac.wordpress.org/ticket/21938#comment:47
 * @param array<string, string>|mixed $headers Header names and field values.
 * @return array<string, string> Headers.
 */
function filter_nocache_headers( $headers ): array {
	/**
	 * Because plugins do bad things.
	 *
	 * @var array<string, string> $headers
	 */
	if ( ! is_array( $headers ) ) {
		$headers = array();
	}

	// This does not short-circuit if is_user_logged_in() because some plugins send `Cache-Control: no-store` (CCNS)
	// even when the user is not logged in. WooCommerce, for example, sends CCNS on the cart, checkout, and account
	// pages even when the user is not logged in to WordPress, at least until <https://github.com/woocommerce/woocommerce/pull/58445>
	// is merged. So this function will automatically replace the `no-store` directive, if present, with alternate
	// directives that prevent caching in proxies (especially `private`) without breaking the browser's bfcache.
	if ( ! isset( $headers['Cache-Control'] ) || ! is_string( $headers['Cache-Control'] ) ) {
		return $headers;
	}

	// See the commit message for <https://core.trac.wordpress.org/changeset/55968> which the following seeks to unto in how it introduced 'no-store'.
	$directives = (array) preg_split( '/\s*,\s*/', $headers['Cache-Control'] );
	if ( in_array( 'no-store', $directives, true ) ) {
		// Remove 'no-store' so that the browser is allowed to store the response in the bfcache.
		// And remove 'public' too for good measure (although it surely would not be present) since 'private' is added below.
		$directives = array_diff(
			$directives,
			array( 'no-store', 'public' )
		);

		// Since no-store was removed, make sure that other key directives are present which prevent the response from being stored in a proxy cache.
		// WooCommerce's WC_Cache_Helper::additional_nocache_headers() neglects to add the `private` directive. But a fix has been proposed in
		// <https://github.com/woocommerce/woocommerce/pull/58445>.
		$directives = array_unique(
			array_merge(
				$directives,
				array(
					// Note: Explanatory comments derived from Gemini 2.5 Pro.
					'private',         // This is the key directive for your concern about proxies. It explicitly states that the response is for a single user and must not be stored by shared caches. Compliant proxy caches will respect this.
					'no-cache',        // This directive indicates that a cache (browser or proxy) must revalidate the stored response with the origin server before using it. It doesn't prevent storage, but it does ensure freshness if it were stored.
					'max-age=0',       // This tells caches that the response is considered stale immediately. When combined with no-cache (or must-revalidate), it forces revalidation on each subsequent request.
					'must-revalidate', // This directive is stricter than no-cache. Once the content is stale (which max-age=0 makes immediate), the cache must revalidate with the origin server and must not serve the stale content if the origin server is unavailable (it should return a 504 Gateway Timeout error, for example).
				)
			)
		);

		$headers['Cache-Control'] = implode( ', ', $directives );
	}

	return $headers;
}

add_filter(
	'nocache_headers',
	__NAMESPACE__ . '\filter_nocache_headers',
	1000 // Note that WC_Cache_Helper::additional_nocache_headers() runs at priority 10, that is, until <https://github.com/woocommerce/woocommerce/pull/58445>.
);

/**
 * Generates a bfcache session token.
 *
 * When a user authenticates, this session token is set on a cookie which can be read by JavaScript. Similarly, whenever
 * a user logs out, this cookie is cleared. When an authenticated page is loaded, the value of the cookie is captured.
 * When a user navigates back to an authenticated page via bfcache (detected via the `pageshow` event handler), if the
 * current cookie's value does not match the previously captured value, then JavaScript forcibly reloads the page.
 *
 * Initially the current user ID was chosen as the cookie value, but this turned out to not be as secure. If someone
 * logs out and this cookie is cleared, a malicious user could easily re-set that cookie via JavaScript to be able to
 * navigate to an authenticated page via bfcache. By having the cookie value being random, then this risk is eliminated.
 *
 * @since 1.0.0
 * @see \WP_Session_Tokens::create()
 *
 * @return non-empty-string Session token.
 */
function generate_bfcache_session_token(): string {
	/**
	 * Token.
	 *
	 * @var non-empty-string $token
	 */
	$token = wp_generate_password( 43, false, false );
	return $token;
}

/**
 * Sets a cookie containing a bfcache session token when a user logs in.
 *
 * @since 1.0.0
 * @see \wp_set_auth_cookie()
 *
 * @param string $logged_in_cookie The logged-in cookie value.
 * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
 *                                 Default is 12 hours past the cookie's expiration time.
 * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
 *                                 Default is 14 days from now.
 * @param int    $user_id          User ID.
 * @param string $scheme           Authentication scheme. Default 'logged_in'.
 */
function set_logged_in_cookie( string $logged_in_cookie, int $expire, int $expiration, int $user_id, string $scheme ): void {
	unset( $logged_in_cookie, $expiration, $user_id, $scheme ); // Unused args.

	$cookie_name   = get_bfcache_session_token_cookie_name();
	$session_token = generate_bfcache_session_token();

	// The cookies are intentionally not HTTP-only.
	// TODO: Should they be conditionally secure?
	setcookie( $cookie_name, $session_token, $expire, COOKIEPATH, COOKIE_DOMAIN, false, false );
	if ( COOKIEPATH !== SITECOOKIEPATH ) {
		setcookie( $cookie_name, $session_token, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, false, false );
	}
}

// TODO: Or 'set_auth_cookie'? Should this be set if not secure? This cookie should only be set if the other cookies are being set.
add_action(
	'set_logged_in_cookie',
	__NAMESPACE__ . '\set_logged_in_cookie',
	10,
	5
);

/**
 * Clears the bfcache session token cookie when logging out.
 *
 * @since 1.0.0
 * @see \wp_clear_auth_cookie()
 */
function clear_logged_in_cookie(): void {
	$cookie_name = get_bfcache_session_token_cookie_name();
	setcookie( $cookie_name, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, false, false );
	if ( COOKIEPATH !== SITECOOKIEPATH ) {
		setcookie( $cookie_name, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, false, false );
	}
}
add_action( 'clear_auth_cookie', __NAMESPACE__ . '\clear_logged_in_cookie' );

/**
 * Enqueues script module to invalidate bfcache.
 *
 * This script module is only enqueued when the user is logged in. A page loaded from bfcache is invalided if the
 * session token cookie has changed due to the user logging out or logging in as another user.
 *
 * @since 1.0.0
 */
function enqueue_script_module(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$module_id = '@westonruter/bfcache';

	wp_enqueue_script_module(
		$module_id,
		plugins_url( 'bfcache.js', __FILE__ ),
		array(),
		VERSION
	);

	add_filter( "script_module_data_{$module_id}", __NAMESPACE__ . '\export_script_module_data' );
}

foreach ( array( 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'customize_controls_enqueue_scripts' ) as $_action ) {
	add_action( $_action, __NAMESPACE__ . '\enqueue_script_module' );
}

/**
 * Exports script module data.
 *
 * @since 1.0.0
 * @return array{ cookieName: non-empty-string } Data.
 */
function export_script_module_data(): array {
	return array(
		'cookieName' => get_bfcache_session_token_cookie_name(),
	);
}

/**
 * Adds missing hooks to print script modules in the Customizer if they are not present.
 *
 * @since 1.0.0
 * @see \WP_Script_Modules::add_hooks()
 */
function add_script_modules_customizer_hooks(): void {
	$action  = 'customize_controls_print_footer_scripts';
	$methods = array(
		'print_import_map',
		'print_enqueued_script_modules',
		'print_script_module_preloads',
		'print_script_module_data',
	);
	foreach ( $methods as $method ) {
		if ( false === has_action( $action, array( wp_script_modules(), $method ) ) ) {
			add_action( $action, array( wp_script_modules(), $method ) );
		}
	}
}

add_action(
	'after_setup_theme',
	__NAMESPACE__ . '\add_script_modules_customizer_hooks',
	100 // Core does this at priority 10.
);
