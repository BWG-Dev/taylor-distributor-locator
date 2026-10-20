<?php
/**
 * DB-backed email routing event log.
 *
 * Table: {prefix}_tdl_routing_log
 * Created via dbDelta() in TDL_Activator. For sites that were already activated
 * before M8, maybe_create_table() is called on every init with a version-option
 * guard so the dbDelta() cost is only paid once.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_Routing_Log {

	const DB_VERSION_OPTION = 'tdl_routing_log_db_version';
	const DB_VERSION        = '1.0';

	/**
	 * Called on plugin init to ensure the table exists on already-activated sites.
	 * Short-circuits immediately if the version option is already set.
	 */
	public static function maybe_create_table(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create (or upgrade) the routing log table via dbDelta().
	 * Safe to call multiple times — dbDelta() is idempotent.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table           = $wpdb->prefix . 'tdl_routing_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			severity ENUM('info','warning','error') NOT NULL DEFAULT 'info',
			distributor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			distributor_name VARCHAR(255) NOT NULL DEFAULT '',
			recipient_email VARCHAR(255) NOT NULL DEFAULT '',
			routing_source ENUM('email_sales','email_main','none') NOT NULL DEFAULT 'none',
			gf_entry_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message VARCHAR(500) NOT NULL DEFAULT '',
			INDEX idx_created (created_at),
			INDEX idx_severity (severity)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a routing event. Never throws — any DB error is sent to error_log().
	 *
	 * @param array{
	 *   severity: string,
	 *   distributor_id: int,
	 *   distributor_name: string,
	 *   recipient_email: string,
	 *   routing_source: string,
	 *   gf_entry_id: int,
	 *   message: string,
	 * } $data
	 */
	public static function log( array $data ): void {
		global $wpdb;

		$valid_severities = [ 'info', 'warning', 'error' ];
		$valid_sources    = [ 'email_sales', 'email_main', 'none' ];

		$row = [
			'created_at'       => current_time( 'mysql', true ), // UTC
			'severity'         => in_array( $data['severity'] ?? '', $valid_severities, true ) ? $data['severity'] : 'info',
			'distributor_id'   => absint( $data['distributor_id'] ?? 0 ),
			'distributor_name' => sanitize_text_field( $data['distributor_name'] ?? '' ),
			'recipient_email'  => sanitize_email( $data['recipient_email'] ?? '' ),
			'routing_source'   => in_array( $data['routing_source'] ?? '', $valid_sources, true ) ? $data['routing_source'] : 'none',
			'gf_entry_id'      => absint( $data['gf_entry_id'] ?? 0 ),
			'message'          => sanitize_text_field( substr( $data['message'] ?? '', 0, 500 ) ),
		];

		$result = $wpdb->insert(
			$wpdb->prefix . 'tdl_routing_log',
			$row,
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( $result === false ) {
			error_log( 'TDL Routing Log: DB insert failed — ' . $wpdb->last_error );
		}
	}

	/**
	 * Return paginated entries, newest first.
	 *
	 * @return object[]
	 */
	public static function get_entries( int $page = 1, int $per_page = 25 ): array {
		global $wpdb;

		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tdl_routing_log ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) ) ?: [];
	}

	/**
	 * Total entry count for pagination math.
	 */
	public static function get_total(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tdl_routing_log" );
	}
}
