<?php
/**
 * Dynamic email routing for GF Form 1 quote submissions.
 *
 * On submission:
 *   1. Reads the distributor ID from the GF hidden field.
 *   2. Resolves the routing email: wpcf-email_sales → wpcf-email_main → error.
 *   3. Sends an HTML quote-request email via wp_mail().
 *   4. Logs every routing decision to TDL_Routing_Log.
 *
 * Also suppresses GF's static-address (admin) notifications on this form to
 * prevent double-sending. Customer confirmation notifications (toType='field')
 * are intentionally left untouched.
 *
 * This class has no dependency on M9 (HubSpot). A HubSpot failure cannot
 * block, delay, or alter email delivery here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_Email_Router {

	/**
	 * GF Form 1 visible field IDs — confirmed from the client-supplied form JSON.
	 * Name is an "advanced" GF name field: first = 1.3, last = 1.6.
	 */
	const FIELD_FIRST_NAME = '1.3';
	const FIELD_LAST_NAME  = '1.6';
	const FIELD_EMAIL      = '3';
	const FIELD_PHONE      = '4';
	const FIELD_COMPANY    = '5';
	const FIELD_MESSAGE    = '6';

	public static function init(): void {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		$form_id = TDL_GF_Integration::get_form_id();

		// Fire after GF saves the entry, 2 args ($entry, $form).
		//
		// Priority 5, not 10: Gravity Forms feed add-ons (including Webhooks, which
		// M9 uses for HubSpot) process feeds on gform_after_submission at priority
		// 10, and gravityformswebhooks loads before this plugin. At equal priority
		// the webhook would run first, so a hung HubSpot could consume the request's
		// remaining max_execution_time before the distributor email is dispatched.
		// Routing the email first makes M8 delivery genuinely independent of M9.
		add_action( 'gform_after_submission_' . $form_id, [ __CLASS__, 'route_submission' ], 5, 2 );

		// Suppress GF's static-address admin notifications to prevent double-sending.
		// This filter is always registered (via plugins_loaded) so it fires whether GF
		// sends notifications before or after gform_after_submission.
		add_filter( 'gform_disable_notification_' . $form_id, [ __CLASS__, 'suppress_admin_notification' ], 10, 4 );
	}

	/**
	 * Main routing handler — fires after GF saves the entry.
	 *
	 * @param array $entry GF entry array.
	 * @param array $form  GF form array.
	 */
	public static function route_submission( array $entry, array $form ): void {
		$dist_field_id  = TDL_GF_Integration::get_distributor_field_id();
		$distributor_id = absint( rgar( $entry, strval( $dist_field_id ) ) );
		$gf_entry_id    = absint( rgar( $entry, 'id' ) );

		if ( $distributor_id <= 0 ) {
			// gform_validation should have already blocked this, but be explicit.
			TDL_Routing_Log::log( [
				'severity'         => 'error',
				'distributor_id'   => 0,
				'distributor_name' => '',
				'recipient_email'  => '',
				'routing_source'   => 'none',
				'gf_entry_id'      => $gf_entry_id,
				'message'          => 'Submission reached router with no distributor ID.',
			] );
			error_log( "TDL Email Router: entry {$gf_entry_id} has no distributor ID." );
			return;
		}

		// Distributor name — hidden field populated by JS, with post-title fallback.
		$dist_name_field_id = TDL_GF_Integration::get_distributor_name_field_id();
		$distributor_name   = sanitize_text_field( rgar( $entry, strval( $dist_name_field_id ) ) );
		if ( empty( $distributor_name ) ) {
			$post             = get_post( $distributor_id );
			$distributor_name = $post ? $post->post_title : '';
		}

		// Resolve the routing email.
		[ $recipient, $routing_source ] = self::get_routing_email( $distributor_id );

		if ( $recipient === null ) {
			TDL_Routing_Log::log( [
				'severity'         => 'error',
				'distributor_id'   => $distributor_id,
				'distributor_name' => $distributor_name,
				'recipient_email'  => '',
				'routing_source'   => 'none',
				'gf_entry_id'      => $gf_entry_id,
				'message'          => "No valid email found for distributor {$distributor_id}. Quote not delivered.",
			] );
			error_log( "TDL Email Router: distributor {$distributor_id} has no email_sales or email_main. Entry {$gf_entry_id} not delivered." );
			return;
		}

		$sent = self::send_email( $recipient, $distributor_name, $entry, $gf_entry_id );

		if ( $routing_source === 'email_main' ) {
			$severity = 'warning';
			$message  = "Routed to email_main — email_sales was empty for distributor {$distributor_id}.";
		} elseif ( ! $sent ) {
			$severity = 'error';
			$message  = "wp_mail() failed for distributor {$distributor_id}. Email may not have been delivered.";
		} else {
			$severity = 'info';
			$message  = "Quote routed to email_sales for distributor {$distributor_id}.";
		}

		TDL_Routing_Log::log( [
			'severity'         => $severity,
			'distributor_id'   => $distributor_id,
			'distributor_name' => $distributor_name,
			'recipient_email'  => $recipient,
			'routing_source'   => $routing_source,
			'gf_entry_id'      => $gf_entry_id,
			'message'          => $message,
		] );

		if ( ! $sent ) {
			error_log( "TDL Email Router: wp_mail() returned false for entry {$gf_entry_id}, recipient {$recipient}." );
		}
	}

	/**
	 * Suppress GF's static-address notifications so our wp_mail() is the only
	 * distributor-facing email. Customer confirmation emails (toType='field') are
	 * not suppressed.
	 *
	 * @param bool  $is_disabled
	 * @param array $notification
	 * @param array $form
	 * @param array $entry
	 * @return bool
	 */
	public static function suppress_admin_notification( bool $is_disabled, array $notification, array $form, array $entry ): bool {
		// 'email'  = static address → we own this notification; suppress it.
		// 'field'  = dynamic (customer email from a form field) → leave intact.
		// 'hidden' = routing key field → not a real notification type; leave intact.
		if ( rgar( $notification, 'toType' ) === 'email' ) {
			return true;
		}
		return $is_disabled;
	}

	/**
	 * Resolve the routing email address for a distributor.
	 *
	 * @return array{ 0: string|null, 1: string } [ email_address_or_null, routing_source ]
	 */
	private static function get_routing_email( int $distributor_id ): array {
		$email_sales = sanitize_email( (string) get_post_meta( $distributor_id, 'wpcf-email_sales', true ) );
		if ( ! empty( $email_sales ) && is_email( $email_sales ) ) {
			return [ $email_sales, 'email_sales' ];
		}

		$email_main = sanitize_email( (string) get_post_meta( $distributor_id, 'wpcf-email_main', true ) );
		if ( ! empty( $email_main ) && is_email( $email_main ) ) {
			return [ $email_main, 'email_main' ];
		}

		return [ null, 'none' ];
	}

	/**
	 * Build and send the HTML quote-request email.
	 *
	 * @return bool True if wp_mail() reported success.
	 */
	private static function send_email( string $to, string $distributor_name, array $entry, int $entry_id ): bool {
		$subject = sprintf(
			/* translators: %s: distributor name */
			__( 'Quote Request — %s', 'taylor-distributor-locator' ),
			$distributor_name
		);

		$template_data = self::build_template_data( $entry, $distributor_name, $entry_id );

		ob_start();
		include TDL_PLUGIN_DIR . 'templates/email/quote-request.php';
		$message = ob_get_clean();

		$from_name  = get_option( 'blogname', 'Taylor Company' );
		$from_email = sanitize_email( (string) get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		// CC support — address confirmed by client goes in WP option 'tdl_cc_email'.
		$cc_email = sanitize_email( (string) get_option( 'tdl_cc_email', '' ) );
		if ( ! empty( $cc_email ) && is_email( $cc_email ) ) {
			$headers[] = 'Cc: ' . $cc_email;
		}

		return (bool) wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Extract and sanitize GF entry fields for the email template.
	 *
	 * GF stores advanced-name sub-fields as "{field_id}.{input_id}":
	 *   first name = "1.3", last name = "1.6" (confirmed from Form 1 JSON).
	 * Other visible fields: email=3, phone=4, company=5, message=6.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_template_data( array $entry, string $distributor_name, int $entry_id ): array {
		$first = sanitize_text_field( rgar( $entry, self::FIELD_FIRST_NAME ) );
		$last  = sanitize_text_field( rgar( $entry, self::FIELD_LAST_NAME ) );
		$full  = trim( $first . ' ' . $last );

		return [
			'distributor_name' => $distributor_name,
			'entry_id'         => $entry_id,
			'customer_name'    => $full ?: __( '(not provided)', 'taylor-distributor-locator' ),
			'customer_email'   => sanitize_email( rgar( $entry, self::FIELD_EMAIL ) ),
			'customer_phone'   => sanitize_text_field( rgar( $entry, self::FIELD_PHONE ) ),
			'customer_company' => sanitize_text_field( rgar( $entry, self::FIELD_COMPANY ) ),
			'customer_message' => sanitize_textarea_field( rgar( $entry, self::FIELD_MESSAGE ) ),
			'submitted_at'     => esc_html( rgar( $entry, 'date_created' ) ?: current_time( 'mysql' ) ),
			'site_name'        => get_option( 'blogname', 'Taylor Company' ),
			'site_url'         => home_url(),
			'gf_entry_url'     => admin_url(
				'admin.php?page=gf_entries&view=entry&id=' .
				absint( TDL_GF_Integration::get_form_id() ) .
				'&lid=' . $entry_id
			),
		];
	}
}
