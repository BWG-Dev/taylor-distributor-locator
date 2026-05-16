<?php
/**
 * Internationalization class
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_i18n {
    
    /**
     * Load the plugin text domain for translation
     */
    public static function load_textdomain() {
        load_plugin_textdomain(
            'taylor-distributor-locator',
            false,
            dirname(TDL_PLUGIN_BASENAME) . '/languages/'
        );
    }
}
