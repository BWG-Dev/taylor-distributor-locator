<?php
/**
 * CSV Import Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Import Distributors from CSV', 'taylor-distributor-locator'); ?></h1>
    
    <div class="tdl-import-container">
        <div class="tdl-import-instructions">
            <h2><?php esc_html_e('CSV Format', 'taylor-distributor-locator'); ?></h2>
            <p><?php esc_html_e('Your CSV file should include the following columns. Only company_name is required.', 'taylor-distributor-locator'); ?></p>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Column', 'taylor-distributor-locator'); ?></th>
                        <th><?php esc_html_e('Description', 'taylor-distributor-locator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>company_name</code></td><td><?php esc_html_e('Required. Company name.', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>phone</code></td><td><?php esc_html_e('Main phone number', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>website</code></td><td><?php esc_html_e('Website URL', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>email_main</code></td><td><?php esc_html_e('Main email address', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>email_sales</code></td><td><?php esc_html_e('Sales email', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>email_parts</code></td><td><?php esc_html_e('Parts email', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>email_service</code></td><td><?php esc_html_e('Service email', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>email_installations</code></td><td><?php esc_html_e('Installations email', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>location_name</code></td><td><?php esc_html_e('e.g., "Headquarters"', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>street_address</code></td><td><?php esc_html_e('Physical address', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>city</code></td><td><?php esc_html_e('City', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>state_province</code></td><td><?php esc_html_e('State/province code (e.g., TX, ON)', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>zip_postal</code></td><td><?php esc_html_e('ZIP or postal code', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>country_code</code></td><td><?php esc_html_e('ISO 2-letter code (US, CA, MX, etc.)', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>is_primary</code></td><td><?php esc_html_e('1 for primary location, 0 otherwise', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>zip_codes_served</code></td><td><?php esc_html_e('Semicolon-separated (e.g., 75001;75002;75100-75199)', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>states_served</code></td><td><?php esc_html_e('Semicolon-separated state codes (e.g., TX;OK;NM)', 'taylor-distributor-locator'); ?></td></tr>
                    <tr><td><code>countries_served</code></td><td><?php esc_html_e('Semicolon-separated ISO codes (e.g., FR;DE;IT)', 'taylor-distributor-locator'); ?></td></tr>
                </tbody>
            </table>
            
            <p><strong><?php esc_html_e('Multiple Locations:', 'taylor-distributor-locator'); ?></strong> <?php esc_html_e('To add multiple locations for one distributor, use multiple rows with the same company_name.', 'taylor-distributor-locator'); ?></p>
        </div>
        
        <div class="tdl-import-form">
            <h2><?php esc_html_e('Upload CSV', 'taylor-distributor-locator'); ?></h2>
            
            <form id="tdl-csv-import-form" enctype="multipart/form-data">
                <p>
                    <label for="csv_file"><?php esc_html_e('Select CSV File:', 'taylor-distributor-locator'); ?></label><br>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                </p>
                <p>
                    <button type="submit" class="button button-primary" id="tdl-import-btn"><?php esc_html_e('Import', 'taylor-distributor-locator'); ?></button>
                </p>
            </form>
            
            <div id="tdl-import-progress" style="display:none;">
                <div class="tdl-progress-bar">
                    <div class="tdl-progress-fill"></div>
                </div>
                <p class="tdl-progress-text"></p>
            </div>
            
            <div id="tdl-import-result" style="display:none;"></div>
        </div>
    </div>
</div>

<style>
.tdl-import-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}
.tdl-import-instructions table {
    margin-top: 15px;
}
.tdl-import-instructions code {
    background: #f0f0f0;
    padding: 2px 6px;
}
.tdl-progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
    margin: 15px 0;
}
.tdl-progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}
#tdl-import-result.success {
    color: #00a32a;
    font-weight: 600;
}
#tdl-import-result.error {
    color: #d63638;
    font-weight: 600;
}
@media (max-width: 1200px) {
    .tdl-import-container {
        grid-template-columns: 1fr;
    }
}
</style>
