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

        // M8 — email routing event log.
        $routing_log_table = $wpdb->prefix . 'tdl_routing_log';
        $routing_log_sql = "CREATE TABLE {$routing_log_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            severity ENUM('info','warning','error') NOT NULL DEFAULT 'info',
            distributor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            distributor_name VARCHAR(255) NOT NULL DEFAULT '',
            recipient_email VARCHAR(255) NOT NULL DEFAULT '',
            routing_source ENUM('email_sales','email_main','none') NOT NULL DEFAULT 'none',
            gf_entry_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            message VARCHAR(500) NOT NULL DEFAULT '',
            INDEX idx_created (created_at),
            INDEX idx_severity (severity)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($locations_sql);
        dbDelta($zones_sql);
        dbDelta($routing_log_sql);

        // Mark routing log table version so maybe_create_table() skips on init.
        update_option( TDL_Routing_Log::DB_VERSION_OPTION, TDL_Routing_Log::DB_VERSION );
    }
    
    /**
     * Run any pending data migrations.
     *
     * Called on every plugins_loaded (after the plugin bootstraps) but each
     * migration is guarded by a version check so it runs exactly once.
     * Add new migrations as additional `if` blocks — never edit existing ones.
     */
    public static function maybe_run_migrations() {
        $ran = get_option( 'tdl_migration_version', '0.0.0' );

        // v0.6.0 — Fix Mexican state service zones that the CSV importer stored
        // with incorrect country_context and non-standard 2-letter abbreviations.
        // Three classes of bad data corrected:
        //   1. Unambiguous MX 2-letter codes stored as country_context='US'
        //   2. Remaining MX codes missed in the first pass (DU,GR,QT,SL,TM)
        //   3. Ambiguous codes (CO,HI,MO,NL) on distributors physically in Mexico
        if ( version_compare( $ran, '0.6.0', '<' ) ) {
            global $wpdb;
            $zones = $wpdb->prefix . 'tdl_service_zones';
            $locs  = $wpdb->prefix . 'tdl_locations';

            // Pass 1 — unambiguous MX shorthand codes stored under US context.
            $pass1_map = [
                'AG'  => 'AGU', 'BS'  => 'BCS', 'CH'  => 'CHH', 'CL'  => 'COL',
                'CM'  => 'CAM', 'CMX' => 'CDMX','CS'  => 'CHP', 'GT'  => 'GUA',
                'JA'  => 'JAL', 'MX'  => 'MEX', 'NA'  => 'NAY', 'OA'  => 'OAX',
                'PU'  => 'PUE', 'QR'  => 'ROO', 'SI'  => 'SIN', 'SO'  => 'SON',
                'TB'  => 'TAB', 'TL'  => 'TLA', 'VE'  => 'VER', 'YU'  => 'YUC',
                'ZA'  => 'ZAC',
            ];
            foreach ( $pass1_map as $old => $new ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$zones} SET zone_value = %s, country_context = 'MX'
                     WHERE zone_type = 'state' AND zone_value = %s",
                    $new, $old
                ) );
            }

            // Pass 2 — five additional MX shorthand codes missed in pass 1.
            $pass2_map = [
                'DU' => 'DUR', 'GR' => 'GRO', 'QT' => 'QUE',
                'SL' => 'SLP', 'TM' => 'TAM',
            ];
            foreach ( $pass2_map as $old => $new ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$zones} SET zone_value = %s, country_context = 'MX'
                     WHERE zone_type = 'state' AND zone_value = %s",
                    $new, $old
                ) );
            }

            // Pass 3 — ambiguous codes that collide with US/CA codes but belong to
            // distributors whose primary location is physically in Mexico.
            $pass3_map = [
                'CO' => 'COA', 'HI' => 'HID', 'MO' => 'MOR', 'NL' => 'NLE',
            ];
            foreach ( $pass3_map as $old => $new ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$zones} s
                     JOIN {$locs} l ON l.distributor_id = s.distributor_id AND l.is_primary = 1
                     SET s.zone_value = %s, s.country_context = 'MX'
                     WHERE s.zone_type = 'state' AND s.zone_value = %s AND l.country_code = 'MX'",
                    $new, $old
                ) );
            }

            // Bust REST API transient cache so stale results are not served.
            update_option( 'tdl_data_version', time() );

            update_option( 'tdl_migration_version', '0.6.0' );
        }
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
