<?php


namespace Uncanny_Automator_Pro;

use GFCommon;
use RGFormsModel;
use Uncanny_Automator\Gravity_Forms_Helpers;

/**
 * Class Gravity_Forms_Pro_Helpers
 *
 * @package Uncanny_Automator_Pro
 */
class Gravity_Forms_Pro_Helpers extends Gravity_Forms_Helpers {
	/**
	 * Gravity_Forms_Pro_Helpers constructor.
	 */
	public function __construct( $load_actions = true ) {

		$this->load_options = true;

		if ( $load_actions ) {

			// Include the trigger code in common tokens so it automatically rendered and parsed.
			// @see (base plugin) uncanny-automator-src/integrations/gravity-forms/tokens/gf-common-tokens.php
			add_filter(
				'automator_gf_common_tokens_form_tokens',
				function( $triggers ) {
					$triggers[] = 'ANON_GF_FORM_FIELD_MATCHABLE';
					return $triggers;
				},
				20,
				1
			);

			add_action( 'wp_ajax_select_form_fields_ANONGFFORMS', array( $this, 'select_form_fields_func' ) );

			add_action( 'wp_ajax_select_form_fields_GFFORMS', array( $this, 'select_form_fields_func' ) );

			add_action( 'wp_ajax_get_form_fields_GFFORMS', array( $this, 'get_fields_rows_gfforms' ) );

			add_action( 'wp_ajax_retrieve_fields_from_form_id', array( $this, 'get_fields_from_form_id' ) );

		}

	}

	/**
	 * @param Gravity_Forms_Pro_Helpers $pro
	 */
	public function setPro( Gravity_Forms_Pro_Helpers $pro ) {
		parent::setPro( $pro );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_form_fields_func() {

		Automator()->utilities->ajax_auth_check( $_POST );

		$fields = array();
		if ( isset( $_POST ) ) {
			$form_id = absint( $_POST['value'] );

			$form = RGFormsModel::get_form_meta( $form_id );

			if ( is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
						foreach ( $field['inputs'] as $input ) {
							$fields[] = array(
								'value' => $input['id'],
								'text'  => GFCommon::get_label( $field, $input['id'] ),
							);
						}
					} elseif ( ! rgar( $field, 'displayOnly' ) ) {
						$fields[] = array(
							'value' => $field['id'],
							'text'  => GFCommon::get_label( $field ),
						);
					}
				}
			}
		}
		echo wp_json_encode( $fields );
		die();
	}


	/**
	 * @return void
	 */
	public function get_fields_rows_gfforms() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$response = (object) array(
			'success' => false,
			'fields'  => array(),
		);

		$fields = array();

		if ( isset( $_POST ) ) {
			$form_id = absint( $_POST['form_id'] );

			$form = RGFormsModel::get_form_meta( $form_id );

			if ( is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {

					if ( isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
						foreach ( $field['inputs'] as $input ) {
							$fields[] = array(
								'key'  => $input['id'] . ' - ' . GFCommon::get_label( $field, $input['id'] ),
								'type' => 'text',
								'data' => $input['id'] . ' - ' . GFCommon::get_label( $field, $input['id'] ),
							);
						}
					} elseif ( ! rgar( $field, 'displayOnly' ) ) {
						$fields[] = array(
							'value' => $field['id'],
							'text'  => $field['id'] . ' - ' . GFCommon::get_label( $field ),
						);
					}
				}

				$response = (object) array(
					'success' => true,
					'fields'  => array( $fields ),
				);

				return wp_send_json_success( $response );
			}
		}

		$response = (object) array(
			'success' => false,
			'error'   => "Couldn't fetch fields",
		);

		return wp_send_json_success( $response );
	}

	/**
	 * Retrieves all forms as option fields.
	 *
	 * @return array The list of option fields from Gravity forms.
	 */
	public function get_forms_as_option_fields() {

		if ( ! class_exists( '\GFAPI' ) || ! is_admin() ) {
			return array();
		}

		$forms = \GFAPI::get_forms();

		foreach ( $forms as $form ) {
			$options[ absint( $form['id'] ) ] = $form['title'];
		}

		return ! empty( $options ) ? $options : array();

	}

	/**
	 * Retrieves all form fields from specific form using form ID.
	 *
	 * Callback method to wp_ajax_retrieve_fields_from_form_id.
	 *
	 * @return void
	 */
	public function get_fields_from_form_id() {

		Automator()->utilities->ajax_auth_check();

		if ( ! class_exists( '\GFAPI' ) ) {
			return array();
		}

		$form_id = absint( automator_filter_input( 'value', INPUT_POST ) );

		$form_selected = \GFAPI::get_form( $form_id );

		$fields = ! empty( $form_selected['fields'] ) ? $form_selected['fields'] : array();

		foreach ( $fields as $field ) {
			$options[] = array(
				'text'  => ! empty( $field['label'] ) ? esc_html( $field['label'] ) : 'Field: ' . absint( $field['id'] ),
				'value' => absint( $field['id'] ),
			);
		}

		wp_send_json( isset( $options ) ? $options : array() );

		die;

	}

}
