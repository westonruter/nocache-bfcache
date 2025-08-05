<?php
/**
 * User opt-in for BFCache.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

use WP_Session_Tokens;

/**
 * Name for the cookie which captures whether JavaScript is enabled when logging in.
 *
 * This is similar in principle to the `wordpress_test_cookie` cookie. There is no way of knowing when WordPress serves
 * a request in PHP whether JavaScript is enabled on the client. In the same way that there is a `Sec-CH-UA-Mobile`
 * header, it would be nice if there was a User-Agent Client Hints header like `Sec-CH-UA-Scripting` that could be used
 * for this purpose, but it doesn't exist, and apparently it hasn't been proposed from looking at
 * [WICG/ua-client-hints](https://github.com/WICG/ua-client-hints).
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const JAVASCRIPT_ENABLED_COOKIE_NAME = 'nocache_bfcache_js_enabled';

/**
 * ID for the `template` element containing the `button` which opens the popover to introduce the opt-in feature.
 *
 * @since 1.1.0
 * @access private
 */
const BUTTON_TEMPLATE_ID = 'nocache-bfcache-feature-button-tmpl';

/**
 * User session key for the bfcache session token.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const BFCACHE_SESSION_TOKEN_USER_SESSION_KEY = 'bfcache_session_token';

/**
 * Determines whether the "Remember Me" checkbox on the login screen is used as an opt-in to bfcache.
 *
 * @return bool Whether the "Remember Me" checkbox on the login screen is used as an opt-in to bfcache.
 */
function is_remember_me_used_as_opt_in(): bool {
	/**
	 * Filters whether the "Remember Me" checkbox on the login screen is used as an opt-in to bfcache.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $enabled Whether the "Remember Me" checkbox on the login screen is used as an opt-in to bfcache.
	 */
	return (bool) apply_filters( 'nocache_bfcache_use_remember_me_as_opt_in', true );
}

/**
 * Enqueues bfcache opt-in script module and style.
 *
 * @since 1.1.0
 * @access private
 */
function enqueue_bfcache_opt_in_script_module_and_style(): void {
	if ( ! is_remember_me_used_as_opt_in() ) {
		return;
	}

	wp_enqueue_style( BFCACHE_OPT_IN_STYLE_HANDLE );

	wp_enqueue_script_module( BFCACHE_OPT_IN_SCRIPT_MODULE_ID );
	export_script_module_data(
		BFCACHE_OPT_IN_SCRIPT_MODULE_ID,
		array(
			'buttonTemplateId' => BUTTON_TEMPLATE_ID,
		)
	);
}

/**
 * Enqueues script module to detect whether scripting is enabled at login.
 *
 * @since 1.2.0
 * @access private
 */
function enqueue_detect_scripting_enabled_at_login_script_module(): void {
	wp_enqueue_script_module( DETECT_SCRIPTING_ENABLED_AT_LOGIN_SCRIPT_MODULE_ID );
	export_script_module_data(
		DETECT_SCRIPTING_ENABLED_AT_LOGIN_SCRIPT_MODULE_ID,
		array(
			'cookieName'     => JAVASCRIPT_ENABLED_COOKIE_NAME,
			'cookiePath'     => COOKIEPATH,
			'siteCookiePath' => SITECOOKIEPATH,
			'loginPostUrl'   => site_url( 'wp-login.php', 'login_post' ),
		)
	);
}

add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_bfcache_opt_in_script_module_and_style' );
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_detect_scripting_enabled_at_login_script_module' );

/**
 * Enqueues script modules for the login form which is rendered on the frontend.
 *
 * There are two login forms in WordPress:
 *
 * 1. The form constructed on wp-login.php
 * 2. The form constructed by {@see wp_login_form()} for use on the frontend.
 *
 * For the former, scripts can be enqueued at the `login_enqueue_scripts` and the form can be extended with the
 * `login_form` action. However, for the latter, there are no such actions. The only hooks provided by `wp_login_form()`
 * are a set of filters that are applied. Therefore, in order to enqueue script modules when the login form is used on
 * the frontend, one of these filters has to be hackily used as an action, with the filtered value passed through.
 *
 * @since 1.2.0
 * @access private
 *
 * @param mixed $pass_through Pass through filter data.
 * @return mixed Passed through filter data.
 */
function enqueue_script_modules_for_frontend_login_form( $pass_through ) { // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
	enqueue_detect_scripting_enabled_at_login_script_module();
	return $pass_through;
}

// A filter is used because there is no action in wp_login_form().
add_filter( 'login_form_defaults', __NAMESPACE__ . '\enqueue_script_modules_for_frontend_login_form' );

/**
 * Augments the login form with a popover to promote the feature.
 *
 * @since 1.1.0
 * @access private
 */
function print_login_form_remember_me_popover(): void {
	?>
	<template id="<?php echo esc_attr( BUTTON_TEMPLATE_ID ); ?>">
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

add_action( 'login_form', __NAMESPACE__ . '\print_login_form_remember_me_popover' );

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
 * Gets the name for the cookie which contains a session token for the bfcache.
 *
 * This incorporates the `COOKIEHASH` to prevent cookie collisions on multisite subdirectory installs. The values which
 * populate the cookie come from {@see generate_bfcache_session_token()}. The value is is stored in the user session via
 * {@see set_bfcache_session_token_cookie()} and retrieved via {@see get_user_bfcache_session_token()}.
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

	/*
	 * A cookie is used as opposed to a hidden input field on the login form for improved plugin compatibility. Plugins
	 * like Two Factor add an interstitial login screen which doesn't carry those hidden fields on to the final
	 * authentication request when the session is created. See <https://github.com/WordPress/two-factor/issues/705>.
	 */
	if (
		(
			isset( $_POST['rememberme'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			||
			! is_remember_me_used_as_opt_in()
		)
		&&
		isset( $_COOKIE[ JAVASCRIPT_ENABLED_COOKIE_NAME ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
	) {
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
 * @param positive-int|null     $user_id       User ID. Defaults to the current user ID.
 * @param non-empty-string|null $session_token Session token. Defaults to the current session token.
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
 * @param positive-int $user_id User ID.
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
 * @phpstan-param int<1752496038, max> $expire The minimum value is the timestamp at which this code was written.
 *
 * @param positive-int     $user_id               User ID.
 * @param non-empty-string $bfcache_session_token Bfcache session token.
 * @param int              $expire                Expiration time as a Unix timestamp.
 */
function set_bfcache_session_token_cookie( int $user_id, string $bfcache_session_token, int $expire ): void {
	$cookie_name = get_bfcache_session_token_cookie_name();

	// The cookies are intentionally not HTTP-only.
	$secure_logged_in_cookie = is_logged_in_cookie_secure( $user_id );
	setcookie( $cookie_name, $bfcache_session_token, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, false );
	if ( COOKIEPATH !== SITECOOKIEPATH ) {
		setcookie( $cookie_name, $bfcache_session_token, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, false );
	}
}

/**
 * Sets a cookie containing a bfcache session token when a user logs in.
 *
 * @since 1.0.0
 * @access private
 * @see \wp_set_auth_cookie()
 *
 * @phpstan-param int<1752496038, max> $expire     The minimum value is the timestamp at which this code was written.
 * @phpstan-param int<1752496038, max> $expiration The minimum value is the timestamp at which this code was written.
 *
 * @param non-empty-string $logged_in_cookie The logged-in cookie value.
 * @param int              $expire           The time the login grace period expires as a UNIX timestamp.
 *                                           Default is 12 hours past the cookie's expiration time.
 * @param int              $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
 *                                               Default is 14 days from now.
 * @param positive-int     $user_id          User ID.
 * @param non-empty-string $scheme           Authentication scheme. Default 'logged_in'.
 * @param non-empty-string $token            User's session token to use for this cookie. Empty string when clearing cookies.
 */
function set_logged_in_cookie( string $logged_in_cookie, int $expire, int $expiration, int $user_id, string $scheme, string $token ): void {
	unset( $logged_in_cookie, $expire, $scheme ); // Unused args.

	$bfcache_session_token = get_user_bfcache_session_token( $user_id, $token );
	if ( null !== $bfcache_session_token ) {
		set_bfcache_session_token_cookie( $user_id, $bfcache_session_token, $expiration );
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
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
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
			/**
			 * Current user ID.
			 *
			 * This will be a positive int because of the logged-in check above.
			 *
			 * @var positive-int $user_id
			 */
			$user_id = get_current_user_id();

			/**
			 * Current time in Unix time.
			 *
			 * The minimum value is the timestamp at which this code was written.
			 *
			 * @var int<1752496038, max> $now
			 */
			$now = time();

			set_bfcache_session_token_cookie( $user_id, $bfcache_session_token, $now + 14 * DAY_IN_SECONDS );
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
		// WooCommerce's WC_Cache_Helper::additional_nocache_headers() neglected to add the `private` directive before v10.1, which was fixed in
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

/*
 * WC_Cache_Helper::additional_nocache_headers() runs at priority 10, until <https://github.com/woocommerce/woocommerce/pull/58445>.
 * Jetpack_Admin::add_no_store_header() runs at priority 100, until <https://github.com/Automattic/jetpack/pull/44322>.
 */
add_filter(
	'nocache_headers',
	__NAMESPACE__ . '\filter_nocache_headers',
	1000
);
