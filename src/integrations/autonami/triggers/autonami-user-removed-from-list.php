<?php

namespace Uncanny_Automator_Pro;

/**
 * Class AUTONAMI_USER_REMOVED_FROM_LIST
 *
 * @package Uncanny_Automator
 */
class AUTONAMI_USER_REMOVED_FROM_LIST {

	use \Uncanny_Automator\Recipe\Triggers;

	/**
	 * @var Autonami_Pro_Helpers
	 */
	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->helpers = new Autonami_Pro_Helpers();
		$this->setup_trigger();
		$this->register_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'AUTONAMI' );
		$this->set_trigger_code( 'USER_REMOVED_FROM_LIST' );
		$this->set_trigger_meta( 'LIST' );
		$this->set_is_login_required( false );
		$this->set_is_pro( true );
		$this->set_support_link( $this->helpers->support_link( $this->trigger_code ) );

		/* Translators: List name */
		$this->set_sentence( sprintf( 'A user is removed from {{a list:%1$s}}', $this->get_trigger_meta() ) );

		$this->set_readable_sentence( 'A user is removed from {{a list}}' );

		$this->add_action( 'automator_bwfan_contact_removed_from_list' );

		$this->set_action_args_count( 2 );

		$this->set_options_callback( array( $this, 'load_options' ) );

	}

	/**
	 * Method load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$options[] = $this->helpers->get_list_dropdown();
		return array( 'options' => $options );

	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function validate_trigger( ...$args ) {
		try {

			$contact = $this->helpers->extract_contact_from_args( $args );
			$user_id = $this->helpers->get_wp_id( $contact );
			$this->set_user_id( $user_id );

		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Method prepare_to_run
	 *
	 * @param $data
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Check list ID against the trigger meta
	 *
	 * @param $args
	 */
	public function trigger_conditions( $args ) {

		try {
			$list_id = $this->helpers->extract_list_id_from_args( $args );
		} catch ( \Exception $e ) {
			return;
		}

		$this->do_find_any( true ); // Support "Any tag" option

		// Find the tag in trigger meta
		$this->do_find_this( $this->get_trigger_meta() );
		$this->do_find_in( $list_id );

	}

}
