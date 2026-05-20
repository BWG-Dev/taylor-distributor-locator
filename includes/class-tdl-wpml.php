<?php
/**
 * WPML integration
 *
 * Guards all hooks behind ICL_SITEPRESS_VERSION — safe to load on sites
 * without WPML. Responsibilities:
 *
 * 1. Bust the REST API transient cache when WPML switches language.
 * 2. Register frontend UI strings with WPML String Translation so translators
 *    can manage them in the WPML admin UI (safe no-op if ST module absent).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TDL_WPML {

    public static function init(): void {
        if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
            return;
        }

        // Bust search transients whenever the visitor's language switches.
        add_action( 'wpml_language_has_switched', [ __CLASS__, 'bust_search_cache' ] );

        // Register UI strings at init so the textdomain is already loaded.
        add_action( 'init', [ __CLASS__, 'register_strings' ] );
    }

    /**
     * Bump tdl_data_version so every transient cache key derived from it
     * becomes stale immediately after a language switch.
     */
    public static function bust_search_cache(): void {
        update_option( 'tdl_data_version', time() );
    }

    /**
     * Register frontend UI strings with WPML String Translation.
     *
     * wpml_register_single_string is an action provided by the WPML ST module.
     * Calling do_action() when ST is not active is a safe no-op.
     *
     * Strings are registered with their English originals so translators see
     * the source text in the WPML Translation Editor. The shortcode's __()
     * calls handle runtime translation via the standard WP textdomain
     * mechanism; both mechanisms work in parallel.
     */
    public static function register_strings(): void {
        $context = 'Taylor Distributor Locator';

        $strings = [
            'search_placeholder'   => 'Enter ZIP code, city, state, or country...',
            'search_button'        => 'Search',
            'searching'            => 'Searching distributors...',
            'no_results'           => 'No distributors found.',
            'distributors_found'   => '%d distributor(s) found',
            'visit_website'        => 'Visit Website',
            'get_directions'       => 'Get Directions',
            'additional_locations' => '%d additional location(s)',
            'error_generic'        => 'An error occurred. Please try again.',
            'request_quote'        => 'Request Quote',
            'service_area'         => 'Service Area',
            'email_main_label'     => 'Main',
            'email_sales_label'    => 'Sales',
            'email_parts_label'    => 'Parts',
            'email_service_label'  => 'Service',
            'email_installs_label' => 'Installations',
            'hours_label'          => 'Hours of Operation',
            'quote_modal_title'    => 'Request a Quote',
            'map_toggle_label'     => 'Map',
            'list_toggle_label'    => 'List',
            'close_modal_label'    => 'Close',
            'search_aria_label'    => 'Search for distributors',
            'toggle_aria_label'    => 'Toggle map or list view',
        ];

        foreach ( $strings as $name => $value ) {
            do_action( 'wpml_register_single_string', $context, $name, $value );
        }
    }
}
