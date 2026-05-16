<?php
/**
 * Contact Information Meta Box Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="form-table tdl-meta-table">
    <tr>
        <th><label for="tdl_website"><?php esc_html_e('Website', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="url" name="_tdl_website" id="tdl_website" value="<?php echo esc_url($website); ?>" class="regular-text" placeholder="https://example.com" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_phone_main"><?php esc_html_e('Main Phone', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="text" name="_tdl_phone_main" id="tdl_phone_main" value="<?php echo esc_attr($phone); ?>" class="regular-text" placeholder="555-123-4567" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_email_main"><?php esc_html_e('Main Email', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="email" name="_tdl_email_main" id="tdl_email_main" value="<?php echo esc_attr($email_main); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_email_sales"><?php esc_html_e('Sales Email', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="email" name="_tdl_email_sales" id="tdl_email_sales" value="<?php echo esc_attr($email_sales); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_email_parts"><?php esc_html_e('Parts Email', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="email" name="_tdl_email_parts" id="tdl_email_parts" value="<?php echo esc_attr($email_parts); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_email_service"><?php esc_html_e('Service Email', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="email" name="_tdl_email_service" id="tdl_email_service" value="<?php echo esc_attr($email_service); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th><label for="tdl_email_installs"><?php esc_html_e('Installations Email', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <input type="email" name="_tdl_email_installs" id="tdl_email_installs" value="<?php echo esc_attr($email_installs); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Additional Emails', 'taylor-distributor-locator'); ?></th>
        <td>
            <div id="tdl-other-emails">
                <?php if (!empty($email_other)): ?>
                    <?php foreach ($email_other as $i => $item): ?>
                        <div class="tdl-email-row">
                            <input type="text" name="_tdl_email_other_label[]" value="<?php echo esc_attr($item['label']); ?>" placeholder="<?php esc_attr_e('Label', 'taylor-distributor-locator'); ?>" />
                            <input type="email" name="_tdl_email_other_email[]" value="<?php echo esc_attr($item['email']); ?>" placeholder="<?php esc_attr_e('Email', 'taylor-distributor-locator'); ?>" />
                            <button type="button" class="button tdl-remove-email">&times;</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button tdl-add-email" id="tdl-add-email"><?php esc_html_e('Add Email', 'taylor-distributor-locator'); ?></button>
        </td>
    </tr>
    <tr>
        <th><label for="tdl_service_area_notes"><?php esc_html_e('Service Area Notes', 'taylor-distributor-locator'); ?></label></th>
        <td>
            <textarea name="_tdl_service_area_notes" id="tdl_service_area_notes" rows="3" class="large-text"><?php echo esc_textarea($notes); ?></textarea>
            <p class="description"><?php esc_html_e('Internal notes about service areas (not displayed publicly)', 'taylor-distributor-locator'); ?></p>
        </td>
    </tr>
</table>
