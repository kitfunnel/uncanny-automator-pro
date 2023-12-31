<?php

namespace Uncanny_Automator_Pro;

/**
 * Class BDB_POSTATOPICREPLY
 *
 * @package Uncanny_Automator_Pro
 */
class BDB_POSTATOPICREPLY {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $action_code;
	private $action_meta;

	/**
	 * Set Triggers constructor.
	 */
	public function __construct() {
		$this->action_code = 'BDBPOSTATOPICREPLY';
		$this->action_meta = 'BDBFORUMTOPIC';

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
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyBoss */
			'sentence'           => sprintf( esc_attr__( 'Post a reply to {{a topic:%1$s}} in {{a forum:%2$s}}', 'uncanny-automator-pro' ), 'BDBTOPIC:' . $this->action_meta, $this->action_meta ),
			/* translators: Action - BuddyBoss */
			'select_option_name' => esc_attr__( 'Post a reply to {{a topic}} in {{a forum}}', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'post_a_forum_topic_reply' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		$this->set_action_tokens(
			array(
				'REPLY_ID'  => array(
					'name' => __( 'Reply ID', 'uncanny-automator-pro' ),
					'type' => 'int',
				),
				'REPLY_URL' => array(
					'name' => __( 'Reply URL', 'uncanny-automator-pro' ),
					'type' => 'url',
				),
			),
			$this->action_code
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		$bbp_forum_post_type = apply_filters( 'bbp_forum_post_type', 'forum' );
		$args                = array(
			'post_type'      => $bbp_forum_post_type,
			'posts_per_page' => 99999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'private' ),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->select_field_ajax(
							$this->action_meta,
							__( 'Forum', 'uncanny-automator-pro' ),
							Automator()->helpers->recipe->options->wp_query( $args ),
							'',
							'',
							false,
							true,
							array(
								'target_field' => 'BDBTOPIC',
								'endpoint'     => 'select_topic_from_forum_BDBTOPICREPLY_NOANY',
							)
						),
						Automator()->helpers->recipe->field->select_field( 'BDBTOPIC', __( 'Topic', 'uncanny-automator-pro' ), array(), false, false, false ),
						Automator()->helpers->recipe->field->text_field( 'BDBREPLYCONTENT', esc_attr__( 'Reply content', 'uncanny-automator-pro' ), true, 'textarea' ),
					),
				),
			)
		);
	}

	/**
	 * Remove from BP Groups
	 *
	 * @param string $user_id
	 * @param array $action_data
	 * @param string $recipe_id
	 *
	 * @return void
	 *
	 * @since 1.1
	 */
	public function post_a_forum_topic_reply( $user_id, $action_data, $recipe_id, $args ) {

		$forum_id       = Automator()->parse->text( $action_data['meta']['BDBFORUMTOPIC'], $recipe_id, $user_id, $args );
		$topic_id       = Automator()->parse->text( $action_data['meta']['BDBTOPIC'], $recipe_id, $user_id, $args );
		$topic_id       = do_shortcode( $topic_id );
		$reply_content  = Automator()->parse->text( $action_data['meta']['BDBREPLYCONTENT'], $recipe_id, $user_id, $args );
		$reply_content  = do_shortcode( $reply_content );
		$reply_author   = $user_id;
		$anonymous_data = 0;
		$reply_title    = '';
		$reply_to       = 0;

		if ( ! bbp_get_topic( $topic_id ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, Discussion does not exist.', 'uncanny-automator-pro' ) );

			return;
		}

		if ( ! bbp_get_forum( $forum_id ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, Forum does not exist.', 'uncanny-automator-pro' ) );

			return;
		}
		// Forum exists.
		if ( ! empty( $forum_id ) ) {

			// Forum is a category.
			if ( bbp_is_forum_category( $forum_id ) ) {
				$action_data['complete_with_errors'] = true;
				Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, This forum is a category. No discussions can be created in this forum.', 'uncanny-automator-pro' ) );

				return;

				// Forum is not a category.
			} else {

				// Forum is closed and user cannot access.
				if ( bbp_is_forum_closed( $forum_id ) && ! current_user_can( 'edit_forum', $forum_id ) ) {
					$action_data['complete_with_errors'] = true;
					Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, This forum has been closed to new discussions.', 'uncanny-automator-pro' ) );

					return;
				}

				/**
				 * Added logic for group forum
				 * Current user is part of that group or not.
				 * We need to check manually because bbpress updating that caps only on group forum page and
				 * in API those conditional tag will not work.
				 */
				$is_member = false;
				$group_ids = array();
				if ( function_exists( 'bbp_get_forum_group_ids' ) ) {
					$group_ids = bbp_get_forum_group_ids( $forum_id );
					if ( ! empty( $group_ids ) ) {
						foreach ( $group_ids as $group_id ) {
							if ( groups_is_user_member( $reply_author, $group_id ) ) {
								$is_member = true;
								break;
							}
						}
					}
				}

				// Forum is private and user cannot access.
				if ( bbp_is_forum_private( $forum_id ) && ! bbp_is_user_keymaster() ) {
					if (
						( empty( $group_ids ) && ! current_user_can( 'read_private_forums' ) )
						|| ( ! empty( $group_ids ) && ! $is_member )
					) {
						$action_data['complete_with_errors'] = true;
						Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, This forum is private and you do not have the capability to read or create new discussions in it.', 'uncanny-automator-pro' ) );

						return;
					}

					// Forum is hidden and user cannot access.
				} elseif ( bbp_is_forum_hidden( $forum_id ) && ! bbp_is_user_keymaster() ) {
					if (
						( empty( $group_ids ) && ! current_user_can( 'read_hidden_forums' ) )
						|| ( ! empty( $group_ids ) && ! $is_member )
					) {
						$action_data['complete_with_errors'] = true;
						Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, This forum is hidden and you do not have the capability to read or create new discussions in it.', 'uncanny-automator-pro' ) );

						return;
					}
				}
			}
		}

		/** Unfiltered HTML */
		// Remove kses filters from title and content for capable users and if the nonce is verified.
		if ( current_user_can( 'unfiltered_html' ) ) {
			remove_filter( 'bbp_new_reply_pre_title', 'wp_filter_kses' );
			remove_filter( 'bbp_new_reply_pre_content', 'bbp_encode_bad', 10 );
			remove_filter( 'bbp_new_reply_pre_content', 'bbp_filter_kses', 30 );
		}

		// Filter and sanitize.
		$reply_content = apply_filters( 'bbp_new_reply_pre_content', $reply_content );

		/** Reply Duplicate */
		if ( ! bbp_check_for_duplicate(
			array(
				'post_type'      => bbp_get_reply_post_type(),
				'post_author'    => $reply_author,
				'post_content'   => $reply_content,
				'post_parent'    => $topic_id,
				'anonymous_data' => $anonymous_data,
			)
		) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( "Duplicate reply detected; it looks as though you've already said that!", 'uncanny-automator-pro' ) );

			return;
		}

		/** Topic Closed */
		// If topic is closed, moderators can still reply.
		if ( bbp_is_topic_closed( $topic_id ) && ! current_user_can( 'moderate' ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, Discussion is closed.', 'uncanny-automator-pro' ) );

			return;
		}

		/** Reply Blacklist */
		if ( ! bbp_check_for_blacklist( $anonymous_data, $reply_author, $reply_title, $reply_content ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, Your reply cannot be created at this time.', 'uncanny-automator-pro' ) );

			return;
		}

		/** Reply Status */
		// Maybe put into moderation.
		if ( ! bbp_check_for_moderation( $anonymous_data, $reply_author, $reply_title, $reply_content ) ) {
			$reply_status = bbp_get_pending_status_id();
		} else {
			$reply_status = bbp_get_public_status_id();
		}

		/** Topic Closed */
		// If topic is closed, moderators can still reply.
		if ( bbp_is_topic_closed( $topic_id ) && ! current_user_can( 'moderate' ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'Sorry, Discussion is closed.', 'uncanny-automator-pro' ) );

			return;
		}

		/** No Errors */

		// Add the content of the form to $reply_data as an array.
		// Just in time manipulation of reply data before being created.
		$reply_data = apply_filters(
			'bbp_new_reply_pre_insert',
			array(
				'post_author'    => $reply_author,
				'post_title'     => $reply_title,
				'post_content'   => $reply_content,
				'post_status'    => $reply_status,
				'post_parent'    => $topic_id,
				'post_type'      => bbp_get_reply_post_type(),
				'comment_status' => 'closed',
				'menu_order'     => bbp_get_topic_reply_count( $topic_id, false ) + 1,
			)
		);

		// Insert reply.
		$reply_id = wp_insert_post( $reply_data );

		if ( empty( $reply_id ) || is_wp_error( $reply_id ) ) {
			$append_error = (
			( is_wp_error( $reply_id ) && $reply_id->get_error_message() )
				? __( 'The following problem(s) have been found with your reply: ', 'uncanny-automator-pro' ) . $reply_id->get_error_message()
				: __( 'We are facing a problem to creating a reply.', 'uncanny-automator-pro' )
			);

			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $append_error );

			return;
		}

		/** Trash Check */
		// If this reply starts as trash, add it to pre_trashed_replies.
		// for the topic, so it is properly restored.
		if ( bbp_is_topic_trash( $topic_id ) || ( bbp_get_trash_status_id() === $reply_data['post_status'] ) ) {

			// Trash the reply.
			wp_trash_post( $reply_id );

			// Only add to pre-trashed array if topic is trashed.
			if ( bbp_is_topic_trash( $topic_id ) ) {

				// Get pre_trashed_replies for topic.
				$pre_trashed_replies = (array) get_post_meta( $topic_id, '_bbp_pre_trashed_replies', true );

				// Add this reply to the end of the existing replies.
				$pre_trashed_replies[] = $reply_id;

				// Update the pre_trashed_reply post meta.
				update_post_meta( $topic_id, '_bbp_pre_trashed_replies', $pre_trashed_replies );
			}

			/** Spam Check */
			// If reply or topic are spam, officially spam this reply.
		} elseif ( bbp_is_topic_spam( $topic_id ) || ( bbp_get_spam_status_id() === $reply_data['post_status'] ) ) {
			add_post_meta( $reply_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );

			// Only add to pre-spammed array if topic is spam.
			if ( bbp_is_topic_spam( $topic_id ) ) {

				// Get pre_spammed_replies for topic.
				$pre_spammed_replies = (array) get_post_meta( $topic_id, '_bbp_pre_spammed_replies', true );

				// Add this reply to the end of the existing replies.
				$pre_spammed_replies[] = $reply_id;

				// Update the pre_spammed_replies post meta.
				update_post_meta( $topic_id, '_bbp_pre_spammed_replies', $pre_spammed_replies );
			}
		}

		/**
		 * Removed notification sent and called additionally.
		 * Due to we have moved all filters on title and content.
		 */
		remove_action( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11 );

		/** Update counts, etc... */
		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, false, $reply_to );

		/** Additional Actions (After Save) */
		do_action( 'bbp_new_reply_post_extras', $reply_id );

		$this->hydrate_tokens(
			array(
				'REPLY_ID'  => absint( $reply_id ),
				'REPLY_URL' => esc_url( get_the_permalink( $reply_id ) ),
			)
		);

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
