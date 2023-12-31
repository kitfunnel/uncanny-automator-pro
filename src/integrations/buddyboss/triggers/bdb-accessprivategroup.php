<?php

namespace Uncanny_Automator_Pro;

/**
 * Class BDB_ACCESSPRIVATEGROUP
 *
 * @package Uncanny_Automator_Pro
 */
class BDB_ACCESSPRIVATEGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	/**
	 * @var string
	 */
	private $trigger_code;

	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BDBACCESSPRIVATEGROUP';
		$this->trigger_meta = 'BDBGROUPS';
		$this->define_trigger();
	}

	/**
	 * @throws \Exception
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/buddyboss/' ),
			'is_pro'              => true,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - BuddyBoss */
			'sentence'            => sprintf( __( 'A user requests access to {{a private group:%1$s}}', 'uncanny-automator-pro' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - BuddyBoss */
			'select_option_name'  => __( 'A user requests access to {{a private group}}', 'uncanny-automator-pro' ),
			'action'              => 'groups_membership_requested',
			'priority'            => 60,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'groups_requests_access' ),
			'options_callback'    => array( $this, 'load_options' ),
		);
		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$bp_group_args = array(
			'uo_include_any' => true,
			'uo_any_label'   => __( 'Any private group', 'uncanny-automator-pro' ),
			'status'         => array( 'private' ),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->buddyboss->options->all_buddyboss_groups(
						__( 'Private group', 'uncanny-automator-pro' ),
						$this->trigger_meta,
						$bp_group_args
					),
				),
			)
		);
	}

	/**
	 * @param int $user_id ID of the user requesting membership.
	 * @param array $admins Array of group admins.
	 * @param int $group_id ID of the group being requested to.
	 * @param int $request_id ID of the request.
	 */
	public function groups_requests_access( $user_id, $admins, $group_id, $request_id ) {
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$group              = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			// Match recipe if trigger for Any group '-1', or matching Group ID.
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $group[ $recipe_id ][ $trigger_id ] )
					|| intval( $group_id ) === intval( $group[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $args, false );
				// Save trigger meta
				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] && $result['args']['trigger_id'] &&
							$result['args']['trigger_log_id'] ) {
							$run_number = Automator()->get->trigger_run_number(
								$result['args']['trigger_id'],
								$result['args']['trigger_log_id'],
								$user_id
							);

							$save_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'run_number'     => $run_number, //get run number
								'trigger_log_id' => $result['args']['trigger_log_id'],
							);

							$save_meta['meta_key']   = $this->trigger_meta;
							$save_meta['meta_value'] = $group_id;
							Automator()->insert_trigger_meta( $save_meta );

							$save_meta['meta_key']   = 'USER_PROFILE_URL';
							$save_meta['meta_value'] = '';

							// Check if `bbp_get_user_profile_url` exists. Overwrite `meta_value` with data coming from `bbp_get_user_profile_url`.
							if ( function_exists( 'bbp_get_user_profile_url' ) ) {
								$save_meta['meta_value'] = maybe_serialize( bbp_get_user_profile_url( $user_id ) );
							}

							Automator()->insert_trigger_meta( $save_meta );

							$group_obj               = groups_get_group( $group_id );
							$save_meta['meta_key']   = 'MANAGE_GROUP_REQUESTS_URL';
							$save_meta['meta_value'] = maybe_serialize( bp_get_group_permalink( $group_obj ) . 'admin/membership-requests/' );
							Automator()->insert_trigger_meta( $save_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}
