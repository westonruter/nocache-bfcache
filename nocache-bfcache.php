<?php
/**
 * Plugin Name: No-cache BFCache
 * Plugin URI: https://github.com/westonruter/nocache-bfcache
 * Description: Enables back/forward cache (bfcache) for instant history navigations even when "nocache" headers are sent, such as when a user is logged in.
 * Requires at least: 6.8
 * Requires PHP: 7.2
 * Version: 1.0.0
 * Author: Weston Ruter
 * Author URI: https://weston.ruter.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/westonruter/nocache-bfcache
 * Primary Branch: main
 *
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

// Abort executing the plugin if the core patch has been applied. See <https://github.com/WordPress/wordpress-develop/pull/9131>.
if ( defined( 'BFCACHE_SESSION_TOKEN_COOKIE' ) || function_exists( 'wp_enqueue_bfcache_script_module' ) ) {
	return;
}

use WP_HTML_Tag_Processor;
use WP_Session_Tokens;

/**
 * Version.
 *
 * @since 1.0.0
 * @access private
 * @var string
 */
const VERSION = '1.0.0';

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
 * Name for the hidden input field that captures whether JavaScript is enabled when logging in.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const JAVASCRIPT_ENABLED_INPUT_FIELD_NAME = 'nocache_bfcache_javascript_enabled';

/**
 * User session key for the bfcache session token.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_SESSION_TOKEN_USER_SESSION_KEY = 'bfcache_session_token';

/**
 * Gets the name for the cookie which contains a session token for the bfcache.
 *
 * This incorporates the `COOKIEHASH` to prevent cookie collisions on multisite subdirectory installs.
 *
 * @since 1.0.0
 * @access private
 *
 * @link https://core.trac.wordpress.org/ticket/29095
 * @return non-empty-string Cookie name.
 */
function get_bfcache_session_token_cookie_name(): string {
	return 'wordpress_bfcache_session_' . COOKIEHASH;
}

/**
 * Attaches session information for whether the user requested to "Remember Me" and whether JS was enabled.
 *
 * When the user has elected to have their session remembered, and they have JavaScript enabled, then pages will be
 * served without the no-store directive in the Cache-Control header. Additionally, a script module will be printed on
 * the pages to facilitate invalidating pages from bfcache after the user has logged out to protect privacy. Storing
 * the bfcache session token in the user's session information allows for it to be restored when switching back to the
 * user with a plugin like User Switching. It also allows the cookie to be re-set if it gets deleted in the course of
 * a user's authenticated session.
 *
 * @since 1.1.0
 * @access private
 * @see WP_Session_Tokens::create()
 *
 * @param array<string, mixed>|mixed $session Session.
 * @return array<string, mixed> Session.
 */
function attach_session_information( $session ): array {
	/**
	 * Because plugins do bad things.
	 *
	 * @var array<string, mixed> $session
	 */
	if ( ! is_array( $session ) ) {
		$session = array();
	}
	if ( isset( $_POST['rememberme'] ) && isset( $_POST[ JAVASCRIPT_ENABLED_INPUT_FIELD_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$session[ BFCACHE_SESSION_TOKEN_USER_SESSION_KEY ] = generate_bfcache_session_token();
	}
	return $session;
}

add_filter( 'attach_session_information', __NAMESPACE__ . '\attach_session_information' );

/**
 * Gets the bfcache session token for the current user.
 *
 * @since 1.1.0
 * @access private
 *
 * @param int|null    $user_id       User ID. Defaults to the current user ID.
 * @param string|null $session_token Session token. Defaults to the current session token.
 * @return non-empty-string|null Bfcache session token if available.
 */
function get_user_bfcache_session_token( ?int $user_id = null, ?string $session_token = null ): ?string {
	if ( ! is_user_logged_in() && null === $user_id && null === $session_token ) {
		return null;
	}

	$instance = WP_Session_Tokens::get_instance( $user_id ?? get_current_user_id() );
	$session  = $instance->get( $session_token ?? wp_get_session_token() );
	if (
		is_array( $session ) &&
		isset( $session[ BFCACHE_SESSION_TOKEN_USER_SESSION_KEY ] ) &&
		is_string( $session[ BFCACHE_SESSION_TOKEN_USER_SESSION_KEY ] ) &&
		'' !== $session[ BFCACHE_SESSION_TOKEN_USER_SESSION_KEY ]
	) {
		return $session[ BFCACHE_SESSION_TOKEN_USER_SESSION_KEY ];
	}
	return null;
}

/**
 * Filters nocache_headers to remove the no-store directive.
 *
 * @since 1.0.0
 * @access private
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

	// If a user is logged in, then enabling bfcache is contingent upon the "Remember Me" opt-in and JS being enabled, since bfcache invalidation becomes important.
	if ( is_user_logged_in() ) {
		// Abort if the user session doesn't have bfcache enabled since they hadn't logged in with "Remember Me" and JavaScript enabled.
		$bfcache_session_token = get_user_bfcache_session_token();
		if ( null === $bfcache_session_token ) {
			return $headers;
		}

		// The bfcache session cookie is normally set during log in. If it was deleted for some reason, then it needs to be
		// re-set so that it is available to JavaScript so that the pageshow event can invalidate bfcache when the cookie
		// has changed. The bfcache session token is only generated when JavaScript has been detected to be enabled and
		// the user has elected to "Remember Me".
		$cookie_name = get_bfcache_session_token_cookie_name();
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			set_bfcache_session_token_cookie( get_current_user_id(), $bfcache_session_token, 14 * DAY_IN_SECONDS );
		}
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
 * @access private
 * @see WP_Session_Tokens::create()
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
 * Determines whether the logged_in_cookie should be set as secure.
 *
 * This logic is copied from the `wp_set_auth_cookie()` function in core. This is because the `$secure_logged_in_cookie`
 * value is computed internally and isn't readily available to filters that need access to this value. In reality, the
 * bfcache session token would have a very low risk of being set as non-secure since its only purpose is to evict pages
 * from the bfcache when someone logs out or logs in to another user account. Even here, however, this only applies in
 * Safari which needs to rely on the `pageshow` event to manually evict pages from bfcache. Chrome and Firefox are able
 * to evict pages from bfcache more cleanly simply via sending a message via BroadcastChannel.
 *
 * @since 1.1.0
 * @access private
 * @link https://github.com/WordPress/wordpress-develop/blob/f1d5beb452bda5035faaf1ab8a6c8c80c8ccd5d5/src/wp-includes/pluggable.php#L1010-L1036
 *
 * @param int $user_id User ID.
 * @return bool Whether the logged_in_cookie is secure.
 */
function is_logged_in_cookie_secure( int $user_id ): bool {
	$secure = is_ssl();
	$home   = get_option( 'home' );

	// Front-end cookie is secure when the auth cookie is secure and the site's home URL uses HTTPS.
	$secure_logged_in_cookie = $secure && is_string( $home ) && 'https' === wp_parse_url( $home, PHP_URL_SCHEME );

	/** This filter is documented in wp-includes/pluggable.php */
	$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );

	/** This filter is documented in wp-includes/pluggable.php */
	return (bool) apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure );
}

/**
 * Sets the bfcache session token.
 *
 * @since 1.1.0
 * @access private
 *
 * @param int    $user_id       User ID.
 * @param string $session_token Bfcache session token.
 * @param int    $expire        Expiration time.
 */
function set_bfcache_session_token_cookie( int $user_id, string $session_token, int $expire ): void {
	$cookie_name = get_bfcache_session_token_cookie_name();

	// The cookies are intentionally not HTTP-only.
	$secure_logged_in_cookie = is_logged_in_cookie_secure( $user_id );
	setcookie( $cookie_name, $session_token, time() + $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, false );
	if ( COOKIEPATH !== SITECOOKIEPATH ) {
		setcookie( $cookie_name, $session_token, time() + $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, false );
	}
}

/**
 * Sets a cookie containing a bfcache session token when a user logs in.
 *
 * @since 1.0.0
 * @access private
 * @see \wp_set_auth_cookie()
 *
 * @param string $logged_in_cookie The logged-in cookie value.
 * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
 *                                 Default is 12 hours past the cookie's expiration time.
 * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
 *                                 Default is 14 days from now.
 * @param int    $user_id          User ID.
 * @param string $scheme           Authentication scheme. Default 'logged_in'.
 * @param string $token            User's session token to use for this cookie. Empty string when clearing cookies.
 */
function set_logged_in_cookie( string $logged_in_cookie, int $expire, int $expiration, int $user_id, string $scheme, string $token ): void {
	unset( $logged_in_cookie, $expire, $scheme ); // Unused args.

	$session_token = get_user_bfcache_session_token( $user_id, $token );
	if ( null !== $session_token ) {
		set_bfcache_session_token_cookie( $user_id, $session_token, $expiration );
	}
}

// The logged-in cookie is used because the bfcache session token cookie should be available on the frontend and the backend.
add_action(
	'set_logged_in_cookie',
	__NAMESPACE__ . '\set_logged_in_cookie',
	10,
	6
);

/**
 * Clears the bfcache session token cookie when logging out.
 *
 * @since 1.0.0
 * @access private
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
 * This script module is only enqueued when the user is logged in and had opted in to bfcache via electing to "Remember
 * Me" and having JavaScript enabled.
 *
 * @since 1.0.0
 * @access private
 */
function enqueue_script_module(): void {
	if ( null === get_user_bfcache_session_token() ) {
		return;
	}

	$module_id = '@westonruter/bfcache-invalidation';

	wp_enqueue_script_module(
		$module_id,
		plugins_url( 'bfcache-invalidation.js', __FILE__ ),
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
 * @access private
 *
 * @return array{ cookieName: non-empty-string, loginBroadcastChannelName: non-empty-string } Data.
 */
function export_script_module_data(): array {
	return array(
		'cookieName'                => get_bfcache_session_token_cookie_name(),
		'loginBroadcastChannelName' => LOGIN_BROADCAST_CHANNEL_NAME,
		'debug'                     => WP_DEBUG,
	);
}

/**
 * Augments the login form with a hidden input field when JavaScript is enabled and a popover to promote the feature.
 *
 * Only when JavaScript is enabled will the bfcache session token cookie be set, and only when this cookie is set will
 * the `no-store` directive be removed from the `Cache-Control` response header. This is important because the pages in
 * bfcache can only be invalidated (once the user logs out) when JavaScript is enabled.
 *
 * @since 1.1.0
 * @access private
 */
function print_login_form_additions(): void {
	?>
	<style>
		/* Button */
		#nocache-bfcache-feature {
			margin-left: 0.5em;
			background: none;
			border: none;
			padding: 0;
			line-height: 1;
			min-height: auto;
			cursor: help;
		}
		#nocache-bfcache-feature > svg {
			display: inline-block;
		}
		@keyframes nocache-bfcache-throb {
			0%, 100% {
				transform: scale(0.9);
			}
			50% {
				transform: scale(1);
			}
		}
		@starting-style {
			#nocache-bfcache-feature {
				opacity: 0;
			}
		}
		#nocache-bfcache-feature {
			transform: translateY(0);
			transition: opacity 1s ease-out;
		}
		#nocache-bfcache-feature svg {
			transform: scale(0.9);
			animation: nocache-bfcache-throb 2s ease-in-out 0.7s infinite;
		}

		/* Popover */
		#nocache-bfcache-feature-info {
			text-align: left;
			margin: auto;
			padding: 26px 24px;
			font-weight: 400;
			overflow: hidden;
			background: #fff;
			border: 1px solid #c3c4c7;
			max-width: 320px;
		}
		#nocache-bfcache-feature-info::backdrop {
			background: #000;
			opacity: 0.5;
		}
		#nocache-bfcache-feature-info h2 {
			font-size: 14px;
		}
		#nocache-bfcache-feature-info p {
			margin-top: 1em;
		}
		#nocache-bfcache-feature-info p.action-row {
			text-align: center;
		}
	</style>
	<?php ob_start(); ?>
	<script type="module">
		// Add a hidden input that indicates JavaScript is enabled.
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = <?php echo wp_json_encode( JAVASCRIPT_ENABLED_INPUT_FIELD_NAME ); ?>;
		input.value = '1';
		document.getElementById( 'loginform' ).appendChild( input );

		// Add a button that opens a popover with information about the instant navigation feature.
		const p = document.querySelector( 'p.forgetmenot:has(> input#rememberme ):has(> label:last-child[for="rememberme"] )' );
		if ( p ) {
			const tmpl = /** @type {HTMLTemplateElement} */ ( document.getElementById( 'nocache-bfcache-feature-button-tmpl' ) );
			const button = tmpl.content.firstElementChild.cloneNode( true );
			p.append( button );
		}
	</script>
	<?php print_inline_script_tag_from_html( (string) ob_get_clean() ); ?>
	<template id="nocache-bfcache-feature-button-tmpl">
		<button id="nocache-bfcache-feature" popovertarget="nocache-bfcache-feature-info" type="button" class="button-secondary" aria-label="<?php esc_attr_e( 'New feature', 'nocache-bfcache' ); ?>">
			<!-- Source: https://s.w.org/images/core/emoji/16.0.1/svg/2728.svg -->
			<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><path fill="#FFAC33" d="M34.347 16.893l-8.899-3.294-3.323-10.891c-.128-.42-.517-.708-.956-.708-.439 0-.828.288-.956.708l-3.322 10.891-8.9 3.294c-.393.146-.653.519-.653.938 0 .418.26.793.653.938l8.895 3.293 3.324 11.223c.126.424.516.715.959.715.442 0 .833-.291.959-.716l3.324-11.223 8.896-3.293c.391-.144.652-.518.652-.937 0-.418-.261-.792-.653-.938z"/><path fill="#FFCC4D" d="M14.347 27.894l-2.314-.856-.9-3.3c-.118-.436-.513-.738-.964-.738-.451 0-.846.302-.965.737l-.9 3.3-2.313.856c-.393.145-.653.52-.653.938 0 .418.26.793.653.938l2.301.853.907 3.622c.112.444.511.756.97.756.459 0 .858-.312.97-.757l.907-3.622 2.301-.853c.393-.144.653-.519.653-.937 0-.418-.26-.793-.653-.937zM10.009 6.231l-2.364-.875-.876-2.365c-.145-.393-.519-.653-.938-.653-.418 0-.792.26-.938.653l-.875 2.365-2.365.875c-.393.146-.653.52-.653.938 0 .418.26.793.653.938l2.365.875.875 2.365c.146.393.52.653.938.653.418 0 .792-.26.938-.653l.875-2.365 2.365-.875c.393-.146.653-.52.653-.938 0-.418-.26-.792-.653-.938z"/></svg>
		</button>
	</template>
	<div popover id="nocache-bfcache-feature-info">
		<h2><?php esc_html_e( 'New: Instant Back/Forward Navigation', 'nocache-bfcache' ); ?></h2>
		<p><?php esc_html_e( 'When you opt to “Remember Me”, WordPress will tell your browser to save the state of pages when you navigate away from them. This allows them to be restored instantly when you use the back and forward buttons in your browser.', 'nocache-bfcache' ); ?></p>
		<p class="action-row">
			<button popovertarget="nocache-bfcache-feature-info" class="button-secondary" type="button"><?php esc_html_e( 'OK', 'nocache-bfcache' ); ?></button>
		</p>
	</div>
	<?php
}

add_action( 'login_form', __NAMESPACE__ . '\print_login_form_additions' );

/**
 * Prints a script on the login screen to broadcast that the screen has been accessed to invalidate the bfcache.
 *
 * When a page is subscribed to messages from a given BroadcastChannel, a message received while it is in bfcache
 * results in the page being evicted from the bfcache with a "broadcastchannel-message" blocking reason, at least in
 * Chrome and Firefox. This behavior has been [proposed](https://github.com/whatwg/html/issues/7253#issuecomment-2632953500)
 * for standardization.
 *
 * Technically, this message only should be broadcast if an unauthenticated user lands on this screen, since an
 * authenticated user could decide to not go ahead with logging in as another user (without first logging out). However,
 * to achieve this, a script would need to run on the first page accessed after logging in to broadcast the message.
 * This adds complexity, and in general it is unnecessary since only unauthenticated users will only ever find
 * themselves navigating to the login screen.
 *
 * @since 1.1.0
 * @access private
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message
 * @link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500
 * @link https://github.com/mozilla-firefox/firefox/blob/dc64a7e82ff4e2e31b7dafaaa0a9599640a2c87c/testing/web-platform/tests/html/browsers/browsing-the-web/back-forward-cache/broadcastchannel/evict-on-message.tentative.window.js
 */
function print_login_accessed_broadcast_script(): void {
	ob_start();
	?>
	<script type="module">
		const bc = new BroadcastChannel( <?php echo wp_json_encode( LOGIN_BROADCAST_CHANNEL_NAME ); ?> );
		bc.postMessage( true ); // The value is irrelevant.
	</script>
	<?php
	print_inline_script_tag_from_html( (string) ob_get_clean() );
}

add_action( 'login_footer', __NAMESPACE__ . '\print_login_accessed_broadcast_script' );

/**
 * Adds missing hooks to print script modules in the Customizer if they are not present.
 *
 * @since 1.0.0
 * @access private
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

/**
 * Prints an inline script tag from an HTML script tag.
 *
 * This is used to make sure the script contents are printed via `wp_print_inline_script_tag()` so that filters may
 * inject additional attributes into the SCRIPT tag, such as used for CSP. The HTML markup is used inline in the PHP
 * in order for IDEs to provide static analysis.
 *
 * @since 1.1.0
 * @access private
 * @see \wp_remove_surrounding_empty_script_tags()
 *
 * @param string $script Script contents.
 */
function print_inline_script_tag_from_html( string $script ): void {
	$p = new WP_HTML_Tag_Processor( $script );
	if ( ! $p->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'No SCRIPT tag found.', 'nocache-bfcache' ), 'nocache-bfcache 1.1.0' );
		return;
	}
	$attributes = array();
	$attr_names = $p->get_attribute_names_with_prefix( '' );
	if ( is_array( $attr_names ) ) {
		foreach ( $attr_names as $name ) {
			if ( is_string( $name ) ) { // TODO: This type check should not be required in core. The get_attribute_names_with_prefix() return value should be string[]|null instead of array|null.
				$attributes[ $name ] = $p->get_attribute( $name );
			}
		}
	}
	wp_print_inline_script_tag(
		$p->get_modifiable_text(),
		$attributes
	);
	if ( $p->next_tag() ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Only one tag may be supplied.', 'nocache-bfcache' ), 'nocache-bfcache 1.1.0' );
	}
}
