<?php

namespace Uncanny_Automator_Pro;

/**
 * Class GP_REVOKERANK_A
 *
 * @package Uncanny_Automator_Pro
 */
class GP_REVOKERANK_A {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'REVOKERANK';
		$this->action_meta = 'GPRANK';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/gamipress/' ),
			'is_pro'             => true,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Actions - GamiPress */
			'sentence'           => sprintf( __( 'Revoke {{a rank:%1$s}} from the user', 'uncanny-automator-pro' ), $this->action_meta ),
			/* translators: Actions - GamiPress */
			'select_option_name' => __( 'Revoke {{a rank}} from the user', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'revoke_a_rank' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->gamipress->options->list_gp_rank_types(
							__( 'Rank type', 'uncanny-automator' ),
							'GPRANKTYPES',
							array(
								'token'        => false,
								'is_ajax'      => true,
								'target_field' => $this->action_meta,
								'endpoint'     => 'select_ranks_from_types_REVOKERANK',
							)
						),
						Automator()->helpers->recipe->field->select_field( $this->action_meta, __( 'Rank', 'uncanny-automator' ) ),
					),
				),
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function revoke_a_rank( $user_id, $action_data, $recipe_id, $args ) {

		$rank_types = gamipress_get_rank_types();

		$rank_id = $action_data['meta'][ $this->action_meta ];
		$rank    = get_post( $rank_id );

		if ( ! $rank || ! isset( $rank_types[ $rank->post_type ] ) ) {
			return;
		}

		$user_rank_id = gamipress_get_user_rank_id( absint( $user_id ), $rank->post_type );

		if ( ! empty( $user_rank_id ) && $rank_id == $user_rank_id ) {
			gamipress_revoke_rank_to_user( absint( $user_id ), $user_rank_id, 0, array( 'admin_id' => absint( $user_id ) ) );
			// if still rank is assigned to user
			$user_rank_id = gamipress_get_user_rank_id( absint( $user_id ), $rank->post_type );
			if ( ! empty( $user_rank_id ) && $rank_id == $user_rank_id ) {
				$meta = "_gamipress_{$rank->post_type}_rank";
				gamipress_delete_user_meta( $user_id, $meta );
			}
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( "The user didn't have the specified rank.", 'uncanny-automator-pro' ) );
		}
	}

}
