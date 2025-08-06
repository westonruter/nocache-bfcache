# No-cache BFCache

Enables back/forward cache (bfcache) for instant history navigations even when “nocache” headers are sent, such as when a user is logged in.

![Banner](.wordpress-org/banner.svg)

**Contributors:** [westonruter](https://profile.wordpress.org/westonruter), [wordpressdotorg](https://profile.wordpress.org/wordpressdotorg), [performanceteam](https://profile.wordpress.org/performanceteam)  
**Tags:**         performance, caching  
**Tested up to:** 6.8  
**Stable tag:**   1.3.0  
**License:**      [GPLv2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## Description

This plugin enables instant back/forward navigation via the browser's [bfcache](https://developer.mozilla.org/en-US/docs/Glossary/bfcache). It does this by removing the `no-store` directive from the `Cache-Control` response header, which WordPress sends by default when `nocache_headers()` is called. This happens primarily when a user is logged in, but some plugins may send these “no-cache” headers such as on the Cart or Checkout pages for an e-commerce site. **Upon activation, to see the effect, you must log out of WordPress and log back in again, ensuring “Remember Me” is checked.** Even so, another plugin, theme or server configuration may be active which makes pages [ineligible for bfcache](https://web.dev/articles/bfcache#optimize) due to other [blocking reasons](https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#blocking_reasons). Nevertheless, the removal of `no-store` will _still speed up back/forward navigations_ since pages may then be served from the browser's HTTP cache, eliminating the need to re-download the HTML from the server. This is a feature plugin to implement [#63636](https://core.trac.wordpress.org/ticket/63636) in WordPress core.

Blog post: [Instant Back/Forward Navigations in WordPress](https://weston.ruter.net/2025/07/23/instant-back-forward-navigations-in-wordpress/)

The speed of page navigations in WordPress saw a big boost in 6.8 with the introduction of [Speculative Loading](https://make.wordpress.org/core/2025/03/06/speculative-loading-in-6-8/). However, by default Speculative Loading in WordPress is not configured to enable _instant_ page loads, which requires a non-conservative eagerness with the prerender mode; not all sites can even opt in to prerendering due to compatibility issues, such as with analytics, and due to concerns about sustainability with unused prerenders (e.g. increasing server load and taxing a user's bandwidth/CPU). While Speculative Loading (i.e. the Speculation Rules API) is relatively new and currently only supported in Chromium browsers (e.g. Chrome and Edge), there is a much older web platform technology that enables prerendering _and_ which is supported in all browsers: the back/forward cache (bfcache). This instant loading involves no network traffic and no CPU load, since previously visited pages are stored in memory. According to the web.dev article on [back/forward cache](https://web.dev/articles/bfcache):

**Chrome usage data shows that 1 in 10 navigations on desktop and 1 in 5 on mobile are either back or forward. With bfcache enabled, browsers could eliminate the data transfer and time spent loading for billions of web pages every single day!**

Also learn more via the following video:

[![Debugging bfcache, make your page load instantly #DevToolsTips](https://img.youtube.com/vi/Y2IVv5KnrmI/maxresdefault.jpg)](https://youtu.be/Y2IVv5KnrmI)

Normally, WordPress sends a `Cache-Control` header with the `no-store` directive when a user is logged in. This has the effect of [breaking the browser's bfcache](https://web.dev/articles/bfcache#minimize-no-store), which means that navigating back or forward in the browser requires the pages to be re-fetched from the server and for any JavaScript on the page to re-execute. The result can be a sluggish navigation experience not only when navigating around the WP Admin (see [Jetpack demo video](https://github.com/Automattic/jetpack/pull/44322#:~:text=page%20load%20is%3A-,Without%20bfcache,-With%20bfcache) and demo video below) but also when navigating around the frontend of a site. Furthermore, the lack of bfcache can cause data loss when data has been entered via a JavaScript-built UI since this state is lost when a page is not restored via bfcache (see [WooCommerce demo video](https://github.com/woocommerce/woocommerce/pull/58445#issuecomment-3014404754) and demo video below).

The reason why the `no-store` directive was added in the first place was due to a privacy concern where an authenticated user may log out of WordPress, only for another person to access the computer and click the back button to view the contents of the authenticated page loaded from bfcache or the HTTP cache. (See [#21938](https://core.trac.wordpress.org/ticket/21938).) In practice this issue depends on the user being on a shared computer who didn't exit the browser, and it requires the malicious user to act soon before the page is evicted from bfcache (e.g. Chrome as a [10-minute timeout](https://developer.chrome.com/docs/web-platform/bfcache-ccns#:~:text=The%20bfcache%20timeout%20for%20Cache%2DControl%3A%20no%2Dstore%20pages%20is%20also%20reduced%20to%203%20minutes%20(from%2010%20minutes%20used%20for%20pages%20which%20don%27t%20use%20Cache%2DControl%3A%20no%2Dstore)%20to%20further%20reduce%20risk.)).

To address this privacy concern, a safeguard is in place to protect against restoring pages from bfcache and the HTTP cache after the user has logged out:

When authenticating to WordPress, a “bfcache session token” cookie is set along with the other authentication cookies. This cookie is not HTTP-only so that it can be read in JavaScript; it is a random string not used for any other purpose. When an authenticated page is served, this bfcache session token is included in the HTML as well as a script which reads the value of this cookie. When a user navigates away from the page and then navigates back to it, a script on the page checks if the current session token in the cookie matches the initial session token sent with the page. If they do not match (e.g. because the user has logged out or another user has logged in), then the contents of the page are cleared and the page is reloaded so that the contents are not available.

Since JavaScript is required to invalidate cached pages, the login form is extended to pass along whether scripting is enabled. Only when JS is enabled will the `no-store` directive be omitted from the `Cache-Control` response header. This ensures that users with JavaScript turned off will retain the privacy protection after logging out. Lastly, `no-store` is also only omitted if the user checked the “Remember Me” checkbox on the login form. Since it is highly unlikely a user on a shared computer would have checked this checkbox, this provides yet an additional safeguard (which may in the end prove excessive). A ✨ emoji is displayed next to the checkbox in a button that opens a popover that promotes the capability. If you want to opt out of this opt-in (and the sparkle) so that all logged-in users get bfcache, you can use the `nocache_bfcache_use_remember_me_as_opt_in` filter which you can use in a custom plugin or your theme:

```php
add_filter( 'nocache_bfcache_use_remember_me_as_opt_in', '__return_false' );
```

When this plugin strips out the `no-store` directive, it also ensures that the [`private` directive](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control#private) is sent in its place: “The `private` response directive indicates that the response can be stored only in a private cache (e.g., local caches in browsers).” WordPress is already sending `private` as of [#57627](https://core.trac.wordpress.org/ticket/57627). This directive ensures that proxies do not cache authenticated pages. In addition to ensuring `private` is present, this plugin also adds `no-cache`, `max-age=0`, and `must-revalidate` while ensuring `public` is removed, all to further guard against any misconfigured proxy from caching the private response.

### Demo: Navigating the WordPress Admin

Without bfcache:

[![Navigating the WordPress Admin without Back/forward Cache (bfcache)](https://img.youtube.com/vi/Cz-_L6q9ZRc/maxresdefault.jpg)](https://youtu.be/Cz-_L6q9ZRc?t=22)

With bfcache:

[![Navigating the WordPress Admin with Back/forward Cache (bfcache)](https://img.youtube.com/vi/z4dILiwe0Rk/maxresdefault.jpg)](https://youtu.be/z4dILiwe0Rk?t=27)

### Demo: Navigating the WordPress Frontend

**Without bfcache:** The drafted BuddyPress activity update is lost when navigating away from the page before submitting. The activity feed and Tweet have to be reconstructed with each back/forward navigation.

[![Navigating the WordPress Frontend without Back/forward Cache (bfcache)](https://img.youtube.com/vi/Ko1w_SRlCig/maxresdefault.jpg)](https://youtu.be/Ko1w_SRlCig)

**With bfcache:** The drafted BuddyPress activity update is preserved when navigating away from the page without submitting. The activity feed and Tweet do not have to be reconstructed when navigating to previously visited pages via the back/forward buttons.

[![Navigating the WordPress Admin with Back/forward Cache (bfcache)](https://img.youtube.com/vi/bzGm6LcHHAs/maxresdefault.jpg)](https://youtu.be/bzGm6LcHHAs)

## Installation

### Installation from within WordPress

1. Visit **Plugins > Add New** in the WordPress Admin.
2. Search for **No-cache BFCache**.
3. Install and activate the **No-cache BFCache** plugin.
4. Log out of WordPress and log back in with the “Remember Me” checkbox checked.

You may also install and update via [Git Updater](https://git-updater.com/) using the [plugin's GitHub URL](https://github.com/westonruter/nocache-bfcache).

### Manual Installation

1. Download the [plugin ZIP](https://downloads.wordpress.org/plugin/nocache-bfcache.zip) from WordPress.org. (You may also download a development [ZIP from GitHub](https://github.com/westonruter/nocache-bfcache/archive/refs/heads/main.zip); alternatively, if you have a local clone of [the repo](https://github.com/westonruter/nocache-bfcache), run `npm run plugin-zip`.)
2. Visit **Plugins > Add New Plugin** in the WordPress Admin.
3. Click **Upload Plugin**.
4. Select the `nocache-bfcache.zip` file on your system from step 1 and click **Install Now**.
5. Click the **Activate Plugin** button.
6. Log out of WordPress and log back in with the “Remember Me” checkbox checked.

## FAQ

### What about stale content being served in cached pages?

Please see the [Stale Content in Page Caches](https://weston.ruter.net/2025/07/23/instant-back-forward-navigations-in-wordpress/#stale-content-in-page-caches) section of the above blog post. 

### What WordPress core tickets does this plugin relate to?

The functionality in this plugin is proposed for WordPress core in Trac ticket [#63636](https://core.trac.wordpress.org/ticket/63636): Enable instant page navigations from browser history via bfcache when sending “nocache” headers.

Other relevant core tickets that this revisits:

* [#21938](https://core.trac.wordpress.org/ticket/21938): Add “no-store” to `Cache-Control` header to prevent history caching of admin resources
* [#55491](https://core.trac.wordpress.org/ticket/55491): Replace `unload` event handlers from core
* [#57627](https://core.trac.wordpress.org/ticket/57627): The Cache-Control header for logged-in pages should include `private`
* [#61942](https://core.trac.wordpress.org/ticket/61942): Add “no-store” to `Cache-Control` header to prevent unexpected cache behavior

### Why not use the Clear-Site-Data header?

Instead of using the `pageshow` event handler, an alternative method to evict pages from bfcache is to send the `Clear-Site-Data: "cache"` at logout. Per [MDN](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Clear-Site-Data#cache), this header “sends a signal to the client that it should remove all browsing data of certain types (cookies, storage, cache) associated with the requesting website.” This header is supposedly supported by 91%+ of users according to [Can I Use…](https://caniuse.com/mdn-http_headers_clear-site-data_cache) and it is "Baseline 2023 Newly available" but with an asterisk. In testing, only Chromium-based browsers (e.g. Chrome and Edge) seem to evict pages from bfcache when this header is sent, _but_ there is currently a bug ([40233601](https://issues.chromium.org/issues/40233601)) where responses with this header can take 10-30 seconds to load. Furthermore, Firefox does not currently evict pages from bfcache with this header, but like Chromium browsers, it does evict pages from HTTP cache, meaning authenticated pages will not be accessible when reopening closed browser tabs. Safari, however, does not seem to evict pages from either bfcache or the HTTP cache. Lastly, `Clear-Site-Data` only works in a secure context (i.e. over HTTPS), meaning insecure sites still on HTTP would have yet another concern.

For all these reasons, `Clear-Site-Data` is not yet a reliable method to invalidate pages from bfcache. Hopefully the Chromium bug will be fixed in the near future.

The `Clear-Site-Data` header was also mentioned in Trac tickets [#49258](https://core.trac.wordpress.org/ticket/49258#comment:3) and [#57627](https://core.trac.wordpress.org/ticket/57627#comment:1).

### Why is bfcache working in Chrome even though my site is using Cache-Control: no-store?

Chrome [may even now](https://developer.chrome.com/docs/web-platform/bfcache-ccns) store pages served with `no-store` in bfcache, although there are still failure scenarios in which bfcache will still be blocked. These can be observed in the “Back/forward cache” panel in the Application tab of Chrome DevTools, for example:

* `JsNetworkRequestReceivedCacheControlNoStoreResource`: JavaScript on a page makes a request to a resource served with the `no-store` directive (e.g. REST API or admin-ajax).
* `CacheControlNoStoreCookieModified`: JavaScript on a page modifies cookies.

These scenarios happen frequently when browsing the WP Admin, and they occur frequently on the frontend when using plugins like WooCommerce or BuddyPress. Such bfcache failures can also occur when not being logged in to WordPress, as it can happen whenever a site calls `nocache_headers()`. For example, WooCommerce currently calls `nocache_headers()` when an unauthenticated user is on the Cart, Checkout, or My Account pages (but see [woocommerce#58445](https://github.com/woocommerce/woocommerce/pull/58445) which has been merged to remove this as of v10.1). These failure scenarios do not occur when the `no-store` directive is omitted from the `Cache-Control` header.

### What is preventing bfcache from working?

See the [Back/forward cache](https://web.dev/articles/bfcache) article on web.dev for [reasons](https://web.dev/articles/bfcache#optimize) why bfcache may be blocked. See also the list of [blocking reasons](https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Monitoring_bfcache_blocking_reasons#blocking_reasons) on MDN. See also the YouTube video on [Debugging bfcache, make your page load instantly](https://youtu.be/Y2IVv5KnrmI).

If you can identify the plugin or theme which is setting `Cache-Control: no-store` or doing something else that blocks bfcache (like adding an `unload` event handler), please report the issue to the respective plugin/theme support forum.

The [Performance Lab](https://wordpress.org/plugins/performance-lab/) plugin also includes a [Site Health test](https://github.com/WordPress/performance/issues/1692) for whether the server is sending the `Cache-Control: no-store` header.

### How can I enable bfcache when Jetpack is active?

When Jetpack is active, you may see that bfcache isn't working on any page and that the “Back/forward cache” panel of Chrome DevTools says:

_Pages with WebSocket cannot enter back/forward cache._

Here you'll also see:

_Pending Support: Chrome support for these reasons is pending i.e. they will not prevent the page from being eligible for back/forward cache in a future version of Chrome._

The reason for this is likely the “[Notifications](https://jetpack.com/support/notifications/)” module of Jetpack, which shows up as a bell icon in the top right of the admin bar. If you do not rely on this feature of Jetpack, you can enable bfcache by going to WP Admin > Jetpack > Settings and in the footer click “Modules”. Here you can disable the Notifications module.

Aside from this, bfcache may be disabled on some Jetpack screens because the plugin is still sending `no-store`. A [pull request](https://github.com/Automattic/jetpack/pull/44322) has been opened to remove these.

Lastly, the Akismet screen has an `iframe` which contains a page with an `unload` event listener. This event [should never be used](https://web.dev/articles/bfcache#never-use-the-unload-event) anymore; the Akismet team should replace it with a more appropriate event, as was done in core ([#55491](https://core.trac.wordpress.org/ticket/55491)).

### Why is bfcache not working on my site hosted by Pantheon?

Pantheon sites have a [must-use plugin](https://github.com/pantheon-systems/pantheon-mu-plugin) which includes some [Page Cache functionality](https://github.com/pantheon-systems/pantheon-mu-plugin/blob/main/inc/pantheon-page-cache.php). When a user is logged in, it is currently sending a `Cache-Control: no-cache, no-store, must-revalidate` response header. This prevents bfcache from working. A [pull request](https://github.com/pantheon-systems/pantheon-mu-plugin/pull/94) has been opened to fix this, but in the meantime you may work around the issue by preventing this header from being sent with the following plugin code:

```php
// Workaround for Pantheon MU plugin sending Cache-Control: no-store which prevents bfcache.
// See https://github.com/pantheon-systems/pantheon-mu-plugin/pull/94
add_filter(
	'pantheon_skip_cache_control',
	static function (): bool {
		return is_admin() || is_user_logged_in();
	}
);
```

## Screenshots

### The “Remember Me” checkbox now has a ✨ button which opens a popover.

![WordPress login screen](.wordpress-org/screenshot-1.png)

### The popover describes the benefits of clicking the “Remember Me” checkbox.

![Popover on login screen](.wordpress-org/screenshot-2.png)

### Pages are served from bfcache where previously they would fail to do so with an issue like `MainResourceHasCacheControlNoStore` showing up in the “Back/forward cache” panel of the Application tab in Chrome DevTools.

![Chrome DevTools showing successful bfcache navigation](.wordpress-org/screenshot-3.png)

## Changelog

### 1.3.0

* Add `nocache_bfcache_use_remember_me_as_opt_in` filter to opt out of opt-in ([#29](https://github.com/westonruter/nocache-bfcache/pull/29))
* Ensure `is_user_logged_in()` function exists before using ([#37](https://github.com/westonruter/nocache-bfcache/pull/37))
* Update plugin-check to v1.6 and reuse PHPCS ruleset ([#27](https://github.com/westonruter/nocache-bfcache/pull/27))

### 1.2.0

* Add path data for CSS file ([#15](https://github.com/westonruter/nocache-bfcache/pull/15))
* Only run update-dotorg-assets manually on workflow_dispatch ([#16](https://github.com/westonruter/nocache-bfcache/pull/16))
* Explored using `Clear-Site-Data` header for bfcache invalidation, but ultimately removed it due to browser inconsistencies ([#17](https://github.com/westonruter/nocache-bfcache/pull/17), [#20](https://github.com/westonruter/nocache-bfcache/pull/20))
* Eliminate Broadcast Channel bfcache eviction method ([#18](https://github.com/westonruter/nocache-bfcache/pull/18))
* Integrate with frontend login forms ([#19](https://github.com/westonruter/nocache-bfcache/pull/19))
* Improve robustness of detecting login form submissions ([#21](https://github.com/westonruter/nocache-bfcache/pull/21))
* Implement HTTP cache invalidation ([#23](https://github.com/westonruter/nocache-bfcache/pull/23))

### 1.1.0

* Add GHA workflow ([#1](https://github.com/westonruter/nocache-bfcache/pull/1))
* Integrate with interim login (wp-auth-check) modal ([#2](https://github.com/westonruter/nocache-bfcache/pull/2))
* Log out warning when page navigation is not restored from bfcache when `WP_DEBUG` is enabled ([#3](https://github.com/westonruter/nocache-bfcache/pull/3))
* Retain CCNS when the user has JavaScript disabled ([#4](https://github.com/westonruter/nocache-bfcache/pull/4))
* Improve bfcache invalidation via BroadcastChannel, store token in user session, promote feature on login ([#5](https://github.com/westonruter/nocache-bfcache/pull/5))
* Prepare for dotorg directory release ([#6](https://github.com/westonruter/nocache-bfcache/pull/6))
* Fix compatibility with the Two-Factor plugin and any plugins with interstitial login screens ([#7](https://github.com/westonruter/nocache-bfcache/pull/7))
* Fix bfcache invalidation via Broadcast Channel ([#8](https://github.com/westonruter/nocache-bfcache/pull/8))
* Improve docs with info about Jetpack and add FAQ ([#9](https://github.com/westonruter/nocache-bfcache/pull/9))
* Add FAQ for why bfcache may not work on Pantheon ([#10](https://github.com/westonruter/nocache-bfcache/pull/10))
* Add dotorg plugin directory assets ([#11](https://github.com/westonruter/nocache-bfcache/pull/11))
* Configure Dependabot ([#12](https://github.com/westonruter/nocache-bfcache/pull/12))
* Add deployment workflows ([#14](https://github.com/westonruter/nocache-bfcache/pull/14))

### 1.0.0

* Initial release.
