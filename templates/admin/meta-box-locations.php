<?php
/**
 * Physical Locations Meta Box Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$countries = TDL_REST_API::get_country_names();
asort($countries);
?>

<div id="tdl-locations-wrapper">
    <?php if (empty($locations)): ?>
        <p class="tdl-no-locations"><?php esc_html_e('No locations added yet. Click "Add Location" to add one.', 'taylor-distributor-locator'); ?></p>
    <?php else: ?>
        <?php foreach ($locations as $i => $loc): ?>
            <div class="tdl-location-card" data-index="<?php echo esc_attr($i); ?>">
                <div class="tdl-location-header">
                    <span class="tdl-location-title"><?php echo esc_html($loc->location_name ?: __('Location', 'taylor-distributor-locator')); ?></span>
                    <button type="button" class="button tdl-remove-location"><?php esc_html_e('Remove', 'taylor-distributor-locator'); ?></button>
                </div>
                <div class="tdl-location-fields">
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('Location Name', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][location_name]" value="<?php echo esc_attr($loc->location_name); ?>" placeholder="<?php esc_attr_e('Headquarters', 'taylor-distributor-locator'); ?>" />
                        </div>
                        <div class="tdl-field tdl-field-primary">
                            <label>
                                <input type="radio" name="tdl_location_primary" value="<?php echo $i; ?>" <?php checked($loc->is_primary, 1); ?> />
                                <?php esc_html_e('Primary Location', 'taylor-distributor-locator'); ?>
                            </label>
                            <input type="hidden" name="tdl_location[<?php echo $i; ?>][is_primary]" value="<?php echo $loc->is_primary ? '1' : '0'; ?>" class="tdl-is-primary" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field tdl-field-full">
                            <label><?php esc_html_e('Street Address', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][street_address]" value="<?php echo esc_attr($loc->street_address); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field tdl-field-full">
                            <label><?php esc_html_e('Address Line 2', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][address_2]" value="<?php echo esc_attr($loc->address_2 ?? ''); ?>" placeholder="<?php esc_attr_e('Suite, Unit, Building, etc.', 'taylor-distributor-locator'); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field tdl-field-full">
                            <label><?php esc_html_e('Address Line 3', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][address_3]" value="<?php echo esc_attr($loc->address_3 ?? ''); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('City', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][city]" value="<?php echo esc_attr($loc->city); ?>" />
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('State/Province', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][state_province]" value="<?php echo esc_attr($loc->state_province); ?>" />
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('ZIP/Postal Code', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][zip_postal]" value="<?php echo esc_attr($loc->zip_postal); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('Country', 'taylor-distributor-locator'); ?></label>
                            <select name="tdl_location[<?php echo $i; ?>][country_code]">
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($loc->country_code, $code); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('Phone', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][phone]" value="<?php echo esc_attr($loc->phone); ?>" placeholder="<?php esc_attr_e('Optional override', 'taylor-distributor-locator'); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('Sales Email', 'taylor-distributor-locator'); ?></label>
                            <input type="email" name="tdl_location[<?php echo $i; ?>][email_sales]" value="<?php echo esc_attr($loc->email_sales ?? ''); ?>" placeholder="<?php esc_attr_e('Override Sales Email', 'taylor-distributor-locator'); ?>" />
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('Parts Email', 'taylor-distributor-locator'); ?></label>
                            <input type="email" name="tdl_location[<?php echo $i; ?>][email_parts]" value="<?php echo esc_attr($loc->email_parts ?? ''); ?>" placeholder="<?php esc_attr_e('Override Parts Email', 'taylor-distributor-locator'); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('Service Email', 'taylor-distributor-locator'); ?></label>
                            <input type="email" name="tdl_location[<?php echo $i; ?>][email_service]" value="<?php echo esc_attr($loc->email_service ?? ''); ?>" placeholder="<?php esc_attr_e('Override Service Email', 'taylor-distributor-locator'); ?>" />
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('Installations Email', 'taylor-distributor-locator'); ?></label>
                            <input type="email" name="tdl_location[<?php echo $i; ?>][email_installations]" value="<?php echo esc_attr($loc->email_installations ?? ''); ?>" placeholder="<?php esc_attr_e('Override Install Email', 'taylor-distributor-locator'); ?>" />
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field tdl-field-full">
                            <label><?php esc_html_e('Hours of Operation', 'taylor-distributor-locator'); ?></label>
                            <textarea name="tdl_location[<?php echo $i; ?>][hours_operation]" rows="3" style="width:100%"><?php echo esc_textarea($loc->hours_operation ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="tdl-field-row">
                        <div class="tdl-field">
                            <label><?php esc_html_e('Latitude', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][latitude]" value="<?php echo esc_attr($loc->latitude); ?>" class="tdl-lat" readonly />
                        </div>
                        <div class="tdl-field">
                            <label><?php esc_html_e('Longitude', 'taylor-distributor-locator'); ?></label>
                            <input type="text" name="tdl_location[<?php echo $i; ?>][longitude]" value="<?php echo esc_attr($loc->longitude); ?>" class="tdl-lng" readonly />
                        </div>
                        <div class="tdl-field tdl-field-geocode">
                            <button type="button" class="button tdl-geocode-btn"><?php esc_html_e('Geocode', 'taylor-distributor-locator'); ?></button>
                            <span class="tdl-geocode-status"></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="button" class="button button-primary" id="tdl-add-location"><?php esc_html_e('Add Location', 'taylor-distributor-locator'); ?></button>

<script type="text/html" id="tmpl-tdl-location">
<div class="tdl-location-card" data-index="{{data.index}}">
    <div class="tdl-location-header">
        <span class="tdl-location-title"><?php esc_html_e('New Location', 'taylor-distributor-locator'); ?></span>
        <button type="button" class="button tdl-remove-location"><?php esc_html_e('Remove', 'taylor-distributor-locator'); ?></button>
    </div>
    <div class="tdl-location-fields">
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('Location Name', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][location_name]" value="" placeholder="<?php esc_attr_e('Headquarters', 'taylor-distributor-locator'); ?>" />
            </div>
            <div class="tdl-field tdl-field-primary">
                <label>
                    <input type="radio" name="tdl_location_primary" value="{{data.index}}" />
                    <?php esc_html_e('Primary Location', 'taylor-distributor-locator'); ?>
                </label>
                <input type="hidden" name="tdl_location[{{data.index}}][is_primary]" value="0" class="tdl-is-primary" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field tdl-field-full">
                <label><?php esc_html_e('Street Address', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][street_address]" value="" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field tdl-field-full">
                <label><?php esc_html_e('Address Line 2', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][address_2]" value="" placeholder="<?php esc_attr_e('Suite, Unit, Building, etc.', 'taylor-distributor-locator'); ?>" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field tdl-field-full">
                <label><?php esc_html_e('Address Line 3', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][address_3]" value="" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('City', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][city]" value="" />
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('State/Province', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][state_province]" value="" />
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('ZIP/Postal Code', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][zip_postal]" value="" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('Country', 'taylor-distributor-locator'); ?></label>
                <select name="tdl_location[{{data.index}}][country_code]">
                    <?php foreach ($countries as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected('US', $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('Phone', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][phone]" value="" placeholder="<?php esc_attr_e('Optional override', 'taylor-distributor-locator'); ?>" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('Sales Email', 'taylor-distributor-locator'); ?></label>
                <input type="email" name="tdl_location[{{data.index}}][email_sales]" value="" placeholder="<?php esc_attr_e('Override Sales Email', 'taylor-distributor-locator'); ?>" />
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('Parts Email', 'taylor-distributor-locator'); ?></label>
                <input type="email" name="tdl_location[{{data.index}}][email_parts]" value="" placeholder="<?php esc_attr_e('Override Parts Email', 'taylor-distributor-locator'); ?>" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('Service Email', 'taylor-distributor-locator'); ?></label>
                <input type="email" name="tdl_location[{{data.index}}][email_service]" value="" placeholder="<?php esc_attr_e('Override Service Email', 'taylor-distributor-locator'); ?>" />
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('Installations Email', 'taylor-distributor-locator'); ?></label>
                <input type="email" name="tdl_location[{{data.index}}][email_installations]" value="" placeholder="<?php esc_attr_e('Override Install Email', 'taylor-distributor-locator'); ?>" />
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field tdl-field-full">
                <label><?php esc_html_e('Hours of Operation', 'taylor-distributor-locator'); ?></label>
                <textarea name="tdl_location[{{data.index}}][hours_operation]" rows="3" style="width:100%"></textarea>
            </div>
        </div>
        <div class="tdl-field-row">
            <div class="tdl-field">
                <label><?php esc_html_e('Latitude', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][latitude]" value="" class="tdl-lat" readonly />
            </div>
            <div class="tdl-field">
                <label><?php esc_html_e('Longitude', 'taylor-distributor-locator'); ?></label>
                <input type="text" name="tdl_location[{{data.index}}][longitude]" value="" class="tdl-lng" readonly />
            </div>
            <div class="tdl-field tdl-field-geocode">
                <button type="button" class="button tdl-geocode-btn"><?php esc_html_e('Geocode', 'taylor-distributor-locator'); ?></button>
                <span class="tdl-geocode-status"></span>
            </div>
        </div>
    </div>
</div>
</script>
