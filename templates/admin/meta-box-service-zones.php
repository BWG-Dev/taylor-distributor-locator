<?php
/**
 * Service Zones Meta Box Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$us_states = TDL_REST_API::get_us_states();
$ca_provinces = TDL_REST_API::get_ca_provinces();
$mx_states = TDL_REST_API::get_mx_states();

// Get all countries except US, CA, MX
// Get all countries except US, CA, MX
$all_countries = TDL_REST_API::get_country_names();
unset($all_countries['US'], $all_countries['CA'], $all_countries['MX']);

// Keep original sorting logic or just rely on API? API returns array keyed by code.
// The template iterates $all_countries as $code => $name.
// TDL_REST_API::get_country_names() returns [Code => Name]
// So we just need to filter out US, CA, MX.

asort($all_countries);
?>

<div class="tdl-service-zones">
    <!-- Tabs -->
    <div class="tdl-zone-tabs">
        <button type="button" class="tdl-zone-tab active" data-tab="zip"><?php esc_html_e('US ZIP Codes', 'taylor-distributor-locator'); ?></button>
        <button type="button" class="tdl-zone-tab" data-tab="states"><?php esc_html_e('States/Provinces', 'taylor-distributor-locator'); ?></button>
        <button type="button" class="tdl-zone-tab" data-tab="countries"><?php esc_html_e('Countries', 'taylor-distributor-locator'); ?></button>
    </div>
    
    <!-- ZIP Codes Tab -->
    <div class="tdl-zone-panel active" data-panel="zip">
        <p class="description"><?php esc_html_e('Enter ZIP codes and ranges, one per line. Use format: 75001 (single) or 75000-75999 (range)', 'taylor-distributor-locator'); ?></p>
        <textarea name="tdl_zip_codes" rows="10" class="large-text"><?php echo esc_textarea(implode("\n", $zip_codes)); ?></textarea>
    </div>
    
    <!-- States Tab -->
    <div class="tdl-zone-panel" data-panel="states">
        <div class="tdl-states-columns">
            <!-- US States -->
            <div class="tdl-states-column">
                <h4>
                    <?php esc_html_e('US States', 'taylor-distributor-locator'); ?>
                    <button type="button" class="button-link tdl-select-all" data-target="us"><?php esc_html_e('Select All', 'taylor-distributor-locator'); ?></button>
                </h4>
                <div class="tdl-checkbox-list">
                    <?php foreach ($us_states as $code => $name): ?>
                        <label>
                            <input type="checkbox" name="tdl_states_us[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $states['US'])); ?> />
                            <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Canadian Provinces -->
            <div class="tdl-states-column">
                <h4>
                    <?php esc_html_e('Canadian Provinces', 'taylor-distributor-locator'); ?>
                    <button type="button" class="button-link tdl-select-all" data-target="ca"><?php esc_html_e('Select All', 'taylor-distributor-locator'); ?></button>
                </h4>
                <div class="tdl-checkbox-list">
                    <?php foreach ($ca_provinces as $code => $name): ?>
                        <label>
                            <input type="checkbox" name="tdl_states_ca[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $states['CA'])); ?> />
                            <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Mexican States -->
            <div class="tdl-states-column">
                <h4>
                    <?php esc_html_e('Mexican States', 'taylor-distributor-locator'); ?>
                    <button type="button" class="button-link tdl-select-all" data-target="mx"><?php esc_html_e('Select All', 'taylor-distributor-locator'); ?></button>
                </h4>
                <div class="tdl-checkbox-list">
                    <?php foreach ($mx_states as $code => $name): ?>
                        <label>
                            <input type="checkbox" name="tdl_states_mx[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $states['MX'])); ?> />
                            <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Countries Tab -->
    <div class="tdl-zone-panel" data-panel="countries">
        <p class="description"><?php esc_html_e('Select countries this distributor serves (not where they are located). US, Canada, and Mexico are handled in the States tab.', 'taylor-distributor-locator'); ?></p>
        <div class="tdl-checkbox-list tdl-countries-list">
            <?php foreach ($all_countries as $code => $name): ?>
                <label>
                    <input type="checkbox" name="tdl_countries[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $countries)); ?> />
                    <?php echo esc_html($name); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
