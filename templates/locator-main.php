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
            <input 
                type="text" 
                id="tdl-search-input" 
                class="tdl-search-input" 
                placeholder="<?php echo esc_attr($config['i18n']['searchPlaceholder']); ?>"
                aria-label="<?php esc_attr_e('Search for distributors', 'taylor-distributor-locator'); ?>"
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
