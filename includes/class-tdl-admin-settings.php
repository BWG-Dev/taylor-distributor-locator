<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Admin_Settings {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_footer', [__CLASS__, 'render_settings_scripts']);
        add_action('admin_init', [__CLASS__, 'process_clear_cache']);
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
    }
    
    /**
     * Add settings page under Distributors menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=distributor',
            __('Settings', 'taylor-distributor-locator'),
            __('Settings', 'taylor-distributor-locator'),
            'manage_options',
            'tdl-settings',
            [__CLASS__, 'render_settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        // General settings section
        add_settings_section(
            'tdl_general_section',
            __('General Settings', 'taylor-distributor-locator'),
            null,
            'tdl-settings'
        );

        // Map Provider
        register_setting('tdl_settings', 'tdl_map_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'openstreetmap',
        ]);
        add_settings_field(
            'tdl_map_provider',
            __('Map Provider', 'taylor-distributor-locator'),
            [__CLASS__, 'render_select_field'],
            'tdl-settings',
            'tdl_general_section',
            [
                'name' => 'tdl_map_provider',
                'id' => 'tdl_map_provider',
                'options' => [
                    'google' => __('Google Maps', 'taylor-distributor-locator'),
                    'openstreetmap' => __('OpenStreetMap (Leaflet)', 'taylor-distributor-locator'),
                ]
            ]
        );
        
        // Google Maps API Key
        register_setting('tdl_settings', 'tdl_google_maps_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        add_settings_field(
            'tdl_google_maps_api_key',
            __('Google Maps API Key', 'taylor-distributor-locator'),
            [__CLASS__, 'render_text_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_google_maps_api_key', 'class' => 'tdl-google-field']
        );

        // Google Map ID (Required for Advanced Markers)
        register_setting('tdl_settings', 'tdl_google_maps_map_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        add_settings_field(
            'tdl_google_maps_map_id',
            __('Google Map ID (Required for Advanced Markers)', 'taylor-distributor-locator'),
            [__CLASS__, 'render_text_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_google_maps_map_id', 'class' => 'tdl-google-field']
        );
        
        // Default Map Zoom
        register_setting('tdl_settings', 'tdl_default_map_zoom', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 4,
        ]);
        add_settings_field(
            'tdl_default_map_zoom',
            __('Default Map Zoom', 'taylor-distributor-locator'),
            [__CLASS__, 'render_number_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_default_map_zoom', 'min' => 1, 'max' => 20]
        );
        
        // Default Map Center Lat
        register_setting('tdl_settings', 'tdl_default_map_center_lat', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '39.8283',
        ]);
        add_settings_field(
            'tdl_default_map_center_lat',
            __('Default Map Center Latitude', 'taylor-distributor-locator'),
            [__CLASS__, 'render_text_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_default_map_center_lat']
        );
        
        // Default Map Center Lng
        register_setting('tdl_settings', 'tdl_default_map_center_lng', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '-98.5795',
        ]);
        add_settings_field(
            'tdl_default_map_center_lng',
            __('Default Map Center Longitude', 'taylor-distributor-locator'),
            [__CLASS__, 'render_text_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_default_map_center_lng']
        );
        
        // Results Per Page
        register_setting('tdl_settings', 'tdl_results_per_page', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20,
        ]);
        add_settings_field(
            'tdl_results_per_page',
            __('Results Per Page', 'taylor-distributor-locator'),
            [__CLASS__, 'render_number_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_results_per_page', 'min' => 1, 'max' => 100]
        );
        
        // Cache Duration
        register_setting('tdl_settings', 'tdl_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 60,
        ]);
        add_settings_field(
            'tdl_cache_duration',
            __('Cache Duration (minutes)', 'taylor-distributor-locator'),
            [__CLASS__, 'render_number_field'],
            'tdl-settings',
            'tdl_general_section',
            ['name' => 'tdl_cache_duration', 'min' => 0, 'max' => 1440]
        );
        
        // Clear Cache Button
        add_settings_field(
            'tdl_clear_cache',
            __('Clear Search Cache', 'taylor-distributor-locator'),
            [__CLASS__, 'render_clear_cache_field'],
            'tdl-settings',
            'tdl_general_section'
        );
        
        // Colors settings section
        add_settings_section(
            'tdl_colors_section',
            __('Color Settings', 'taylor-distributor-locator'),
            [__CLASS__, 'render_colors_section_description'],
            'tdl-settings'
        );
        
        $color_fields = [
            'tdl_color_primary' => __('Primary Color', 'taylor-distributor-locator'),
            'tdl_color_secondary' => __('Secondary Color', 'taylor-distributor-locator'),
            'tdl_color_accent' => __('Accent Color', 'taylor-distributor-locator'),
            'tdl_color_btn_bg' => __('Button Background', 'taylor-distributor-locator'),
            'tdl_color_btn_text' => __('Button Text', 'taylor-distributor-locator'),
            'tdl_color_card_border' => __('Card Border', 'taylor-distributor-locator'),
            'tdl_color_card_active' => __('Active Card Highlight', 'taylor-distributor-locator'),
            'tdl_color_text' => __('Text Color', 'taylor-distributor-locator'),
            'tdl_color_link' => __('Link Color', 'taylor-distributor-locator'),
        ];
        
        foreach ($color_fields as $name => $label) {
            register_setting('tdl_settings', $name, [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_hex_color',
            ]);
            add_settings_field(
                $name,
                $label,
                [__CLASS__, 'render_color_field'],
                'tdl-settings',
                'tdl_colors_section',
                ['name' => $name]
            );
        }
    }
    
    /**
     * Render the settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('tdl_settings');
                do_settings_sections('tdl-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render colors section description
     */
    public static function render_colors_section_description() {
        echo '<p>' . esc_html__('Leave colors blank to inherit from your theme (Kadence CSS variables). Set a color to override.', 'taylor-distributor-locator') . '</p>';
    }
    
    /**
     * Render a text input field
     */
    public static function render_text_field($args) {
        $value = get_option($args['name'], '');
        $required = !empty($args['required']) ? 'required' : '';
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text" %s />',
            esc_attr($args['name']),
            esc_attr($value),
            $required
        );
    }
    
    /**
     * Render a select dropdown field
     */
    public static function render_select_field($args) {
        $value = get_option($args['name'], isset($args['default']) ? $args['default'] : '');
        $id = isset($args['id']) ? ' id="' . esc_attr($args['id']) . '"' : '';
        echo '<select name="' . esc_attr($args['name']) . '"' . $id . '>';
        foreach ($args['options'] as $key => $label) {
            $selected = selected($value, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Render a number input field
     */
    public static function render_number_field($args) {
        $value = get_option($args['name'], '');
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 9999;
        printf(
            '<input type="number" name="%s" value="%s" min="%d" max="%d" class="small-text" />',
            esc_attr($args['name']),
            esc_attr($value),
            $min,
            $max
        );
    }
    
    /**
     * Render a color picker field
     */
    public static function render_color_field($args) {
        $value = get_option($args['name'], '');
        printf(
            '<input type="text" name="%s" value="%s" class="tdl-color-picker" data-default-color="" />',
            esc_attr($args['name']),
            esc_attr($value)
        );
    }
    
    /**
     * Get a color setting with fallback
     */
    public static function get_color($name, $default = '') {
        $value = get_option($name, '');
        return !empty($value) ? $value : $default;
    }
    
    /**
     * Render settings page JavaScript for conditional field visibility
     */
    public static function render_settings_scripts() {
        // Only render on our settings page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'distributor_page_tdl-settings') {
            return;
        }
        ?>
        <script type="text/javascript">
        (function() {
            const providerSelect = document.getElementById('tdl_map_provider');
            if (!providerSelect) return;
            
            function toggleGoogleFields() {
                const isGoogle = providerSelect.value === 'google';
                // Find the table rows containing Google-specific fields
                const googleFields = document.querySelectorAll('input[name="tdl_google_maps_api_key"], input[name="tdl_google_maps_map_id"]');
                googleFields.forEach(function(field) {
                    const row = field.closest('tr');
                    if (row) {
                        row.style.display = isGoogle ? '' : 'none';
                    }
                    // Toggle required attribute based on provider
                    if (isGoogle) {
                        field.setAttribute('required', 'required');
                    } else {
                        field.removeAttribute('required');
                    }
                });
            }
            
            // Run on page load
            toggleGoogleFields();
            
            // Run on change
            providerSelect.addEventListener('change', toggleGoogleFields);
        })();
        </script>
        <?php
    }

    /**
     * Render the Clear Cache button
     */
    public static function render_clear_cache_field() {
        $clear_url = wp_nonce_url(
            admin_url('edit.php?post_type=distributor&page=tdl-settings&action=clear_cache'),
            'tdl_clear_cache_nonce',
            'tdl_nonce'
        );
        
        echo '<a href="' . esc_url($clear_url) . '" class="button button-secondary">' . 
             esc_html__('Clear Cache Now', 'taylor-distributor-locator') . 
             '</a>';
        echo '<p class="description">' . esc_html__('Click to force clear all cached search results immediately.', 'taylor-distributor-locator') . '</p>';
    }

    /**
     * Process the clear cache action
     */
    public static function process_clear_cache() {
        // Check if our action is set
        if (!isset($_GET['action']) || $_GET['action'] !== 'clear_cache') {
            return;
        }

        // Check if we are on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'tdl-settings') {
            return;
        }

        // Verify nonce
        if (!isset($_GET['tdl_nonce']) || !wp_verify_nonce($_GET['tdl_nonce'], 'tdl_clear_cache_nonce')) {
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Update the data version to invalidate all cache keys
        update_option('tdl_data_version', time());

        // Redirect back to settings with success message
        wp_redirect(admin_url('edit.php?post_type=distributor&page=tdl-settings&settings-updated=cache_cleared'));
        exit;
    }

    /**
     * Render admin notices
     */
    public static function render_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'cache_cleared') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Search cache cleared successfully.', 'taylor-distributor-locator'); ?></p>
            </div>
            <?php
        }
    }
}
