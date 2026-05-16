<?php
/**
 * CSV Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_CSV_Importer {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_import_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_tdl_import_csv', [__CLASS__, 'handle_import']);
    }
    
    /**
     * Add import page to admin menu
     */
    public static function add_import_page() {
        add_submenu_page(
            'edit.php?post_type=tdl_distributor',
            __('Import CSV', 'taylor-distributor-locator'),
            __('Import CSV', 'taylor-distributor-locator'),
            'manage_options',
            'tdl-csv-import',
            [__CLASS__, 'render_import_page']
        );
    }
    
    /**
     * Enqueue scripts for import page
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'tdl_distributor_page_tdl-csv-import') {
            return;
        }
        
        wp_enqueue_script(
            'tdl-csv-import',
            TDL_PLUGIN_URL . 'assets/js/tdl-csv-import.js',
            ['jquery'],
            TDL_VERSION,
            true
        );
        
        wp_localize_script('tdl-csv-import', 'tdlCsvImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tdl_csv_import'),
            'i18n' => [
                'uploading' => __('Uploading...', 'taylor-distributor-locator'),
                'processing' => __('Processing...', 'taylor-distributor-locator'),
                'complete' => __('Import complete!', 'taylor-distributor-locator'),
                'error' => __('An error occurred during import.', 'taylor-distributor-locator'),
            ],
        ]);
    }
    
    /**
     * Render the import page
     */
    public static function render_import_page() {
        include TDL_PLUGIN_DIR . 'templates/admin/csv-import-page.php';
    }
    
    /**
     * Handle CSV import AJAX request
     */
    public static function handle_import() {
        check_ajax_referer('tdl_csv_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'taylor-distributor-locator')]);
        }
        
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'taylor-distributor-locator')]);
        }
        
        $file = $_FILES['csv_file'];
        
        // Validate file type
        $allowed = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed) && !str_ends_with($file['name'], '.csv')) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV file.', 'taylor-distributor-locator')]);
        }
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(['message' => __('Could not read the file', 'taylor-distributor-locator')]);
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            wp_send_json_error(['message' => __('CSV file is empty', 'taylor-distributor-locator')]);
        }
        
        // Normalize headers
        $headers = array_map('strtolower', array_map('trim', $headers));
        
        // Check for required column
        if (!in_array('company_name', $headers)) {
            fclose($handle);
            wp_send_json_error(['message' => __('CSV must contain a company_name column', 'taylor-distributor-locator')]);
        }
        
        // Read all rows and group by company_name
        $companies = [];
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $company = trim($data['company_name'] ?? '');
            
            if (empty($company)) {
                continue;
            }
            
            if (!isset($companies[$company])) {
                $companies[$company] = [
                    'meta' => $data,
                    'locations' => [],
                ];
            }
            
            // Add location if location data exists
            if (!empty($data['street_address']) || !empty($data['city'])) {
                $companies[$company]['locations'][] = $data;
            }
        }
        fclose($handle);
        
        // Process each company
        $stats = ['created' => 0, 'updated' => 0, 'errors' => 0];
        
        foreach ($companies as $company_name => $company_data) {
            $result = self::import_distributor($company_name, $company_data);
            if ($result === 'created') {
                $stats['created']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('Import complete: %d created, %d updated, %d errors', 'taylor-distributor-locator'),
                $stats['created'], $stats['updated'], $stats['errors']
            ),
            'stats' => $stats,
        ]);
    }
    
    /**
     * Import a single distributor
     */
    private static function import_distributor($company_name, $data) {
        global $wpdb;
        
        // Check if distributor exists
        $existing = get_posts([
            'post_type' => 'tdl_distributor',
            'title' => $company_name,
            'post_status' => 'any',
            'numberposts' => 1,
        ]);
        
        $meta = $data['meta'];
        $is_update = !empty($existing);
        
        if ($is_update) {
            $post_id = $existing[0]->ID;
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $company_name,
                'post_status' => 'publish',
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type' => 'tdl_distributor',
                'post_title' => $company_name,
                'post_status' => 'publish',
            ]);
            
            if (is_wp_error($post_id)) {
                return 'error';
            }
        }
        
        // Update meta fields
        $meta_fields = [
            'phone' => '_tdl_phone_main',
            'website' => '_tdl_website',
            'email_main' => '_tdl_email_main',
            'email_sales' => '_tdl_email_sales',
            'email_parts' => '_tdl_email_parts',
            'email_service' => '_tdl_email_service',
            'email_installations' => '_tdl_email_installs',
            'service_area_notes' => '_tdl_service_area_notes',
        ];
        
        foreach ($meta_fields as $csv_col => $meta_key) {
            if (isset($meta[$csv_col]) && !empty($meta[$csv_col])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($meta[$csv_col]));
            }
        }
        
        // Clear existing locations and zones
        $wpdb->delete($wpdb->prefix . 'tdl_locations', ['distributor_id' => $post_id]);
        $wpdb->delete($wpdb->prefix . 'tdl_service_zones', ['distributor_id' => $post_id]);
        
        // Import locations
        $sort_order = 0;
        foreach ($data['locations'] as $loc) {
            $is_primary = !empty($loc['is_primary']) && $loc['is_primary'] === '1';
            
            $wpdb->insert($wpdb->prefix . 'tdl_locations', [
                'distributor_id' => $post_id,
                'location_name' => sanitize_text_field($loc['location_name'] ?? ''),
                'street_address' => sanitize_text_field($loc['street_address'] ?? ''),
                'address_2' => sanitize_text_field($loc['address_2'] ?? ''),
                'address_3' => sanitize_text_field($loc['address_3'] ?? ''),
                'city' => sanitize_text_field($loc['city'] ?? ''),
                'state_province' => sanitize_text_field($loc['state_province'] ?? ''),
                'zip_postal' => sanitize_text_field($loc['zip_postal'] ?? ''),
                'country_code' => sanitize_text_field($loc['country_code'] ?? 'US'),
                'latitude' => null,
                'longitude' => null,
                'phone' => sanitize_text_field($loc['location_phone'] ?? $loc['phone'] ?? ''),
                'email_sales' => sanitize_email($loc['loc_email_sales'] ?? ''),
                'email_parts' => sanitize_email($loc['loc_email_parts'] ?? ''),
                'email_service' => sanitize_email($loc['loc_email_service'] ?? ''),
                'email_installations' => sanitize_email($loc['loc_email_installations'] ?? ''),
                'hours_operation' => sanitize_textarea_field($loc['hours_operation'] ?? ''),
                'is_primary' => $is_primary ? 1 : 0,
                'sort_order' => $sort_order++,
            ]);
        }
        
        // Import service zones from first row
        self::import_service_zones($post_id, $meta);
        
        // Queue geocoding (background processing)
        if (!wp_next_scheduled('tdl_geocode_distributor', [$post_id])) {
            wp_schedule_single_event(time() + 5, 'tdl_geocode_distributor', [$post_id]);
        }
        
        return $is_update ? 'updated' : 'created';
    }
    
    /**
     * Import service zones from CSV data
     */
    private static function import_service_zones($post_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tdl_service_zones';
        
        // ZIP codes (accept both | and ; as separators)
        if (!empty($data['zip_codes_served'])) {
            $zips = preg_split('/[|;]/', $data['zip_codes_served']);
            foreach ($zips as $zip) {
                $zip = trim($zip);
                if (preg_match('/^(\d{5})-(\d{5})$/', $zip, $matches)) {
                    $wpdb->insert($table, [
                        'distributor_id' => $post_id,
                        'zone_type' => 'zip_range',
                        'range_start' => intval($matches[1]),
                        'range_end' => intval($matches[2]),
                        'country_context' => 'US',
                    ]);
                } elseif (preg_match('/^\d{5}$/', $zip)) {
                    $wpdb->insert($table, [
                        'distributor_id' => $post_id,
                        'zone_type' => 'zip',
                        'zone_value' => $zip,
                        'country_context' => 'US',
                    ]);
                }
            }
        }
        
        // States served (accept both | and ; as separators)
        if (!empty($data['states_served'])) {
            $states = preg_split('/[|;]/', $data['states_served']);
            foreach ($states as $state) {
                $state = strtoupper(trim($state));
                if (empty($state)) continue;
                
                // Determine country context
                $country = 'US';
                if (in_array($state, array_keys(TDL_REST_API::get_ca_provinces()))) {
                    $country = 'CA';
                } elseif (in_array($state, array_keys(TDL_REST_API::get_mx_states()))) {
                    $country = 'MX';
                }
                
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'state',
                    'zone_value' => $state,
                    'country_context' => $country,
                ]);
            }
        }
        
        // Countries served (accept both | and ; as separators)
        if (!empty($data['countries_served'])) {
            $countries = preg_split('/[|;]/', $data['countries_served']);
            foreach ($countries as $country) {
                $country = strtoupper(trim($country));
                if (empty($country)) continue;
                
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'country',
                    'zone_value' => $country,
                    'country_context' => $country,
                ]);
            }
        }
    }
}
