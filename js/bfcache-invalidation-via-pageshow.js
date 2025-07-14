/**
 * Invalidates bfcache via the pageshow event.
 *
 * When a user authenticates, a session token is set on a cookie which can be read by JavaScript. Thereafter, when an
 * authenticated page is loaded, the value of the cookie is captured in a local variable. When a user navigates back to
 * an authenticated page via bfcache (detected via the `pageshow` event handler with `persisted` property set to true),
 * if the current cookie's value does not match the previously captured value, then JavaScript forcibly reloads the
 * page. When a new user logs in, a different session token is stored in the cookie; when a user logs out, this cookie
 * is cleared. This ensures that an authenticated page loaded for a specific user is able to be restored from bfcache.
 *
 * This is a JavaScript module, so the global namespace is not polluted.
 *
 * @since 1.0.0
 */

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById(
		'wp-script-module-data-@nocache-bfcache/bfcache-invalidation-via-pageshow'
	)
);

const bfcacheInvalidatedStorageKey = 'nocache_bfcache_invalidated';

/**
 * Exports from PHP.
 *
 * @type {{
 *     cookieName: string,
 *     loginBroadcastChannelName: string,
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
 * Broadcast channel for updates from the login screen in order to invalidate the bfcache.
 *
 * When a page in bfcache receives a message from BroadcastChannel, the page may be automatically be blocked from being
 * restored. This ensures that if another user logs in that they are not able to go back to access an authenticated page
 * for the previously authenticated user. Otherwise, if the page wasn't already automatically invalidated from bfcache
 * in this instance, it would have been invalidated via the `pageshow` event below since the `initialSessionToken` in
 * the frozen page in bfcache would not match the current session token. In Chrome DevTools, the bfcache error code here
 * is `BroadcastChannelOnMessage`. In the PerformanceObserver, this is exposed in `notRestoredReasons` as the
 * "broadcastchannel-message" blocking reason. Both Chrome and Firefox seem to have implemented this, but Safari has not.
 *
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message}
 * @see {@link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500}
 * @see {@link https://github.com/mozilla-firefox/firefox/blob/dc64a7e82ff4e2e31b7dafaaa0a9599640a2c87c/testing/web-platform/tests/html/browsers/browsing-the-web/back-forward-cache/broadcastchannel/evict-on-message.tentative.window.js}}
 *
 * TODO: When re-authenticating via the wp-auth-check iframe a new history entry seems to be added in the browser (at least in Chrome) meaning the back button has to be hit twice to go to the previous page. This is an existing issue in core.
 *
 * @type {BroadcastChannel}
 */
const loginBroadcastChannel = new window.BroadcastChannel(
	data.loginBroadcastChannelName
);
loginBroadcastChannel.addEventListener( 'message', () => {
	// The only purpose of this listener is to trigger the "broadcastchannel-message" bfcache blocking reason.
} );

/**
 * Initial session token.
 *
 * @type {string|null}
 */
const initialSessionToken = getCurrentSessionToken();

/**
 * Reloads the page when navigating to a page via bfcache and the session has changed.
 *
 * This seems to only be needed by Safari since both Chrome and Firefox evict pages from bfcache when the login screen
 * messages are received via BroadcastChannel, in which case the "broadcastchannel-message" blocking reason occurs.
 *
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	if ( data.debug ) {
		if ( event.persisted ) {
			window.console.info(
				'[No-cache BFCache] Page restored from bfcache.'
			);
		} else if ( sessionStorage.getItem( bfcacheInvalidatedStorageKey ) ) {
			window.console.info(
				'[No-cache BFCache] Page invalidated from cache via pageshow event handler.'
			);
		}
		sessionStorage.removeItem( bfcacheInvalidatedStorageKey );
	}

	// In the case of bfcache, the event.persisted property is true, and a local variable from the restored JavaScript
	// heap is looked at.
	if ( event.persisted && initialSessionToken !== getCurrentSessionToken() ) {
		if ( data.debug ) {
			sessionStorage.setItem( bfcacheInvalidatedStorageKey, '1' );
		}

		// Immediately clear out the contents of the page since otherwise the authenticated content will appear while the page reloads.
		document.body.innerHTML = '';
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
