<?php
/**
 * HubSpot Integration — M9
 *
 * Transport is the Gravity Forms Webhooks Add-On (per client requirement).
 * This class supplies the pieces the add-on cannot do natively:
 *
 *   1. HubSpot's CRM v3 contacts endpoint expects a nested {"properties": {...}}
 *      body. The add-on only emits a flat field map, so the body is rebuilt in
 *      gform_webhooks_request_args.
 *   2. The access token is injected as an Authorization header at request time
 *      so it never has to be typed into — or stored in — the Gravity Forms feed UI.
 *   3. The distributor name is resolved server-side from the distributor post ID,
 *      never taken from the posted hidden field (see M7 validation rules).
 *
 * Independence from M8: nothing here runs inside the email routing path. Every
 * public entry point is wrapped so a HubSpot outage, a malformed payload, or a
 * PHP error in this class cannot prevent TDL_Email_Router from sending. The
 * request also carries a short timeout so a hung HubSpot cannot burn the
 * request's remaining max_execution_time before the email is dispatched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_HubSpot {

	// Option keys. All integration config is admin-editable — nothing hardcoded.
	const OPT_ENABLED      = 'tdl_hubspot_enabled';
	const OPT_TOKEN        = 'tdl_hubspot_token';
	const OPT_PORTAL_ID    = 'tdl_hubspot_portal_id';
	const OPT_ENDPOINT     = 'tdl_hubspot_endpoint';
	const OPT_PROP_COMPANY = 'tdl_hubspot_prop_company';
	const OPT_PROP_DIST    = 'tdl_hubspot_prop_distributor';
	const OPT_PROP_MESSAGE = 'tdl_hubspot_prop_message';
	const OPT_PROP_SOURCE  = 'tdl_hubspot_prop_lead_source';
	const OPT_SOURCE_VALUE = 'tdl_hubspot_lead_source_value';

	/**
	 * Defaults verified against portal 7290009 on 2026-08-04 via
	 * GET /crm/v3/properties/contacts. All four are free-text/textarea
	 * properties — deliberately not HubSpot "enumeration" properties,
	 * which silently discard values that do not match a defined option.
	 */
	const DEFAULT_ENDPOINT     = 'https://api.hubapi.com/crm/v3/objects/contacts';
	const DEFAULT_PROP_COMPANY = 'company';
	const DEFAULT_PROP_DIST    = 'distributor_name';
	const DEFAULT_PROP_MESSAGE = 'taylor_message';
	const DEFAULT_PROP_SOURCE  = 'lead_source';
	const DEFAULT_SOURCE_VALUE = 'Distributor Locator';

	/** Kept short so a hung HubSpot cannot starve the M8 email send. */
	const REQUEST_TIMEOUT = 10;

	// Gravity Forms field IDs — verified against Form 1 "Request Quote".
	const F_FIRST     = '1.3';
	const F_LAST      = '1.6';
	const F_EMAIL     = '3';
	const F_PHONE     = '4';
	const F_COMPANY   = '5';
	const F_MESSAGE   = '6';
	const F_DIST_ID   = '7';
	const F_DIST_NAME = '8';

	/**
	 * Context for the in-flight webhook, set when the request args are built and
	 * consumed by the http_api_debug logger. The Webhooks Add-On exposes no
	 * post-request hook, so the WP HTTP layer is where the response is observed.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $pending = null;

	public static function init(): void {
		// Registered unconditionally: the filters no-op unless the feed targets HubSpot.
		add_filter( 'gform_webhooks_request_url',  [ __CLASS__, 'filter_request_url' ], 10, 4 );
		add_filter( 'gform_webhooks_request_args', [ __CLASS__, 'filter_request_args' ], 10, 4 );
		add_action( 'http_api_debug',              [ __CLASS__, 'log_response' ], 10, 5 );
	}

	// ── Configuration ──────────────────────────────────────────────────────────

	public static function is_enabled(): bool {
		return (bool) get_option( self::OPT_ENABLED, false );
	}

	/**
	 * Access token. A wp-config constant always wins over the stored option so
	 * production can keep the credential out of the database entirely.
	 */
	public static function get_token(): string {
		if ( defined( 'TDL_HUBSPOT_TOKEN' ) && TDL_HUBSPOT_TOKEN ) {
			return (string) TDL_HUBSPOT_TOKEN;
		}
		return (string) get_option( self::OPT_TOKEN, '' );
	}

	public static function token_is_from_constant(): bool {
		return defined( 'TDL_HUBSPOT_TOKEN' ) && TDL_HUBSPOT_TOKEN;
	}

	public static function get_endpoint(): string {
		$url = trim( (string) get_option( self::OPT_ENDPOINT, self::DEFAULT_ENDPOINT ) );
		return $url !== '' ? $url : self::DEFAULT_ENDPOINT;
	}

	private static function get_prop( string $option, string $default ): string {
		$name = trim( (string) get_option( $option, $default ) );
		return $name !== '' ? $name : '';
	}

	// ── Gravity Forms Webhooks hooks ───────────────────────────────────────────

	/**
	 * Only feeds pointing at the HubSpot API are ours. Any other webhook feed on
	 * any form passes through untouched.
	 *
	 * @param array $feed The current Feed object.
	 */
	private static function is_hubspot_feed( array $feed ): bool {
		$url = (string) ( $feed['meta']['requestURL'] ?? '' );
		if ( $url === '' ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && ( $host === 'api.hubapi.com' || substr( $host, -11 ) === '.hubapi.com' );
	}

	/**
	 * Abort the webhook before any HTTP call when it cannot possibly succeed.
	 * Returning an empty URL makes the add-on log a feed error and stop — it does
	 * not raise an exception, so the submission (and M8 email routing) continues.
	 *
	 * @return string The request URL, or '' to abort.
	 */
	public static function filter_request_url( $request_url, $feed, $entry, $form ) {
		try {
			if ( ! is_array( $feed ) || ! self::is_hubspot_feed( $feed ) ) {
				return $request_url;
			}

			if ( ! self::is_enabled() ) {
				self::log( 'warning', $entry, 'Skipped — HubSpot integration is disabled in settings.' );
				return '';
			}

			if ( self::get_token() === '' ) {
				self::log( 'error', $entry, 'Skipped — no HubSpot access token configured.' );
				return '';
			}

			// HubSpot keys contacts on email; without one the call is guaranteed to 400.
			if ( sanitize_email( (string) rgar( $entry, self::F_EMAIL ) ) === '' ) {
				self::log( 'error', $entry, 'Skipped — submission has no valid email address.' );
				return '';
			}

			return self::get_endpoint();
		} catch ( \Throwable $e ) {
			error_log( '[TDL][M9] filter_request_url failed: ' . $e->getMessage() );
			return ''; // Abort rather than send an unverified request.
		}
	}

	/**
	 * Replace the add-on's flat body with HubSpot's nested properties envelope and
	 * attach the Authorization header.
	 *
	 * On any failure the original args are returned unchanged rather than throwing,
	 * so a bug here can never interrupt form submission or M8 routing.
	 *
	 * @param array $request_args HTTP request arguments.
	 */
	public static function filter_request_args( $request_args, $feed, $entry, $form ) {
		try {
			if ( ! is_array( $request_args ) || ! is_array( $feed ) || ! self::is_hubspot_feed( $feed ) ) {
				return $request_args;
			}

			$properties = self::build_properties( $entry );
			if ( empty( $properties['email'] ) ) {
				return $request_args;
			}

			$request_args['method']  = 'POST';
			$request_args['timeout'] = self::REQUEST_TIMEOUT;
			$request_args['body']    = wp_json_encode( [ 'properties' => $properties ] );
			$request_args['headers'] = array_merge(
				is_array( $request_args['headers'] ?? null ) ? $request_args['headers'] : [],
				[
					'Authorization' => 'Bearer ' . self::get_token(),
					'Content-Type'  => 'application/json',
				]
			);

			// Handed to log_response() once the HTTP layer reports back.
			self::$pending = [
				'entry_id'         => absint( rgar( $entry, 'id' ) ),
				'distributor_id'   => absint( rgar( $entry, self::F_DIST_ID ) ),
				'distributor_name' => (string) ( $properties[ self::get_prop( self::OPT_PROP_DIST, self::DEFAULT_PROP_DIST ) ] ?? '' ),
				'email'            => (string) $properties['email'],
			];

			return $request_args;
		} catch ( \Throwable $e ) {
			error_log( '[TDL][M9] filter_request_args failed: ' . $e->getMessage() );
			return $request_args;
		}
	}

	/**
	 * Build the HubSpot contact property map from a Gravity Forms entry.
	 *
	 * Property names are read from settings so a HubSpot schema change is a
	 * configuration edit, not a code change.
	 *
	 * @return array<string,string>
	 */
	private static function build_properties( $entry ): array {
		$props = [];

		$email = sanitize_email( (string) rgar( $entry, self::F_EMAIL ) );
		if ( $email === '' ) {
			return $props;
		}
		$props['email'] = $email;

		$first = self::clean( (string) rgar( $entry, self::F_FIRST ) );
		$last  = self::clean( (string) rgar( $entry, self::F_LAST ) );
		if ( $first !== '' ) {
			$props['firstname'] = $first;
		}
		if ( $last !== '' ) {
			$props['lastname'] = $last;
		}

		$phone = self::clean( (string) rgar( $entry, self::F_PHONE ) );
		if ( $phone !== '' ) {
			$props['phone'] = $phone;
		}

		$map = [
			self::get_prop( self::OPT_PROP_COMPANY, self::DEFAULT_PROP_COMPANY ) => self::clean( (string) rgar( $entry, self::F_COMPANY ) ),
			self::get_prop( self::OPT_PROP_MESSAGE, self::DEFAULT_PROP_MESSAGE ) => self::clean( (string) rgar( $entry, self::F_MESSAGE ) ),
			self::get_prop( self::OPT_PROP_DIST, self::DEFAULT_PROP_DIST )       => self::resolve_distributor_name( $entry ),
			self::get_prop( self::OPT_PROP_SOURCE, self::DEFAULT_PROP_SOURCE )   => self::clean( (string) get_option( self::OPT_SOURCE_VALUE, self::DEFAULT_SOURCE_VALUE ) ),
		];

		foreach ( $map as $property => $value ) {
			// A blank property name means "don't send this" — e.g. Lead Source
			// can be cleared in settings if the client changes their mind.
			if ( $property !== '' && $value !== '' ) {
				$props[ $property ] = $value;
			}
		}

		return $props;
	}

	/**
	 * Resolve the distributor company name from the posted distributor post ID.
	 *
	 * Server-side lookup is the source of truth; the hidden distributor_name field
	 * is only a fallback for entries whose distributor post has since been deleted.
	 *
	 * Post titles use the M4 "Company — Location" convention, so the location
	 * suffix is stripped. Titles are stored with HTML entities (e.g. "ABS &#038;
	 * Taylor Enterprises"), which must be decoded or HubSpot stores them literally.
	 */
	private static function resolve_distributor_name( $entry ): string {
		$post_id = absint( rgar( $entry, self::F_DIST_ID ) );
		$title   = '';

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && $post->post_type === 'distributor' && $post->post_status === 'publish' ) {
				$title = $post->post_title;
			}
		}

		if ( $title === '' ) {
			$title = (string) rgar( $entry, self::F_DIST_NAME );
		}

		// Strip the location suffix. Both dash characters appear in real titles.
		$parts = preg_split( '/\s+[—–]\s+/u', $title );
		if ( is_array( $parts ) && isset( $parts[0] ) ) {
			$title = $parts[0];
		}

		return self::clean( $title );
	}

	/** Decode stored entities and normalise whitespace before sending upstream. */
	private static function clean( string $value ): string {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( wp_strip_all_tags( $value ) );
	}

	// ── Logging ────────────────────────────────────────────────────────────────

	/**
	 * Record the HubSpot response. The Webhooks Add-On has no post-request hook,
	 * so the WP HTTP layer is observed instead; the pending context is only set
	 * for requests this class actually built.
	 *
	 * @param array|WP_Error $response
	 */
	public static function log_response( $response, $context, $class, $args, $url ): void {
		if ( self::$pending === null || $context !== 'response' ) {
			return;
		}

		try {
			$pending       = self::$pending;
			self::$pending = null; // Consume once, whatever the outcome.

			if ( is_wp_error( $response ) ) {
				self::write_log( 'error', $pending, 'Request failed: ' . $response->get_error_message() );
				return;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( $code >= 200 && $code < 300 ) {
				self::write_log( 'info', $pending, sprintf( 'Contact synced (HTTP %d).', $code ) );
				return;
			}

			// Body is truncated by the log column; enough to identify the failure.
			$body = trim( (string) wp_remote_retrieve_body( $response ) );
			self::write_log( 'error', $pending, sprintf( 'HTTP %d — %s', $code, substr( $body, 0, 300 ) ) );
		} catch ( \Throwable $e ) {
			error_log( '[TDL][M9] log_response failed: ' . $e->getMessage() );
		}
	}

	/** Log before the request context exists (skips/aborts). */
	private static function log( string $severity, $entry, string $message ): void {
		self::write_log(
			$severity,
			[
				'entry_id'         => absint( rgar( (array) $entry, 'id' ) ),
				'distributor_id'   => absint( rgar( (array) $entry, self::F_DIST_ID ) ),
				'distributor_name' => '',
				'email'            => '',
			],
			$message
		);
	}

	/**
	 * Writes to the M8 routing log table. Entries are prefixed [HubSpot] and use
	 * routing_source 'none' so they are visually distinct from email routing rows
	 * without altering M8's schema or its allowed source values.
	 */
	private static function write_log( string $severity, array $ctx, string $message ): void {
		if ( ! class_exists( 'TDL_Routing_Log' ) ) {
			return;
		}
		TDL_Routing_Log::log( [
			'severity'         => $severity,
			'distributor_id'   => $ctx['distributor_id'] ?? 0,
			'distributor_name' => $ctx['distributor_name'] ?? '',
			'recipient_email'  => $ctx['email'] ?? '',
			'routing_source'   => 'none',
			'gf_entry_id'      => $ctx['entry_id'] ?? 0,
			'message'          => '[HubSpot] ' . $message,
		] );
	}

	// ── Admin connection test ──────────────────────────────────────────────────

	/**
	 * Read-only connection check used by the settings page. Calls the account-info
	 * endpoint — no CRM records are read or written.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function test_connection(): array {
		$token = self::get_token();
		if ( $token === '' ) {
			return [ 'ok' => false, 'message' => __( 'No access token configured.', 'taylor-distributor-locator' ) ];
		}

		$response = wp_remote_get( 'https://api.hubapi.com/account-info/v3/details', [
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'message' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return [
				'ok'      => false,
				/* translators: %d: HTTP status code. */
				'message' => sprintf( __( 'HubSpot returned HTTP %d. Check the token and its scopes.', 'taylor-distributor-locator' ), $code ),
			];
		}

		$data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$portal  = isset( $data['portalId'] ) ? absint( $data['portalId'] ) : 0;
		$type    = isset( $data['accountType'] ) ? sanitize_text_field( (string) $data['accountType'] ) : '?';
		$is_prod = $type === 'STANDARD';

		return [
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: portal ID, 2: account type, 3: production/sandbox warning. */
				__( 'Connected to portal %1$d (%2$s). %3$s', 'taylor-distributor-locator' ),
				$portal,
				$type,
				$is_prod
					? __( 'This is a PRODUCTION portal — submissions create real contacts.', 'taylor-distributor-locator' )
					: ''
			),
		];
	}
}
