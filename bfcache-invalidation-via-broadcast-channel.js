/**
 * Invalidates bfcache via Broadcast Channel.
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
 * This is a JavaScript module, so the global namespace is not polluted.
 *
 * @since 1.1.0
 *
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#broadcastchannel-message}
 * @see {@link https://github.com/whatwg/html/issues/7253#issuecomment-2632953500}
 * @see {@link https://github.com/mozilla-firefox/firefox/blob/dc64a7e82ff4e2e31b7dafaaa0a9599640a2c87c/testing/web-platform/tests/html/browsers/browsing-the-web/back-forward-cache/broadcastchannel/evict-on-message.tentative.window.js}
 */

const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById(
		'wp-script-module-data-@westonruter/bfcache-invalidation-via-broadcast-channel'
	)
);

/**
 * Exports from PHP.
 *
 * @type {{
 *     channelName: string,
 * }}
 */
const data = JSON.parse( jsonScript.text );

const bc = new BroadcastChannel( data.channelName );
bc.postMessage( true ); // The value is irrelevant.
