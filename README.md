# No-cache BFCache #

Contributors: [westonruter](https://profile.wordpress.org/westonruter)  
Tested up to: 6.8  
Stable tag:   1.0.0  
License:      [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) or later  
Tags:         performance

## Description ##

Enables back/forward cache (bfcache) for instant history navigations even when "nocache" headers are sent.

Normally WordPress sends a `Cache-Control` header with the `no-store` directive when logged in. This has the effect of [breaking the browser's bfcache](https://web.dev/articles/bfcache#minimize-no-store), which means that navigating back or forward in the browser requires the pages to be re-fetched from the server and for any JavaScript on the page to re-execute. The result can be a sluggish navigation experience not only when navigating around the WP Admin but also potentially when navigating around the frontend of a site. Furthermore, the lack of bfcache can result in data loss when data has been entered via a JavaScript-built UI since this state is lost when a page is not restored via bfcache. (See [demo video](https://github.com/woocommerce/woocommerce/pull/58445#issuecomment-3014404754) in WooCommerce.)

Note that Chrome [may now](https://developer.chrome.com/docs/web-platform/bfcache-ccns) still serve pages served with `no-store` from bfcache, although there are still failure scenarios in which bfcache will still be blocked. These can be observed in the "Back/forward cache" panel in the Application tab of Chrome DevTools, for example:

* `JsNetworkRequestReceivedCacheControlNoStoreResource`: JavaScript on a page makes a request to a resource served with the `no-store` directive (e.g. REST API or admin-ajax).
* `CacheControlNoStoreCookieModified`: JavaScript on a page modifies cookies.

These scenarios happen frequently when browsing the WP Admin, and they occur frequently on the frontend when using plugins like WooCommerce or BuddyPress. Such bfcache failures can also occur when not being logged in to WordPress, as it can happen whenever a site calls `nocache_headers()`. For example, WooCommerce currently calls `nocache_headers()` when an unauthenticated user is on the Cart, Checkout, or My Account pages (but see [woocommerce#58445](https://github.com/woocommerce/woocommerce/pull/58445) which proposes removing this). These failure scenarios do not occur when the `no-store` directive is omitted from the `Cache-Control` header.

This plugin strips out the `no-store` directive when it is present while ensuring that the `private` directive is sent in its place. (If your site absolutely needs `no-store` for some reason, then don't use this plugin.) The reason behind why the `no-store` directive was added in the first place was to prevent proxies from caching private page responses. However, there is the more appropriate [`private` directive](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control#private) for this purpose:

> The `private` response directive indicates that the response can be stored only in a private cache (e.g., local caches in browsers).
>
> You should add the `private` directive for user-personalized content, especially for responses received after login and for sessions managed via cookies.
>
> If you forget to add `private` to a response with personalized content, then that response can be stored in a shared cache and end up being reused for multiple users, which can cause personal information to leak.

This is in contrast with the [`no-store` directive](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control#no-store) which prevents caching by proxies _and_ by the browser's bfcache:

> The `no-store` response directive indicates that any caches of any kind (private or shared) should not store this response.

In addition to replacing `no-store` with `private`, this plugin also adds `no-cache`, `max-age=0`, and `must-revalidate` and ensures `public` is removed all to further guard against any misconfigured proxy from caching the private response.

There is one additional reason for why the `no-store` directive is used and that is due to a potential privacy issue where an authenticated user may log out of WordPress, only for another person to access the computer and click the back button in order to view the contents of the authenticated page loaded from the bfcache. (See [#21938](https://core.trac.wordpress.org/ticket/21938).) In practice this issue depends on the user being on a shared computer, and it also requires the malicious user to act soon since the bfcache has a timeout ([10 minutes](https://developer.chrome.com/docs/web-platform/bfcache-ccns#:~:text=The%20bfcache%20timeout%20for%20Cache%2DControl%3A%20no%2Dstore%20pages%20is%20also%20reduced%20to%203%20minutes%20(from%2010%20minutes%20used%20for%20pages%20which%20don%27t%20use%20Cache%2DControl%3A%20no%2Dstore)%20to%20further%20reduce%20risk.) in Chrome for pages sent without `no-store`).

In order to address this privacy concern, this plugin also has a safeguard to protect against restoring pages from bfcache after the user has logged out. This is achieved as follows: When authenticating to WordPress, a "bfcache session token" cookie is sent along with the other authentication cookies. This cookie is not HTTP-only so that it can be read in JavaScript; it is a random string not used for any other purpose. When an authenticated page is served, a script is included which reads the value of this cookie. When a user navigates away from the page and then navigates back to it, a `pageshow` event handler checks to see if it was restored from bfcache. If so, it checks the latest value of the cookie, and if the value doesn't match, the contents of the page are cleared and the page is reloads so that the contents are not available.

Another scenario this plugin implements is invalidating a page restored from a closed tab when the authentication state has changed. Other currently open tabs for the given session are not invalidated as soon as one of the tabs in the session is signed out, which is the same as the default core behavior.

The logic in this plugin is also proposed in a [core patch](https://github.com/WordPress/wordpress-develop/pull/9131) for [#63636](https://core.trac.wordpress.org/ticket/63636): Enable instant page navigations from browser history via bfcache when sending "nocache" headers.

Relevant core tickets that this revisits:

* [#21938](https://core.trac.wordpress.org/ticket/21938): Add "no-store" to Cache-Control header to prevent history caching of admin resources
* [#55491](https://core.trac.wordpress.org/ticket/55491): Replace `unload` event handlers from core
* [#57627](https://core.trac.wordpress.org/ticket/57627): The Cache-Control header for logged-in pages should include `private`
* [#61942](https://core.trac.wordpress.org/ticket/61942): Add "no-store" to Cache-Control header to prevent unexpected cache behavior

## Installation ##

1. Download the plugin [ZIP from GitHub](https://github.com/westonruter/bfcache/archive/refs/heads/main.zip) or if you have a local clone of the repo, run `npm run plugin-zip`.
2. Visit **Plugins > Add New Plugin** in the WordPress Admin.
3. Click **Upload Plugin**.
4. Select the `nocache-bfcache.zip` file on your system from step 1 and click **Install Now**.
5. Click the **Activate Plugin** button.

You may also install and update via [Git Updater](https://git-updater.com/).

## Changelog ##

### 1.0.0 ###

* Initial release.
