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
                aria-label="<?php esc_attr_e('Search for distributors', 'taylor-distributor-locator'); ?>"
                autocomplete="off"
            />
            <button type="button" id="tdl-search-btn" class="tdl-search-btn">
                <?php echo esc_html($config['i18n']['search']); ?>
            </button>
        </div>
    </div>
    
    <!-- Status/Message Area -->
    <div id="tdl-status" class="tdl-status" role="status" aria-live="polite"></div>
    
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
</div>
