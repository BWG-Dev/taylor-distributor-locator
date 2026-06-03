<?php
/**
 * Main Locator Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tdl-locator" id="tdl-locator">
    <script type="application/json" id="tdl-config"><?php echo wp_json_encode($config); ?></script>
    
    <!-- Search Form — four explicit modes replacing the unified text box -->
    <div class="tdl-search-form">
        <div class="tdl-search-tabs" role="tablist" aria-label="<?php echo esc_attr( TDL_WPML::translate( 'search_tabs_label', __( 'Search mode', 'taylor-distributor-locator' ) ) ); ?>">
            <button type="button" role="tab" class="tdl-search-tab active" id="tdl-tab-zip"
                aria-selected="true" aria-controls="tdl-panel-zip" data-mode="zip">
                <?php echo esc_html( TDL_WPML::translate( 'tab_zip', __( 'ZIP Code', 'taylor-distributor-locator' ) ) ); ?>
            </button>
            <button type="button" role="tab" class="tdl-search-tab" id="tdl-tab-state"
                aria-selected="false" aria-controls="tdl-panel-state" data-mode="state">
                <?php echo esc_html( TDL_WPML::translate( 'tab_state', __( 'State / Province', 'taylor-distributor-locator' ) ) ); ?>
            </button>
            <button type="button" role="tab" class="tdl-search-tab" id="tdl-tab-country"
                aria-selected="false" aria-controls="tdl-panel-country" data-mode="country">
                <?php echo esc_html( TDL_WPML::translate( 'tab_country', __( 'Country', 'taylor-distributor-locator' ) ) ); ?>
            </button>
            <button type="button" role="tab" class="tdl-search-tab" id="tdl-tab-city"
                aria-selected="false" aria-controls="tdl-panel-city" data-mode="city">
                <?php echo esc_html( TDL_WPML::translate( 'tab_city', __( 'City / Region', 'taylor-distributor-locator' ) ) ); ?>
            </button>
        </div>

        <div class="tdl-search-input-row">
            <!-- ZIP panel -->
            <div class="tdl-tab-panel active" id="tdl-panel-zip" role="tabpanel" aria-labelledby="tdl-tab-zip">
                <input type="text" id="tdl-zip-input" class="tdl-search-input"
                    placeholder="<?php echo esc_attr( TDL_WPML::translate( 'zip_placeholder', __( 'Enter 5-digit ZIP code', 'taylor-distributor-locator' ) ) ); ?>"
                    maxlength="5" inputmode="numeric" pattern="[0-9]*"
                    aria-label="<?php echo esc_attr( TDL_WPML::translate( 'zip_aria', __( 'ZIP code', 'taylor-distributor-locator' ) ) ); ?>"
                    autocomplete="off" />
            </div>
            <!-- State / Province panel -->
            <div class="tdl-tab-panel" id="tdl-panel-state" role="tabpanel" aria-labelledby="tdl-tab-state" hidden>
                <select id="tdl-state-select" class="tdl-search-select"
                    aria-label="<?php echo esc_attr( TDL_WPML::translate( 'state_aria', __( 'State or province', 'taylor-distributor-locator' ) ) ); ?>">
                    <option value=""><?php echo esc_html( TDL_WPML::translate( 'select_state', __( 'Select a state or province...', 'taylor-distributor-locator' ) ) ); ?></option>
                </select>
            </div>
            <!-- Country panel -->
            <div class="tdl-tab-panel" id="tdl-panel-country" role="tabpanel" aria-labelledby="tdl-tab-country" hidden>
                <select id="tdl-country-select" class="tdl-search-select"
                    aria-label="<?php echo esc_attr( TDL_WPML::translate( 'country_aria', __( 'Country', 'taylor-distributor-locator' ) ) ); ?>">
                    <option value=""><?php echo esc_html( TDL_WPML::translate( 'select_country', __( 'Select a country...', 'taylor-distributor-locator' ) ) ); ?></option>
                </select>
            </div>
            <!-- City / Region panel -->
            <div class="tdl-tab-panel" id="tdl-panel-city" role="tabpanel" aria-labelledby="tdl-tab-city" hidden>
                <input type="text" id="tdl-city-input" class="tdl-search-input"
                    placeholder="<?php echo esc_attr( TDL_WPML::translate( 'city_placeholder', __( 'Enter city or region', 'taylor-distributor-locator' ) ) ); ?>"
                    aria-label="<?php echo esc_attr( TDL_WPML::translate( 'city_aria', __( 'City or region', 'taylor-distributor-locator' ) ) ); ?>"
                    autocomplete="off" />
            </div>

            <button type="button" id="tdl-search-btn" class="tdl-search-btn">
                <?php echo esc_html( $config['i18n']['search'] ); ?>
            </button>
        </div>
    </div>
    
    <!-- Status/Message Area -->
    <div id="tdl-status" class="tdl-status" role="status" aria-live="polite"></div>

    <?php if ($atts['show_map'] && $atts['show_list']): ?>
    <!-- Mobile Map/List Toggle — hidden on desktop via CSS, visible on ≤960px -->
    <div class="tdl-mobile-toggle" id="tdl-mobile-toggle" role="group" aria-label="<?php echo esc_attr( TDL_WPML::translate( 'toggle_aria_label', __( 'Toggle map or list view', 'taylor-distributor-locator' ) ) ); ?>">
        <button class="tdl-toggle-btn tdl-toggle-map active" id="tdl-toggle-map" type="button" aria-pressed="true">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M1 3.5L5 2L9 3.5L13 2V11.5L9 13L5 11.5L1 13V3.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><line x1="5" y1="2" x2="5" y2="11.5" stroke="currentColor" stroke-width="1.3"/><line x1="9" y1="3.5" x2="9" y2="13" stroke="currentColor" stroke-width="1.3"/></svg>
            <?php echo esc_html( TDL_WPML::translate( 'map_toggle_label', __( 'Map', 'taylor-distributor-locator' ) ) ); ?>
        </button>
        <button class="tdl-toggle-btn tdl-toggle-list" id="tdl-toggle-list" type="button" aria-pressed="false">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><line x1="1" y1="3" x2="13" y2="3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><line x1="1" y1="7" x2="13" y2="7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><line x1="1" y1="11" x2="13" y2="11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            <?php echo esc_html( TDL_WPML::translate( 'list_toggle_label', __( 'List', 'taylor-distributor-locator' ) ) ); ?>
        </button>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="tdl-content <?php echo esc_attr($atts['show_map'] && $atts['show_list'] ? 'tdl-split' : ''); ?>">

        <?php if ($atts['show_map']): ?>
        <!-- Map Container -->
        <div class="tdl-map-container" style="height: <?php echo esc_attr($atts['map_height']); ?>">
            <div id="tdl-map" class="tdl-map"></div>
        </div>
        <?php endif; ?>

        <?php if ($atts['show_list']): ?>
        <!-- Results List -->
        <div class="tdl-results-container">
            <div id="tdl-results" class="tdl-results"></div>

            <!-- Pagination -->
            <div id="tdl-pagination" class="tdl-pagination"></div>
        </div>
        <?php endif; ?>

    </div>

    <?php if (!empty($gf_form_html)): ?>
    <!-- Quote Request Modal — opened when a "Request Quote" button is clicked -->
    <div id="tdl-quote-modal" class="tdl-modal" role="dialog" aria-modal="true" aria-labelledby="tdl-modal-title" hidden>
        <div class="tdl-modal-backdrop" aria-hidden="true"></div>
        <div class="tdl-modal-dialog">
            <div class="tdl-modal-header">
                <h2 id="tdl-modal-title" class="tdl-modal-title">
                    <?php echo esc_html( TDL_WPML::translate( 'quote_modal_title', __( 'Request a Quote', 'taylor-distributor-locator' ) ) ); ?>
                </h2>
                <button type="button" class="tdl-modal-close" aria-label="<?php echo esc_attr( TDL_WPML::translate( 'close_modal_label', __( 'Close', 'taylor-distributor-locator' ) ) ); ?>">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <line x1="2" y1="2" x2="16" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="16" y1="2" x2="2" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="tdl-modal-body">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gravity_form() output is trusted GF HTML
                echo $gf_form_html;
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
