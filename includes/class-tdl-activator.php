<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Activator {
    
    /**
     * Run activation tasks
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table: tdl_locations
        $locations_table = $wpdb->prefix . 'tdl_locations';
        $locations_sql = "CREATE TABLE {$locations_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            distributor_id BIGINT UNSIGNED NOT NULL,
            location_name VARCHAR(255),
            street_address VARCHAR(255),
            address_2 VARCHAR(255),
            address_3 VARCHAR(255),
            city VARCHAR(100),
            state_province VARCHAR(100),
            zip_postal VARCHAR(20),
            country_code CHAR(2),
            latitude DECIMAL(10,7),
            longitude DECIMAL(10,7),
            phone VARCHAR(50),
            email_sales VARCHAR(255),
            email_parts VARCHAR(255),
            email_service VARCHAR(255),
            email_installations VARCHAR(255),
            hours_operation TEXT,
            is_primary TINYINT(1) DEFAULT 0,
            geocode_error TEXT NULL,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_distributor (distributor_id),
            INDEX idx_coords (latitude, longitude),
            INDEX idx_country (country_code),
            INDEX idx_city_state (city, state_province)
        ) {$charset_collate};";
        
        // Table: tdl_service_zones
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        $zones_sql = "CREATE TABLE {$zones_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            distributor_id BIGINT UNSIGNED NOT NULL,
            zone_type ENUM('zip','zip_range','state','country') NOT NULL,
            zone_value VARCHAR(20),
            range_start INT UNSIGNED NULL,
            range_end INT UNSIGNED NULL,
            country_context CHAR(2) DEFAULT 'US',
            INDEX idx_distributor (distributor_id),
            INDEX idx_zone_lookup (zone_type, zone_value),
            INDEX idx_zip_range (zone_type, range_start, range_end),
            INDEX idx_country (zone_type, country_context)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($locations_sql);
        dbDelta($zones_sql);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        add_option('tdl_map_provider', 'openstreetmap');
        add_option('tdl_google_maps_api_key', '');
        add_option('tdl_google_maps_map_id', '');
        add_option('tdl_default_map_zoom', 5);
        add_option('tdl_default_map_center_lat', '42.4620761');
        add_option('tdl_default_map_center_lng', '-89.0706026');
        add_option('tdl_results_per_page', 20);
        add_option('tdl_cache_duration', 60);
    }
}
