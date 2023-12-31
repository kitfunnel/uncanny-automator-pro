<?php

namespace Uncanny_Automator_Pro;

/**
 * Class BDB_SENDNOTIFICATION
 *
 * @package Uncanny_Automator_Pro
 */
class BDB_SENDNOTIFICATION {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $action_code;
	private $action_meta;

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->action_code = 'BDBSENDNOTIFICATION';
		$this->action_meta = 'BDBNOTIFICATION';

		$this->define_action();

		// Registering custom component
		add_filter(
			'bp_notifications_get_registered_components',
			array(
				$this,
				'uo_bdb_component',
			),
			10,
			2
		);

		// BDB notification content
		add_filter(
			'bp_notifications_get_notifications_for_user',
			array(
				$this,
				'uo_bdb_notification_content',
			),
			10,
			8
		);
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/buddyboss/' ),
			'is_pro'             => true,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyBoss */
			'sentence'           => sprintf( esc_attr__( 'Send the user a {{notification:%1$s}}', 'uncanny-automator-pro' ), $this->action_meta ),
			/* translators: Action - BuddyBoss */
			'select_option_name' => esc_attr__( 'Send the user a {{notification}}', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'send_notification' ),
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
						Automator()->helpers->recipe->field->text_field( 'BDBNOTIFICATIONCONTENT', esc_attr__( 'Notification content', 'uncanny-automator-pro' ), true, 'textarea' ),
						Automator()->helpers->recipe->field->text_field( 'BDBNOTIFICATIONLINK', esc_attr__( 'Notification link', 'uncanny-automator-pro' ), true, 'text', '', false ),
					),
				),
			)
		);
	}

	/**
	 * Send notification to user
	 *
	 * @param string $user_id
	 * @param array $action_data
	 * @param string $recipe_id
	 *
	 * @since 1.1
	 * @return void
	 *
	 */
	public function send_notification( $user_id, $action_data, $recipe_id, $args ) {

		$notification_content = Automator()->parse->text( $action_data['meta']['BDBNOTIFICATIONCONTENT'], $recipe_id, $user_id, $args );
		$notification_content = do_shortcode( $notification_content );
		$notification_link    = Automator()->parse->text( $action_data['meta']['BDBNOTIFICATIONLINK'], $recipe_id, $user_id, $args );
		$notification_link    = do_shortcode( $notification_link );

		// Attempt to send notification.
		if ( function_exists( 'bp_notifications_add_notification' ) ) {

			$notification_id = bp_notifications_add_notification(
				array(
					'user_id'           => $user_id,
					'item_id'           => $action_data['ID'],
					'secondary_item_id' => $user_id,
					'component_name'    => 'uncanny-automator',
					'component_action'  => 'uncannyautomator_bdb_notification',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
					'allow_duplicate'   => true,
				)
			);

			if ( is_wp_error( $notification_id ) ) {
				$action_data['complete_with_errors'] = true;
				Automator()->complete_action( $user_id, $action_data, $recipe_id, $notification_id->get_error_message() );
			} else {

				// Add the link
				if ( ! empty( $notification_link ) ) {
					$notification_content = '<a href="' . esc_attr( esc_url( $notification_link ) ) . '" title="' . esc_attr( wp_strip_all_tags( $notification_content ) ) . '">' . ( $notification_content ) . '</a>';
				}

				// Adding meta for notification display on front-end
				bp_notifications_update_meta( $notification_id, 'uo_notification_content', $notification_content );
				bp_notifications_update_meta( $notification_id, 'uo_notification_link', $notification_link );
				Automator()->complete_action( $user_id, $action_data, $recipe_id );
			}
		} else {
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'BuddyBoss message module is not active.', 'uncanny-automator-pro' ) );
		}
	}

	/**
	 * Filters active components with registered notifications callbacks.
	 *
	 * @param array $component_names Array of registered component names.
	 * @param array $active_components Array of active components.
	 *
	 * @since BuddyPress 1.9.1
	 *
	 */
	public function uo_bdb_component( $component_names, $active_components ) {

		$component_names = ! is_array( $component_names ) ? array() : $component_names;
		array_push( $component_names, 'uncanny-automator' );

		return $component_names;
	}

	/**
	 * Filters the notification content for notifications created by plugins.
	 * If your plugin extends the {@link BP_Component} class, you should use
	 * the
	 * 'notification_callback' parameter in your extended
	 * {@link BP_Component::setup_globals()} method instead.
	 *
	 * @param string $content Component action. Deprecated. Do not do checks
	 *     against this! Use the 6th parameter instead -
	 *     $component_action_name.
	 * @param int $item_id Notification item ID.
	 * @param int $secondary_item_id Notification secondary item ID.
	 * @param int $action_item_count Number of notifications with the same
	 *     action.
	 * @param string $format Format of return. Either 'string' or 'object'.
	 * @param string $component_action_name Canonical notification action.
	 * @param string $component_name Notification component ID.
	 * @param int $id Notification ID.
	 *
	 * @since BuddyPress 1.9.0
	 * @since BuddyPress 2.6.0 Added $component_action_name, $component_name,
	 *     $id as parameters.
	 *
	 * @return string|array If $format is 'string', return a string of the
	 *     notification content. If $format is 'object', return an array
	 *     formatted like: array( 'text' => 'CONTENT', 'link' => 'LINK' )
	 */
	public function uo_bdb_notification_content( $content, $item_id, $secondary_item_id, $action_item_count, $format, $component_action_name, $component_name, $id ) {

		if ( 'uncannyautomator_bdb_notification' === $component_action_name ) {

			$notification_content = bp_notifications_get_meta( $id, 'uo_notification_content' );
			$notification_link    = bp_notifications_get_meta( $id, 'uo_notification_link' );

			if ( 'string' === $format ) {
				return $notification_content;
			} elseif ( 'object' === $format ) {
				return array(
					'text' => $notification_content,
					'link' => $notification_link,
				);
			}
		}

		return $content;
	}

}
