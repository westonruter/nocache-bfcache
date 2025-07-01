// This is a JavaScript module, so the global namespace is not polluted.

const script = /** @type {HTMLScriptElement} */ (
	document.getElementById(
		'wp-script-module-data-@westonruter/bfcache-invalidation'
	)
);

/**
 * Exports from PHP.
 *
 * @type {{
 *     cookieName: string,
 *     interimLoginBroadcastChannelName: string,
 * }}
 */
const data = JSON.parse( script.text );

/**
 * Gets the current bfcache session token from a cookie.
 *
 * A change to the session token indicates that the bfcache needs to be
 * invalidated.
 *
 * @return {string|null} Session token if the cookie is set.
 */
function getCurrentSessionToken() {
	const re = new RegExp( '(?:^|;\\s*)' + data.cookieName + '=([^;]+)' );
	// Note that CookieStore is not being used since it requires HTTPS, and a synchronous API is preferable to more
	// quickly invalidate the bfcache in the pageshow event handler.
	const matches = document.cookie.match( re );
	return matches ? decodeURIComponent( matches[ 1 ] ) : null;
}

let latestSessionToken = getCurrentSessionToken();

/*
 * Make sure that the bfcache session token is updated whenever the interim login is shown or successfully closed.
 * A message to the 'nocache_bfcache_interim_login' BroadcastChannel is sent at the `login_footer` action for the
 * interim login screen. A message is sent when a login is prompted due to the session expiring, and another message is
 * sent after a successful login. Whether the message is for a successful login or not is not relevant as in both cases
 * we just need to read the cookie to get the current bfcache session token (which may be none).
 *
 * There is another benefit to the use of BroadcastChannel: when a page in bfcache receives a message from
 * BroadcastChannel, the page will automatically be blocked from being restored. This ensures that if another user logs
 * in via the interim login, they are not able to go back to access an authenticated page for the previously
 * authenticated user. Otherwise, if the page wasn't already automatically invalidated from bfcache in this instance,
 * it would have been invalidated via the `pageshow` event below since the `latestSessionToken` in the frozen page in
 * bfcache would not match the current session token. In Chrome DevTools, the bfcache error code here is
 * `BroadcastChannelOnMessage`. In the PerformanceObserver, this is exposed in `notRestoredReasons` as the
 * "broadcastchannel-message" blocking reason. See: <https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message>.
 *
 * TODO: The wp-auth-check iframe should be made inert when it is hidden. Currently the back button seems to be navigating in the iframe after re-auth.
 */
const interimLoginBroadcastChannel = new window.BroadcastChannel(
	data.interimLoginBroadcastChannelName
);
interimLoginBroadcastChannel.addEventListener( 'message', () => {
	latestSessionToken = getCurrentSessionToken();
} );

/**
 * Reloads the page when navigating to a page via bfcache and the authentication state has changed.
 *
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	// TODO: The persisted property is not actually relevant because it is not true when a closed tab is restored.
	if ( event.persisted && getCurrentSessionToken() !== latestSessionToken ) {
		// Immediately clear out the contents of the page since otherwise the authenticated content will appear while the page reloads.
		document.body.innerHTML = '';

		// TODO: Could there be a scenario where an infinite reload happens? No, because the pageshow event would not have the persisted property set to true after a reload.
		window.location.reload();
	}
}

window.addEventListener( 'pageshow', onPageShow );

// TODO: If WP_DEBUG enabled, log out the bfcache restore reasons.
