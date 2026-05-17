<?php
/**
 * CSV Importer — M4 Admin Upload Tool
 *
 * Self-service CSV import from the WordPress admin. One CSV row = one
 * distributor post, matching M2's parent/child model:
 *   is_primary=1 (or blank) → parent post
 *   is_primary=0            → child post, linked via _tdl_parent_id
 *
 * Post title format: "Company Name — Location Label"
 * where Location Label = location_name, else city, else no suffix.
 *
 * Duplicate detection uses an exact post_title match, so titles must be
 * stable across import runs. Run the title migration tool once before the
 * first M4 import to align existing M2 posts with this format.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_CSV_Importer {

	/**
	 * All columns the importer recognises.
	 * Also drives the downloadable sample CSV template.
	 */
	private static array $known_columns = [
		'company_name',
		'phone',
		'website',
		'email_main',
		'email_sales',
		'email_parts',
		'email_service',
		'email_installations',
		'service_area_notes',
		'location_name',
		'street_address',
		'address_2',
		'address_3',
		'city',
		'state_province',
		'zip_postal',
		'country_code',
		'location_phone',
		'hours_operation',
		'is_primary',
		'zip_codes_served',
		'states_served',
		'countries_served',
	];

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'add_import_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_tdl_import_csv',          [ __CLASS__, 'handle_import' ] );
		add_action( 'wp_ajax_tdl_download_sample_csv', [ __CLASS__, 'handle_download_sample' ] );
	}

	public static function add_import_page(): void {
		add_submenu_page(
			'edit.php?post_type=distributor',
			__( 'Import CSV', 'taylor-distributor-locator' ),
			__( 'Import CSV', 'taylor-distributor-locator' ),
			'manage_options',
			'tdl-csv-import',
			[ __CLASS__, 'render_import_page' ]
		);
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( $hook !== 'distributor_page_tdl-csv-import' ) {
			return;
		}

		wp_enqueue_script(
			'tdl-csv-import',
			TDL_PLUGIN_URL . 'assets/js/tdl-csv-import.js',
			[ 'jquery' ],
			TDL_VERSION,
			true
		);

		wp_localize_script( 'tdl-csv-import', 'tdlCsvImport', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tdl_csv_import' ),
			'i18n'    => [
				'uploading'       => __( 'Uploading…', 'taylor-distributor-locator' ),
				'processing'      => __( 'Processing…', 'taylor-distributor-locator' ),
				'complete'        => __( 'Import complete', 'taylor-distributor-locator' ),
				'error'           => __( 'An error occurred during import.', 'taylor-distributor-locator' ),
				'created'         => __( 'Created', 'taylor-distributor-locator' ),
				'updated'         => __( 'Updated', 'taylor-distributor-locator' ),
				'skipped'         => __( 'Skipped', 'taylor-distributor-locator' ),
				'errors'          => __( 'Errors', 'taylor-distributor-locator' ),
				'rowErrors'       => __( 'Row warnings', 'taylor-distributor-locator' ),
				'company'         => __( 'Distributor', 'taylor-distributor-locator' ),
				'rows'            => __( 'Row', 'taylor-distributor-locator' ),
				'issue'           => __( 'Issue', 'taylor-distributor-locator' ),
			],
		] );
	}

	public static function render_import_page(): void {
		include TDL_PLUGIN_DIR . 'templates/admin/csv-import-page.php';
	}

	// ── Migration ──────────────────────────────────────────────────────────────

	// ── Title builder ──────────────────────────────────────────────────────────

	/**
	 * Build a stable post title from company name + location label.
	 * Priority: location_name → city → no suffix (bare company name).
	 */
	private static function build_post_title(
		string $company,
		string $location_name,
		string $city
	): string {
		$label = trim( $location_name ) !== '' ? trim( $location_name ) : trim( $city );
		return $label !== '' ? $company . ' — ' . $label : $company;
	}

	// ── Sample CSV download ────────────────────────────────────────────────────

	/**
	 * Output a downloadable sample CSV template.
	 * Nonce-verified GET request; not accessible to unauthenticated users.
	 */
	public static function handle_download_sample(): void {
		if (
			! isset( $_GET['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'tdl_csv_import' )
		) {
			wp_die( 'Invalid request.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="distributor-import-sample.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );

		fputcsv( $out, self::$known_columns );

		// Parent / HQ row (is_primary = 1)
		fputcsv( $out, [
			'Acme Distributors Inc.',   // company_name
			'555-123-4567',             // phone
			'https://www.example.com',  // website
			'info@example.com',         // email_main
			'sales@example.com',        // email_sales
			'parts@example.com',        // email_parts
			'service@example.com',      // email_service
			'',                         // email_installations
			'Serving the southwest US', // service_area_notes
			'Headquarters',             // location_name
			'123 Main St',              // street_address
			'Suite 100',                // address_2
			'',                         // address_3
			'Dallas',                   // city
			'TX',                       // state_province
			'75001',                    // zip_postal
			'US',                       // country_code
			'',                         // location_phone
			'Mon–Fri 8am–5pm CST',      // hours_operation
			'1',                        // is_primary — 1 = parent/HQ post
			'75001;75002;75100-75199',  // zip_codes_served
			'TX;OK;NM',                 // states_served
			'US',                       // countries_served
		] );

		// Branch / child row (is_primary = 0) — same company_name, different location.
		fputcsv( $out, [
			'Acme Distributors Inc.',   // company_name — must match parent exactly
			'555-987-6543',             // phone (branch phone)
			'https://www.example.com',  // website
			'',                         // email_main (inherits from parent in UI)
			'',                         // email_sales
			'',                         // email_parts
			'',                         // email_service
			'',                         // email_installations
			'',                         // service_area_notes
			'West Branch',              // location_name
			'456 Commerce Rd',          // street_address
			'',                         // address_2
			'',                         // address_3
			'Albuquerque',              // city
			'NM',                       // state_province
			'87101',                    // zip_postal
			'US',                       // country_code
			'555-987-6543',             // location_phone
			'Mon–Fri 8am–5pm MST',      // hours_operation
			'0',                        // is_primary — 0 = child/branch post
			'87101;87102',              // zip_codes_served
			'NM',                       // states_served
			'US',                       // countries_served
		] );

		fclose( $out );
		exit;
	}

	// ── Import handler ─────────────────────────────────────────────────────────

	/**
	 * Handle the CSV import AJAX request.
	 *
	 * Strategy:
	 *   1. Parse rows; attach 1-based CSV row number for error reporting.
	 *   2. Split into parent rows (is_primary != '0') and child rows (is_primary == '0').
	 *   3. Pass 1 — import parent rows; build parent_map[company_name] = post_id.
	 *   4. Pass 2 — import child rows; resolve parent_id from parent_map or DB fallback.
	 *   5. Each row's custom-table writes are inside a DB transaction.
	 *   6. Bump tdl_data_version to invalidate frontend search caches.
	 */
	public static function handle_import(): void {
		check_ajax_referer( 'tdl_csv_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'taylor-distributor-locator' ) ] );
		}

		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'taylor-distributor-locator' ) ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['csv_file'];

		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];
		$mime_ok       = in_array( $file['type'], $allowed_types, true );
		$ext_ok        = str_ends_with( strtolower( (string) $file['name'] ), '.csv' );

		if ( ! $mime_ok && ! $ext_ok ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file type. Please upload a CSV file.', 'taylor-distributor-locator' ) ] );
		}

		// Read and normalise line endings before parsing.
		// fgetcsv() on a file handle silently drops rows when line endings differ
		// from the OS default (e.g. \r\n CSV opened on Linux). Reading once and
		// normalising eliminates platform-specific parse failures.
		$content = file_get_contents( $file['tmp_name'] );
		if ( $content === false ) {
			wp_send_json_error( [ 'message' => __( 'Could not read the uploaded file.', 'taylor-distributor-locator' ) ] );
		}

		$content = str_replace( [ "\r\n", "\r" ], "\n", $content );
		$lines   = array_values(
			array_filter(
				explode( "\n", $content ),
				fn( $l ) => trim( $l ) !== ''
			)
		);

		if ( empty( $lines ) ) {
			wp_send_json_error( [ 'message' => __( 'CSV file is empty.', 'taylor-distributor-locator' ) ] );
		}

		// ── Parse header ──────────────────────────────────────────────────────
		$headers      = array_map( 'strtolower', array_map( 'trim', str_getcsv( $lines[0] ) ) );
		$header_count = count( $headers );

		if ( ! in_array( 'company_name', $headers, true ) ) {
			wp_send_json_error( [ 'message' => __( 'CSV must contain a "company_name" column.', 'taylor-distributor-locator' ) ] );
		}

		// ── Parse data rows ───────────────────────────────────────────────────
		$parent_rows = [];
		$child_rows  = [];

		for ( $i = 1; $i < count( $lines ); $i++ ) {
			$row = str_getcsv( $lines[ $i ] );
			$row = array_slice( array_pad( $row, $header_count, '' ), 0, $header_count );
			$row = array_combine( $headers, $row );

			$company = trim( $row['company_name'] ?? '' );
			if ( $company === '' ) {
				continue;
			}

			// Attach the 1-based CSV row number (header = row 1, data starts at row 2).
			$row['_row_number'] = $i + 1;

			if ( trim( $row['is_primary'] ?? '' ) === '0' ) {
				$child_rows[] = $row;
			} else {
				$parent_rows[] = $row;
			}
		}

		if ( empty( $parent_rows ) && empty( $child_rows ) ) {
			wp_send_json_error( [ 'message' => __( 'No valid data rows found in the CSV.', 'taylor-distributor-locator' ) ] );
		}

		// ── Import ────────────────────────────────────────────────────────────
		$stats         = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 ];
		$created_names = [];
		$updated_names = [];
		$row_errors    = [];
		$parent_map    = []; // company_name => post_id

		// Pass 1: parent rows (is_primary = 1 or blank).
		foreach ( $parent_rows as $row ) {
			$company    = trim( $row['company_name'] );
			$post_title = self::build_post_title( $company, $row['location_name'] ?? '', $row['city'] ?? '' );

			$errors      = self::validate_row( $row );
			$warning_idx = self::add_warning( $errors, $post_title, $row['_row_number'], $row_errors );

			$result = self::import_row( $row, $post_title, 0 );

			self::attach_edit_url( $warning_idx, $result, $row_errors );
			self::tally( $result, $stats, $created_names, $updated_names, $row_errors, $post_title, $row['_row_number'] );

			if ( isset( $result['post_id'] ) && $result['post_id'] > 0 ) {
				$parent_map[ $company ] = (int) $result['post_id'];
			}
		}

		// Pass 2: child rows (is_primary = 0).
		foreach ( $child_rows as $row ) {
			$company    = trim( $row['company_name'] );
			$post_title = self::build_post_title( $company, $row['location_name'] ?? '', $row['city'] ?? '' );

			$errors      = self::validate_row( $row );
			$warning_idx = self::add_warning( $errors, $post_title, $row['_row_number'], $row_errors );

			$parent_id = self::find_parent_id( $company, $parent_map );
			$result    = self::import_row( $row, $post_title, $parent_id );

			self::attach_edit_url( $warning_idx, $result, $row_errors );
			self::tally( $result, $stats, $created_names, $updated_names, $row_errors, $post_title, $row['_row_number'] );
		}

		update_option( 'tdl_data_version', time() );

		wp_send_json_success( [
			'stats'         => $stats,
			'created_names' => $created_names,
			'updated_names' => $updated_names,
			'row_errors'    => $row_errors,
		] );
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	/**
	 * Resolve a parent post ID for a child row.
	 *
	 * Checks the pass-1 parent_map first (posts created/found in this import
	 * batch). Falls back to a DB lookup for pre-existing parent posts where the
	 * post_title starts with the company name and has no _tdl_parent_id set.
	 */
	private static function find_parent_id( string $company, array $parent_map ): int {
		global $wpdb;

		if ( isset( $parent_map[ $company ] ) ) {
			return (int) $parent_map[ $company ];
		}

		// DB fallback: find a distributor post whose title is the bare company
		// name OR starts with "Company — " (migrated format), and is itself a
		// parent (no _tdl_parent_id meta).
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				        ON pm.post_id = p.ID AND pm.meta_key = '_tdl_parent_id'
				 WHERE p.post_type   = 'distributor'
				   AND p.post_status != 'trash'
				   AND ( p.post_title = %s OR p.post_title LIKE %s )
				   AND pm.meta_id IS NULL
				 ORDER BY p.ID ASC
				 LIMIT 1",
				$company,
				$wpdb->esc_like( $company ) . ' — %'
			)
		);
	}

	/**
	 * Create or update a single distributor post from one CSV row.
	 *
	 * Post and meta are written via WordPress functions. Custom-table writes
	 * (one location row + service zones) are wrapped in a transaction so a
	 * DB failure never leaves partial data committed.
	 *
	 * @param array  $row        Parsed CSV row (all columns).
	 * @param string $post_title Pre-built title ("Company — Location").
	 * @param int    $parent_id  0 for parent posts; non-zero for children.
	 * @return array{status: 'created'|'updated'|'error', post_id?: int, error?: string}
	 */
	private static function import_row( array $row, string $post_title, int $parent_id ): array {
		global $wpdb;

		// Duplicate detection — exact title match.
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_title = %s
				   AND post_type  = 'distributor'
				   AND post_status != 'trash'
				 LIMIT 1",
				$post_title
			)
		);

		$is_update = $existing_id > 0;

		if ( $is_update ) {
			$result = wp_update_post( [
				'ID'          => $existing_id,
				'post_title'  => $post_title,
				'post_status' => 'publish',
			] );
			if ( is_wp_error( $result ) ) {
				return [ 'status' => 'error', 'error' => $result->get_error_message() ];
			}
			$post_id = $existing_id;
		} else {
			$post_id = wp_insert_post(
				[
					'post_type'   => 'distributor',
					'post_title'  => $post_title,
					'post_status' => 'publish',
				],
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return [
					'status' => 'error',
					'error'  => is_wp_error( $post_id )
						? $post_id->get_error_message()
						: __( 'wp_insert_post returned 0.', 'taylor-distributor-locator' ),
				];
			}
		}

		// Post meta (company-level contact fields).
		self::save_post_meta( $post_id, $row );

		// Parent link — set or clear depending on role.
		if ( $parent_id > 0 ) {
			update_post_meta( $post_id, '_tdl_parent_id', $parent_id );
		} elseif ( $is_update ) {
			// If a previously-child post is re-imported as a parent, remove the link.
			delete_post_meta( $post_id, '_tdl_parent_id' );
		}

		// ── Custom-table writes inside a transaction ───────────────────────────
		$locations_table = $wpdb->prefix . 'tdl_locations';
		$zones_table     = $wpdb->prefix . 'tdl_service_zones';

		$wpdb->query( 'START TRANSACTION' );

		// Replace the single location row for this post.
		$wpdb->delete( $locations_table, [ 'distributor_id' => $post_id ] );
		$wpdb->delete( $zones_table,     [ 'distributor_id' => $post_id ] );

		$wpdb->insert(
			$locations_table,
			[
				'distributor_id'  => $post_id,
				'location_name'   => sanitize_text_field( $row['location_name']   ?? '' ),
				'street_address'  => sanitize_text_field( $row['street_address']  ?? '' ),
				'address_2'       => sanitize_text_field( $row['address_2']       ?? '' ),
				'address_3'       => sanitize_text_field( $row['address_3']       ?? '' ),
				'city'            => sanitize_text_field( $row['city']            ?? '' ),
				'state_province'  => sanitize_text_field( $row['state_province']  ?? '' ),
				'zip_postal'      => sanitize_text_field( $row['zip_postal']      ?? '' ),
				'country_code'    => self::sanitize_country_code( $row['country_code'] ?? '' ),
				'latitude'        => null,
				'longitude'       => null,
				'phone'           => sanitize_text_field( $row['location_phone']  ?? '' ),
				'hours_operation' => sanitize_textarea_field( $row['hours_operation'] ?? '' ),
				// Each post has exactly one location row; is_primary=1 ensures it
				// appears in the /markers endpoint which filters on this flag.
				'is_primary'      => 1,
				'sort_order'      => 0,
			]
		);

		if ( $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' );
			return [
				'status' => 'error',
				/* translators: DB error string */
				'error'  => sprintf(
					__( 'Database error inserting location: %s', 'taylor-distributor-locator' ),
					$wpdb->last_error
				),
			];
		}

		$zone_error = self::insert_service_zones( $post_id, $row, $zones_table );
		if ( $zone_error !== null ) {
			$wpdb->query( 'ROLLBACK' );
			return [
				'status' => 'error',
				/* translators: DB error string */
				'error'  => sprintf(
					__( 'Database error inserting service zone: %s', 'taylor-distributor-locator' ),
					$zone_error
				),
			];
		}

		$wpdb->query( 'COMMIT' );

		// Queue geocoding for the new/updated location.
		if ( ! wp_next_scheduled( 'tdl_geocode_distributor', [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, 'tdl_geocode_distributor', [ $post_id ] );
		}

		return [
			'status'  => $is_update ? 'updated' : 'created',
			'post_id' => $post_id,
		];
	}

	/**
	 * Write company-level contact fields to post meta.
	 * Skips empty values to avoid overwriting existing data with blanks.
	 */
	private static function save_post_meta( int $post_id, array $row ): void {
		$meta_map = [
			'phone'               => [ 'key' => 'wpcf-phone',                    'fn' => 'sanitize_text_field' ],
			'website'             => [ 'key' => 'wpcf-website',                  'fn' => 'esc_url_raw' ],
			'email_main'          => [ 'key' => 'wpcf-email_main',               'fn' => 'sanitize_email' ],
			'email_sales'         => [ 'key' => 'wpcf-email_sales',              'fn' => 'sanitize_email' ],
			'email_parts'         => [ 'key' => 'wpcf-email_parts',              'fn' => 'sanitize_email' ],
			'email_service'       => [ 'key' => 'wpcf-email_service',            'fn' => 'sanitize_email' ],
			'email_installations' => [ 'key' => 'wpcf-email_installations',      'fn' => 'sanitize_email' ],
			'service_area_notes'  => [ 'key' => 'wpcf-service_area_description', 'fn' => 'sanitize_textarea_field' ],
		];

		foreach ( $meta_map as $csv_col => $meta ) {
			$value = trim( $row[ $csv_col ] ?? '' );
			if ( $value !== '' ) {
				update_post_meta( $post_id, $meta['key'], call_user_func( $meta['fn'], $value ) );
			}
		}
	}

	/**
	 * Validate a single CSV row. All errors here are soft warnings —
	 * they are reported but do not block the row from being imported.
	 *
	 * @return string[]
	 */
	/**
	 * Normalise a country_code value for DB insertion.
	 * If the raw value is not exactly 2 letters, fall back to 'US' so the
	 * column constraint is never violated. The validation warning is still
	 * shown to the admin via validate_row().
	 */
	private static function sanitize_country_code( string $raw ): string {
		$cc = strtoupper( trim( sanitize_text_field( $raw ) ) );
		return strlen( $cc ) === 2 ? $cc : 'US';
	}

	private static function validate_row( array $row ): array {
		$errors = [];

		// Email fields.
		foreach ( [ 'email_main', 'email_sales', 'email_parts', 'email_service', 'email_installations' ] as $field ) {
			$value = trim( $row[ $field ] ?? '' );
			if ( $value !== '' && ! is_email( $value ) ) {
				/* translators: 1: field name, 2: value */
				$errors[] = sprintf( __( 'Invalid email in %1$s: "%2$s"', 'taylor-distributor-locator' ), $field, $value );
			}
		}

		// Website URL.
		$website = trim( $row['website'] ?? '' );
		if ( $website !== '' && ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
			/* translators: URL value */
			$errors[] = sprintf( __( 'Invalid website URL: "%s"', 'taylor-distributor-locator' ), $website );
		}

		// country_code.
		$cc = trim( $row['country_code'] ?? '' );
		if ( $cc !== '' && strlen( $cc ) !== 2 ) {
			/* translators: country code value */
			$errors[] = sprintf( __( 'country_code must be a 2-letter ISO code, got "%s"', 'taylor-distributor-locator' ), $cc );
		}

		// is_primary value.
		$is_primary = trim( $row['is_primary'] ?? '' );
		if ( $is_primary !== '' && ! in_array( $is_primary, [ '0', '1' ], true ) ) {
			$errors[] = __( 'is_primary must be 0 or 1.', 'taylor-distributor-locator' );
		}

		return $errors;
	}

	/**
	 * If validation produced errors, push a warning entry into $row_errors and
	 * return its index so attach_edit_url() can update it once the post ID is known.
	 * Returns null when there are no errors.
	 */
	private static function add_warning(
		array  $errors,
		string $post_title,
		int    $row_number,
		array  &$row_errors
	): ?int {
		if ( empty( $errors ) ) {
			return null;
		}

		$idx          = count( $row_errors );
		$row_errors[] = [
			'company'  => $post_title,
			'rows'     => [ $row_number ],
			'errors'   => $errors,
			'edit_url' => null,
		];

		return $idx;
	}

	/**
	 * After a successful import_row() call, attach the WP admin edit URL to the
	 * corresponding warning entry so the JS can render an "Edit post" link.
	 * No-ops when the import failed or when there was no warning entry.
	 */
	private static function attach_edit_url( ?int $warning_idx, array $result, array &$row_errors ): void {
		if (
			$warning_idx === null ||
			! isset( $result['post_id'] ) ||
			! in_array( $result['status'], [ 'created', 'updated' ], true )
		) {
			return;
		}

		$row_errors[ $warning_idx ]['edit_url'] = admin_url(
			'post.php?post=' . (int) $result['post_id'] . '&action=edit'
		);
	}

	/**
	 * Update stats counters and name lists after a single import_row() call.
	 * DB-level errors are also folded into the row_errors report here.
	 */
	private static function tally(
		array  $result,
		array  &$stats,
		array  &$created_names,
		array  &$updated_names,
		array  &$row_errors,
		string $post_title,
		int    $row_number
	): void {
		switch ( $result['status'] ) {
			case 'created':
				$stats['created']++;
				$created_names[] = $post_title;
				break;
			case 'updated':
				$stats['updated']++;
				$updated_names[] = $post_title;
				break;
			default:
				$stats['errors']++;
				$row_errors[] = [
					'company' => $post_title,
					'rows'    => [ $row_number ],
					'errors'  => [ $result['error'] ?? __( 'Unknown import error.', 'taylor-distributor-locator' ) ],
				];
		}
	}

	/**
	 * Insert service zone rows for a distributor.
	 * Called from within an open transaction — returns the first DB error or null.
	 *
	 * @return string|null  Error message on failure, null on success.
	 */
	private static function insert_service_zones( int $post_id, array $data, string $table ): ?string {
		global $wpdb;

		// ZIP codes — pipe or semicolon separated; supports individual ZIPs and ranges.
		if ( ! empty( $data['zip_codes_served'] ) ) {
			$zips = preg_split( '/[|;]/', $data['zip_codes_served'] );
			foreach ( $zips as $zip ) {
				$zip = trim( $zip );
				if ( $zip === '' ) {
					continue;
				}

				if ( preg_match( '/^(\d{5})-(\d{5})$/', $zip, $m ) ) {
					$wpdb->insert( $table, [
						'distributor_id'  => $post_id,
						'zone_type'       => 'zip_range',
						'range_start'     => (int) $m[1],
						'range_end'       => (int) $m[2],
						'country_context' => 'US',
					] );
				} elseif ( preg_match( '/^\d{5}$/', $zip ) ) {
					$wpdb->insert( $table, [
						'distributor_id'  => $post_id,
						'zone_type'       => 'zip',
						'zone_value'      => $zip,
						'country_context' => 'US',
					] );
				}

				if ( $wpdb->last_error ) {
					return $wpdb->last_error;
				}
			}
		}

		// States / provinces.
		if ( ! empty( $data['states_served'] ) ) {
			$states = preg_split( '/[|;]/', $data['states_served'] );
			foreach ( $states as $state ) {
				$state = strtoupper( trim( $state ) );
				if ( $state === '' ) {
					continue;
				}

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
				] );

				if ( $wpdb->last_error ) {
					return $wpdb->last_error;
				}
			}
		}

		// Countries.
		if ( ! empty( $data['countries_served'] ) ) {
			$countries = preg_split( '/[|;]/', $data['countries_served'] );
			foreach ( $countries as $country ) {
				$country = strtoupper( trim( $country ) );
				if ( $country === '' ) {
					continue;
				}

				$wpdb->insert( $table, [
					'distributor_id'  => $post_id,
					'zone_type'       => 'country',
					'zone_value'      => $country,
					'country_context' => $country,
				] );

				if ( $wpdb->last_error ) {
					return $wpdb->last_error;
				}
			}
		}

		return null;
	}
}
