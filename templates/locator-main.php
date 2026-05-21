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
    
    <!-- Search Form -->
    <div class="tdl-search-form">
        <div class="tdl-search-input-wrap">
            <span class="tdl-search-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 6.5C10 8.43 8.43 10 6.5 10C4.57 10 3 8.43 3 6.5C3 4.57 4.57 3 6.5 3C8.43 3 10 4.57 10 6.5Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M13 13L9.5 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <input
                type="text"
                id="tdl-search-input"
                class="tdl-search-input"
                placeholder="<?php echo esc_attr($config['i18n']['searchPlaceholder']); ?>"
                aria-label="<?php echo esc_attr( TDL_WPML::translate( 'search_aria_label', __( 'Search for distributors', 'taylor-distributor-locator' ) ) ); ?>"
                autocomplete="off"
            />
            <button type="button" id="tdl-search-btn" class="tdl-search-btn">
                <?php echo esc_html($config['i18n']['search']); ?>
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
