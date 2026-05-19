<?php
/**
 * Plugin Name: Taylor Distributor Locator
 * Plugin URI: https://welldressedwalrus.com
 * Description: Custom distributor locator for Taylor Company
 * Version: 0.4.3
 * Requires PHP: 8.1
 * Author: Well Dressed Walrus
 * Text Domain: taylor-distributor-locator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('TDL_VERSION', '0.4.3');
define('TDL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TDL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TDL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-activator.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-deactivator.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-i18n.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-post-type.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-admin-settings.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-admin-status.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-geocoder.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-meta-boxes.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-rest-api.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-shortcode.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-gf-integration.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-csv-importer.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-csv-exporter.php';
require_once TDL_PLUGIN_DIR . 'includes/class-tdl-m2-importer.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, ['TDL_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['TDL_Deactivator', 'deactivate']);

/**
 * Initialize the plugin on plugins_loaded
 */
function tdl_init() {
    // Load text domain
    TDL_i18n::load_textdomain();
    
    // Initialize components
    TDL_Post_Type::init();
    TDL_Admin_Settings::init();
    TDL_Admin_Status::init();
    TDL_Geocoder::init();
    TDL_Meta_Boxes::init();
    TDL_REST_API::init();
    TDL_Shortcode::init();
    TDL_GF_Integration::init();
    TDL_CSV_Importer::init();
    TDL_CSV_Exporter::init();
    TDL_M2_Importer::init();
}
add_action('plugins_loaded', 'tdl_init');
