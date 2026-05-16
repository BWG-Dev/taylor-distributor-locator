<?php
/**
 * Custom Post Type registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Post_Type {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('save_post_tdl_distributor', [__CLASS__, 'update_data_version']);
        
        // Custom Columns
        add_filter('manage_edit-tdl_distributor_columns', [__CLASS__, 'add_custom_columns']);
        add_action('manage_tdl_distributor_posts_custom_column', [__CLASS__, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-tdl_distributor_sortable_columns', [__CLASS__, 'sortable_columns']);
        
        // Sorting
        add_action('pre_get_posts', [__CLASS__, 'default_sort_order']);
        add_filter('posts_clauses', [__CLASS__, 'sort_by_geocode_status'], 10, 2);

        // Filters
        add_action('restrict_manage_posts', [__CLASS__, 'filter_by_geocode_status']);
        add_filter('parse_query', [__CLASS__, 'handle_geocode_status_filter']);
        
        // AJAX
        add_action('wp_ajax_tdl_geocode_distributor', [__CLASS__, 'ajax_geocode_distributor']);
        
        // Assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue scripts for the distributor list table
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'edit.php' || get_post_type() !== 'tdl_distributor') {
            return;
        }

        wp_enqueue_script(
            'tdl-admin-list',
            TDL_PLUGIN_URL . 'assets/js/tdl-admin-list.js',
            ['jquery'],
            TDL_VERSION,
            true
        );

        wp_localize_script('tdl-admin-list', 'tdlAdminList', [
            'nonce' => wp_create_nonce('tdl-admin-nonce')
        ]);
        
        // Add some basic styles inline for the spinner/button
        wp_add_inline_style('common', '
            .tdl-geocode-now { margin-top: 5px; }
            .spinner.is-active { float: none; margin: 0 0 0 5px; vertical-align: bottom; }
        ');
    }


    /**
     * Render filter dropdown
     */
    public static function filter_by_geocode_status($post_type) {
        if ($post_type !== 'tdl_distributor') {
            return;
        }

        $current = sanitize_text_field($_GET['tdl_geocode_filter'] ?? '');
        $options = [
            'completed' => __('Completed', 'taylor-distributor-locator'),
            'partial' => __('Pending / Partial', 'taylor-distributor-locator'),
            'no_locs' => __('No Locations', 'taylor-distributor-locator'),
            'error' => __('Has Errors', 'taylor-distributor-locator'),
        ];

        echo '<select name="tdl_geocode_filter">';
        echo '<option value="">' . __('All Geocode Statuses', 'taylor-distributor-locator') . '</option>';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Handle filter logic
     */
    public static function handle_geocode_status_filter($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'tdl_distributor') {
            return;
        }

        $filter = sanitize_text_field($_GET['tdl_geocode_filter'] ?? '');
        if (empty($filter)) {
            return;
        }

        global $wpdb;
        $locations_table = $wpdb->prefix . 'tdl_locations';

        // We can reuse the join logic from sorting or build specific WHERE clauses
        // Ideally we filter by ID
        
        $sql = "SELECT distributor_id, 
                COUNT(*) as total, 
                SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 THEN 1 ELSE 0 END) as geocoded 
                FROM {$locations_table} 
                GROUP BY distributor_id";
        
        $stats = $wpdb->get_results($sql);
        $ids = [];

        foreach ($stats as $stat) {
            switch ($filter) {
                case 'completed':
                    if ($stat->total > 0 && $stat->geocoded == $stat->total) {
                        $ids[] = $stat->distributor_id;
                    }
                    break;
                case 'partial':
                    if ($stat->total > 0 && $stat->geocoded < $stat->total) {
                        $ids[] = $stat->distributor_id;
                    }
                    break;
                case 'no_locs':
                    // This logic is tricky because "no locations" means no rows in the table
                    // So we handle it differently (Exclude all IDs present in table)
                    break;
            }
        }

        if ($filter === 'no_locs') {
            // Find IDs present in table
            $all_with_locs = $wpdb->get_col("SELECT DISTINCT distributor_id FROM {$locations_table}");
            if (!empty($all_with_locs)) {
                $query->set('post__not_in', $all_with_locs);
            }
        } elseif ($filter === 'error') {
            // Use post meta flag set by batch_geocode_locations()
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key' => '_tdl_has_geocode_errors',
                'value' => '1',
            ];
            $query->set('meta_query', $meta_query);
        } else {
            if (!empty($ids)) {
                $query->set('post__in', $ids);
            } else {
                $query->set('post__in', [0]); // Force empty result
            }
        }
    }

    /**
     * AJAX handler for manual geocoding
     */
    public static function ajax_geocode_distributor() {
        check_ajax_referer('tdl-admin-nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'taylor-distributor-locator'));
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error(__('Invalid ID', 'taylor-distributor-locator'));
        }
        
        $result = TDL_Geocoder::batch_geocode_locations($post_id);
        
        wp_send_json_success($result);
    }

    /**
     * Register the distributor post type
     */
    public static function register_post_type() {
        $labels = [
            'name'                  => _x('Distributors', 'Post type general name', 'taylor-distributor-locator'),
            'singular_name'         => _x('Distributor', 'Post type singular name', 'taylor-distributor-locator'),
            'menu_name'             => _x('Distributors', 'Admin Menu text', 'taylor-distributor-locator'),
            'name_admin_bar'        => _x('Distributor', 'Add New on Toolbar', 'taylor-distributor-locator'),
            'add_new'               => __('Add New', 'taylor-distributor-locator'),
            'add_new_item'          => __('Add New Distributor', 'taylor-distributor-locator'),
            'new_item'              => __('New Distributor', 'taylor-distributor-locator'),
            'edit_item'             => __('Edit Distributor', 'taylor-distributor-locator'),
            'view_item'             => __('View Distributor', 'taylor-distributor-locator'),
            'all_items'             => __('All Distributors', 'taylor-distributor-locator'),
            'search_items'          => __('Search Distributors', 'taylor-distributor-locator'),
            'not_found'             => __('No distributors found.', 'taylor-distributor-locator'),
            'not_found_in_trash'    => __('No distributors found in Trash.', 'taylor-distributor-locator'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-location-alt',
            'supports'            => ['title', 'thumbnail'],
            'has_archive'         => false,
            'publicly_queryable'  => false,
            'show_in_rest'        => true,
            'capability_type'     => 'post',
        ];
        
        register_post_type('tdl_distributor', $args);
    }
    
    /**
     * Update data version on save
     */
    public static function update_data_version() {
        update_option('tdl_data_version', time());
    }

    /**
     * Add custom columns to distributor list
     */
    public static function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            // Insert after title
            if ($key === 'title') {
                $new_columns['tdl_locations_count'] = __('Locations', 'taylor-distributor-locator');
                $new_columns['tdl_service_area'] = __('Service Area', 'taylor-distributor-locator');
                $new_columns['tdl_geocode_status'] = __('Geocode Status', 'taylor-distributor-locator');
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public static function render_custom_columns($column, $post_id) {
        global $wpdb;

        switch ($column) {
            case 'tdl_locations_count':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}tdl_locations WHERE distributor_id = %d",
                    $post_id
                ));
                echo esc_html($count);
                break;

            case 'tdl_service_area':
                $zones = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT zone_value, country_context, zone_type FROM {$wpdb->prefix}tdl_service_zones WHERE distributor_id = %d ORDER BY country_context, zone_value",
                    $post_id
                ));
                
                if (empty($zones)) {
                    echo '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
                } else {
                    $areas = [];
                    foreach ($zones as $zone) {
                        if ($zone->zone_type === 'state') {
                            $areas[] = $zone->zone_value;
                        } elseif ($zone->zone_type === 'country') {
                            $areas[] = $zone->zone_value;
                        }
                    }
                    // Limit display
                    if (count($areas) > 5) {
                        $remaining = count($areas) - 5;
                        $areas = array_slice($areas, 0, 5);
                        echo esc_html(implode(', ', $areas)) . ' <span title="' . esc_attr(__('More...', 'taylor-distributor-locator')) . '">+' . $remaining . '</span>';
                    } else {
                        echo esc_html(implode(', ', $areas));
                    }
                }
                break;

            case 'tdl_geocode_status':
                $locations = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, latitude, longitude FROM {$wpdb->prefix}tdl_locations WHERE distributor_id = %d",
                    $post_id
                ));

                if (empty($locations)) {
                    echo '<span style="color: #999;">' . __('N/A', 'taylor-distributor-locator') . '</span>';
                    break;
                }

                $needs_geocode = false;

                // Check for specific errors
                $failures = get_transient('tdl_geocode_failures_' . $post_id);
                if ($failures) {
                    echo '<span style="color: #dc3232; font-weight: bold;"><span class="dashicons dashicons-warning"></span> ' . __('Error', 'taylor-distributor-locator') . '</span>';
                    $needs_geocode = true;
                } else {
                    $total = count($locations);
                    $geocoded = 0;
                    foreach ($locations as $loc) {
                        if (!empty($loc->latitude) && !empty($loc->longitude)) {
                            $geocoded++;
                        }
                    }

                    if ($geocoded === $total) {
                        echo '<span style="color: #46b450; font-weight: bold;"><span class="dashicons dashicons-yes"></span> ' . __('Completed', 'taylor-distributor-locator') . '</span>';
                    } elseif ($geocoded === 0) {
                        echo '<span style="color: #f56e28; font-weight: bold;"><span class="dashicons dashicons-clock"></span> ' . __('Not Done', 'taylor-distributor-locator') . '</span>';
                        $needs_geocode = true;
                    } else {
                        echo '<span style="color: #ffb900; font-weight: bold;">' . sprintf(__('%d/%d Geocoded', 'taylor-distributor-locator'), $geocoded, $total) . '</span>';
                        $needs_geocode = true;
                    }
                }

                if ($needs_geocode) {
                    echo '<br><button type="button" class="button button-small tdl-geocode-now" data-id="' . esc_attr($post_id) . '" style="margin-top: 5px;">' . __('Run Geocode', 'taylor-distributor-locator') . '</button>';
                    echo '<span class="spinner tdl-spinner-' . esc_attr($post_id) . '"></span>';
                }
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public static function sortable_columns($columns) {
        $columns['tdl_locations_count'] = 'tdl_locations_count';
        $columns['tdl_geocode_status'] = 'tdl_geocode_status';
        return $columns;
    }

    /**
     * Set default sort order
     */
    public static function default_sort_order($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'tdl_distributor') {
            return;
        }

        if (!$query->get('orderby')) {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }
    }

    /**
     * Sort by geocode status (calculated)
     */
    public static function sort_by_geocode_status($clauses, $query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('orderby') !== 'tdl_geocode_status') {
            return $clauses;
        }

        global $wpdb;

        // Subquery to calculate completion percentage: (geocoded / total) * 100
        // We use COALESCE to handle division by zero or nulls
        // 100 = Fully Geocoded
        // 0 = Not Geocoded
        // Between = Partial
        $locations_table = $wpdb->prefix . 'tdl_locations';
        
        $clauses['join'] .= " LEFT JOIN (
            SELECT 
                distributor_id,
                COUNT(*) as total_locs,
                SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 THEN 1 ELSE 0 END) as geocoded_locs
            FROM {$locations_table}
            GROUP BY distributor_id
        ) AS loc_stats ON {$wpdb->posts}.ID = loc_stats.distributor_id";

        // Calculate percentage, default to -1 for no locations so they sort last/first properly
        $clauses['orderby'] = "COALESCE((loc_stats.geocoded_locs / NULLIF(loc_stats.total_locs, 0)), -1) " . $query->get('order');

        return $clauses;
    }
}
