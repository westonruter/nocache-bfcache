<?php
/**
 * Logic for the initial opt-in for BFCache.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

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
 * Enqueues bfcache opt-in script module and style.
 *
 * @since 1.1.0
 * @access private
 */
function enqueue_bfcache_opt_in_script_module_and_style(): void {
	wp_enqueue_script_module( BFCACHE_OPT_IN_SCRIPT_MODULE_ID );
	wp_enqueue_style( BFCACHE_OPT_IN_STYLE_HANDLE );

	export_script_module_data(
		BFCACHE_OPT_IN_SCRIPT_MODULE_ID,
		array(
			'cookieName'       => JAVASCRIPT_ENABLED_COOKIE_NAME,
			'buttonTemplateId' => BUTTON_TEMPLATE_ID,
			'cookiePath'       => COOKIEPATH,
			'siteCookiePath'   => SITECOOKIEPATH,
		)
	);
}
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\enqueue_bfcache_opt_in_script_module_and_style' );

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
