<?php
/**
 * Admin submenu page for the email routing log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_Admin_Log {

	const PER_PAGE = 25;

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_log_page' ] );
	}

	public static function add_log_page(): void {
		add_submenu_page(
			'edit.php?post_type=distributor',
			__( 'Email Routing Log', 'taylor-distributor-locator' ),
			__( 'Routing Log', 'taylor-distributor-locator' ),
			'manage_options',
			'tdl-routing-log',
			[ __CLASS__, 'render_log_page' ]
		);
	}

	public static function render_log_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only pagination param.
		$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$total        = TDL_Routing_Log::get_total();
		$total_pages  = ( $total > 0 ) ? (int) ceil( $total / self::PER_PAGE ) : 1;
		$entries      = TDL_Routing_Log::get_entries( $current_page, self::PER_PAGE );

		include TDL_PLUGIN_DIR . 'templates/admin/email-routing-log.php';
	}
}
