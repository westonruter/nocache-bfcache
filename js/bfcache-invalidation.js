/**
 * Invalidates pages stored in bfcache and the HTTP cache when the session token changes.
 *
 * When a user authenticates, a session token is set on a cookie which can be read by JavaScript. Thereafter, when an
 * authenticated page is loaded, the HTML is served with this session token in a JSON script. When a user navigates back
 * to an authenticated page via bfcache (detected via the `pageshow` event handler with `persisted` property set to
 * true), if the current cookie's value does not match the previously captured value, then JavaScript forcibly reloads
 * the page. This also happens when the page is restored from the HTTP cache, when the cookie's value is compared with
 * the value of the session token in the HTML. When a new user logs in, a different session token is stored in the
 * cookie; when a user logs out, this cookie is cleared. This ensures that an authenticated page loaded for a specific
 * user is able to be restored from bfcache.
 *
 * This is a JavaScript module, so the global namespace is not polluted.
 *
 * @since 1.0.0
 */

/**
 * Script module ID.
 *
 * @since 1.1.0
 * @type {string}
 */
const moduleId = '@nocache-bfcache/bfcache-invalidation';

/**
 * JSON script containing the PHP exports.
 *
 * @since 1.0.0
 * @type {HTMLScriptElement}
 */
const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById( `wp-script-module-data-${ moduleId }` )
);

const bfcacheInvalidatedStorageKey = 'nocache_bfcache_invalidated';

/**
 * Exports from PHP.
 *
 * @since 1.0.0
 * @type {{
 *     cookieName: string,
 *     initialSessionToken: string,
 *     invalidatedPageReloadedQueryParam: string,
 *     debug: boolean,
 *     i18n: {
 *         logPrefix: string,
 *         pageInvalidating: string,
 *         pageInvalidated: string,
 *         pageRestored: string,
 *         notRestoredReasons: string,
 *     }
 * }}
 */
const data = JSON.parse( jsonScript.text );

/**
 * Logs info on the console.
 *
 * @since 1.2.0
 * @param {any[]} args
 */
function logInfo( ...args ) {
	window.console.info( data.i18n.logPrefix, ...args );
}

/**
 * Logs warning on the console.
 *
 * @since 1.2.0
 * @param {any[]} args
 */
function logWarning( ...args ) {
	window.console.warn( data.i18n.logPrefix, ...args );
}

/**
 * Gets the current bfcache session token from a cookie.
 *
 * A change to the session token indicates that the bfcache needs to be invalidated.
 *
 * @since 1.0.0
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
 * Invalidate the cache for the current page by wiping out the page contents and reloading.
 *
 * This should not result in an infinite reload because this JS module is only ever served on authenticated pages which
 * should never be cached in a proxy due to the private directive on the Cache-Control header. However, it is possible
 * that the page could have been served from a separate cache via a service worker.
 *
 * @todo Should there be a safeguard to prevent automatic reloads?
 */
function invalidateCache() {
	if ( data.debug ) {
		sessionStorage.setItem( bfcacheInvalidatedStorageKey, '1' );
	}

	// Immediately clear out the contents of the page since otherwise the authenticated content will appear while the page reloads.
	document.documentElement.innerHTML = '';

	const reload = () => {
		window.location.reload();
	};

	// Reload the page to get a copy with the current session token.
	if ( data.debug ) {
		logInfo( data.i18n.pageInvalidating );
		const p = document.createElement( 'p' );
		p.textContent = data.i18n.pageInvalidating;
		document.body.append( p );
		setTimeout( reload, 3000 );
	} else {
		reload();
	}
}

/**
 * Reloads the page when navigating to a page via bfcache and the session has changed.
 *
 * @since 1.0.0
 * @param {PageTransitionEvent} event - The pageshow event object.
 */
function onPageShow( event ) {
	if ( data.debug ) {
		if ( event.persisted ) {
			logInfo( data.i18n.pageRestored );
		} else if ( sessionStorage.getItem( bfcacheInvalidatedStorageKey ) ) {
			logInfo( data.i18n.pageInvalidated );
		}
		sessionStorage.removeItem( bfcacheInvalidatedStorageKey );
	}

	// In the case of bfcache, the event.persisted property is true, and a local variable from the restored JavaScript
	// heap is looked at.
	if (
		event.persisted &&
		data.initialSessionToken !== getCurrentSessionToken()
	) {
		invalidateCache();
	}
}

// Log out reasons for why the page was not restored from bfcache when WP_DEBUG is enabled.
if ( data.debug ) {
	const [ navigationEntry ] = performance.getEntriesByType( 'navigation' );
	if (
		'notRestoredReasons' in navigationEntry &&
		null !== navigationEntry.notRestoredReasons
	) {
		logWarning(
			data.i18n.notRestoredReasons,
			navigationEntry.notRestoredReasons
		);
	}
}

/*
 * When the page is restored from the HTTP cache, as early as possible invalidate the page if the session token served
 * with the page's HTML no longer matches the value of the session token cookie. Otherwise, add a pageshow event
 * listener to check later when/if the page is restored via bfcache when the event's persisted property is true.
 */
if ( data.initialSessionToken !== getCurrentSessionToken() ) {
	invalidateCache();
} else {
	window.addEventListener( 'pageshow', onPageShow );
}
