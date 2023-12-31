<?php

namespace Uncanny_Automator_Pro;

use WC_Order_Item_Product;

/**
 * Class ANON_WC_PURCHPROD
 * @package Uncanny_Automator_Pro
 */
class ANON_WC_PURCHPROD_DEP {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WC';

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
		$this->trigger_code = 'ANONWCPURCHPROD';
		$this->trigger_meta = 'WOOPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/woocommerce/' ),
			'is_pro'              => true,
			'is_deprecated'       => true,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce */
			'sentence'            => sprintf( __( '{{A product:%1$s}} is purchased via guest checkout (deprecated)', 'uncanny-automator-pro' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WooCommerce */
			'select_option_name'  => __( '{{A product}} is purchased via guest checkout (deprecated)', 'uncanny-automator-pro' ),
			'action'              => array(
				'woocommerce_order_status_completed',
				'woocommerce_thankyou',
				'woocommerce_payment_complete',
			),
			'priority'            => 999,
			'accepted_args'       => 1,
			'type'                => 'anonymous',
			'validation_function' => array( $this, 'payment_completed' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );

	}

	/**
	 * @return array
	 */
	public function load_options() {

		$options = Automator()->helpers->recipe->woocommerce->options->all_wc_products( __( 'Product', 'uncanny-automator' ) );

		$options['options'] = array( '-1' => __( 'Any product', 'uncanny-automator' ) ) + $options['options'];
		$options_array      = array(
			'options' => array(
				Automator()->helpers->recipe->options->number_of_times(),
				$options,
			),
		);

		return Automator()->utilities->keep_order_of_options( $options_array );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 */
	public function payment_completed( $order_id ) {

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( 'completed' !== $order->get_status() ) {
			return;
		}

		$user_id            = $order->get_customer_id();
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_product   = Automator()->get->meta_from_recipes( $recipes, 'WOOPRODUCT' );
		$matched_recipe_ids = array();

		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( - 1 === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);

					break;
				}
			}
		}
		$items       = $order->get_items();
		$product_ids = array();
		/** @var WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			$product_ids[] = $item->get_product_id();
		}
		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( in_array( $required_product[ $recipe_id ][ $trigger_id ], $product_ids, false ) ) {
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

				//Adding an action to save order id in trigger meta
				do_action( 'uap_wc_trigger_save_meta', $order_id, $matched_recipe_id['recipe_id'], $args, 'product' );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;
	}
}
