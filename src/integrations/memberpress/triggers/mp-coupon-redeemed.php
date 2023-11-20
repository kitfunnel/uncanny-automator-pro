<?php

namespace Uncanny_Automator_Pro;

use Uncanny_Automator\Recipe;

/**
 * Class MP_COUPON_REDEEMED
 *
 * @package Uncanny_Automator_Pro
 */
class MP_COUPON_REDEEMED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
		$this->set_helper( new Memberpress_Pro_Helpers() );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'MP' );
		$this->set_trigger_code( 'MP_COUPON_REDEEMED' );
		$this->set_trigger_meta( 'MP_COUPONS' );
		$this->set_is_pro( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/memberpress/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence - Memberpress */
			sprintf( esc_html__( '{{A coupon code:%1$s}} is redeemed', 'uncanny-automator-pro' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( '{{A coupon code}} is redeemed', 'uncanny-automator-pro' ) );
		// Which do_action() fires this trigger.
		$this->add_action( array( 'mepr-event-transaction-completed', 'mepr-event-recurring-transaction-completed' ), 9999, 1 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * callback load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helper()->get_all_mp_coupons(
						array(
							'option_code' => $this->get_trigger_meta(),
							'is_any'      => true,
						)
					),
				),
			)
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $event ) = array_shift( $args );

		if ( ! $event instanceof \MeprEvent ) {
			return false;
		}

		/** @var \MeprTransaction $transaction */
		$transaction = $event->get_data();
		if ( empty( $transaction->coupon() ) ) {
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
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check product_id against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $event ) = $args[0];

		/** @var \MeprTransaction $transaction */
		$transaction = $event->get_data();
		$coupon      = $transaction->coupon();

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( absint( $coupon->rec->ID ) ) )
					->format( array( 'intval' ) )
					->get();

	}

}
