<?php
/**
 * REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDL_REST_API {
    
    /**
     * State/Province mappings
     */
    private static $us_states = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'AS' => 'American Samoa', 'GU' => 'Guam', 'MP' => 'Northern Mariana Islands',
        'PR' => 'Puerto Rico', 'VI' => 'U.S. Virgin Islands',
    ];
    
    private static $ca_provinces = [
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
        'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
        'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];
    
    private static $mx_states = [
        'AGU' => 'Aguascalientes', 'BCN' => 'Baja California', 'BCS' => 'Baja California Sur',
        'CAM' => 'Campeche', 'CHP' => 'Chiapas', 'CHH' => 'Chihuahua', 'COA' => 'Coahuila',
        'COL' => 'Colima', 'CDMX' => 'Ciudad de México', 'DUR' => 'Durango',
        'GUA' => 'Guanajuato', 'GRO' => 'Guerrero', 'HID' => 'Hidalgo', 'JAL' => 'Jalisco',
        'MEX' => 'Estado de México', 'MIC' => 'Michoacán', 'MOR' => 'Morelos', 'NAY' => 'Nayarit',
        'NLE' => 'Nuevo León', 'OAX' => 'Oaxaca', 'PUE' => 'Puebla', 'QUE' => 'Querétaro',
        'ROO' => 'Quintana Roo', 'SLP' => 'San Luis Potosí', 'SIN' => 'Sinaloa',
        'SON' => 'Sonora', 'TAB' => 'Tabasco', 'TAM' => 'Tamaulipas', 'TLA' => 'Tlaxcala',
        'VER' => 'Veracruz', 'YUC' => 'Yucatán', 'ZAC' => 'Zacatecas',
    ];
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_endpoints']);
    }
    
    /**
     * Register REST endpoints
     */
    public static function register_endpoints() {
        // Main search endpoint
        register_rest_route('tdl/v1', '/search', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                ],
                'city' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'state' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'zip' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'country' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // Single distributor endpoint
        register_rest_route('tdl/v1', '/distributor/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_distributor'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // Countries list endpoint
        register_rest_route('tdl/v1', '/countries', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_countries'],
            'permission_callback' => '__return_true',
        ]);
        
        // Lightweight all-marker endpoint — used by the default map view to render
        // all distributor pins without paginating through /search.
        register_rest_route('tdl/v1', '/markers', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_get_markers'],
            'permission_callback' => '__return_true',
        ]);

        // States list endpoint
        register_rest_route('tdl/v1', '/states', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_states'],
            'permission_callback' => '__return_true',
            'args' => [
                'country' => [
                    'type' => 'string',
                    'default' => 'US',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
    
    /**
     * Handle search request
     */
    public static function handle_search($request) {
        $query = trim($request->get_param('q') ?? '');
        $city = trim($request->get_param('city') ?? '');
        $state = trim($request->get_param('state') ?? '');
        $zip = trim($request->get_param('zip') ?? '');
        $country = trim($request->get_param('country') ?? '');
        
        $page = max(1, $request->get_param('page'));
        $per_page = min(500, max(1, $request->get_param('per_page')));
        
        // Cache setup must come before the early-return so both paths can use it.
        $data_version   = get_option('tdl_data_version', time());
        $cache_duration = get_option('tdl_cache_duration', 60) * MINUTE_IN_SECONDS;

        // Only return all distributors when no search criteria are present at all.
        // Previously this checked only $query, which caused explicit params like
        // ?zip=, ?state=, ?country=, ?city= to be ignored — returning all 164
        // distributors regardless of the filter the frontend sent.
        if ( empty( $query ) && empty( $city ) && empty( $state ) && empty( $zip ) && empty( $country ) ) {
            $cache_key = 'tdl_all_' . md5($page . '_' . $per_page . '_' . $data_version . '_' . TDL_VERSION);
            if ($cache_duration > 0) {
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    return new WP_REST_Response($cached, 200);
                }
            }
            $result = self::get_all_distributors($page, $per_page);
            if ($cache_duration > 0) {
                set_transient($cache_key, $result, $cache_duration);
            }
            return new WP_REST_Response($result, 200);
        }

        // Incorporate all params into cache key
        $cache_key = 'tdl_search_' . md5($query . '_' . $city . '_' . $state . '_' . $zip . '_' . $country . '_' . $page . '_' . $per_page . '_' . $data_version . '_' . TDL_VERSION);
        
        if ($cache_duration > 0) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new WP_REST_Response($cached, 200);
            }
        }
        
        // Use structured search if we have structured data
        if (!empty($city) || !empty($state) || !empty($zip) || !empty($country)) {
            $result = self::search_complex($query, $city, $state, $zip, $country, $page, $per_page);
        } else {
            // Convert simple query to city if it looks like one (no digits, no comma) and was just passed as q
            // This logic is partially handled by detect_and_search, but we can be explicit here if needed.
            $result = self::detect_and_search($query, $page, $per_page);
        }
        
        // Cache result
        if ($cache_duration > 0) {
            set_transient($cache_key, $result, $cache_duration);
        }
        
        return new WP_REST_Response($result, 200);
    }

    /**
     * Complex search handling structured data
     */
    private static function search_complex($query, $city, $state, $zip, $country, $page, $per_page) {
        global $wpdb;

        $locations_table = $wpdb->prefix . 'tdl_locations';
        $zones_table = $wpdb->prefix . 'tdl_service_zones';

        $parts = [];
        $params = [];

        // Normalize state to code for location matching.
        // Also infer the country from the state when no explicit country is provided —
        // without this, 'CO' matches both US-Colorado and any MX zone stored as 'CO'
        // (e.g. Coahuila abbreviation collisions from the CSV import).
        $state_match    = self::match_state($state);
        $state_code     = $state_match ? $state_match['code']    : $state;
        $state_name     = $state_match ? $state_match['name']    : $state;
        $state_country  = $state_match ? $state_match['country'] : '';
        // Explicit $country takes precedence; fall back to state-inferred country.
        $effective_country = ! empty( $country ) ? $country : $state_country;

        // Name match only when a text query is present. An empty query produces
        // LIKE '%%' which matches every post and would swamp the zone conditions.
        if ( ! empty( $query ) ) {
            $parts[] = 'post_title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $query ) . '%';
        }

        // 1. Match by City (Physical Location)
        if (!empty($city)) {
            $parts[] = "ID IN (
                SELECT DISTINCT distributor_id FROM {$locations_table}
                WHERE city LIKE %s
                " . ($state ? " AND state_province = %s" : "") . "
            )";
            $params[] = '%' . $wpdb->esc_like($city) . '%';
            if ($state) $params[] = $state_code;
        }

        // 2. Match by State — service zone first, physical location as fallback.
        // country_context is always applied (inferred from the state when not explicit)
        // to prevent abbreviation collisions across US/CA/MX (e.g. 'CO' = Colorado or
        // a Mexican state shorthand).
        if (!empty($state)) {
            $parts[] = "(ID IN (
                SELECT DISTINCT distributor_id FROM {$zones_table}
                WHERE zone_type = 'state' AND (zone_value = %s OR zone_value = %s)
                AND country_context = %s
            ) OR ID IN (
                SELECT DISTINCT distributor_id FROM {$locations_table}
                WHERE (state_province = %s OR state_province = %s)
                " . ( $effective_country ? " AND country_code = %s" : "" ) . "
            ))";
            $params[] = $state_code;
            $params[] = $state_name;
            $params[] = $effective_country;
            $params[] = $state_code;
            $params[] = $state_name;
            if ( $effective_country ) $params[] = $effective_country;
        }

        // 3. Match by ZIP (Service Zone - single or range)
        if (!empty($zip) && preg_match('/^\d+$/', $zip)) {
            $zip_int = intval($zip);
            $parts[] = "ID IN (
                SELECT DISTINCT distributor_id FROM {$zones_table} 
                WHERE (zone_type = 'zip' AND zone_value = %s)
                OR (zone_type = 'zip_range' AND range_start <= %d AND range_end >= %d)
            )";
            $params[] = $zip;
            $params[] = $zip_int;
            $params[] = $zip_int;
        }
        
        // 4. Match by Country (Service Zone) — only when no state or ZIP is present.
        // If state is already provided, it implies the country; adding country as an OR
        // condition would match every distributor serving that country, swamping the
        // more specific state results.
        if (!empty($country) && empty($state) && empty($zip)) {
            $na_countries = ['US', 'CA', 'MX'];
            if (in_array($country, $na_countries, true)) {
                // For US/CA/MX, match any service zone belonging to this country
                $parts[] = "ID IN (
                    SELECT DISTINCT distributor_id FROM {$zones_table} 
                    WHERE country_context = %s
                )";
            } else {
                // For other countries, match explicit country zone entries
                $parts[] = "ID IN (
                    SELECT DISTINCT distributor_id FROM {$zones_table} 
                    WHERE zone_type = 'country' AND zone_value = %s
                )";
            }
            $params[] = $country;
        }

        // Guard: if no conditions were added (e.g. empty query + non-numeric zip),
        // return an empty result rather than generating invalid SQL.
        if ( empty( $parts ) ) {
            return [
                'success'     => true,
                'search_type' => 'none',
                'query'       => $query,
                'total'       => 0,
                'page'        => $page,
                'per_page'    => $per_page,
                'results'     => [],
            ];
        }

        $offset = ($page - 1) * $per_page;
        $where_clause = implode(' OR ', $parts);

        // Count total matches
        $count_sql = "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} 
                WHERE post_type = 'distributor' AND post_status = 'publish' 
                AND ({$where_clause})";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

        // Main Query with ORDER BY and pagination
        $sql = "SELECT DISTINCT ID FROM {$wpdb->posts} 
                WHERE post_type = 'distributor' AND post_status = 'publish' 
                AND ({$where_clause})
                ORDER BY post_title ASC
                LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        // Prepare safe query
        $prepared_sql = $wpdb->prepare($sql, $params);
        $paged_ids = $wpdb->get_col($prepared_sql);
        
        $results = self::get_distributors_data($paged_ids, $country);

        return [
            'success' => true,
            'search_type' => 'complex',
            'query' => $query,
            'data' => compact('city', 'state', 'zip', 'country'),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'results' => $results,
        ];
    }
    
    /**
     * Detect query type and perform appropriate search
     */
    private static function detect_and_search($query, $page, $per_page) {
        // Check for US ZIP code (5 digits)
        if (preg_match('/^\d{5}$/', $query)) {
            return self::search_by_zip($query, $page, $per_page);
        }
        
        // Check for comma — could be "City, State" OR "State, Country" (e.g. "FL, USA").
        // Try "State, Country" first: if the right side is a country and the left is a
        // recognisable state, route directly to the state search instead of city lookup.
        if (strpos($query, ',') !== false) {
            $comma_parts  = array_map('trim', explode(',', $query, 2));
            $right_country = self::match_country($comma_parts[1] ?? '');
            if ($right_country) {
                $left_state = self::match_state($comma_parts[0]);
                if ($left_state) {
                    return self::search_by_state($left_state['code'], $right_country, $page, $per_page);
                }
            }
            return self::search_by_city_state($query, $page, $per_page);
        }
        
        // Try to match as a state/province
        $state_match = self::match_state($query);
        if ($state_match) {
            return self::search_by_state($state_match['code'], $state_match['country'], $page, $per_page);
        }
        
        // Try to match as a country
        $country_match = self::match_country($query);
        if ($country_match) {
            return self::search_by_country($country_match, $page, $per_page);
        }
        
        // Fallback: try as city name or state name
        // If we can't match it to a state/country/zip, and it doesn't have a comma, treat as city
        return self::search_by_city_state($query, $page, $per_page);
    }
    
    /**
     * Search by ZIP code
     */
    private static function search_by_zip($zip, $page, $per_page) {
        global $wpdb;
        
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        $offset = ($page - 1) * $per_page;
        
        // Find distributors matching this ZIP
        $zip_int = intval($zip);

        // Count total matches
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT z.distributor_id) FROM {$zones_table} z
             JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
             WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
             AND ((z.zone_type = 'zip' AND z.zone_value = %s)
                OR (z.zone_type = 'zip_range' AND z.range_start <= %d AND z.range_end >= %d))",
            $zip, $zip_int, $zip_int
        ));

        // Fetch sorted page
        $paged_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT z.distributor_id FROM {$zones_table} z
             JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
             WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
             AND ((z.zone_type = 'zip' AND z.zone_value = %s)
                OR (z.zone_type = 'zip_range' AND z.range_start <= %d AND z.range_end >= %d))
             ORDER BY p.post_title ASC
             LIMIT %d OFFSET %d",
            $zip, $zip_int, $zip_int, $per_page, $offset
        ));
        
        $results = self::get_distributors_data($paged_ids);
        
        return [
            'success' => true,
            'search_type' => 'zip',
            'query' => $zip,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'results' => $results,
        ];
    }
    
    /**
     * Search by city, state
     */
    private static function search_by_city_state($query, $page, $per_page) {
        global $wpdb;
        
        // Check for comma first
        if (strpos($query, ',') !== false) {
            $parts = array_map('trim', explode(',', $query, 2));
            $city = $parts[0];
            $state_input = $parts[1] ?? '';
            
            // Try to normalize state
            $state_match = self::match_state($state_input);
            $state_code = $state_match ? $state_match['code'] : strtoupper($state_input);
        } else {
            // No comma, assume whole query is city
            $city = trim($query);
            $state_code = null;
        }
        
        $locations_table = $wpdb->prefix . 'tdl_locations';
        $offset = ($page - 1) * $per_page;
        
        // Search locations by city and optionally state
        if ($state_code) {
            // Count total
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT l.distributor_id) FROM {$locations_table} l
                 JOIN {$wpdb->posts} p ON l.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND l.city LIKE %s AND l.state_province = %s",
                '%' . $wpdb->esc_like($city) . '%',
                $state_code
            ));
            // Fetch sorted page
            $paged_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT l.distributor_id FROM {$locations_table} l
                 JOIN {$wpdb->posts} p ON l.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND l.city LIKE %s AND l.state_province = %s
                 ORDER BY p.post_title ASC
                 LIMIT %d OFFSET %d",
                '%' . $wpdb->esc_like($city) . '%',
                $state_code,
                $per_page,
                $offset
            ));
        } else {
            // Count total
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT l.distributor_id) FROM {$locations_table} l
                 JOIN {$wpdb->posts} p ON l.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND l.city LIKE %s",
                '%' . $wpdb->esc_like($city) . '%'
            ));
            // Fetch sorted page
            $paged_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT l.distributor_id FROM {$locations_table} l
                 JOIN {$wpdb->posts} p ON l.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND l.city LIKE %s
                 ORDER BY p.post_title ASC
                 LIMIT %d OFFSET %d",
                '%' . $wpdb->esc_like($city) . '%',
                $per_page,
                $offset
            ));
        }
        
        $results = self::get_distributors_data($paged_ids);
        
        return [
            'success' => true,
            'search_type' => $state_code ? 'city_state' : 'city',
            'query' => $query,
            'city' => $city,
            'state' => $state_code,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'results' => $results,
        ];
    }
    
    /**
     * Search by state/province.
     *
     * Checks both explicit state service zones AND physical location rows so that
     * distributors whose territory is defined only through ZIP ranges (no state zone)
     * still surface when a user searches by state name.
     */
    private static function search_by_state($state_code, $country_code, $page, $per_page) {
        global $wpdb;

        $zones_table     = $wpdb->prefix . 'tdl_service_zones';
        $locations_table = $wpdb->prefix . 'tdl_locations';
        $offset          = ($page - 1) * $per_page;

        // Resolve full state name so zone_value comparisons cover both 'FL' and 'Florida'.
        $all_states = array_merge(self::$us_states, self::$ca_provinces, self::$mx_states);
        $state_name = $all_states[$state_code] ?? $state_code;

        // Base subquery condition shared by count and paged fetch.
        $base_where = "FROM {$wpdb->posts} p
            WHERE p.post_type = 'distributor' AND p.post_status = 'publish'
            AND (
                p.ID IN (
                    SELECT DISTINCT distributor_id FROM {$zones_table}
                    WHERE zone_type = 'state'
                      AND (zone_value = %s OR zone_value = %s)
                      AND country_context = %s
                )
                OR p.ID IN (
                    SELECT DISTINCT distributor_id FROM {$locations_table}
                    WHERE state_province = %s OR state_province = %s
                )
            )";

        $base_params = [$state_code, $state_name, $country_code, $state_code, $state_name];

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(DISTINCT p.ID) {$base_where}", $base_params)
        );

        $paged_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID {$base_where} ORDER BY p.post_title ASC LIMIT %d OFFSET %d",
                array_merge($base_params, [$per_page, $offset])
            )
        );

        $results = self::get_distributors_data($paged_ids);

        return [
            'success'     => true,
            'search_type' => 'state',
            'query'       => $state_code,
            'country'     => $country_code,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'results'     => $results,
        ];
    }
    
    /**
     * Search by country
     */
    private static function search_by_country($country_code, $page, $per_page) {
        global $wpdb;
        
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        $offset = ($page - 1) * $per_page;
        
        // For US, CA, MX: find all distributors that have ANY service zone
        // with matching country_context (state zones, zip zones, etc.)
        // For other countries: match explicit zone_type='country' entries only
        $north_america = ['US', 'CA', 'MX'];
        
        if (in_array($country_code, $north_america, true)) {
            // Match any service zone belonging to this country
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT z.distributor_id) FROM {$zones_table} z
                 JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND z.country_context = %s",
                $country_code
            ));

            $paged_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT z.distributor_id FROM {$zones_table} z
                 JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND z.country_context = %s
                 ORDER BY p.post_title ASC
                 LIMIT %d OFFSET %d",
                $country_code, $per_page, $offset
            ));
        } else {
            // For other countries, match explicit country zone entries
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT z.distributor_id) FROM {$zones_table} z
                 JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND z.zone_type = 'country' AND z.zone_value = %s",
                $country_code
            ));

            $paged_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT z.distributor_id FROM {$zones_table} z
                 JOIN {$wpdb->posts} p ON z.distributor_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = 'distributor'
                 AND z.zone_type = 'country' AND z.zone_value = %s
                 ORDER BY p.post_title ASC
                 LIMIT %d OFFSET %d",
                $country_code, $per_page, $offset
            ));
        }
        
        $results = self::get_distributors_data($paged_ids, $country_code);
        
        return [
            'success' => true,
            'search_type' => 'country',
            'query' => $country_code,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'results' => $results,
        ];
    }
    
    /**
     * Match a string to a state/province
     */
    private static function match_state($input) {
        $input = trim(strtoupper($input));
        
        // Check US states
        if (isset(self::$us_states[$input])) {
            return ['code' => $input, 'country' => 'US', 'name' => self::$us_states[$input]];
        }
        foreach (self::$us_states as $code => $name) {
            if (strtoupper($name) === $input) {
                return ['code' => $code, 'country' => 'US', 'name' => $name];
            }
        }
        
        // Check Canadian provinces
        if (isset(self::$ca_provinces[$input])) {
            return ['code' => $input, 'country' => 'CA', 'name' => self::$ca_provinces[$input]];
        }
        foreach (self::$ca_provinces as $code => $name) {
            if (strtoupper($name) === $input) {
                return ['code' => $code, 'country' => 'CA', 'name' => $name];
            }
        }
        
        // Check Mexican states
        if (isset(self::$mx_states[$input])) {
            return ['code' => $input, 'country' => 'MX', 'name' => self::$mx_states[$input]];
        }
        foreach (self::$mx_states as $code => $name) {
            if (strtoupper($name) === $input) {
                return ['code' => $code, 'country' => 'MX', 'name' => $name];
            }
        }
        
        return null;
    }
    
    /**
     * Match a string to an ISO-2 country code.
     *
     * Covers the full get_country_names() list so that any country name typed
     * in the free-text search path (or sent via ?q=) resolves correctly.
     * Previously only 13 countries were recognised, causing Argentina, UAE, etc.
     * to fall through to city search and return 0 results.
     */
    private static function match_country( $input ) {
        $input = trim( strtoupper( $input ) );

        if ( empty( $input ) ) {
            return null;
        }

        // Aliases for names/abbreviations not present in the ISO name list.
        $aliases = [
            'USA'                      => 'US',
            'U.S.A.'                   => 'US',
            'U.S.'                     => 'US',
            'AMERICA'                  => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'UK'                       => 'GB',
            'GREAT BRITAIN'            => 'GB',
            'ENGLAND'                  => 'GB',
            'MÉXICO'                   => 'MX',
            'DEUTSCHLAND'              => 'DE',
            'ITALIA'                   => 'IT',
            'ESPAÑA'                   => 'ES',
            'BRASIL'                   => 'BR',
        ];

        if ( isset( $aliases[ $input ] ) ) {
            return $aliases[ $input ];
        }

        $country_names = self::get_country_names();

        // Exact ISO-2 code match.
        if ( isset( $country_names[ $input ] ) ) {
            return $input;
        }

        // Full name match (case-insensitive).
        foreach ( $country_names as $code => $name ) {
            if ( strtoupper( $name ) === $input ) {
                return $code;
            }
        }

        return null;
    }
    
    /**
     * Return all published distributors ordered by name, paginated.
     * Used when the search query is empty (default page-load state).
     */
    private static function get_all_distributors(int $page, int $per_page): array {
        global $wpdb;

        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'distributor' AND post_status = 'publish'"
        );

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'distributor' AND post_status = 'publish'
             ORDER BY post_title ASC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        return [
            'success'     => true,
            'search_type' => 'all',
            'query'       => '',
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'results'     => self::get_distributors_data($ids),
        ];
    }

    /**
     * Get distributor data for a list of IDs
     */
    private static function get_distributors_data($ids, $filter_country = null) {
        if (empty($ids)) {
            return [];
        }
        
        global $wpdb;
        $locations_table = $wpdb->prefix . 'tdl_locations';
        
        // Batch fetch all posts at once
        $posts = get_posts([
            'post_type' => 'distributor',
            'post_status' => 'publish',
            'post__in' => $ids,
            'orderby' => 'post__in', // Preserve the SQL-sorted order from caller
            'numberposts' => count($ids),
        ]);
        
        if (empty($posts)) {
            return [];
        }
        
        // Prime the meta cache for all posts in one query
        $post_ids = wp_list_pluck($posts, 'ID');
        update_postmeta_cache($post_ids);
        
        // Batch fetch all locations in one query
        $id_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $all_locations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$locations_table} WHERE distributor_id IN ({$id_placeholders}) ORDER BY distributor_id, is_primary DESC, sort_order ASC",
            ...$post_ids
        ));
        
        // Group locations by distributor_id
        $locations_by_distributor = [];
        foreach ($all_locations as $loc) {
            $locations_by_distributor[$loc->distributor_id][] = $loc;
        }
        
        $results = [];
        
        foreach ($posts as $post) {
            $id = $post->ID;
            
            // Build location data from pre-fetched results
            $location_data = [];
            $locations = $locations_by_distributor[$id] ?? [];
            
            // Filter locations if checking specific validation countries (US, CA, MX)
            if ($filter_country && in_array($filter_country, ['US', 'CA', 'MX'], true)) {
                $filtered_locations = array_filter($locations, function($loc) use ($filter_country) {
                    return $loc->country_code === $filter_country;
                });
                
                // Only use filtered lists if acceptable matches were found
                // Otherwise fall back to showing all loctions (e.g. MX distributor serving US)
                if (!empty($filtered_locations)) {
                    $locations = $filtered_locations;
                }
            }

            foreach ($locations as $loc) {
                $location_data[] = [
                    'id' => (int) $loc->id,
                    'name' => $loc->location_name,
                    'address' => $loc->street_address,
                    'address_2' => $loc->address_2,
                    'address_3' => $loc->address_3,
                    'city' => $loc->city,
                    'state' => self::expand_state_name( (string) $loc->state_province, (string) $loc->country_code ),
                    'zip' => $loc->zip_postal,
                    'country' => $loc->country_code,
                    'lat' => $loc->latitude ? (float) $loc->latitude : null,
                    'lng' => $loc->longitude ? (float) $loc->longitude : null,
                    'phone' => $loc->phone,
                    'hours' => $loc->hours_operation,
                    'is_primary' => (bool) $loc->is_primary,
                ];
            }
            
            $results[] = [
                'id' => $id,
                'name' => $post->post_title,
                'phone' => get_post_meta($id, 'wpcf-phone', true),
                'website' => get_post_meta($id, 'wpcf-website', true),
                'service_area_notes' => get_post_meta($id, 'wpcf-service_area_description', true),
                'emails' => [
                    'main' => get_post_meta($id, 'wpcf-email_main', true),
                    'sales' => get_post_meta($id, 'wpcf-email_sales', true),
                    'parts' => get_post_meta($id, 'wpcf-email_parts', true),
                    'service' => get_post_meta($id, 'wpcf-email_service', true),
                    'installations' => get_post_meta($id, 'wpcf-email_installations', true),
                ],
                'locations' => $location_data,
            ];
        }
        
        return $results;
    }
    
    /**
     * Return lightweight pin data (id, name, lat, lng, city, state) for every
     * published distributor that has a geocoded primary location.
     *
     * This endpoint is intentionally minimal — it does not include contact details
     * or service-territory data so that fetching all 164+ pins in one request
     * stays fast. Full details are loaded on-demand via /search or /distributor/:id.
     */
    public static function handle_get_markers($request) {
        global $wpdb;

        $data_version   = get_option('tdl_data_version', time());
        // Cache key includes TDL_VERSION so deploys bust stale responses.
        // Keyed v2 because the response shape now includes contact/email fields
        // required by the M3 info window — old cached payloads must not be served.
        $cache_key      = 'tdl_markers_v2_' . md5( $data_version . '_' . TDL_VERSION );
        $cache_duration = get_option('tdl_cache_duration', 60) * MINUTE_IN_SECONDS;

        if ($cache_duration > 0) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new WP_REST_Response($cached, 200);
            }
        }

        $locations_table = $wpdb->prefix . 'tdl_locations';

        // Primary locations only — one pin per distributor on the default map view.
        // Fetch all location fields needed by the M3 info window so the popup can
        // render without a second round-trip fetch on every pin click.
        $rows = $wpdb->get_results(
            "SELECT l.distributor_id, p.post_title,
                    l.latitude, l.longitude,
                    l.location_name, l.street_address, l.address_2, l.address_3,
                    l.city, l.state_province, l.zip_postal, l.country_code,
                    l.phone AS loc_phone, l.hours_operation
             FROM {$locations_table} l
             JOIN {$wpdb->posts} p ON l.distributor_id = p.ID
             WHERE p.post_type = 'distributor' AND p.post_status = 'publish'
               AND l.is_primary = 1
               AND l.latitude  IS NOT NULL AND l.latitude  != 0
               AND l.longitude IS NOT NULL AND l.longitude != 0
             ORDER BY p.post_title ASC"
        );

        // Prime the post meta cache for all distributor IDs in one query.
        // This prevents N+1 when we call get_post_meta() below.
        $dist_ids = array_map( 'intval', array_column( (array) $rows, 'distributor_id' ) );
        if ( ! empty( $dist_ids ) ) {
            update_postmeta_cache( $dist_ids );
        }

        $markers = [];
        foreach ($rows as $row) {
            $id = (int) $row->distributor_id;
            $markers[] = [
                'id'            => $id,
                'name'          => $row->post_title,
                'lat'           => (float) $row->latitude,
                'lng'           => (float) $row->longitude,
                // Location fields for the popup address block.
                'location_name' => $row->location_name ?: '',
                'address'       => $row->street_address ?: '',
                'address_2'     => $row->address_2 ?: '',
                'address_3'     => $row->address_3 ?: '',
                'city'          => $row->city ?: '',
                'state'         => self::expand_state_name( (string) $row->state_province, (string) $row->country_code ),
                'zip'           => $row->zip_postal ?: '',
                'hours'         => $row->hours_operation ?: '',
                // Location-level phone; JS falls back to distributor phone when empty.
                'loc_phone'     => $row->loc_phone ?: '',
                // Distributor-level contact fields (meta cache is warm — no extra queries).
                'phone'         => get_post_meta( $id, 'wpcf-phone', true ) ?: '',
                'website'       => get_post_meta( $id, 'wpcf-website', true ) ?: '',
                'emails'        => [
                    'main'          => get_post_meta( $id, 'wpcf-email_main', true ) ?: '',
                    'sales'         => get_post_meta( $id, 'wpcf-email_sales', true ) ?: '',
                    'parts'         => get_post_meta( $id, 'wpcf-email_parts', true ) ?: '',
                    'service'       => get_post_meta( $id, 'wpcf-email_service', true ) ?: '',
                    'installations' => get_post_meta( $id, 'wpcf-email_installations', true ) ?: '',
                ],
            ];
        }

        $response = ['success' => true, 'markers' => $markers];

        if ($cache_duration > 0) {
            set_transient($cache_key, $response, $cache_duration);
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Handle get single distributor request
     */
    public static function handle_get_distributor($request) {
        $id = $request->get_param('id');
        $data = self::get_distributors_data([$id]);
        
        if (empty($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Distributor not found', 'taylor-distributor-locator'),
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'distributor' => $data[0],
        ], 200);
    }
    
    /**
     * Handle get countries list request
     */
    public static function handle_get_countries($request) {
        global $wpdb;
        
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        
        // Get countries that have distributors
        $country_codes = $wpdb->get_col(
            "SELECT DISTINCT zone_value FROM {$zones_table} WHERE zone_type = 'country'"
        );
        
        // Also include US, CA, MX if they have state-level coverage
        $state_countries = $wpdb->get_col(
            "SELECT DISTINCT country_context FROM {$zones_table} WHERE zone_type = 'state'"
        );
        
        $all_codes = array_unique(array_merge($country_codes, $state_countries));
        sort($all_codes);
        
        $countries = [];
        $country_names = self::get_country_names();
        
        foreach ($all_codes as $code) {
            $countries[] = [
                'code' => $code,
                'name' => $country_names[$code] ?? $code,
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'countries' => $countries,
        ], 200);
    }
    
    /**
     * Handle get states list request
     */
    public static function handle_get_states($request) {
        $country = strtoupper($request->get_param('country'));
        
        global $wpdb;
        $zones_table = $wpdb->prefix . 'tdl_service_zones';
        
        // Get states that have distributors for this country
        $state_codes = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT zone_value FROM {$zones_table} 
             WHERE zone_type = 'state' AND country_context = %s",
            $country
        ));
        
        // Map to names
        $state_map = match($country) {
            'US' => self::$us_states,
            'CA' => self::$ca_provinces,
            'MX' => self::$mx_states,
            default => [],
        };
        
        $states = [];
        foreach ($state_codes as $code) {
            $states[] = [
                'code' => $code,
                'name' => $state_map[$code] ?? $code,
            ];
        }
        
        // Sort by name
        usort($states, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return new WP_REST_Response([
            'success' => true,
            'country' => $country,
            'states' => $states,
        ], 200);
    }
    
    /**
     * Get country name mappings
     */
    public static function get_country_names() {
        return [
            'AF' => 'Afghanistan',
            'AX' => 'Åland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire, Sint Eustatius and Saba',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'CV' => 'Cabo Verde',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic of the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Côte d\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Curaçao',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'SZ' => 'Eswatini',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and McDonald Islands',
            'VA' => 'Holy See',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => 'Korea (North)',
            'KR' => 'Korea (South)',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MK' => 'North Macedonia',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestine, State of',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Réunion',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthélemy',
            'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin (French part)',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SX' => 'Sint Maarten (Dutch part)',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'SS' => 'South Sudan',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands (British)',
            'VI' => 'Virgin Islands (U.S.)',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }
    
    /**
     * Expand a state/province abbreviation to its full name.
     * Uses country_code to pick the correct map; falls back to the raw value
     * if no match is found so existing data is never silently dropped.
     */
    private static function expand_state_name( string $code, string $country_code ): string {
        if ( $code === '' ) {
            return '';
        }
        $map = match( strtoupper( $country_code ) ) {
            'CA'    => self::$ca_provinces,
            'MX'    => self::$mx_states,
            default => self::$us_states,
        };
        return $map[ strtoupper( $code ) ] ?? $code;
    }

    /**
     * Get US states list (public)
     */
    public static function get_us_states() {
        return self::$us_states;
    }
    
    /**
     * Get Canadian provinces list (public)
     */
    public static function get_ca_provinces() {
        return self::$ca_provinces;
    }
    
    /**
     * Get Mexican states list (public)
     */
    public static function get_mx_states() {
        return self::$mx_states;
    }
}
