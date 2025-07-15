<?php
/**
 * Interface containing constants for script module IDs.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore
}

/**
 * Script module IDs.
 *
 * @since 1.1.0
 * @access private
 */
interface Script_Module_Ids {

	/**
	 * Script module ID for the bfcache opt-in.
	 *
	 * @since 1.1.0
	 * @access private
	 * @var string
	 */
	const BFCACHE_OPT_IN = '@nocache-bfcache/bfcache-opt-in';

	/**
	 * Script module ID for bfcache invalidation via the pageshow event.
	 *
	 * @since 1.1.0
	 * @access private
	 * @var string
	 */
	const BFCACHE_INVALIDATION_VIA_PAGESHOW = '@nocache-bfcache/bfcache-invalidation-via-pageshow';

	/**
	 * Script module ID for bfcache invalidation via Broadcast Channel (emitter).
	 *
	 * @since 1.1.0
	 * @access private
	 * @var string
	 */
	const BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_EMITTER = '@nocache-bfcache/bfcache-invalidation-via-broadcast-channel-emitter';

	/**
	 * Script module ID for bfcache invalidation via Broadcast Channel (listener).
	 *
	 * @since 1.1.0
	 * @access private
	 * @var string
	 */
	const BFCACHE_INVALIDATION_VIA_BROADCAST_CHANNEL_LISTENER = '@nocache-bfcache/bfcache-invalidation-via-broadcast-channel-listener';
}
