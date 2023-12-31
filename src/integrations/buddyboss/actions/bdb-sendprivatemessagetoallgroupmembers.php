<?php

namespace Uncanny_Automator_Pro;

/**
 * Class BDB_SENDPRIVATEMESSAGETOALLGROUPMEMBERS
 *
 * @package Uncanny_Automator_Pro
 */
class BDB_SENDPRIVATEMESSAGETOALLGROUPMEMBERS {

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
		$this->action_code = 'BDBSENDPRIVATEMESSAGETOALLGROUPMEMBERS';
		$this->action_meta = 'BDBGROUPS';

		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/buddyboss/' ),
			'is_pro'             => true,
			'requires_user'      => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyBoss */
			'sentence'           => sprintf( esc_attr__( 'Send a private message to all members of {{a group:%1$s}}', 'uncanny-automator-pro' ), $this->action_meta ),
			/* translators: Action - BuddyBoss */
			'select_option_name' => esc_attr__( 'Send a private message to all members of {{a group}}', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'send_message_to_members' ),
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
						Automator()->helpers->recipe->buddyboss->options->all_buddyboss_users( esc_attr__( 'Sender user', 'uncanny-automator-pro' ), 'BDBFROMUSER' ),
						Automator()->helpers->recipe->buddyboss->options->all_buddyboss_groups(
							esc_attr__( 'Group', 'uncanny-automator-pro' ),
							'BDBGROUPS',
							array(
								'status' => array(
									'public',
									'private',
									'hidden',
								),
							)
						),
						Automator()->helpers->recipe->field->text_field( 'SENDPMGROUPBDBSUBJECT', esc_attr__( 'Message subject', 'uncanny-automator-pro' ), true, 'text', '', false ),
						Automator()->helpers->recipe->field->text_field( 'BDBMESSAGE', esc_attr__( 'Message content', 'uncanny-automator-pro' ), true, 'textarea' ),
					),
				),
			)
		);
	}

	/**
	 * Send a private message
	 *
	 * @param string $user_id
	 * @param array $action_data
	 * @param string $recipe_id
	 *
	 * @since 1.1
	 * @return void
	 */
	public function send_message_to_members( $user_id, $action_data, $recipe_id, $args ) {

		$sender_id       = absint( Automator()->parse->text( $action_data['meta']['BDBFROMUSER'], $recipe_id, $user_id, $args ) );
		$group_id        = absint( Automator()->parse->text( $action_data['meta']['BDBGROUPS'], $recipe_id, $user_id, $args ) );
		$subject         = Automator()->parse->text( $action_data['meta']['SENDPMGROUPBDBSUBJECT'], $recipe_id, $user_id, $args );
		$subject         = do_shortcode( $subject );
		$message_content = $action_data['meta']['BDBMESSAGE'];
		$message_content = Automator()->parse->text( $message_content, $recipe_id, $user_id, $args );
		$message_content = do_shortcode( $message_content );
		$members_ids     = array();

		if ( function_exists( 'groups_get_group_members' ) ) {
			$members = groups_get_group_members(
				array(
					'group_id'       => $group_id,
					'per_page'       => - 1,
					'type'           => 'last_joined',
					'exclude_banned' => true,
				)
			);

			if ( isset( $members['members'] ) && count( $members['members'] ) ) {

				foreach ( $members['members'] as $member ) {
					array_push( $members_ids, absint( $member->ID ) );
				}

				// Attempt to send the message.
				$msg = array(
					'sender_id'  => $sender_id,
					'recipients' => $members_ids,
					'subject'    => $subject,
					'content'    => $message_content,
					'error_type' => 'wp_error',
				);

				if ( function_exists( 'messages_new_message' ) ) {
					$send = messages_new_message( $msg );
					if ( is_wp_error( $send ) ) {
						$messages = $send->get_error_messages();
						$err      = array();
						if ( $messages ) {
							foreach ( $messages as $msg ) {
								$err[] = $msg;
							}
						}
						$action_data['complete_with_errors'] = true;
						Automator()->complete_action( $user_id, $action_data, $recipe_id, join( ', ', $err ) );
					} else {
						Automator()->complete_action( $user_id, $action_data, $recipe_id );
					}
				}
			} else {
				$action_data['complete_with_errors'] = true;
				Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'No members found in this group.', 'uncanny-automator-pro' ) );
			}
		} else {
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'BuddyBoss message module is not active.', 'uncanny-automator-pro' ) );
		}

	}

}
