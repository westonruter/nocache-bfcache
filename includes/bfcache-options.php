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
const BFCACHE_TEXT_DOMAIN = 'no-cache-bfcache'; // @TODO: Move to main file?

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
        'rest_sanitize_boolean' // The sanitization callback function
    );

    register_setting(
        BFCACHE_OPTIONS_PAGE,
        BFCACHE_ENABLED_KEY,
        'rest_sanitize_boolean'
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
    $option_key = BFCACHE_DISALLOW_UNLOAD_KEY;
    $option = get_option($option_key);

    $checked = checked($option, 1, false);

    $label_text = esc_html__('Disallow Unload JS Events', BFCACHE_TEXT_DOMAIN);

    $html = sprintf(
        '<label><input name="%1$s" type="checkbox" value="1" %2$s> %3$s</label>',
        esc_attr($option_key),
        $checked,
        $label_text
    );

    echo $html;
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
    $option_key = BFCACHE_ENABLED_KEY;
    $option = get_option($option_key);

    $checked = checked($option, 1, false);

    $label_text = esc_html__('Enable BFCache by default', BFCACHE_TEXT_DOMAIN);

    $html = sprintf(
        '<label><input name="%1$s" type="checkbox" value="1" %2$s> %3$s</label>',
        esc_attr($option_key),
        $checked,
        $label_text
    );

    echo $html;
}
