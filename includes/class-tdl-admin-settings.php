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
        add_action('wp_ajax_tdl_hubspot_test', [__CLASS__, 'ajax_hubspot_test']);
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
        
        // Show City/Region search tab
        register_setting('tdl_settings', 'tdl_show_city_tab', [
            'type'              => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
            'default'           => true,
        ]);
        add_settings_field(
            'tdl_show_city_tab',
            __('Show City / Region Tab', 'taylor-distributor-locator'),
            [__CLASS__, 'render_checkbox_field'],
            'tdl-settings',
            'tdl_general_section',
            [
                'name'        => 'tdl_show_city_tab',
                'label'       => __('Show the City / Region search tab on the frontend locator', 'taylor-distributor-locator'),
            ]
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
        
        // Email Routing section — M8 settings.
        add_settings_section(
            'tdl_email_section',
            __('Email Routing', 'taylor-distributor-locator'),
            [__CLASS__, 'render_email_section_description'],
            'tdl-settings'
        );

        // CC Email — awaiting client confirmation; empty by default.
        register_setting('tdl_settings', 'tdl_cc_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]);
        add_settings_field(
            'tdl_cc_email',
            __('CC Email (optional)', 'taylor-distributor-locator'),
            [__CLASS__, 'render_cc_email_field'],
            'tdl-settings',
            'tdl_email_section'
        );

        // GF Quote Form ID — configurable in case the form is recreated.
        register_setting('tdl_settings', 'tdl_gf_quote_form_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);
        add_settings_field(
            'tdl_gf_quote_form_id',
            __('Quote Form ID', 'taylor-distributor-locator'),
            [__CLASS__, 'render_number_field'],
            'tdl-settings',
            'tdl_email_section',
            ['name' => 'tdl_gf_quote_form_id', 'min' => 1, 'max' => 9999]
        );

        // HubSpot Integration section — M9 settings.
        // Every value the integration needs lives here so nothing is hardcoded
        // and the client can adjust property names without a code change.
        add_settings_section(
            'tdl_hubspot_section',
            __('HubSpot Integration', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_section_description'],
            'tdl-settings'
        );

        register_setting('tdl_settings', TDL_HubSpot::OPT_ENABLED, [
            'type'              => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
            'default'           => false,
        ]);
        add_settings_field(
            TDL_HubSpot::OPT_ENABLED,
            __('Enable HubSpot', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_enabled_field'],
            'tdl-settings',
            'tdl_hubspot_section'
        );

        // Token is write-only in the UI: the stored value is never rendered back.
        register_setting('tdl_settings', TDL_HubSpot::OPT_TOKEN, [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_hubspot_token'],
            'default'           => '',
        ]);
        add_settings_field(
            TDL_HubSpot::OPT_TOKEN,
            __('Access Token', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_token_field'],
            'tdl-settings',
            'tdl_hubspot_section'
        );

        register_setting('tdl_settings', TDL_HubSpot::OPT_ENDPOINT, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => TDL_HubSpot::DEFAULT_ENDPOINT,
        ]);
        add_settings_field(
            TDL_HubSpot::OPT_ENDPOINT,
            __('API Endpoint', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_endpoint_field'],
            'tdl-settings',
            'tdl_hubspot_section'
        );

        // Property names — sanitize_key would lowercase and strip, which is exactly
        // the character set HubSpot internal names use.
        $hubspot_props = [
            TDL_HubSpot::OPT_PROP_COMPANY => [
                'label'   => __('Company Property', 'taylor-distributor-locator'),
                'default' => TDL_HubSpot::DEFAULT_PROP_COMPANY,
                'desc'    => __('HubSpot internal name for the company field.', 'taylor-distributor-locator'),
            ],
            TDL_HubSpot::OPT_PROP_DIST => [
                'label'   => __('Distributor Property', 'taylor-distributor-locator'),
                'default' => TDL_HubSpot::DEFAULT_PROP_DIST,
                'desc'    => __('Must be a free-text property. Do not use "taylor_distributor" — it is a dropdown covering only 35 US distributors, and HubSpot silently discards unmatched values.', 'taylor-distributor-locator'),
            ],
            TDL_HubSpot::OPT_PROP_MESSAGE => [
                'label'   => __('Message Property', 'taylor-distributor-locator'),
                'default' => TDL_HubSpot::DEFAULT_PROP_MESSAGE,
                'desc'    => __('HubSpot internal name for the message/needs field.', 'taylor-distributor-locator'),
            ],
            TDL_HubSpot::OPT_PROP_SOURCE => [
                'label'   => __('Lead Source Property', 'taylor-distributor-locator'),
                'default' => TDL_HubSpot::DEFAULT_PROP_SOURCE,
                'desc'    => __('Leave blank to omit lead source entirely. Note the portal also has "leadsource" (a Salesforce-synced dropdown) — that one requires the option to exist first.', 'taylor-distributor-locator'),
            ],
        ];

        foreach ($hubspot_props as $option => $meta) {
            register_setting('tdl_settings', $option, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
                'default'           => $meta['default'],
            ]);
            add_settings_field(
                $option,
                $meta['label'],
                [__CLASS__, 'render_hubspot_prop_field'],
                'tdl-settings',
                'tdl_hubspot_section',
                ['name' => $option, 'default' => $meta['default'], 'desc' => $meta['desc']]
            );
        }

        register_setting('tdl_settings', TDL_HubSpot::OPT_SOURCE_VALUE, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => TDL_HubSpot::DEFAULT_SOURCE_VALUE,
        ]);
        add_settings_field(
            TDL_HubSpot::OPT_SOURCE_VALUE,
            __('Lead Source Value', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_source_value_field'],
            'tdl-settings',
            'tdl_hubspot_section'
        );

        add_settings_field(
            'tdl_hubspot_test',
            __('Connection', 'taylor-distributor-locator'),
            [__CLASS__, 'render_hubspot_test_field'],
            'tdl-settings',
            'tdl_hubspot_section'
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
     * Render email routing section description
     */
    public static function render_email_section_description() {
        echo '<p>' . esc_html__( 'Configure email routing for the distributor quote form. Quote requests are routed to each distributor\'s Sales Email field, with fallback to Main Email.', 'taylor-distributor-locator' ) . '</p>';
    }

    /**
     * Render the CC email field
     */
    public static function render_cc_email_field() {
        $value = get_option( 'tdl_cc_email', '' );
        printf(
            '<input type="email" name="tdl_cc_email" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr( $value ),
            esc_attr__( 'e.g. team@taylorcompany.com', 'taylor-distributor-locator' )
        );
        echo '<p class="description">' . esc_html__( 'Optional. When set, all routed quote emails also CC this address. Leave blank until the client confirms the address.', 'taylor-distributor-locator' ) . '</p>';
    }

    // ── HubSpot (M9) ───────────────────────────────────────────────────────────

    /**
     * Render HubSpot section description
     */
    public static function render_hubspot_section_description() {
        echo '<p>' . esc_html__( 'Sends quote-form submissions to HubSpot as contacts. Transport is the Gravity Forms Webhooks Add-On — you must also add a Webhook feed on the quote form pointing at the API endpoint below (method POST, format JSON). The access token is attached automatically; do not enter it in the Gravity Forms feed.', 'taylor-distributor-locator' ) . '</p>';
        echo '<p>' . esc_html__( 'A HubSpot failure never blocks distributor email routing. Results are recorded under Distributors → Routing Log, prefixed [HubSpot].', 'taylor-distributor-locator' ) . '</p>';
    }

    /**
     * Render the HubSpot enable checkbox
     */
    public static function render_hubspot_enabled_field() {
        $enabled = (bool) get_option( TDL_HubSpot::OPT_ENABLED, false );
        printf(
            '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
            esc_attr( TDL_HubSpot::OPT_ENABLED ),
            checked( $enabled, true, false ),
            esc_html__( 'Send quote submissions to HubSpot', 'taylor-distributor-locator' )
        );
        echo '<p class="description">' . esc_html__( 'When unchecked, the webhook is aborted before any HTTP request is made.', 'taylor-distributor-locator' ) . '</p>';
    }

    /**
     * Render the access token field.
     *
     * The stored token is never echoed back into the page — only a masked hint.
     * Submitting the field empty preserves the existing value.
     */
    public static function render_hubspot_token_field() {
        if ( TDL_HubSpot::token_is_from_constant() ) {
            printf(
                '<input type="text" class="regular-text" value="%s" disabled />',
                esc_attr__( 'Defined in wp-config.php', 'taylor-distributor-locator' )
            );
            echo '<p class="description">' . esc_html__( 'TDL_HUBSPOT_TOKEN is set in wp-config.php and takes precedence over this field. This is the recommended production setup — the credential stays out of the database.', 'taylor-distributor-locator' ) . '</p>';
            return;
        }

        $stored = (string) get_option( TDL_HubSpot::OPT_TOKEN, '' );
        printf(
            '<input type="password" name="%s" value="" class="regular-text" autocomplete="new-password" placeholder="%s" />',
            esc_attr( TDL_HubSpot::OPT_TOKEN ),
            esc_attr( $stored !== '' ? self::mask_secret( $stored ) : 'pat-na1-…' )
        );

        if ( $stored !== '' ) {
            echo '<p class="description">' . esc_html__( 'A token is stored. Leave blank to keep it, or paste a new one to replace it.', 'taylor-distributor-locator' ) . '</p>';
        }
        echo '<p class="description">' . wp_kses_post( __( 'Preferred: define <code>TDL_HUBSPOT_TOKEN</code> in <code>wp-config.php</code> instead, so the credential is never stored in the database.', 'taylor-distributor-locator' ) ) . '</p>';
    }

    /**
     * Keep the existing token when the field is submitted empty, so the masked
     * placeholder cannot silently wipe a working credential.
     */
    public static function sanitize_hubspot_token( $value ) {
        $value = trim( (string) $value );

        // Strip common copy/paste artifacts: wrapping quotes, and trailing
        // backslashes or whitespace picked up from wrapped terminal output or
        // chat clients. A single stray character produces an opaque HTTP 401,
        // so it is worth removing them here rather than debugging it later.
        $value = trim( $value, "\"' \t\n\r\0\x0B\\" );

        if ( $value === '' ) {
            return (string) get_option( TDL_HubSpot::OPT_TOKEN, '' );
        }
        return sanitize_text_field( $value );
    }

    /**
     * Show enough of a secret to identify it, never enough to use it.
     */
    private static function mask_secret( $secret ) {
        $secret = (string) $secret;
        if ( strlen( $secret ) < 12 ) {
            return str_repeat( '•', 8 );
        }
        return substr( $secret, 0, 8 ) . str_repeat( '•', 8 ) . substr( $secret, -4 );
    }

    /**
     * Render the API endpoint field
     */
    public static function render_hubspot_endpoint_field() {
        $value = (string) get_option( TDL_HubSpot::OPT_ENDPOINT, TDL_HubSpot::DEFAULT_ENDPOINT );
        printf(
            '<input type="url" name="%s" value="%s" class="large-text code" />',
            esc_attr( TDL_HubSpot::OPT_ENDPOINT ),
            esc_attr( $value )
        );
        echo '<p class="description">' . esc_html__( 'This exact URL must also be set as the Request URL on the Gravity Forms Webhook feed — the feed is only recognised as a HubSpot feed if it points at hubapi.com.', 'taylor-distributor-locator' ) . '</p>';
    }

    /**
     * Render a HubSpot property-name field
     */
    public static function render_hubspot_prop_field( $args ) {
        $name  = $args['name'];
        $value = (string) get_option( $name, $args['default'] );
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text code" placeholder="%s" />',
            esc_attr( $name ),
            esc_attr( $value ),
            esc_attr( $args['default'] )
        );
        if ( ! empty( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
        }
    }

    /**
     * Render the lead source value field
     */
    public static function render_hubspot_source_value_field() {
        $value = (string) get_option( TDL_HubSpot::OPT_SOURCE_VALUE, TDL_HubSpot::DEFAULT_SOURCE_VALUE );
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text" />',
            esc_attr( TDL_HubSpot::OPT_SOURCE_VALUE ),
            esc_attr( $value )
        );
        echo '<p class="description">' . esc_html__( 'Value written to the Lead Source property on every locator lead.', 'taylor-distributor-locator' ) . '</p>';
    }

    /**
     * Render the connection test button.
     * Read-only — calls HubSpot's account-info endpoint, touches no CRM records.
     */
    public static function render_hubspot_test_field() {
        $nonce = wp_create_nonce( 'tdl_hubspot_test' );
        ?>
        <button type="button" class="button" id="tdl-hubspot-test"><?php esc_html_e( 'Test connection', 'taylor-distributor-locator' ); ?></button>
        <span id="tdl-hubspot-test-result" style="margin-left:10px;"></span>
        <p class="description"><?php esc_html_e( 'Verifies the token and reports the portal ID and whether it is a production or sandbox account. Saves settings first if you have unsaved changes.', 'taylor-distributor-locator' ); ?></p>
        <script>
        (function () {
            var btn = document.getElementById('tdl-hubspot-test');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                var out = document.getElementById('tdl-hubspot-test-result');
                btn.disabled = true;
                out.textContent = <?php echo wp_json_encode( __( 'Checking…', 'taylor-distributor-locator' ) ); ?>;
                var body = new URLSearchParams();
                body.append('action', 'tdl_hubspot_test');
                body.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        var ok = res && res.success;
                        out.textContent = (ok ? '✓ ' : '✗ ') + ((res.data && res.data.message) || '');
                        out.style.color = ok ? '#1d7d3f' : '#b32d2e';
                    })
                    .catch(function () {
                        out.textContent = <?php echo wp_json_encode( __( 'Request failed.', 'taylor-distributor-locator' ) ); ?>;
                        out.style.color = '#b32d2e';
                    })
                    .finally(function () { btn.disabled = false; });
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: run the read-only HubSpot connection test.
     */
    public static function ajax_hubspot_test() {
        check_ajax_referer( 'tdl_hubspot_test', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'taylor-distributor-locator' ) ] );
        }

        $result = TDL_HubSpot::test_connection();

        if ( empty( $result['ok'] ) ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
        wp_send_json_success( [ 'message' => $result['message'] ] );
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
     * Render a checkbox field
     */
    public static function render_checkbox_field( $args ) {
        $value = get_option( $args['name'], true );
        printf(
            '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
            esc_attr( $args['name'] ),
            checked( $value, true, false ),
            esc_html( $args['label'] ?? '' )
        );
    }

    /**
     * Sanitize checkbox to boolean — unchecked sends nothing, checked sends '1'.
     */
    public static function sanitize_checkbox( $value ): bool {
        return ! empty( $value );
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
