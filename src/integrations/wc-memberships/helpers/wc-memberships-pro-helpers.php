<?php

namespace Uncanny_Automator_Pro;

use Uncanny_Automator\Wc_Memberships_Helpers;

/**
 * Class Wc_Memberships_Pro_Helpers
 * @package Uncanny_Automator_Pro
 */
class Wc_Memberships_Pro_Helpers extends Wc_Memberships_Helpers {

	/**
	 * @var Wc_Memberships_Helpers
	 */
	public $options;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @var bool
	 */
	public $pro;

	/**
	 * Wc_Memberships_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wc_Memberships_Pro_Helpers $pro
	 */
	public function setPro( Wc_Memberships_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Get Membership Select Condition field args.
	 *
	 * @param string $option_code - The option code identifier.
	 *
	 * @return array
	 */
	public function get_membership_condition_field_args( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html__( 'Plan', 'uncanny-automator-pro' ),
			'required'              => true,
			'options'               => $this->get_membership_condition_options(),
			'supports_custom_value' => true,
		);
	}

	/**
	 * Get the membership condition options
	 *
	 * @return array
	 */
	public function get_membership_condition_options() {

		if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
			return array();
		}

		static $condition_options = null;
		if ( ! is_null( $condition_options ) ) {
			return $condition_options;
		}

		$args              = array(
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$memberships       = wc_memberships_get_membership_plans( $args );
		$condition_options = array();

		if ( ! empty( $memberships ) ) {
			$condition_options[] = array(
				'value' => - 1,
				'text'  => __( 'Any plan', 'uncanny-automator-pro' ),
			);

			foreach ( $memberships as $membership ) {
				$condition_options[] = array(
					'value' => $membership->id,
					'text'  => $membership->name,
				);
			}
		}

		return $condition_options;
	}

	/**
	 * Evalue the condition
	 *
	 * @param $membership_id - WP_Post ID of the membership plan
	 * @param $user_id - WP_User ID
	 *
	 * @return bool
	 */
	public function evaluate_condition_check( $membership_id, $user_id ) {

		// Check for Any Active memberships.
		if ( $membership_id < 0 ) {
			$active_memberships = wc_memberships_get_user_active_memberships( $user_id );

			return ! empty( $active_memberships );
		}

		// Check for specific membership.
		$cache     = true;
		$is_member = wc_memberships_is_user_active_member( $user_id, $membership_id, $cache );

		return $is_member;
	}

}
