/**
 * Opts in to bfcache from the login screen when a user elects to "Remember Me" (and when JavaScript is enabled).
 *
 * This sets a cookie which demonstrates that JavaScript is currently enabled. This cookie only needs to live until
 * the 'attach_session_information' filter in PHP runs upon successful login, hence why no expiration is set, so that
 * the cookie will be removed when the browser session ends. Only when JavaScript is enabled (and the user has checked
 * "Remember Me") will the bfcache session token cookie be set, and only when this cookie is set will the `no-store`
 * directive be removed from the `Cache-Control` response header. This is important because the pages in bfcache can
 * only be invalidated (when a user logs out) when JavaScript is enabled.
 *
 * This is a JavaScript module, so the global namespace is not polluted.
 *
 * @since 1.1.0
 */

const moduleId = '@nocache-bfcache/bfcache-opt-in';

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById( `wp-script-module-data-${ moduleId }` )
);

/**
 * Exports from PHP.
 *
 * @type {{
 *     cookieName: string,
 *     cookiePath: string,
 *     siteCookiePath: string,
 *     buttonTemplateId: string,
 * }}
 */
const data = JSON.parse( jsonScript.text );

document.cookie = `${ data.cookieName }=1; path=${ data.cookiePath }`;
if ( data.cookiePath !== data.siteCookiePath ) {
	document.cookie = `${ data.cookieName }=1; path=${ data.siteCookiePath }`;
}

// Add a button that opens a popover with information about the instant navigation feature.
const p = document.querySelector(
	'p.forgetmenot:has(> input#rememberme ):has(> label:last-child[for="rememberme"] )'
);
if ( p ) {
	const tmpl = /** @type {HTMLTemplateElement} */ (
		document.getElementById( data.buttonTemplateId )
	);
	const button = /** @type {HTMLButtonElement} */ (
		tmpl.content.firstElementChild
	);
	p.append( button );
}
