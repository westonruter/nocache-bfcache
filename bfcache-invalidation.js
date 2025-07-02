// This is a JavaScript module, so the global namespace is not polluted.

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById(
		'wp-script-module-data-@westonruter/bfcache-invalidation'
	)
);

const latestSessionTokenStorageKey = 'nocache_bfcache_latest_session_token';

/**
 * Exports from PHP.
 *
 * @type {{
 *     cookieName: string,
 *     interimLoginBroadcastChannelName: string,
 *     debug: boolean,
 * }}
 */
const data = JSON.parse( jsonScript.text );

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

/**
 * Broadcast channel which listens to updates from the interim login screen.
 *
 * Make sure that the bfcache session token is updated whenever the interim login is shown or successfully closed.
 * A message to the 'nocache_bfcache_interim_login' BroadcastChannel is sent at the `login_footer` action for the
 * interim login screen. A message is sent when a login is prompted due to the session expiring, and another message is
 * sent after a successful login. Whether the message is for a successful login or not is not relevant as in both cases
 * we just need to read the cookie to get the current bfcache session token (which may be none).
 *
 * There is another benefit to the use of BroadcastChannel: when a page in bfcache receives a message from
 * BroadcastChannel, the page may be automatically be blocked from being restored. This ensures that if another user logs
 * in via the interim login, they are not able to go back to access an authenticated page for the previously
 * authenticated user. Otherwise, if the page wasn't already automatically invalidated from bfcache in this instance,
 * it would have been invalidated via the `pageshow` event below since the `latestSessionToken` in the frozen page in
 * bfcache would not match the current session token. In Chrome DevTools, the bfcache error code here is
 * `BroadcastChannelOnMessage`. In the PerformanceObserver, this is exposed in `notRestoredReasons` as the
 * "broadcastchannel-message" blocking reason. Chrome has implemented this.
 *
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message}
 * @see {@link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500}
 *
 * TODO: The wp-auth-check iframe should be made inert when it is hidden. Currently the back button seems to be navigating in the iframe after re-auth.
 *
 * @type {BroadcastChannel}
 */
const interimLoginBroadcastChannel = new window.BroadcastChannel(
	data.interimLoginBroadcastChannelName
);
interimLoginBroadcastChannel.addEventListener( 'message', () => {
	sessionStorage.setItem(
		latestSessionTokenStorageKey,
		String( getCurrentSessionToken() )
	);
} );

/**
 * Reloads the page when navigating to a page via bfcache or via re-opening a closed tab, and the session has changed.
 *
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	window.console.info( 'pageshow, persisted:', event.persisted ); // TODO: Debug.

	const currentSessionTokenString = String( getCurrentSessionToken() );

	const latestSessionToken = sessionStorage.getItem(
		latestSessionTokenStorageKey
	);

	// Populate the sessionStorage key with the current session token so that it will be available going
	// forward when this page is restored from bfcache or the page is restored from a closed tab.
	// This also prevents an infinite reload from happening.
	sessionStorage.setItem(
		latestSessionTokenStorageKey,
		currentSessionTokenString
	);

	// If the hidden field was populated, then we know the page was either restored from bfcache or from a closed tab.
	// In the case of bfcache, the event.persisted property is true, and a local variable could be looked at, but this
	// is not the case for a page restored from a closed tab, so this is why sessionStorage is used. If the value does
	// not match the current session token, then the authentication state has changed and the page needs to be reloaded.
	if (
		latestSessionToken &&
		latestSessionToken !== currentSessionTokenString
	) {
		// Immediately clear out the contents of the page since otherwise the authenticated content will appear while the page reloads.
		document.body.innerHTML = '';

		// TODO: Problem: This can cause a reload to occur when doing a regular navigation in another tab after re-authenticating.
		window.location.reload();
	}
}

window.addEventListener( 'pageshow', onPageShow );

// Log out reasons for why the page was not restored from bfcache when WP_DEBUG is enabled.
if ( data.debug ) {
	const [ navigationEntry ] = performance.getEntriesByType( 'navigation' );
	if (
		'notRestoredReasons' in navigationEntry &&
		null !== navigationEntry.notRestoredReasons
	) {
		window.console.warn(
			'[No-cache BFCache] Reasons page navigation not restored from bfcache:',
			navigationEntry.notRestoredReasons
		);
	}
}
