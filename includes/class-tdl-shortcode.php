<?php
/**
 * Shortcode handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Shortcode {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('tdl_locator', [__CLASS__, 'render_shortcode']);
    }
    
    /**
     * Render the locator shortcode
     */
    public static function render_shortcode($atts) {
        $atts = shortcode_atts([
            'map_height' => '600px',
            'show_map' => 'true',
            'show_list' => 'true',
            'default_country' => '',
            'results_per_page' => get_option('tdl_results_per_page', 20),
            'zoom' => get_option('tdl_default_map_zoom', 4),
            'center_lat' => get_option('tdl_default_map_center_lat', '39.8283'),
            'center_lng' => get_option('tdl_default_map_center_lng', '-98.5795'),
        ], $atts, 'tdl_locator');
        
        // Convert string booleans
        $atts['show_map'] = filter_var($atts['show_map'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_list'] = filter_var($atts['show_list'], FILTER_VALIDATE_BOOLEAN);
        $atts['results_per_page'] = absint($atts['results_per_page']);
        $atts['zoom'] = absint($atts['zoom']);
        $atts['center_lat'] = floatval($atts['center_lat']);
        $atts['center_lng'] = floatval($atts['center_lng']);
        
        // Enqueue assets
        self::enqueue_assets();
        
        // Output color CSS variables
        $css_vars = self::get_css_variables();
        
        // Prepare config for JS
        $config = [
            'restUrl' => rest_url('tdl/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'mapProvider' => get_option('tdl_map_provider', 'openstreetmap'),
            'mapHeight' => $atts['map_height'],
            'showMap' => $atts['show_map'],
            'showList' => $atts['show_list'],
            'defaultCountry' => $atts['default_country'],
            'resultsPerPage' => $atts['results_per_page'],
            'zoom' => $atts['zoom'],
            'centerLat' => $atts['center_lat'],
            'centerLng' => $atts['center_lng'],
            'mapId'                => get_option('tdl_google_maps_map_id', ''),
            'gfFormId'                 => TDL_GF_Integration::get_form_id(),
            'gfDistributorFieldId'     => TDL_GF_Integration::get_distributor_field_id(),
            'gfDistributorNameFieldId' => TDL_GF_Integration::get_distributor_name_field_id(),
            'i18n' => [
                'searchPlaceholder' => __('Enter ZIP code, city, state, or country...', 'taylor-distributor-locator'),
                'search' => __('Search', 'taylor-distributor-locator'),
                'searching' => __('Searching distributors...', 'taylor-distributor-locator'),
                'noResults' => __('No distributors found.', 'taylor-distributor-locator'),
                'distributorsFound' => __('%d distributor(s) found', 'taylor-distributor-locator'),
                'visitWebsite' => __('Visit Website', 'taylor-distributor-locator'),
                'getDirections' => __('Get Directions', 'taylor-distributor-locator'),
                'additionalLocations' => __('%d additional location(s)', 'taylor-distributor-locator'),
                'error' => __('An error occurred. Please try again.', 'taylor-distributor-locator'),
                'requestQuote' => __('Request Quote', 'taylor-distributor-locator'),
                'serviceArea' => __('Service Area', 'taylor-distributor-locator'),
                'emailMain' => __('Main', 'taylor-distributor-locator'),
                'emailSales' => __('Sales', 'taylor-distributor-locator'),
                'emailParts' => __('Parts', 'taylor-distributor-locator'),
                'emailService' => __('Service', 'taylor-distributor-locator'),
                'emailInstalls' => __('Installations', 'taylor-distributor-locator'),
                'hours' => __('Hours of Operation', 'taylor-distributor-locator'),
            ],
        ];
        
        // Render the GF quote form HTML (also enqueues GF scripts).
        // gravity_form() with $ajax=true uses GF's built-in AJAX submission — no page reload.
        // $echo=false returns HTML so we can embed it in the modal template.
        $gf_form_html = '';
        if ( class_exists( 'GFForms' ) && $config['gfFormId'] > 0 ) {
            $gf_form_html = gravity_form( $config['gfFormId'], false, false, false, null, true, 0, false );
        }

        // Start output buffering
        ob_start();
        
        // Output inline styles for CSS variables
        echo '<style>';
        echo '.tdl-locator {';
        foreach ($css_vars as $var => $value) {
            echo esc_html($var) . ': ' . esc_html($value) . ';';
        }
        echo '}';
        echo '</style>';
        
        // Include the template
        include TDL_PLUGIN_DIR . 'templates/locator-main.php';
        
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend assets
     */
    private static function enqueue_assets() {
        $map_provider = get_option('tdl_map_provider', 'openstreetmap');
        $api_key = get_option('tdl_google_maps_api_key', '');
        
        // Plugin CSS
        wp_enqueue_style(
            'tdl-locator',
            TDL_PLUGIN_URL . 'assets/css/tdl-locator.css',
            [],
            TDL_VERSION
        );

        // Leaflet CSS (if needed)
        if ($map_provider === 'openstreetmap') {
            wp_enqueue_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                [],
                '1.9.4'
            );
        }
        
        // Define dependencies for our script
        $script_deps = [];
        
        // Leaflet JS (if needed) - Enqueue BEFORE our script and add as dependency
        if ($map_provider === 'openstreetmap') {
            wp_enqueue_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                [],
                '1.9.4',
                true
            );
            // Leaflet MarkerCluster
            wp_enqueue_style(
                'leaflet-markercluster',
                'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
                ['leaflet'],
                '1.5.3'
            );
            wp_enqueue_style(
                'leaflet-markercluster-default',
                'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
                ['leaflet-markercluster'],
                '1.5.3'
            );
            wp_enqueue_script(
                'leaflet-markercluster',
                'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
                ['leaflet'],
                '1.5.3',
                true
            );
            $script_deps[] = 'leaflet';
            $script_deps[] = 'leaflet-markercluster';
        }

        // Plugin JS — in_footer:true keeps it non-render-blocking.
        // Google Maps API already uses loading=async in its URL; Leaflet tiles
        // load lazily by default. No additional defer needed here.
        wp_enqueue_script(
            'tdl-locator',
            TDL_PLUGIN_URL . 'assets/js/tdl-locator.js',
            $script_deps,
            TDL_VERSION,
            true
        );

        // Google Maps API - must load after our script with callback
        if ($map_provider === 'google' && !empty($api_key)) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&callback=tdlInitMap&libraries=marker,places&v=weekly&loading=async',
                ['tdl-locator'],
                null,
                true
            );
        }
    }
    
    /**
     * Get CSS variables for theming
     */
    private static function get_css_variables() {
        $vars = [];
        
        // Define the color mappings with Kadence fallbacks
        $color_map = [
            '--tdl-primary' => ['option' => 'tdl_color_primary', 'kadence' => 'var(--global-palette1, #0073aa)'],
            '--tdl-secondary' => ['option' => 'tdl_color_secondary', 'kadence' => 'var(--global-palette2, #23282d)'],
            '--tdl-accent' => ['option' => 'tdl_color_accent', 'kadence' => 'var(--global-palette3, #0073aa)'],
            '--tdl-btn-bg' => ['option' => 'tdl_color_btn_bg', 'kadence' => 'var(--global-palette1, #0073aa)'],
            '--tdl-btn-text' => ['option' => 'tdl_color_btn_text', 'kadence' => 'var(--global-palette9, #ffffff)'],
            '--tdl-card-border' => ['option' => 'tdl_color_card_border', 'kadence' => 'var(--global-palette1, #0073aa)'],
            '--tdl-card-active' => ['option' => 'tdl_color_card_active', 'kadence' => 'var(--global-palette1, #0073aa)'],
            '--tdl-text' => ['option' => 'tdl_color_text', 'kadence' => 'var(--global-palette4, #333333)'],
            '--tdl-link' => ['option' => 'tdl_color_link', 'kadence' => 'var(--global-palette1, #0073aa)'],
        ];
        
        foreach ($color_map as $var => $config) {
            $value = get_option($config['option'], '');
            $vars[$var] = !empty($value) ? $value : $config['kadence'];
        }
        
        return $vars;
    }
}
