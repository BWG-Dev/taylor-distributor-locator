<?php
/**
 * M2 One-Time CSV Import
 *
 * Developer-run tool to import the initial 164 distributor records.
 * NOT the M4 self-service importer — do not use for ongoing imports.
 * One CSV row = one distributor post. Parents (is_primary=1) are created
 * first; children (is_primary=0) are linked via _tdl_parent_id in pass 2.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_M2_Importer {

	const EXPECTED_COUNT    = 164;
	const RESULTS_TRANSIENT = 'tdl_m2_import_results';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_import_page' ] );
		add_action( 'admin_post_tdl_m2_import', [ __CLASS__, 'handle_import' ] );
	}

	// -------------------------------------------------------------------------
	// Admin page
	// -------------------------------------------------------------------------

	public static function add_import_page() {
		add_submenu_page(
			'edit.php?post_type=distributor',
			__( 'M2 Initial Import', 'taylor-distributor-locator' ),
			__( 'M2 Initial Import', 'taylor-distributor-locator' ),
			'manage_options',
			'tdl-m2-import',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$current_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'distributor' AND post_status != 'trash'"
		);

		$results = get_transient( self::RESULTS_TRANSIENT );
		if ( $results !== false ) {
			delete_transient( self::RESULTS_TRANSIENT );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'M2 — Initial Distributor Import', 'taylor-distributor-locator' ); ?></h1>

			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'One-time developer tool.', 'taylor-distributor-locator' ); ?></strong>
					<?php esc_html_e( 'Imports the initial distributor records. Run once on a clean database. For ongoing self-service imports use M4 (Import CSV).', 'taylor-distributor-locator' ); ?>
				</p>
			</div>

			<table class="form-table" style="max-width:600px">
				<tr>
					<th><?php esc_html_e( 'Current Distributors', 'taylor-distributor-locator' ); ?></th>
					<td>
						<strong><?php echo esc_html( $current_count ); ?></strong> / <?php echo esc_html( self::EXPECTED_COUNT ); ?>
						<?php if ( $current_count >= self::EXPECTED_COUNT ) : ?>
							<span style="color:#46b450;margin-left:6px;">
								&#10003; <?php esc_html_e( 'Expected count reached', 'taylor-distributor-locator' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tdl_m2_import', 'tdl_m2_nonce' ); ?>
				<input type="hidden" name="action" value="tdl_m2_import">
				<table class="form-table" style="max-width:600px">
					<tr>
						<th><label for="tdl_csv_file"><?php esc_html_e( 'CSV File', 'taylor-distributor-locator' ); ?></label></th>
						<td>
							<input type="file" name="tdl_csv_file" id="tdl_csv_file" accept=".csv" required>
							<p class="description"><?php esc_html_e( 'Select the distributor CSV file to import.', 'taylor-distributor-locator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode', 'taylor-distributor-locator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dry_run" value="1" checked>
								<?php esc_html_e( 'Dry run — preview only, no database changes', 'taylor-distributor-locator' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Run Import', 'taylor-distributor-locator' ), 'primary large' ); ?>
			</form>

			<?php if ( $results !== false ) : ?>
				<?php self::render_results( $results ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// POST handler
	// -------------------------------------------------------------------------

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'taylor-distributor-locator' ) );
		}

		check_admin_referer( 'tdl_m2_import', 'tdl_m2_nonce' );

		$dry_run = ! empty( $_POST['dry_run'] );

		// Validate uploaded file.
		if ( empty( $_FILES['tdl_csv_file'] ) || $_FILES['tdl_csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( esc_html__( 'No CSV file uploaded or upload error occurred.', 'taylor-distributor-locator' ) );
		}

		$uploaded = $_FILES['tdl_csv_file'];
		$ext      = strtolower( pathinfo( $uploaded['name'], PATHINFO_EXTENSION ) );
		if ( $ext !== 'csv' ) {
			wp_die( esc_html__( 'Invalid file type. Please upload a .csv file.', 'taylor-distributor-locator' ) );
		}

		$csv_path = $uploaded['tmp_name'];

		$rows = self::parse_csv( $csv_path );
		if ( is_wp_error( $rows ) ) {
			wp_die( esc_html( $rows->get_error_message() ) );
		}

		$results = self::run_import( $rows, $dry_run );

		if ( ! $dry_run ) {
			$results['log_filename'] = self::generate_log( $results );
		}

		set_transient( self::RESULTS_TRANSIENT, $results, 300 );
		wp_redirect( admin_url( 'edit.php?post_type=distributor&page=tdl-m2-import' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// CSV parsing
	// -------------------------------------------------------------------------

	/**
	 * Parse the M2 CSV into an array of associative rows.
	 *
	 * @param  string        $path  Absolute path to the CSV file.
	 * @return array|WP_Error
	 */
	private static function parse_csv( string $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'csv_open', __( 'Could not open CSV file.', 'taylor-distributor-locator' ) );
		}

		$raw_header = fgetcsv( $handle );
		$headers    = array_map( function ( $h ) {
			// Strip BOM and non-printable characters, then normalise.
			return strtolower( trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/', '', $h ) ) );
		}, $raw_header );

		$rows = [];
		while ( ( $values = fgetcsv( $handle ) ) !== false ) {
			if ( count( $values ) < count( $headers ) ) {
				$values = array_pad( $values, count( $headers ), '' );
			}
			$row = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
			if ( trim( $row['company_name'] ?? '' ) === '' ) {
				continue;
			}
			$rows[] = $row;
		}

		fclose( $handle );
		return $rows;
	}

	// -------------------------------------------------------------------------
	// Two-pass import
	// -------------------------------------------------------------------------

	/**
	 * Run the two-pass import: parents first, children second.
	 *
	 * @param  array $rows    All parsed CSV rows.
	 * @param  bool  $dry_run Preview only — no DB writes.
	 * @return array          Results array used for display and logging.
	 */
	private static function run_import( array $rows, bool $dry_run ): array {
		$results = [
			'dry_run'       => $dry_run,
			'parents'       => [],
			'children'      => [],
			'errors'        => [],
			'skipped'       => [],
			'created'       => 0,
			'skipped_count' => 0,
			'error_count'   => 0,
			'start_time'    => current_time( 'mysql' ),
			'final_count'   => null,
			'log_filename'  => null,
		];

		$parent_rows = array_values( array_filter( $rows, fn( $r ) => (string) ( $r['is_primary'] ?? '1' ) === '1' ) );
		$child_rows  = array_values( array_filter( $rows, fn( $r ) => (string) ( $r['is_primary'] ?? '1' ) === '0' ) );

		// ----- Pass 1: parents -----
		$parent_map = []; // company_name => post_id (or 'DRY_RUN')

		foreach ( $parent_rows as $row ) {
			$company = sanitize_text_field( trim( $row['company_name'] ) );
			$city    = sanitize_text_field( trim( $row['city'] ?? '' ) );
			$state   = sanitize_text_field( trim( $row['state_province'] ?? '' ) );

			$existing = self::find_existing_post( $company, $city, $state );
			if ( $existing ) {
				$parent_map[ $company ] = $existing;
				$results['skipped'][]   = compact( 'company', 'city', 'state' ) + [ 'reason' => "Already exists (ID: {$existing})" ];
				$results['skipped_count']++;
				continue;
			}

			if ( $dry_run ) {
				$parent_map[ $company ] = 'DRY_RUN';
				$results['parents'][]   = [ 'post_id' => null, 'company' => $company, 'city' => $city, 'state' => $state ];
				$results['created']++;
				continue;
			}

			$post_id = self::create_post( $company );
			if ( is_wp_error( $post_id ) ) {
				$results['errors'][] = [ 'company' => $company, 'city' => $city, 'message' => $post_id->get_error_message() ];
				$results['error_count']++;
				continue;
			}

			self::save_meta( $post_id, $row );
			self::save_location( $post_id, $row );
			self::save_service_zones( $post_id, $row );
			self::queue_geocoding( $post_id, $row );

			$parent_map[ $company ] = $post_id;
			$results['parents'][]   = [ 'post_id' => $post_id, 'company' => $company, 'city' => $city, 'state' => $state ];
			$results['created']++;
		}

		// ----- Pass 2: children -----
		foreach ( $child_rows as $row ) {
			$company = sanitize_text_field( trim( $row['company_name'] ) );
			$city    = sanitize_text_field( trim( $row['city'] ?? '' ) );
			$state   = sanitize_text_field( trim( $row['state_province'] ?? '' ) );

			$existing = self::find_existing_post( $company, $city, $state );
			if ( $existing ) {
				$results['skipped'][]   = compact( 'company', 'city', 'state' ) + [ 'reason' => "Already exists (ID: {$existing})" ];
				$results['skipped_count']++;
				continue;
			}

			// Resolve parent ID from pass-1 map.
			$parent_id = 0;
			if ( isset( $parent_map[ $company ] ) && $parent_map[ $company ] !== 'DRY_RUN' ) {
				$parent_id = (int) $parent_map[ $company ];
			} elseif ( ! isset( $parent_map[ $company ] ) ) {
				// Parent row was never seen — log and continue without a link.
				$results['errors'][] = [
					'company' => $company,
					'city'    => $city,
					'message' => __( 'Parent record not found — child imported without parent link.', 'taylor-distributor-locator' ),
				];
				$results['error_count']++;
			}

			if ( $dry_run ) {
				$results['children'][] = [ 'post_id' => null, 'company' => $company, 'city' => $city, 'state' => $state, 'parent_id' => $parent_id ];
				$results['created']++;
				continue;
			}

			$post_id = self::create_post( $company );
			if ( is_wp_error( $post_id ) ) {
				$results['errors'][] = [ 'company' => $company, 'city' => $city, 'message' => $post_id->get_error_message() ];
				$results['error_count']++;
				continue;
			}

			self::save_meta( $post_id, $row );
			self::save_location( $post_id, $row );
			self::save_service_zones( $post_id, $row );
			self::queue_geocoding( $post_id, $row );

			if ( $parent_id > 0 ) {
				update_post_meta( $post_id, '_tdl_parent_id', $parent_id );
			}

			$results['children'][] = [ 'post_id' => $post_id, 'company' => $company, 'city' => $city, 'state' => $state, 'parent_id' => $parent_id ];
			$results['created']++;
		}

		if ( ! $dry_run ) {
			global $wpdb;
			$results['final_count'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = 'distributor' AND post_status != 'trash'"
			);
		}

		return $results;
	}

	// -------------------------------------------------------------------------
	// DB helpers
	// -------------------------------------------------------------------------

	private static function create_post( string $company_name ): int|WP_Error {
		return wp_insert_post(
			[
				'post_type'   => 'distributor',
				'post_title'  => $company_name,
				'post_status' => 'publish',
			],
			true // return WP_Error on failure
		);
	}

	/**
	 * Save Toolset contact/notes meta fields (wpcf-* keys).
	 * Only saves fields with non-empty values — does not overwrite with blanks.
	 */
	private static function save_meta( int $post_id, array $row ): void {
		$fields = [
			'website'             => [ 'key' => 'wpcf-website',                  'fn' => 'esc_url_raw' ],
			'phone'               => [ 'key' => 'wpcf-phone',                    'fn' => 'sanitize_text_field' ],
			'email_main'          => [ 'key' => 'wpcf-email_main',               'fn' => 'sanitize_email' ],
			'email_sales'         => [ 'key' => 'wpcf-email_sales',              'fn' => 'sanitize_email' ],
			'email_parts'         => [ 'key' => 'wpcf-email_parts',              'fn' => 'sanitize_email' ],
			'email_service'       => [ 'key' => 'wpcf-email_service',            'fn' => 'sanitize_email' ],
			'email_installations' => [ 'key' => 'wpcf-email_installations',      'fn' => 'sanitize_email' ],
			'service_area_notes'  => [ 'key' => 'wpcf-service_area_description', 'fn' => 'sanitize_textarea_field' ],
		];

		foreach ( $fields as $csv_col => $meta ) {
			$raw = trim( $row[ $csv_col ] ?? '' );
			if ( $raw !== '' ) {
				update_post_meta( $post_id, $meta['key'], call_user_func( $meta['fn'], $raw ) );
			}
		}
	}

	/**
	 * Insert one physical location row for this distributor.
	 * M2 creates exactly one location per post (one CSV row = one post = one location).
	 */
	private static function save_location( int $post_id, array $row ): void {
		global $wpdb;

		$lat_raw = trim( $row['latitude'] ?? '' );
		$lng_raw = trim( $row['longitude'] ?? '' );
		$lat     = ( is_numeric( $lat_raw ) && (float) $lat_raw !== 0.0 ) ? (float) $lat_raw : null;
		$lng     = ( is_numeric( $lng_raw ) && (float) $lng_raw !== 0.0 ) ? (float) $lng_raw : null;

		$wpdb->insert(
			$wpdb->prefix . 'tdl_locations',
			[
				'distributor_id'      => $post_id,
				'location_name'       => sanitize_text_field( $row['location_name'] ?? '' ),
				'street_address'      => sanitize_text_field( $row['street_address'] ?? '' ),
				'address_2'           => sanitize_text_field( $row['address_2'] ?? '' ),
				'address_3'           => sanitize_text_field( $row['address_3'] ?? '' ),
				'city'                => sanitize_text_field( $row['city'] ?? '' ),
				'state_province'      => sanitize_text_field( $row['state_province'] ?? '' ),
				'zip_postal'          => sanitize_text_field( $row['zip_postal'] ?? '' ),
				'country_code'        => sanitize_text_field( $row['country_code'] ?? 'US' ),
				'latitude'            => $lat,
				'longitude'           => $lng,
				'phone'               => sanitize_text_field( $row['location_phone'] ?? '' ),
				'email_sales'         => sanitize_email( $row['loc_email_sales'] ?? '' ),
				'email_parts'         => sanitize_email( $row['loc_email_parts'] ?? '' ),
				'email_service'       => sanitize_email( $row['loc_email_service'] ?? '' ),
				'email_installations' => sanitize_email( $row['loc_email_installations'] ?? '' ),
				'hours_operation'     => sanitize_textarea_field( $row['hours_operation'] ?? '' ),
				'is_primary'          => 1,
				'sort_order'          => 0,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ]
		);
	}

	/**
	 * Parse and insert service zones (ZIP codes, states/provinces, countries).
	 * Pipe and semicolon are both accepted as delimiters.
	 */
	private static function save_service_zones( int $post_id, array $row ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'tdl_service_zones';

		// ZIP codes — supports individual 5-digit and NNNNN-NNNNN ranges.
		$zip_raw = trim( $row['zip_codes_served'] ?? '' );
		if ( $zip_raw !== '' ) {
			foreach ( preg_split( '/[|;]/', $zip_raw ) as $token ) {
				$token = trim( $token );
				if ( $token === '' ) continue;

				if ( preg_match( '/^(\d{5})-(\d{5})$/', $token, $m ) && (int) $m[1] < (int) $m[2] ) {
					$wpdb->insert( $table, [
						'distributor_id'  => $post_id,
						'zone_type'       => 'zip_range',
						'zone_value'      => '',
						'range_start'     => (int) $m[1],
						'range_end'       => (int) $m[2],
						'country_context' => 'US',
					], [ '%d', '%s', '%s', '%d', '%d', '%s' ] );
				} elseif ( preg_match( '/^\d{5}$/', $token ) ) {
					$wpdb->insert( $table, [
						'distributor_id'  => $post_id,
						'zone_type'       => 'zip',
						'zone_value'      => $token,
						'range_start'     => null,
						'range_end'       => null,
						'country_context' => 'US',
					], [ '%d', '%s', '%s', '%d', '%d', '%s' ] );
				}
			}
		}

		// States/provinces — auto-detects CA and MX codes to set correct country_context.
		$states_raw = trim( $row['states_served'] ?? '' );
		if ( $states_raw !== '' ) {
			foreach ( preg_split( '/[|;]/', $states_raw ) as $token ) {
				$state = strtoupper( trim( $token ) );
				if ( $state === '' ) continue;

				$country = 'US';
				if ( array_key_exists( $state, TDL_REST_API::get_ca_provinces() ) ) {
					$country = 'CA';
				} elseif ( array_key_exists( $state, TDL_REST_API::get_mx_states() ) ) {
					$country = 'MX';
				}

				$wpdb->insert( $table, [
					'distributor_id'  => $post_id,
					'zone_type'       => 'state',
					'zone_value'      => $state,
					'country_context' => $country,
				], [ '%d', '%s', '%s', '%s' ] );
			}
		}

		// Countries.
		$countries_raw = trim( $row['countries_served'] ?? '' );
		if ( $countries_raw !== '' ) {
			foreach ( preg_split( '/[|;]/', $countries_raw ) as $token ) {
				$country = strtoupper( trim( $token ) );
				if ( $country === '' ) continue;

				$wpdb->insert( $table, [
					'distributor_id'  => $post_id,
					'zone_type'       => 'country',
					'zone_value'      => $country,
					'country_context' => $country,
				], [ '%d', '%s', '%s', '%s' ] );
			}
		}
	}

	/**
	 * Look for an existing distributor post matching company name + city + state.
	 * Prevents duplicates when the import is re-run.
	 *
	 * @return int|null  Post ID if a match is found, null otherwise.
	 */
	private static function find_existing_post( string $company, string $city, string $state ): ?int {
		global $wpdb;

		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->prefix}tdl_locations l ON l.distributor_id = p.ID
			 WHERE p.post_type      = 'distributor'
			   AND p.post_status   != 'trash'
			   AND p.post_title     = %s
			   AND l.city           = %s
			   AND l.state_province = %s
			 LIMIT 1",
			$company, $city, $state
		) );

		return $id ? (int) $id : null;
	}

	/**
	 * Schedule geocoding for posts where the CSV did not supply lat/lng.
	 * Runs via the existing WP-Cron batch geocoder — not inline.
	 */
	private static function queue_geocoding( int $post_id, array $row ): void {
		$lat = trim( $row['latitude'] ?? '' );
		$lng = trim( $row['longitude'] ?? '' );

		$has_coords = is_numeric( $lat ) && (float) $lat !== 0.0
		           && is_numeric( $lng ) && (float) $lng !== 0.0;

		if ( ! $has_coords && ! wp_next_scheduled( 'tdl_geocode_distributor', [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, 'tdl_geocode_distributor', [ $post_id ] );
		}
	}

	// -------------------------------------------------------------------------
	// Log generation
	// -------------------------------------------------------------------------

	/**
	 * Write a plain-text import log to logs/. Always generated, even on zero errors.
	 *
	 * @param  array  $results  Import results array.
	 * @return string           Basename of the created log file.
	 */
	private static function generate_log( array $results ): string {
		$log_dir = TDL_PLUGIN_DIR . 'logs/';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '.htaccess', 'Deny from all' );
			file_put_contents( $log_dir . 'index.php', '<?php // Silence is golden.' );
		}

		$filename = 'm2-import-' . gmdate( 'Y-m-d-His' ) . '.log';
		$path     = $log_dir . $filename;
		$sep      = str_repeat( '-', 60 );

		$lines = [
			'Taylor Distributor Locator — M2 Import Log',
			'Generated : ' . $results['start_time'],
			$sep,
			'Created   : ' . $results['created'],
			'Skipped   : ' . $results['skipped_count'],
			'Errors    : ' . $results['error_count'],
			'Final count: ' . ( $results['final_count'] ?? 'N/A' ) . ' / ' . self::EXPECTED_COUNT,
			'',
		];

		if ( ! empty( $results['errors'] ) ) {
			$lines[] = '=== ERRORS ===';
			foreach ( $results['errors'] as $e ) {
				$lines[] = "[ERROR] {$e['company']} ({$e['city']}) — {$e['message']}";
			}
		} else {
			$lines[] = '=== ERRORS: none ===';
		}
		$lines[] = '';

		if ( ! empty( $results['skipped'] ) ) {
			$lines[] = '=== SKIPPED (duplicates) ===';
			foreach ( $results['skipped'] as $s ) {
				$lines[] = "[SKIP] {$s['company']} ({$s['city']}, {$s['state']}) — {$s['reason']}";
			}
			$lines[] = '';
		}

		$lines[] = '=== PARENTS CREATED (' . count( $results['parents'] ) . ') ===';
		foreach ( $results['parents'] as $p ) {
			$id      = $p['post_id'] ?? '?';
			$lines[] = "[PARENT] ID:{$id} | {$p['company']} — {$p['city']}, {$p['state']}";
		}
		$lines[] = '';

		$lines[] = '=== CHILDREN CREATED (' . count( $results['children'] ) . ') ===';
		foreach ( $results['children'] as $c ) {
			$id      = $c['post_id'] ?? '?';
			$lines[] = "[CHILD]  ID:{$id} | {$c['company']} — {$c['city']}, {$c['state']} | parent_id:{$c['parent_id']}";
		}

		file_put_contents( $path, implode( "\n", $lines ) . "\n" );

		return $filename;
	}

	// -------------------------------------------------------------------------
	// Results display
	// -------------------------------------------------------------------------

	private static function render_results( array $results ): void {
		$dry   = $results['dry_run'];
		$class = $results['error_count'] > 0 ? 'notice-warning' : 'notice-success';
		?>
		<hr>
		<h2>
			<?php echo $dry
				? esc_html__( 'Dry Run Preview', 'taylor-distributor-locator' )
				: esc_html__( 'Import Results', 'taylor-distributor-locator' ); ?>
		</h2>

		<div class="notice <?php echo esc_attr( $class ); ?> inline">
			<p>
				<strong><?php esc_html_e( 'Created', 'taylor-distributor-locator' ); ?>:</strong> <?php echo esc_html( $results['created'] ); ?> &nbsp;
				<strong><?php esc_html_e( 'Skipped', 'taylor-distributor-locator' ); ?>:</strong> <?php echo esc_html( $results['skipped_count'] ); ?> &nbsp;
				<strong><?php esc_html_e( 'Errors', 'taylor-distributor-locator' ); ?>:</strong> <?php echo esc_html( $results['error_count'] ); ?>
				<?php if ( ! $dry && $results['final_count'] !== null ) : ?>
					&nbsp;
					<strong><?php esc_html_e( 'Total now', 'taylor-distributor-locator' ); ?>:</strong>
					<?php echo esc_html( $results['final_count'] ); ?> / <?php echo esc_html( self::EXPECTED_COUNT ); ?>
					<?php if ( $results['final_count'] >= self::EXPECTED_COUNT ) : ?>
						<span style="color:#46b450">&#10003;</span>
					<?php else : ?>
						<span style="color:#dc3232"> — <?php esc_html_e( 'count mismatch, check errors', 'taylor-distributor-locator' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		</div>

		<?php if ( ! empty( $results['errors'] ) ) : ?>
		<h3 style="color:#dc3232"><?php esc_html_e( 'Errors', 'taylor-distributor-locator' ); ?></h3>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th><?php esc_html_e( 'Company', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'City', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'Message', 'taylor-distributor-locator' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $results['errors'] as $e ) : ?>
				<tr>
					<td><?php echo esc_html( $e['company'] ); ?></td>
					<td><?php echo esc_html( $e['city'] ); ?></td>
					<td><?php echo esc_html( $e['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<h3><?php printf( esc_html__( 'Parents (%d)', 'taylor-distributor-locator' ), count( $results['parents'] ) ); ?></h3>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th>ID</th>
				<th><?php esc_html_e( 'Company', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'City', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'State', 'taylor-distributor-locator' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $results['parents'] as $p ) : ?>
			<tr>
				<td><?php echo $p['post_id'] ? esc_html( $p['post_id'] ) : '—'; ?></td>
				<td><?php echo esc_html( $p['company'] ); ?></td>
				<td><?php echo esc_html( $p['city'] ); ?></td>
				<td><?php echo esc_html( $p['state'] ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $results['children'] ) ) : ?>
		<h3><?php printf( esc_html__( 'Children (%d)', 'taylor-distributor-locator' ), count( $results['children'] ) ); ?></h3>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th>ID</th>
				<th><?php esc_html_e( 'Company', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'City', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'State', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'Parent ID', 'taylor-distributor-locator' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $results['children'] as $c ) : ?>
			<tr>
				<td><?php echo $c['post_id'] ? esc_html( $c['post_id'] ) : '—'; ?></td>
				<td><?php echo esc_html( $c['company'] ); ?></td>
				<td><?php echo esc_html( $c['city'] ); ?></td>
				<td><?php echo esc_html( $c['state'] ); ?></td>
				<td><?php echo esc_html( $c['parent_id'] ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php if ( ! empty( $results['skipped'] ) ) : ?>
		<h3><?php printf( esc_html__( 'Skipped — duplicates (%d)', 'taylor-distributor-locator' ), count( $results['skipped'] ) ); ?></h3>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th><?php esc_html_e( 'Company', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'City', 'taylor-distributor-locator' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'taylor-distributor-locator' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $results['skipped'] as $s ) : ?>
			<tr>
				<td><?php echo esc_html( $s['company'] ); ?></td>
				<td><?php echo esc_html( $s['city'] ); ?></td>
				<td><?php echo esc_html( $s['reason'] ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php if ( ! $dry && ! empty( $results['log_filename'] ) ) : ?>
		<p style="margin-top:16px">
			<span class="dashicons dashicons-media-text"></span>
			<?php esc_html_e( 'Log saved:', 'taylor-distributor-locator' ); ?>
			<code><?php echo esc_html( 'logs/' . $results['log_filename'] ); ?></code>
		</p>
		<?php endif; ?>
		<?php
	}
}
