<?php

namespace Uncanny_Automator_Pro;

/**
 * Class RESTRICT_CONTENT_MEMBERSHIPCANCELLED
 *
 * @package Uncanny_Automator_Pro
 */
class RESTRICT_CONTENT_MEMBERSHIPCANCELLED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'RC';

	/**
	 * @var string
	 */
	private $trigger_code;

	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'RCMEMBERSHIPCANCELLED';
		$this->trigger_meta = 'RCMEMBERSHIPLEVELCANCEL';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/restrict-content/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'is_pro'              => true,
			/* translators: Logged-in trigger - Wishlist Member */
			'sentence'            => sprintf( esc_attr__( "A user's membership to {{a specific level:%1\$s}} is cancelled", 'uncanny-automator-pro' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Wishlist Member */
			'select_option_name'  => esc_attr__( "A user's membership to {{a specific level}} is cancelled", 'uncanny-automator-pro' ),
			'action'              => 'rcp_transition_membership_status_cancelled',
			'priority'            => 5,
			'accepted_args'       => 2,
			'validation_function' => array(
				$this,
				'user_cancelled_membership_level',
			),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->restrict_content->options->get_membership_levels(
						null,
						$this->trigger_meta,
						array( 'any' => true )
					),
				),
			)
		);
	}

	/**
	 * @param string $old_status of the membership.
	 * @param int $membership_id ID of the membership.
	 */
	public function user_cancelled_membership_level( $old_status, $membership_id ) {

		$membership       = rcp_get_membership( $membership_id );
		$membership_level = rcp_get_membership_level( $membership->get_object_id() );
		$level_id         = $membership_level->get_id();

		$user_id = $membership->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_level     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where Membership Level is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $required_level[ $recipe_id ][ $trigger_id ] === '-1' || (int) $required_level[ $recipe_id ][ $trigger_id ] === (int) $level_id ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {

				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							// Add token for options
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_MEMBERSHIPID',
									'meta_value'     => $membership_id,
									'trigger_log_id' => $result['args']['trigger_log_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;

	}

}
