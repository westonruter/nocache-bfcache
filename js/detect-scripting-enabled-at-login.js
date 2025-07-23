/**
 * Detects whether scripting is enabled during login.
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
 * @since 1.2.0
 */

/**
 * Script module ID.
 *
 * @since 1.1.0
 * @type {string}
 */
const moduleId = '@nocache-bfcache/detect-scripting-enabled-at-login';

/**
 * JSON script containing the PHP exports.
 *
 * @since 1.1.0
 * @type {HTMLScriptElement}
 */
const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById( `wp-script-module-data-${ moduleId }` )
);

/**
 * Exports from PHP.
 *
 * @since 1.1.0
 * @type {{
 *     cookieName: string,
 *     cookiePath: string,
 *     siteCookiePath: string,
 *     loginPostUrl: string,
 * }}
 */
const data = JSON.parse( jsonScript.text );

/**
 * Gets the form action from the `submit` event.
 *
 * @since 1.2.0
 * @param {SubmitEvent} event - Submit event.
 * @return {string} Action.
 */
function getFormActionFromSubmitEvent( event ) {
	if (
		event.submitter instanceof HTMLInputElement ||
		event.submitter instanceof HTMLButtonElement
	) {
		/*
		 * > The formAction IDL attribute must reflect the formaction content attribute, except that on getting, when
		 * > the content attribute is missing or its value is the empty string, the element's node document's URL must
		 * > be returned instead.
		 *
		 * See https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#dom-fs-formaction
		 */
		const contentAttribute = event.submitter.getAttribute( 'formaction' );
		if (
			typeof contentAttribute === 'string' &&
			contentAttribute.trim() !== ''
		) {
			return event.submitter.formAction;
		}
	}
	const form = /** @type {HTMLFormElement} */ ( event.target );
	return form.action;
}

/**
 * Sets a cookie to indicate JavaScript is enabled  when the login form is submitted.
 *
 * Note that a hidden input field is not used because plugins like Two Factor may introduce interstitial login screens
 * which drop hidden fields when redirecting to a final authenticated state. See {@link https://github.com/WordPress/two-factor/issues/705}.
 *
 * @since 1.2.0
 * @param {SubmitEvent} event - Submit event.
 */
function setCookieOnLoginFormSubmit( event ) {
	const action = new URL(
		getFormActionFromSubmitEvent( event ),
		window.location.href
	);
	const loginPostUrl = new URL( data.loginPostUrl, window.location.href );

	if (
		action.origin === loginPostUrl.origin &&
		action.pathname === loginPostUrl.pathname
	) {
		document.cookie = `${ data.cookieName }=1; path=${ data.cookiePath }`;
		if ( data.cookiePath !== data.siteCookiePath ) {
			document.cookie = `${ data.cookieName }=1; path=${ data.siteCookiePath }`;
		}
	}
}

document.addEventListener( 'submit', setCookieOnLoginFormSubmit );
