<?php
/**
 * Admin Status Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Admin_Status {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_status_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_tdl_trigger_geocoding', [__CLASS__, 'ajax_trigger_geocoding']);
        add_action('wp_ajax_tdl_stop_geocoding', [__CLASS__, 'ajax_stop_geocoding']);
        add_action('wp_ajax_tdl_get_geocoding_status', [__CLASS__, 'ajax_get_status']);
        add_action('wp_ajax_tdl_clear_geo_errors', [__CLASS__, 'ajax_clear_errors']);
    }
    
    /**
     * Add status page under Distributors menu
     */
    public static function add_status_page() {
        add_submenu_page(
            'edit.php?post_type=distributor',
            __('Geocoding Status', 'taylor-distributor-locator'),
            __('Geocoding Status', 'taylor-distributor-locator'),
            'manage_options',
            'tdl-geo-status',
            [__CLASS__, 'render_status_page']
        );
    }
    
    /**
     * Enqueue scripts for status page
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'distributor_page_tdl-geo-status') {
            return;
        }
        
        wp_enqueue_style(
            'tdl-admin-status',
            TDL_PLUGIN_URL . 'assets/css/tdl-admin-status.css',
            [],
            TDL_VERSION
        );
        
        wp_enqueue_script(
            'tdl-admin-status',
            TDL_PLUGIN_URL . 'assets/js/tdl-admin-status.js',
            ['jquery'],
            TDL_VERSION,
            true
        );
        
        wp_localize_script('tdl-admin-status', 'tdlGeoStatus', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tdl_geo_status_nonce'),
            'strings' => [
                'running' => __('Geocoding Running...', 'taylor-distributor-locator'),
                'stopping' => __('Stopping...', 'taylor-distributor-locator'),
                'success' => __('Geocoding Initiated', 'taylor-distributor-locator'),
                'error' => __('Error starting geocoding', 'taylor-distributor-locator'),
                'complete' => __('Geocoding Complete', 'taylor-distributor-locator'),
            ]
        ]);
    }
    
    /**
     * Render the status page
     */
    public static function render_status_page() {
        // Get stats
        $stats = get_option('tdl_geocoding_status', []);
        $errors = get_option('tdl_geocoding_errors', []);
        
        // Get counts from DB
        global $wpdb;
        $table_loc = $wpdb->prefix . 'tdl_locations';
        $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc}");
        $geocoded_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc} WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0");
        $errored_locations = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc} WHERE geocode_error IS NOT NULL");
        $pending_locations = $total_locations - $geocoded_locations;
        $percent = $total_locations > 0 ? round(($geocoded_locations / $total_locations) * 100) : 0;
        
        $is_running = !empty($stats['status']) && $stats['status'] === 'running';
        
        // Check if "running" but actually stale (timestamp > 5 mins ago)
        if ($is_running && !empty($stats['timestamp']) && (time() - $stats['timestamp'] > 300)) {
            $is_running = false;
        }

        ?>
        <div class="wrap tdl-status-wrap">
            <h1><?php echo esc_html__('Geocoding System Status', 'taylor-distributor-locator'); ?></h1>
            
            <div class="tdl-status-grid">
                <!-- Overview Card -->
                <div class="tdl-card tdl-overview-card">
                    <h2><?php echo esc_html__('Overview', 'taylor-distributor-locator'); ?></h2>
                    <div class="tdl-stat-circle" data-percent="<?php echo esc_attr($percent); ?>">
                        <span class="tdl-percent"><?php echo esc_html($percent); ?>%</span>
                        <span class="tdl-label"><?php echo esc_html__('Geocoded', 'taylor-distributor-locator'); ?></span>
                    </div>
                    
                    <div class="tdl-progress-container">
                         <div class="tdl-progress-bar">
                             <div class="tdl-progress-fill" style="width: <?php echo esc_attr($percent); ?>%"></div>
                         </div>
                    </div>

                    <div class="tdl-stat-details">
                        <div class="tdl-stat-item">
                            <span class="tdl-stat-label"><?php echo esc_html__('Total Locations', 'taylor-distributor-locator'); ?>:</span>
                            <span class="tdl-stat-value" id="tdl-total-count"><?php echo esc_html($total_locations); ?></span>
                        </div>
                        <div class="tdl-stat-item">
                            <span class="tdl-stat-label"><?php echo esc_html__('Pending Geocoding', 'taylor-distributor-locator'); ?>:</span>
                            <span class="tdl-stat-value tdl-warning" id="tdl-pending-count"><?php echo esc_html($pending_locations); ?></span>
                        </div>
                        <div class="tdl-stat-item">
                            <span class="tdl-stat-label"><?php echo esc_html__('Errored (skipped)', 'taylor-distributor-locator'); ?>:</span>
                            <span class="tdl-stat-value tdl-error" id="tdl-errored-count"><?php echo esc_html($errored_locations); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- System Status Card -->
                <div class="tdl-card tdl-system-card">
                    <h2><?php echo esc_html__('System Status', 'taylor-distributor-locator'); ?></h2>
                    <div class="tdl-status-list">
                        <div class="tdl-status-row">
                            <span class="tdl-status-label"><?php echo esc_html__('Status:', 'taylor-distributor-locator'); ?></span>
                            <span class="tdl-status-value" id="tdl-system-status">
                                <?php echo $is_running ? esc_html__('Running', 'taylor-distributor-locator') : esc_html__('Idle', 'taylor-distributor-locator'); ?>
                            </span>
                        </div>
                        <div class="tdl-status-row">
                            <span class="tdl-status-label"><?php echo esc_html__('Last Activity:', 'taylor-distributor-locator'); ?></span>
                            <span class="tdl-status-value" id="tdl-last-activity">
                                <?php 
                                echo !empty($stats['timestamp']) 
                                    ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $stats['timestamp'])) 
                                    : esc_html__('Unknown', 'taylor-distributor-locator'); 
                                ?>
                            </span>
                        </div>
                        
                        <div class="tdl-actions">
                            <button id="tdl-run-geocoding" class="button button-primary button-large" <?php disabled($is_running || ($pending_locations == 0 && $errored_locations == 0)); ?>>
                                <?php echo esc_html__('Run Geocoding', 'taylor-distributor-locator'); ?>
                            </button>
                            
                            <?php if ($errored_locations > 0): ?>
                            <button id="tdl-force-geocoding" class="button button-secondary button-large" <?php disabled($is_running); ?>>
                                <?php echo esc_html__('Re-geocode All (incl. errors)', 'taylor-distributor-locator'); ?>
                            </button>
                            <?php endif; ?>
                            
                            <button id="tdl-stop-geocoding" class="button button-secondary button-large" <?php echo !$is_running ? 'style="display:none;"' : ''; ?>>
                                <?php echo esc_html__('Cancel', 'taylor-distributor-locator'); ?>
                            </button>
                            
                            <span class="spinner"></span>
                        </div>
                        <p class="description">
                            <?php echo esc_html__('Triggers a background process. You can navigate away from this page.', 'taylor-distributor-locator'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Error Log -->
            <div class="tdl-card tdl-error-card">
                <div class="tdl-card-header">
                    <h2><?php echo esc_html__('Recent Geocoding Errors', 'taylor-distributor-locator'); ?></h2>
                    <?php if (!empty($errors)): ?>
                        <button id="tdl-clear-errors" class="button button-secondary button-small">
                            <?php echo esc_html__('Clear Log', 'taylor-distributor-locator'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($errors)): ?>
                    <p class="tdl-empty-state"><?php echo esc_html__('No errors logged recently.', 'taylor-distributor-locator'); ?></p>
                <?php else: ?>
                    <table class="widefat tdl-error-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Date', 'taylor-distributor-locator'); ?></th>
                                <th><?php echo esc_html__('Location ID', 'taylor-distributor-locator'); ?></th>
                                <th><?php echo esc_html__('Address', 'taylor-distributor-locator'); ?></th>
                                <th><?php echo esc_html__('Error', 'taylor-distributor-locator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($errors) as $error): ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $error['time'])); ?></td>
                                    <td><?php echo esc_html($error['location_id']); ?></td>
                                    <td><?php echo esc_html($error['address']); ?></td>
                                    <td class="tdl-error-msg"><?php echo esc_html($error['error']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to trigger geocoding
     */
    public static function ajax_trigger_geocoding() {
        check_ajax_referer('tdl_geo_status_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'taylor-distributor-locator')]);
        }
        
        $force = !empty($_POST['force']);
        $total = TDL_Geocoder::start_geocoding($force);
        
        wp_send_json_success([
            'message' => __('Geocoding started in background.', 'taylor-distributor-locator'),
            'total' => $total
        ]);
    }
    
    /**
     * AJAX handler to stop geocoding
     */
    public static function ajax_stop_geocoding() {
        check_ajax_referer('tdl_geo_status_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'taylor-distributor-locator')]);
        }
        
        TDL_Geocoder::cancel_geocoding();
        
        wp_send_json_success([
            'message' => __('Cancelling geocoding...', 'taylor-distributor-locator')
        ]);
    }

    /**
     * AJAX handler to get current status
     */
    public static function ajax_get_status() {
        check_ajax_referer('tdl_geo_status_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'taylor-distributor-locator')]);
        }
        
        $stats = get_option('tdl_geocoding_status', []);
        
        // Calculate effective status
        $status = $stats['status'] ?? 'idle';
        if ($status === 'running' && !empty($stats['timestamp']) && (time() - $stats['timestamp'] > 300)) {
            $status = 'stalled';
        }

        // If status is running, ensure cron is spawned so the batch keeps moving
        if ($status === 'running') {
            spawn_cron();
        }
        
        // Get fresh counts
        global $wpdb;
        $table_loc = $wpdb->prefix . 'tdl_locations';
        $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc}");
        $geocoded_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc} WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0");
        $errored_locations = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_loc} WHERE geocode_error IS NOT NULL");
        $pending_locations = $total_locations - $geocoded_locations;
        $percent = $total_locations > 0 ? round(($geocoded_locations / $total_locations) * 100) : 0;
        
        // Get recent errors
        $errors = get_option('tdl_geocoding_errors', []);
        // Send last 5 errors
        $recent_errors = array_slice(array_reverse($errors), 0, 5);
        $formatted_errors = array_map(function($err) {
            $err['time_formatted'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $err['time']);
            return $err;
        }, $recent_errors);
        
        wp_send_json_success([
            'status' => $status,
            'timestamp' => $stats['timestamp'] ?? 0,
            'total' => $total_locations,
            'geocoded' => $geocoded_locations,
            'pending' => $pending_locations,
            'errored' => $errored_locations,
            'percent' => $percent,
            'last_activity_formatted' => !empty($stats['timestamp']) 
                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $stats['timestamp']) 
                : __('Never', 'taylor-distributor-locator'),
            'errors' => $formatted_errors
        ]);
    }
    
    /**
     * AJAX handler to clear errors
     */
    public static function ajax_clear_errors() {
        check_ajax_referer('tdl_geo_status_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'taylor-distributor-locator')]);
        }
        
        delete_option('tdl_geocoding_errors');

        // Also clear per-location error flags so they can be retried
        global $wpdb;
        $table = $wpdb->prefix . 'tdl_locations';
        $wpdb->query("UPDATE {$table} SET geocode_error = NULL WHERE geocode_error IS NOT NULL");
        
        wp_send_json_success(['message' => __('Error log cleared.', 'taylor-distributor-locator')]);
    }
}
