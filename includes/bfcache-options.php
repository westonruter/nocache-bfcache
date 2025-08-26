<?php

/**
 * User opt-in for BFCache.
 *
 * @since 1.1.0
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

// @TODO: determine option names
const BFCACHE_OPTIONS_PAGE = 'reading';
const BFCACHE_BLOCK_UNLOAD_KEY = 'bfcache_block_unload_events';
const BFCACHE_ENABLED_KEY = 'bfcache_enabled';

/**
 * Add a custom checkbox field to the Reading Settings page.
 */
function bfcache_settings_field() {
    // 1. Register the setting
    register_setting(
        BFCACHE_OPTIONS_PAGE, // The page to add the setting to
        BFCACHE_BLOCK_UNLOAD_KEY, // The name of your option
        'sanitize_text_field' // The sanitization callback function
    );

    register_setting(
        BFCACHE_OPTIONS_PAGE,
        BFCACHE_ENABLED_KEY,
        'sanitize_text_field'
    );


    // 2. Options 
    add_settings_field(
        BFCACHE_BLOCK_UNLOAD_KEY,
        'Block Unload Event',
        __NAMESPACE__ . '\render_block_unload_html', //callback
        BFCACHE_OPTIONS_PAGE, // section
        'default' 
    );

    add_settings_field(
        BFCACHE_ENABLED_KEY, 
        'Enabled BFCache', // Title
        __NAMESPACE__ . '\render_bfcache_enabled_html', //callback
        BFCACHE_OPTIONS_PAGE, // section
        'default' 
    );
}

add_action('admin_init', __NAMESPACE__ . '\bfcache_settings_field');

/**
 * Renders the HTML for the checkbox field.
 */
function render_block_unload_html() {
    $option = get_option(BFCACHE_BLOCK_UNLOAD_KEY);
    $checked = ($option == '1') ? 'checked' : ''; 

    debug_log($checked, 'render_block_unload_html');

    echo '<label><input name="rss_use_excerpt" type="checkbox" value="0" ' . $checked . '"> Block unload events</label>';
}

function render_bfcache_enabled_html() {
    $option = get_option(BFCACHE_ENABLED_KEY);
    $checked = ($option == '1') ? 'checked' : ''; 

    debug_log($checked, 'render_bfcache_enabled_html');

    echo '<label><input name="rss_use_excerpt" type="checkbox" value="1" ' . $checked . '"> Enable BFCache by default </label>';
}
