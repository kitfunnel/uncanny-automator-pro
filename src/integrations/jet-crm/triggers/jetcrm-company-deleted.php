<?php

namespace Uncanny_Automator_Pro;

use Uncanny_Automator\Recipe;

/**
 * Class JETCRM_COMPANY_DELETED
 *
 * @package Uncanny_Automator_Pro
 */
class JETCRM_COMPANY_DELETED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'JETCRM' );
		$this->set_trigger_code( 'JETCRM_COMPANY_DELETED' );
		$this->set_trigger_meta( 'JETCRM_COMPANY' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_pro( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/jestpack-crm/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			esc_attr__( 'A company is deleted', 'uncanny-automator-pro' )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A company is deleted', 'uncanny-automator-pro' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'zbs_delete_company' );
		$this->set_action_args_count( 1 );
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		$obj_id = array_shift( $args );

		if ( ! isset( $obj_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( false );
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
