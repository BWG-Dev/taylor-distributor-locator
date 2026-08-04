<?php
/**
 * Gravity Forms integration — quote request form for the distributor locator.
 *
 * Responsibilities:
 *  - Ensure hidden distributor ID and distributor name fields exist on the form (idempotent).
 *  - Validate that the submitted distributor ID resolves to a published distributor.
 *
 * Form ID is stored in option 'tdl_gf_quote_form_id' (defaults to 1).
 * Field IDs are stored in options after first setup.
 * M8 (email routing) and M9 (HubSpot) both depend on the distributor ID field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDL_GF_Integration {

	const FORM_ID_OPTION            = 'tdl_gf_quote_form_id';
	const DIST_FIELD_ID_OPTION      = 'tdl_gf_distributor_field_id';
	const DIST_NAME_FIELD_ID_OPTION = 'tdl_gf_distributor_name_field_id';

	public static function init(): void {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Add hidden fields on init (runs once; no-op after both options are set).
		add_action( 'init', [ __CLASS__, 'ensure_hidden_fields' ], 20 );

		// Validate distributor ID on every submission of our form.
		add_filter( 'gform_validation', [ __CLASS__, 'validate_distributor_id' ] );
	}

	public static function get_form_id(): int {
		return (int) get_option( self::FORM_ID_OPTION, 1 );
	}

	public static function get_distributor_field_id(): int {
		return (int) get_option( self::DIST_FIELD_ID_OPTION, 0 );
	}

	public static function get_distributor_name_field_id(): int {
		return (int) get_option( self::DIST_NAME_FIELD_ID_OPTION, 0 );
	}

	/**
	 * Ensures both hidden fields (distributor ID + distributor name) exist on the form.
	 *
	 * Backwards-compatible: if the ID field already exists but the name field does not,
	 * only the name field is added. Both fields are added in a single GFAPI::update_form()
	 * call to avoid redundant DB writes.
	 *
	 * Safe to call on every request — short-circuits via option check with no DB query.
	 */
	public static function ensure_hidden_fields(): void {
		if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GF_Fields' ) ) {
			return;
		}

		$id_field_id   = self::get_distributor_field_id();
		$name_field_id = self::get_distributor_name_field_id();

		// Both already set — nothing to do.
		if ( $id_field_id > 0 && $name_field_id > 0 ) {
			return;
		}

		$form_id = self::get_form_id();
		$form    = GFAPI::get_form( $form_id );

		if ( ! $form ) {
			return;
		}

		// Scan existing fields so we never add duplicates (handles manual additions too).
		foreach ( $form['fields'] as $field ) {
			if ( $field->adminLabel === 'distributor_id' ) {
				$id_field_id = (int) $field->id;
			}
			if ( $field->adminLabel === 'distributor_name' ) {
				$name_field_id = (int) $field->id;
			}
		}

		$next_id  = (int) $form['nextFieldId'];
		$modified = false;

		if ( $id_field_id <= 0 ) {
			$form['fields'][] = GF_Fields::create( [
				'type'              => 'hidden',
				'id'                => $next_id,
				'formId'            => $form_id,
				'label'             => 'Distributor ID',
				'adminLabel'        => 'distributor_id',
				'defaultValue'      => '',
				'allowsPrepopulate' => false,
				'visibility'        => 'hidden',
			] );
			$id_field_id = $next_id++;
			$modified    = true;
		}

		if ( $name_field_id <= 0 ) {
			$form['fields'][] = GF_Fields::create( [
				'type'              => 'hidden',
				'id'                => $next_id,
				'formId'            => $form_id,
				'label'             => 'Distributor Name',
				'adminLabel'        => 'distributor_name',
				'defaultValue'      => '',
				'allowsPrepopulate' => false,
				'visibility'        => 'hidden',
			] );
			$name_field_id = $next_id++;
			$modified      = true;
		}

		if ( $modified ) {
			$form['nextFieldId'] = $next_id;
			GFAPI::update_form( $form );
		}

		update_option( self::DIST_FIELD_ID_OPTION, $id_field_id );
		update_option( self::DIST_NAME_FIELD_ID_OPTION, $name_field_id );
	}

	/**
	 * Validates that the submitted distributor ID resolves to a real, published distributor.
	 * Hooks into gform_validation so GF rejects the submission before saving an entry.
	 *
	 * @param array $validation_result GF validation result: { 'is_valid', 'form' }.
	 * @return array
	 */
	public static function validate_distributor_id( array $validation_result ): array {
		$form = $validation_result['form'];

		if ( (int) $form['id'] !== self::get_form_id() ) {
			return $validation_result;
		}

		$field_id  = self::get_distributor_field_id();
		$input_key = 'input_' . $field_id;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- GF handles nonce before this filter fires.
		$distributor_id = isset( $_POST[ $input_key ] )
			? absint( wp_unslash( $_POST[ $input_key ] ) )
			: 0;

		if ( $distributor_id <= 0 ) {
			$validation_result['is_valid'] = false;
			error_log( 'TDL GF: Quote submission rejected — missing distributor ID.' );
			return $validation_result;
		}

		$post = get_post( $distributor_id );

		if ( ! $post || $post->post_type !== 'distributor' || $post->post_status !== 'publish' ) {
			$validation_result['is_valid'] = false;
			error_log( sprintf(
				'TDL GF: Quote submission rejected — distributor ID %d is not a valid published distributor.',
				$distributor_id
			) );
		}

		return $validation_result;
	}
}
