<?php

namespace Uncanny_Automator_Pro;

/**
 * Class ANON_WP_COMMENTAPPROVED
 *
 * @package Uncanny_Automator_Pro
 */
class ANON_WP_COMMENTAPPROVED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

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
		$this->trigger_code = 'WPCOMMENTAPPROVED';
		$this->trigger_meta = 'COMMENTAPPROVED';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'is_pro'              => true,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( "A guest comment on a user's {{post:%1\$s}} is approved", 'uncanny-automator-pro' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( "A guest comment on a user's {{post}} is approved", 'uncanny-automator-pro' ),
			'action'              => 'transition_comment_status',
			'priority'            => 90,
			'accepted_args'       => 3,
			'type'                => 'anonymous',
			'validation_function' => array( $this, 'submitted_comment_approved' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$all_posts = Automator()->helpers->recipe->wp->options->all_posts( 'Post', $this->trigger_meta );

		$all_posts['relevant_tokens'][ $this->trigger_meta . '_COMMENTERNAME' ]    = esc_attr__( 'Commenter name', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens'][ $this->trigger_meta . '_COMMENTEREMAIL' ]   = esc_attr__( 'Commenter email', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens'][ $this->trigger_meta . '_COMMENTERWEBSITE' ] = esc_attr__( 'Commenter website', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens'][ $this->trigger_meta . '_COMMENT' ]          = esc_attr__( 'Comment content', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens']['POSTCOMMENTURL']                            = esc_attr__( 'Comment URL', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens']['POSTCOMMENTDATE']                           = esc_attr__( 'Comment submitted date', 'uncanny-automator-pro' );
		$all_posts['relevant_tokens']['POSTCOMMENTSTATUS']                         = esc_attr__( 'Comment status', 'uncanny-automator-pro' );

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array( $all_posts ),
			)
		);

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param \WP_Comment $comment Comment object.
	 */
	public function submitted_comment_approved( $new_status, $old_status, $comment ) {

		if ( $old_status === $new_status || $new_status !== 'approved' ) {
			return;
		}
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where option is set to Any post / specific post
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $required_post[ $recipe_id ][ $trigger_id ] ) ||
					 $required_post[ $recipe_id ][ $trigger_id ] === $comment->comment_post_ID ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		//	If recipe matches
		if ( ! empty( $matched_recipe_ids ) ) {
			$user_id = get_current_user_id();
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'post_id'          => $comment->comment_post_ID,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['trigger_log_id'],
								'run_number'     => $result['args']['run_number'],
							);

							// Comment ID
							Automator()->db->token->save( 'comment_id', maybe_serialize( $comment->comment_ID ), $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}
}
