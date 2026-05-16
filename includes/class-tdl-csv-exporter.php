<?php
/**
 * CSV Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_CSV_Exporter {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_export_page']);
        add_action('admin_init', [__CLASS__, 'handle_export']);
    }
    
    /**
     * Add export page to admin menu
     */
    public static function add_export_page() {
        add_submenu_page(
            'edit.php?post_type=tdl_distributor',
            __('Export CSV', 'taylor-distributor-locator'),
            __('Export CSV', 'taylor-distributor-locator'),
            'manage_options',
            'tdl-csv-export',
            [__CLASS__, 'render_export_page']
        );
    }
    
    /**
     * Render the export page
     */
    public static function render_export_page() {
        include TDL_PLUGIN_DIR . 'templates/admin/csv-export-page.php';
    }
    
    /**
     * Handle CSV export request
     */
    public static function handle_export() {
        if (!isset($_POST['tdl_action']) || $_POST['tdl_action'] !== 'export_csv') {
            return;
        }
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tdl_export_csv')) {
            wp_die(__('Security check failed', 'taylor-distributor-locator'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'taylor-distributor-locator'));
        }
        
        self::generate_csv();
    }
    
    /**
     * Generate and output the CSV
     */
    private static function generate_csv() {
        $filename = 'distributors-export-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers - must match Importer expectations
        $headers = [
            'company_name',
            'website',
            'phone',
            'email_main',
            'email_sales',
            'email_parts',
            'email_service',
            'email_installations',
            'service_area_notes',
            'location_name',
            'street_address',
            'address_2',
            'address_3',
            'city',
            'state_province',
            'zip_postal',
            'country_code',
            'latitude',
            'longitude',
            'is_primary',
            'location_phone',
            'hours_operation',
            'loc_email_sales',
            'loc_email_parts',
            'loc_email_service',
            'loc_email_installations',
            'zip_codes_served',
            'states_served',
            'countries_served'
        ];
        
        fputcsv($output, $headers);
        
        // Fetch all distributors
        $distributors = get_posts([
            'post_type' => 'tdl_distributor',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        
        global $wpdb;
        $locations_table = $wpdb->prefix . 'tdl_locations';
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        
        foreach ($distributors as $distributor) {
            // Get Metadata
            $meta = [
                'company_name' => $distributor->post_title,
                'website' => get_post_meta($distributor->ID, '_tdl_website', true),
                'phone' => get_post_meta($distributor->ID, '_tdl_phone_main', true),
                'email_main' => get_post_meta($distributor->ID, '_tdl_email_main', true),
                'email_sales' => get_post_meta($distributor->ID, '_tdl_email_sales', true),
                'email_parts' => get_post_meta($distributor->ID, '_tdl_email_parts', true),
                'email_service' => get_post_meta($distributor->ID, '_tdl_email_service', true),
                'email_installations' => get_post_meta($distributor->ID, '_tdl_email_installs', true),
                'service_area_notes' => get_post_meta($distributor->ID, '_tdl_service_area_notes', true),
            ];
            
            // Get Service Zones
            $zones = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$zones_table} WHERE distributor_id = %d",
                $distributor->ID
            ));
            
            $zip_codes = [];
            $states = [];
            $countries = [];
            
            foreach ($zones as $zone) {
                switch ($zone->zone_type) {
                    case 'zip':
                        $zip_codes[] = $zone->zone_value;
                        break;
                    case 'zip_range':
                        $zip_codes[] = $zone->range_start . '-' . $zone->range_end;
                        break;
                    case 'state':
                        $states[] = $zone->zone_value;
                        break;
                    case 'country':
                        $countries[] = $zone->zone_value;
                        break;
                }
            }
            
            $zones_data = [
                'zip_codes_served' => implode('|', $zip_codes),
                'states_served' => implode('|', $states),
                'countries_served' => implode('|', $countries),
            ];
            
            // Get Locations
            $locations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$locations_table} WHERE distributor_id = %d ORDER BY sort_order ASC, id ASC",
                $distributor->ID
            ));
            
            if (empty($locations)) {
                // Determine if we should export a row even with no locations (probably yes)
                 $row = array_merge(
                    array_values($meta),
                     ['', '', '', '', '', '', '', 'US', '', '', '0', '', '', '', '', '', ''], // Empty location fields
                    array_values($zones_data)
                );
                 fputcsv($output, $row);
            } else {
                foreach ($locations as $loc) {
                    $loc_data = [
                        'location_name' => $loc->location_name,
                        'street_address' => $loc->street_address,
                        'address_2' => $loc->address_2,
                        'address_3' => $loc->address_3,
                        'city' => $loc->city,
                        'state_province' => $loc->state_province,
                        'zip_postal' => $loc->zip_postal,
                        'country_code' => $loc->country_code,
                        'latitude' => $loc->latitude,
                        'longitude' => $loc->longitude,
                        'is_primary' => $loc->is_primary ? '1' : '0',
                        'location_phone' => $loc->phone,
                        'hours_operation' => $loc->hours_operation,
                        'loc_email_sales' => $loc->email_sales,
                        'loc_email_parts' => $loc->email_parts,
                        'loc_email_service' => $loc->email_service,
                        'loc_email_installations' => $loc->email_installations,
                    ];
                    
                    $row = array_merge(
                        array_values($meta),
                        array_values($loc_data),
                        array_values($zones_data)
                    );
                    
                     fputcsv($output, $row);
                }
            }
        }
        
        fclose($output);
        exit;
    }
}
