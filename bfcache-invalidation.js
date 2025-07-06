// This is a JavaScript module, so the global namespace is not polluted.

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById(
		'wp-script-module-data-@westonruter/bfcache-invalidation'
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
 * "broadcastchannel-message" blocking reason. Chrome has implemented this.
 *
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message}
 * @see {@link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500}
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
 * Reloads the page when navigating to a page via bfcache or via re-opening a closed tab, and the session has changed.
 *
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	if ( data.debug ) {
		const restoredItem = document.getElementById(
			'wp-admin-bar-nocache-bfcache-status-restored'
		);
		const evictedItem = document.getElementById(
			'wp-admin-bar-nocache-bfcache-status-evicted'
		);
		if ( restoredItem ) {
			restoredItem.classList.add( 'hidden' );
		}
		if ( evictedItem ) {
			evictedItem.classList.add( 'hidden' );
		}
		if ( event.persisted ) {
			if ( restoredItem ) {
				restoredItem.classList.remove( 'hidden' );
			}
			window.console.info(
				'[No-cache BFCache] Page restored from bfcache.'
			);
		} else if ( sessionStorage.getItem( bfcacheInvalidatedStorageKey ) ) {
			if ( evictedItem ) {
				evictedItem.classList.remove( 'hidden' );
			}
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
