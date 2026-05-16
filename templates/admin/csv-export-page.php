<?php
/**
 * Admin view for CSV Export
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card" style="max-width: 600px; margin-top: 20px;">
        <h2><?php esc_html_e('Export Distributors', 'taylor-distributor-locator'); ?></h2>
        <p><?php esc_html_e('Export all distributors, locations, and service zones to a CSV file. The format is compatible with the Import feature.', 'taylor-distributor-locator'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('tdl_export_csv'); ?>
            <input type="hidden" name="tdl_action" value="export_csv">
            
            <p class="submit">
                <?php submit_button(__('Download CSV', 'taylor-distributor-locator'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>
</div>
