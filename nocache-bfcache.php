<?php
/**
 * Plugin Name: Instant Back/Forward
 * Plugin URI: https://github.com/westonruter/nocache-bfcache
 * Description: Enables back/forward cache (bfcache) for instant history navigations even when "nocache" headers are sent, such as when a user is logged in. <strong>To see the effect, you must log out and log back in again, ensuring "Remember Me" is checked.</strong>
 * Requires at least: 6.8
 * Requires PHP: 7.2
 * Version: 1.3.1
 * Author: Weston Ruter, WordPress Performance Team
 * Author URI: https://weston.ruter.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/westonruter/nocache-bfcache
 * Primary Branch: main
 *
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

// Abort executing the plugin if the core patch has been applied. See <https://github.com/WordPress/wordpress-develop/pull/9131>.
if ( defined( 'BFCACHE_SESSION_TOKEN_COOKIE' ) || function_exists( 'wp_enqueue_bfcache_script_module' ) ) {
	return;
}

/**
 * Version.
 *
 * @since 1.0.0
 * @access private
 * @var string
 */
const VERSION = '1.3.1';

/**
 * Plugin file.
 *
 * @since 1.1.0
 * @access private
 * @var string
 */
const PLUGIN_FILE = __FILE__;

/**
 * Script and style registration.
 */
require_once __DIR__ . '/includes/script-loader.php';

/**
 * User opt-in for BFCache.
 */
require_once __DIR__ . '/includes/bfcache-opt-in.php';

/**
 * Invalidating pages from bfcache.
 */
require_once __DIR__ . '/includes/bfcache-invalidation.php';
