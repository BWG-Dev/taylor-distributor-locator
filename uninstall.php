<?php
/**
 * Uninstall script - runs when plugin is deleted
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tdl_locations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tdl_service_zones");

// Delete all distributor posts
$distributors = get_posts([
    'post_type' => 'tdl_distributor',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($distributors as $id) {
    wp_delete_post($id, true);
}

// Delete all options
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tdl_%'"
);

// Delete all transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_tdl_%' 
     OR option_name LIKE '_transient_timeout_tdl_%'"
);
