/**
 * Listens for a message broadcast from the login screen to invalidate pages in bfcache.
 *
 * When a page in bfcache receives a message from a BroadcastChannel, the page is automatically be evicted from bfcache
 * in Chrome, Edge, and Firefox. This ensures that if another user logs in that they are not able to go back to access
 * an authenticated page for the previously authenticated user. In Chrome DevTools, the bfcache error code here
 * is `BroadcastChannelOnMessage`. In the PerformanceObserver, this is exposed in `notRestoredReasons` as the
 * "broadcastchannel-message" blocking reason. Chrome, Edge, and Firefox all seem to have implemented this, but Safari
 * has not.
 *
 * For browsers that do not support this bfcache eviction mechanism, invalidation is handled separately via a `pageshow`
 * event handler.
 *
 * @since 1.1.0
 *
 * @see {@link https://issues.chromium.org/issues/40258982}
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message}
 * @see {@link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500}
 * @see {@link https://github.com/mozilla-firefox/firefox/blob/dc64a7e82ff4e2e31b7dafaaa0a9599640a2c87c/testing/web-platform/tests/html/browsers/browsing-the-web/back-forward-cache/broadcastchannel/evict-on-message.tentative.window.js}}
 *
 * This is a JavaScript module, so the global namespace is not polluted.
 */

const moduleId =
	'@nocache-bfcache/bfcache-invalidation-via-broadcast-channel-listener';

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById( `wp-script-module-data-${ moduleId }` )
);

/**
 * Exports from PHP.
 *
 * @type {{
 *     channelName: string,
 * }}
 */
const data = JSON.parse( jsonScript.text );

/**
 * Broadcast channel for updates from the login screen in order to invalidate the bfcache.
 *
 * @type {BroadcastChannel}
 */
const loginBroadcastChannel = new window.BroadcastChannel( data.channelName );
loginBroadcastChannel.addEventListener( 'message', () => {
	// The only purpose of this listener is to trigger the "broadcastchannel-message" bfcache blocking reason.
} );
