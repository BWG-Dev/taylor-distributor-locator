<?php
/**
 * Meta Boxes for Distributor CPT
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Meta_Boxes {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_tdl_distributor', [__CLASS__, 'save_meta_boxes'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_notices', [__CLASS__, 'show_geocode_notices']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type !== 'tdl_distributor') {
            return;
        }
        
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        wp_enqueue_style(
            'tdl-admin',
            TDL_PLUGIN_URL . 'assets/css/tdl-admin.css',
            [],
            TDL_VERSION
        );
        
        wp_enqueue_script(
            'tdl-admin',
            TDL_PLUGIN_URL . 'assets/js/tdl-admin.js',
            ['jquery'],
            TDL_VERSION,
            true
        );

        $map_provider = get_option('tdl_map_provider', 'openstreetmap');
        $api_key = get_option('tdl_google_maps_api_key', '');
        
        if ($map_provider === 'google' && !empty($api_key)) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places&v=weekly&loading=async',
                ['tdl-admin'],
                null,
                true
            );
        }
        
        wp_localize_script('tdl-admin', 'tdlAdmin', [
            'restUrl' => rest_url('tdl/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'geocoding' => __('Geocoding...', 'taylor-distributor-locator'),
                'geocodeSuccess' => __('Geocoded successfully!', 'taylor-distributor-locator'),
                'geocodeError' => __('Geocoding failed', 'taylor-distributor-locator'),
                'confirmRemove' => __('Are you sure you want to remove this item?', 'taylor-distributor-locator'),
            ],
        ]);
    }
    
    /**
     * Register meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'tdl_contact_info',
            __('Contact Information', 'taylor-distributor-locator'),
            [__CLASS__, 'render_contact_meta_box'],
            'tdl_distributor',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tdl_locations',
            __('Physical Locations', 'taylor-distributor-locator'),
            [__CLASS__, 'render_locations_meta_box'],
            'tdl_distributor',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tdl_service_zones',
            __('Service Zones', 'taylor-distributor-locator'),
            [__CLASS__, 'render_service_zones_meta_box'],
            'tdl_distributor',
            'normal',
            'high'
        );
    }
    
    /**
     * Render contact information meta box
     */
    public static function render_contact_meta_box($post) {
        wp_nonce_field('tdl_save_meta_boxes', 'tdl_meta_nonce');
        
        $website = get_post_meta($post->ID, '_tdl_website', true);
        $phone = get_post_meta($post->ID, '_tdl_phone_main', true);
        $email_main = get_post_meta($post->ID, '_tdl_email_main', true);
        $email_sales = get_post_meta($post->ID, '_tdl_email_sales', true);
        $email_parts = get_post_meta($post->ID, '_tdl_email_parts', true);
        $email_service = get_post_meta($post->ID, '_tdl_email_service', true);
        $email_installs = get_post_meta($post->ID, '_tdl_email_installs', true);
        $email_other = get_post_meta($post->ID, '_tdl_email_other', true);
        $email_other = $email_other ? json_decode($email_other, true) : [];
        $notes = get_post_meta($post->ID, '_tdl_service_area_notes', true);
        
        include TDL_PLUGIN_DIR . 'templates/admin/meta-box-contacts.php';
    }
    
    /**
     * Render physical locations meta box
     */
    public static function render_locations_meta_box($post) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tdl_locations';
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE distributor_id = %d ORDER BY sort_order ASC, id ASC",
            $post->ID
        ));
        
        include TDL_PLUGIN_DIR . 'templates/admin/meta-box-locations.php';
    }
    
    /**
     * Render service zones meta box
     */
    public static function render_service_zones_meta_box($post) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tdl_service_zones';
        $zones = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE distributor_id = %d",
            $post->ID
        ));
        
        // Organize zones by type
        $zip_codes = [];
        $states = ['US' => [], 'CA' => [], 'MX' => []];
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
                    if (isset($states[$zone->country_context])) {
                        $states[$zone->country_context][] = $zone->zone_value;
                    }
                    break;
                case 'country':
                    $countries[] = $zone->zone_value;
                    break;
            }
        }
        
        include TDL_PLUGIN_DIR . 'templates/admin/meta-box-service-zones.php';
    }
    
    /**
     * Save meta box data
     */
    public static function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['tdl_meta_nonce']) || !wp_verify_nonce($_POST['tdl_meta_nonce'], 'tdl_save_meta_boxes')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save contact info
        self::save_contact_info($post_id);
        
        // Save locations
        self::save_locations($post_id);
        
        // Save service zones
        self::save_service_zones($post_id);
        
        // Schedule geocoding for locations missing coordinates (runs in background)
        if (!wp_next_scheduled('tdl_geocode_distributor', [$post_id])) {
            wp_schedule_single_event(time(), 'tdl_geocode_distributor', [$post_id]);
        }
        
        // Clear search cache
        self::clear_search_cache();
    }
    
    /**
     * Save contact information
     */
    private static function save_contact_info($post_id) {
        $fields = [
            '_tdl_website' => 'esc_url_raw',
            '_tdl_phone_main' => 'sanitize_text_field',
            '_tdl_email_main' => 'sanitize_email',
            '_tdl_email_sales' => 'sanitize_email',
            '_tdl_email_parts' => 'sanitize_email',
            '_tdl_email_service' => 'sanitize_email',
            '_tdl_email_installs' => 'sanitize_email',
            '_tdl_service_area_notes' => 'sanitize_textarea_field',
        ];
        
        foreach ($fields as $key => $sanitize) {
            if (isset($_POST[$key])) {
                $value = call_user_func($sanitize, $_POST[$key]);
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Handle additional emails (repeater)
        if (isset($_POST['_tdl_email_other_label']) && is_array($_POST['_tdl_email_other_label'])) {
            $other_emails = [];
            $labels = $_POST['_tdl_email_other_label'];
            $emails = $_POST['_tdl_email_other_email'] ?? [];
            
            foreach ($labels as $i => $label) {
                $label = sanitize_text_field($label);
                $email = isset($emails[$i]) ? sanitize_email($emails[$i]) : '';
                
                if (!empty($label) && !empty($email)) {
                    $other_emails[] = [
                        'label' => $label,
                        'email' => $email,
                    ];
                }
            }
            
            update_post_meta($post_id, '_tdl_email_other', wp_json_encode($other_emails));
        } else {
            update_post_meta($post_id, '_tdl_email_other', '[]');
        }
    }
    
    /**
     * Save physical locations
     */
    private static function save_locations($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tdl_locations';
        
        // Delete existing locations
        $wpdb->delete($table, ['distributor_id' => $post_id], ['%d']);
        
        // Insert new locations
        if (!isset($_POST['tdl_location']) || !is_array($_POST['tdl_location'])) {
            return;
        }
        
        $sort_order = 0;
        $primary_set = false;
        
        foreach ($_POST['tdl_location'] as $location) {
            // Skip empty rows
            if (empty($location['location_name']) && empty($location['street_address'])) {
                continue;
            }
            
            $is_primary = !empty($location['is_primary']) && !$primary_set;
            if ($is_primary) {
                $primary_set = true;
            }
            
            $wpdb->insert($table, [
                'distributor_id' => $post_id,
                'location_name' => sanitize_text_field($location['location_name'] ?? ''),
                'street_address' => sanitize_text_field($location['street_address'] ?? ''),
                'address_2' => sanitize_text_field($location['address_2'] ?? ''),
                'address_3' => sanitize_text_field($location['address_3'] ?? ''),
                'city' => sanitize_text_field($location['city'] ?? ''),
                'state_province' => sanitize_text_field($location['state_province'] ?? ''),
                'zip_postal' => sanitize_text_field($location['zip_postal'] ?? ''),
                'country_code' => sanitize_text_field($location['country_code'] ?? 'US'),
                'latitude' => !empty($location['latitude']) ? floatval($location['latitude']) : null,
                'longitude' => !empty($location['longitude']) ? floatval($location['longitude']) : null,
                'phone' => sanitize_text_field($location['phone'] ?? ''),
                'email_sales' => sanitize_email($location['email_sales'] ?? ''),
                'email_parts' => sanitize_email($location['email_parts'] ?? ''),
                'email_service' => sanitize_email($location['email_service'] ?? ''),
                'email_installations' => sanitize_email($location['email_installations'] ?? ''),
                'hours_operation' => sanitize_textarea_field($location['hours_operation'] ?? ''),
                'is_primary' => $is_primary ? 1 : 0,
                'sort_order' => $sort_order++,
            ], [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'
            ]);
        }
    }
    
    /**
     * Save service zones
     */
    private static function save_service_zones($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tdl_service_zones';
        
        // Delete existing zones
        $wpdb->delete($table, ['distributor_id' => $post_id], ['%d']);
        
        // Parse and save ZIP codes
        if (!empty($_POST['tdl_zip_codes'])) {
            $lines = explode("\n", sanitize_textarea_field($_POST['tdl_zip_codes']));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (preg_match('/^(\d{5})-(\d{5})$/', $line, $matches)) {
                    // ZIP range
                    $start = intval($matches[1]);
                    $end = intval($matches[2]);
                    if ($start < $end) {
                        $wpdb->insert($table, [
                            'distributor_id' => $post_id,
                            'zone_type' => 'zip_range',
                            'zone_value' => '',
                            'range_start' => $start,
                            'range_end' => $end,
                            'country_context' => 'US',
                        ], ['%d', '%s', '%s', '%d', '%d', '%s']);
                    }
                } elseif (preg_match('/^\d{5}$/', $line)) {
                    // Single ZIP
                    $wpdb->insert($table, [
                        'distributor_id' => $post_id,
                        'zone_type' => 'zip',
                        'zone_value' => $line,
                        'range_start' => null,
                        'range_end' => null,
                        'country_context' => 'US',
                    ], ['%d', '%s', '%s', '%d', '%d', '%s']);
                }
            }
        }
        
        // Save US states
        if (!empty($_POST['tdl_states_us']) && is_array($_POST['tdl_states_us'])) {
            foreach ($_POST['tdl_states_us'] as $state) {
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'state',
                    'zone_value' => sanitize_text_field($state),
                    'country_context' => 'US',
                ], ['%d', '%s', '%s', '%s']);
            }
        }
        
        // Save Canadian provinces
        if (!empty($_POST['tdl_states_ca']) && is_array($_POST['tdl_states_ca'])) {
            foreach ($_POST['tdl_states_ca'] as $province) {
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'state',
                    'zone_value' => sanitize_text_field($province),
                    'country_context' => 'CA',
                ], ['%d', '%s', '%s', '%s']);
            }
        }
        
        // Save Mexican states
        if (!empty($_POST['tdl_states_mx']) && is_array($_POST['tdl_states_mx'])) {
            foreach ($_POST['tdl_states_mx'] as $state) {
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'state',
                    'zone_value' => sanitize_text_field($state),
                    'country_context' => 'MX',
                ], ['%d', '%s', '%s', '%s']);
            }
        }
        
        // Save countries
        if (!empty($_POST['tdl_countries']) && is_array($_POST['tdl_countries'])) {
            foreach ($_POST['tdl_countries'] as $country) {
                $wpdb->insert($table, [
                    'distributor_id' => $post_id,
                    'zone_type' => 'country',
                    'zone_value' => sanitize_text_field($country),
                    'country_context' => sanitize_text_field($country),
                ], ['%d', '%s', '%s', '%s']);
            }
        }
    }
    
    /**
     * Clear search result cache
     */
    private static function clear_search_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tdl_search_%' 
             OR option_name LIKE '_transient_timeout_tdl_search_%'"
        );
    }
    
    /**
     * Show geocoding failure notices
     */
    public static function show_geocode_notices() {
        global $post;
        
        if (!$post || $post->post_type !== 'tdl_distributor') {
            return;
        }
        
        $failures = get_transient('tdl_geocode_failures_' . $post->ID);
        if ($failures) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Geocoding Issues:', 'taylor-distributor-locator') . '</strong></p>';
            echo '<ul>';
            foreach ($failures as $fail) {
                printf(
                    '<li>%s: %s</li>',
                    esc_html($fail['address']),
                    esc_html($fail['error'])
                );
            }
            echo '</ul>';
            echo '<p>' . esc_html__('Please verify the addresses and try geocoding again.', 'taylor-distributor-locator') . '</p>';
            echo '</div>';
            delete_transient('tdl_geocode_failures_' . $post->ID);
        }
    }
}
