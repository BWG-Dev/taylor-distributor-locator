<?php
/**
 * Google Geocoding API wrapper (admin only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_Geocoder {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_endpoints']);
        // Hook for background processing
        add_action('tdl_geocoding_event', [__CLASS__, 'process_geocoding_batch']);
        // Hook for per-distributor geocoding (scheduled by CSV importer and post save)
        add_action('tdl_geocode_distributor', [__CLASS__, 'batch_geocode_locations']);
    }
    
    /**
     * Register REST endpoint for geocoding
     */
    public static function register_endpoints() {
        register_rest_route('tdl/v1', '/geocode', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_geocode_request'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'address' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'street' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'city' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'state' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'zip' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'country' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }
    
    /**
     * Handle geocode REST request
     */
    public static function handle_geocode_request($request) {
        $address = $request->get_param('address');
        $components = [
            'street' => $request->get_param('street'),
            'city' => $request->get_param('city'),
            'state' => $request->get_param('state'),
            'postalcode' => $request->get_param('zip'),
            'country' => $request->get_param('country'),
        ];

        $result = self::geocode_address($address, array_filter($components));
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
        ], 200);
    }
    
    /**
     * Geocode an address
     *
     * @param string $address The address to geocode
     * @param array $components Optional structured components
     * @return array|WP_Error Returns lat/lng array or WP_Error on failure
     */
    public static function geocode_address($address, $components = []) {
        if (empty($address) && empty($components)) {
            return new WP_Error('empty_address', __('Address is required', 'taylor-distributor-locator'));
        }
        
        // Check cache first
        $cache_key = 'tdl_geo_' . md5($address . wp_json_encode($components));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $provider = get_option('tdl_map_provider', 'openstreetmap');
        
        if ($provider === 'openstreetmap') {
            if (!empty($components)) {
                // Clean street address for better matching
                if (!empty($components['street'])) {
                    $components['street'] = self::clean_street_address($components['street']);
                }
                
                $result = self::geocode_structured_nominatim($components);
                // Fallback to string if structured fails
                if (is_wp_error($result)) {
                    // Try to clean the address string too if possible, but for now structured cleaning is safer
                    $result = self::geocode_nominatim($address);
                }
            } else {
                $result = self::geocode_nominatim($address);
            }
        } else {
            $result = self::geocode_google($address);
        }

        if (!is_wp_error($result)) {
            // Cache for 90 days
            set_transient($cache_key, $result, 90 * DAY_IN_SECONDS);
        }
        
        return $result;
    }

    /**
     * Geocode using Google Maps API
     */
    private static function geocode_google($address) {
        $api_key = get_option('tdl_google_maps_api_key');
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('Google Maps API Key is missing', 'taylor-distributor-locator'));
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $url = add_query_arg([
            'address' => $address,
            'key' => $api_key,
        ], $url);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($body['status'] !== 'OK') {
            return new WP_Error('geocode_failed', $body['error_message'] ?? __('Geocoding failed', 'taylor-distributor-locator'));
        }

        $location = $body['results'][0]['geometry']['location'];

        return [
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
            'formatted' => $body['results'][0]['formatted_address'],
        ];
    }

    /**
     * Geocode using structured Nominatim query
     */
    private static function geocode_structured_nominatim($components) {
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = [
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1
        ];

        if (!empty($components['street'])) $params['street'] = $components['street'];
        if (!empty($components['city'])) $params['city'] = $components['city'];
        if (!empty($components['state'])) $params['state'] = $components['state'];
        if (!empty($components['postalcode'])) $params['postalcode'] = $components['postalcode'];
        if (!empty($components['country'])) $params['country'] = $components['country'];

        $url = add_query_arg($params, $url);

        return self::fetch_nominatim_url($url);
    }

    /**
     * Geocode using Nominatim (OpenStreetMap) string query
     */
    private static function geocode_nominatim($address) {
        $url = 'https://nominatim.openstreetmap.org/search';
        $url = add_query_arg([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1
        ], $url);

        $result = self::fetch_nominatim_url($url);

        // Fallback: If no results for full address, try stripping common parts
        if (is_wp_error($result) && strpos($address, ',') !== false) {
            $parts = explode(',', $address);
            if (count($parts) > 2) {
                $fallback_address = implode(',', array_slice($parts, 1));
                $url_fallback = add_query_arg([
                    'q' => trim($fallback_address),
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1
                ], 'https://nominatim.openstreetmap.org/search');
                $result = self::fetch_nominatim_url($url_fallback);
            }
        }

        return $result;
    }

    /**
     * Internal helper to fetch from Nominatim URL
     */
    private static function fetch_nominatim_url($url) {
        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'TaylorDistributorLocator/1.0 (' . home_url() . ')'
            ]
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !is_array($body) || count($body) === 0) {
            return new WP_Error('geocode_failed', __('Geocoding failed: No results found via Nominatim', 'taylor-distributor-locator'));
        }

        $location = $body[0];
        
        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lon'],
            'formatted' => $location['display_name'] ?? '',
        ];
    }

    /**
     * Process a batch of geocoding in the background
     * Run via cron/scheduler
     */
    public static function process_geocoding_batch() {
        // Prevent overlapping runs if one is already running very recently
        // But allow if the last run was stale (> 2 mins ago)
        $status = get_option('tdl_geocoding_status', []);
        if (!empty($status['status']) && $status['status'] === 'running' && !empty($status['timestamp'])) {
            // Increased to 5 minutes to prevent premature stalling
            if (time() - $status['timestamp'] < 300) {
                // Already running
                return;
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tdl_locations';

        // Check for cancellation
        if (get_option('tdl_geocoding_cancel')) {
            update_option('tdl_geocoding_status', [
                'status' => 'cancelled',
                'processed' => $status['processed'] ?? 0,
                'total' => $status['total'] ?? 0,
                'timestamp' => time()
            ]);
            delete_option('tdl_geocoding_cancel');
            return;
        }

        // Get total count (for progress) — exclude errored locations
        if (empty($status['total'])) {
            $total_pending = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE (latitude IS NULL OR longitude IS NULL OR latitude = 0) AND geocode_error IS NULL");
            $status['total'] = $total_pending;
        }

        // Set status to running
        $status['status'] = 'running';
        $status['timestamp'] = time();
        update_option('tdl_geocoding_status', $status);

        // Fetch a batch of locations to process
        // Process for ~20 seconds to avoid timeout, then reschedule
        // Skip locations that have already errored (geocode_error IS NOT NULL)
        $start_time = time();
        $processed_count = 0;
        
        $locations = $wpdb->get_results(
            "SELECT id, street_address, address_2, address_3, city, state_province, zip_postal, country_code 
             FROM {$table} 
             WHERE (latitude IS NULL OR longitude IS NULL OR latitude = 0) AND geocode_error IS NULL
             LIMIT 50"
        );

        if (empty($locations)) {
            // All done
            update_option('tdl_geocoding_status', [
                'status' => 'completed',
                'processed' => $status['processed'] ?? 0,
                'total' => $status['total'] ?? 0,
                'timestamp' => time()
            ]);
            return;
        }
        
        $errors_log = get_option('tdl_geocoding_errors', []);

        foreach ($locations as $location) {
            // Check cancellation after every item
            if (get_option('tdl_geocoding_cancel')) {
                update_option('tdl_geocoding_status', [
                    'status' => 'cancelled',
                    'processed' => ($status['processed'] ?? 0) + $processed_count,
                    'total' => $status['total'],
                    'timestamp' => time()
                ]);
                delete_option('tdl_geocoding_cancel');
                return;
            }

            // Also stop if we're running too long
            if (time() - $start_time > 20) {
                break;
            }

            try {
                $params = self::get_geocoding_params($location);
                $address = $params['address'];
                $components = $params['components'];
                
                $geocoded = self::geocode_address($address, $components);
                
                // Check for valid coordinates even if not WP_Error
                if (!is_wp_error($geocoded) && (empty($geocoded['latitude']) || empty($geocoded['longitude']))) {
                    $geocoded = new WP_Error('empty_coords', __('Geocoding returned empty coordinates', 'taylor-distributor-locator'));
                }
                
                if (is_wp_error($geocoded)) {
                    // Log persistent error
                    $errors_log[] = [
                        'time' => time(),
                        'location_id' => $location->id,
                        'address' => $address,
                        'error' => $geocoded->get_error_message(),
                    ];
                    // Keep only last 50 errors
                    if (count($errors_log) > 50) {
                        $errors_log = array_slice($errors_log, -50);
                    }
                    update_option('tdl_geocoding_errors', $errors_log);
    
                    // Mark this location as errored so it won't be retried
                    $wpdb->update(
                        $table,
                        ['geocode_error' => $geocoded->get_error_message()],
                        ['id' => $location->id],
                        ['%s'],
                        ['%d']
                    );
                } else {
                    $wpdb->update(
                        $table,
                        [
                            'latitude' => $geocoded['latitude'],
                            'longitude' => $geocoded['longitude'],
                            'geocode_error' => null,
                        ],
                        ['id' => $location->id],
                        ['%f', '%f', null],
                        ['%d']
                    );
                }
    
                $processed_count++;
                
                // Rate limit (sleep 1s to be safe with APIs)
                sleep(1);

            } catch (Exception $e) {
                // Log exception and continue
                $errors_log[] = [
                    'time' => time(),
                    'location_id' => $location->id,
                    'address' => 'System Error',
                    'error' => $e->getMessage(),
                ];
                update_option('tdl_geocoding_errors', $errors_log);
            }
        }

        // Update status for this batch
        $current_processed = ($status['processed'] ?? 0) + $processed_count;
        update_option('tdl_geocoding_status', [
            'status' => 'running',
            'processed' => $current_processed,
            'total' => $status['total'],
            'timestamp' => time()
        ]);

        // If we still have time left, we imply we didn't finish everything 
        // because of the loop break or we finished the batch.
        // Check if there are more remaining
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE (latitude IS NULL OR longitude IS NULL OR latitude = 0) AND geocode_error IS NULL");
        
        if ($remaining > 0) {
            // Schedule next batch immediately
            if (!wp_next_scheduled('tdl_geocoding_event')) {
                wp_schedule_single_event(time(), 'tdl_geocoding_event');
            }
            spawn_cron();
        } else {
            // Done!
            update_option('tdl_geocoding_status', [
                'status' => 'completed',
                'processed' => $current_processed,
                'total' => $status['total'],
                'timestamp' => time()
            ]);
        }
    }

    /**
     * Start the background process
     *
     * @param bool $force If true, clear all geocode_error flags first so everything is retried
     */
    public static function start_geocoding($force = false) {
        // Reset cancel flag
        delete_option('tdl_geocoding_cancel');
        
        global $wpdb;
        $table = $wpdb->prefix . 'tdl_locations';

        // If force mode, clear all error flags so every location is retried
        if ($force) {
            $wpdb->query("UPDATE {$table} SET geocode_error = NULL");
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE (latitude IS NULL OR longitude IS NULL OR latitude = 0) AND geocode_error IS NULL");

        // Init status
        update_option('tdl_geocoding_status', [
            'status' => 'running',
            'processed' => 0,
            'total' => $total,
            'timestamp' => time()
        ]);

        // Schedule event
        if (!wp_next_scheduled('tdl_geocoding_event')) {
            wp_schedule_single_event(time(), 'tdl_geocoding_event');
        }

        // Force WP-Cron to spawn immediately so the batch starts right away
        spawn_cron();
        
        return $total;
    }

    /**
     * Cancel the background process
     */
    public static function cancel_geocoding() {
        update_option('tdl_geocoding_cancel', true);
        // We also unschedule the next event to stop it from running if it's pending
        $timestamp = wp_next_scheduled('tdl_geocoding_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'tdl_geocoding_event');
        }
    }

    /**
     * Helper to build geocoding parameters
     * Handles PO Box logic (strips street address if PO Box detected)
     */
    private static function get_geocoding_params($location) {
        $full_street = implode(' ', array_filter([
            $location->street_address, 
            $location->address_2, 
            $location->address_3
        ]));

        // Check for PO Box variations
        // Matches: "PO Box", "P.O. Box", "P O Box", "Post Office Box"
        $is_po_box = preg_match('/\bP\.?\s*O\.?\s*Box\b/i', $full_street) || 
                     preg_match('/\bPost\s+Office\s+Box\b/i', $full_street);

        if ($is_po_box) {
            // If PO Box, ONLY use city/state/zip/country
            $address_parts = array_filter([
                $location->city,
                $location->state_province,
                $location->zip_postal,
                $location->country_code,
            ]);
            
            $components = array_filter([
                'city' => $location->city,
                'state' => $location->state_province,
                'postalcode' => $location->zip_postal,
                'country' => $location->country_code,
            ]);
        } else {
            // Normal address
            $address_parts = array_filter([
                $full_street,
                $location->city,
                $location->state_province,
                $location->zip_postal,
                $location->country_code,
            ]);

            $components = array_filter([
                'street' => $full_street,
                'city' => $location->city,
                'state' => $location->state_province,
                'postalcode' => $location->zip_postal,
                'country' => $location->country_code,
            ]);
        }

        return [
            'address' => implode(', ', $address_parts),
            'components' => $components
        ];
    }

    /**
     * Clean street address for better OSM geocoding
     * Strips "Suite", "Unit", "#", etc.
     */
    private static function clean_street_address($street) {
        // Regex to remove Suite, Unit, Apt, Ste, or # followed by anything until end of string
        // Case insensitive
        // Matches: " Suite 100", " #123", " unit 5B", etc.
        // Also trims whitespace
        return trim(preg_replace('/(?:\s+(?:suite|ste|unit|apt|#).*)/i', '', $street));
    }

    /**
     * Trigger immediate batch geocoding for a specific distributor
     * Used by "Save Post" and "Geocode Now" button
     */
    public static function batch_geocode_locations($distributor_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tdl_locations';
        
        // Clear error flags for this distributor so all locations are retried
        // (this is triggered by intentional user action: save or "Geocode Now")
        $wpdb->update(
            $table,
            ['geocode_error' => null],
            ['distributor_id' => $distributor_id],
            [null],
            ['%d']
        );

        // Get locations missing coordinates
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, street_address, address_2, address_3, city, state_province, zip_postal, country_code 
             FROM {$table} 
             WHERE distributor_id = %d AND (latitude IS NULL OR longitude IS NULL OR latitude = 0)",
             $distributor_id
        ));
        
        if (empty($locations)) {
            return ['success' => 0, 'failed' => 0, 'total' => 0];
        }
        
        $success_count = 0;
        $fail_count = 0;
        $errors = [];
        
        foreach ($locations as $location) {
            $params = self::get_geocoding_params($location);
            $address = $params['address'];
            $components = $params['components'];
            
            // Geocode
            $result = self::geocode_address($address, $components);
            
            if (is_wp_error($result)) {
                $fail_count++;
                $errors[] = [
                    'address' => $address,
                    'error' => $result->get_error_message()
                ];
                // Mark geocode_error on the location row
                $wpdb->update(
                    $table,
                    ['geocode_error' => $result->get_error_message()],
                    ['id' => $location->id],
                    ['%s'],
                    ['%d']
                );
            } else {
                $success_count++;
                $wpdb->update(
                    $table,
                    [
                        'latitude' => $result['latitude'],
                        'longitude' => $result['longitude'],
                        'geocode_error' => null,
                    ],
                    ['id' => $location->id],
                    ['%f', '%f', null],
                    ['%d']
                );
            }
            
            // Brief pause to be nice to APIs
            usleep(200000); // 0.2s
        }
        
        // Save errors to transient for display
        if (!empty($errors)) {
            set_transient('tdl_geocode_failures_' . $distributor_id, $errors, HOUR_IN_SECONDS);
            update_post_meta($distributor_id, '_tdl_has_geocode_errors', '1');
        } else {
            delete_transient('tdl_geocode_failures_' . $distributor_id);
            delete_post_meta($distributor_id, '_tdl_has_geocode_errors');
        }
        
        return [
            'success' => $success_count, 
            'failed' => $fail_count, 
            'total' => count($locations)
        ];
    }
}
