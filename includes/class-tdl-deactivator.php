<?php
/**
 * Plugin deactivation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Deactivator {
    
    /**
     * Run deactivation tasks
     */
    public static function deactivate() {
        self::clear_transients();
        wp_clear_scheduled_hook('tdl_geocode_distributor');
        wp_clear_scheduled_hook('tdl_geocoding_event');
        delete_option('tdl_geocoding_cancel');
        flush_rewrite_rules();
    }
    
    /**
     * Clear all plugin transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tdl_%' 
             OR option_name LIKE '_transient_timeout_tdl_%'"
        );
    }
}
