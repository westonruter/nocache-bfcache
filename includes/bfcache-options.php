<?php

/**
 * User opt-in for BFCache.
 *
 * @since n.e.x.t
 * @package WestonRuter\NocacheBFCache
 */

namespace WestonRuter\NocacheBFCache;

// @TODO: determine option names
const BFCACHE_OPTIONS_PAGE = 'reading';
const BFCACHE_DISALLOW_UNLOAD_KEY = 'bfcache_disallow_unload_events';
const BFCACHE_ENABLED_KEY = 'bfcache_enabled';

/**
 * Registers the settings and fields for the BFCache options on the WordPress Reading settings page.
 *
 * This function uses the WordPress Settings API to create two new checkbox options:
 * - One to disallow unload events.
 * - One to enable BFCache by default.
 * These settings are placed in the 'default' section of the 'reading' options page.
 *
 * @since n.e.x.t
 * @return void
 */
function bfcache_settings_field() {
    // 1. Register the setting
    register_setting(
        BFCACHE_OPTIONS_PAGE, // The page to add the setting to
        BFCACHE_DISALLOW_UNLOAD_KEY, // The name of your option
        'sanitize_text_field' // The sanitization callback function
    );

    register_setting(
        BFCACHE_OPTIONS_PAGE,
        BFCACHE_ENABLED_KEY,
        'sanitize_text_field'
    );


    // 2. Options 
    add_settings_field(
        BFCACHE_DISALLOW_UNLOAD_KEY,
        'Disallow Unload Event',
        __NAMESPACE__ . '\render_disallow_unload_html', //callback
        BFCACHE_OPTIONS_PAGE, // section
        'default'
    );

    // If this filter hasn't been declared in code, then provide is as a option
    if (!has_filter( 'nocache_bfcache_use_remember_me_as_opt_in' )) {
        add_settings_field(
            BFCACHE_ENABLED_KEY,
            'Enable BFCache by default', // Title
            __NAMESPACE__ . '\render_bfcache_enabled_html', //callback
            BFCACHE_OPTIONS_PAGE, // section
            'default'
        );        
    }

}

add_action('admin_init', __NAMESPACE__ . '\bfcache_settings_field');

/**
 * Renders the HTML for the 'Disallow Unload Event' checkbox.
 *
 * This function retrieves the saved option value and generates the checkbox
 * input field with the correct 'checked' attribute. The label is included
 * for user clarity and accessibility.
 *
 * @since n.e.x.t
 * @return void
 */
function render_disallow_unload_html(): void {
    $option = get_option(BFCACHE_DISALLOW_UNLOAD_KEY);

    echo '<label><input name="' . esc_attr(BFCACHE_DISALLOW_UNLOAD_KEY) . '" type="checkbox" value="1" ' . checked($option, 1, false) . '> Disallow Unload JS Events</label>';
}

/**
 * Renders the HTML for the 'Enabled BFCache' checkbox.
 *
 * This function retrieves the saved option value and generates the checkbox
 * input field with the correct 'checked' attribute. It ensures the name attribute
 * is correctly set to save the option value.
 *
 * @since n.e.x.t
 * @return void
 */
function render_bfcache_enabled_html(): void {
    $option = get_option(BFCACHE_ENABLED_KEY);

    echo '<label><input name="' . esc_attr(BFCACHE_ENABLED_KEY) . '" type="checkbox" value="1" ' . checked($option, 1, false) . '> Enable BFCache by default</label>';
}
