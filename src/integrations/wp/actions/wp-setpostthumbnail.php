<?php

namespace Uncanny_Automator_Pro;

use Uncanny_Automator\Recipe;

/**
 * Class WP_SETPOSTTHUMBNAIL
 *
 * @package Uncanny_Automator
 */
class WP_SETPOSTTHUMBNAIL {

	use Recipe\Actions;

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'WP' );

		$this->set_action_code( 'WP_SETPOSTTHUMBNAIL' );

		$this->set_action_meta( 'WP_SETPOSTTHUMBNAIL_META' );

		$this->set_requires_user( false );

		$this->set_is_pro( true );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Set the featured image of {{a post:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Set the featured image of {{a post}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_action();

	}

	/**
	 * Load options.
	 *
	 * @return void
	 */
	public function load_options() {

		$options = array(

			'options_group' => array(

				$this->get_action_meta() => array(

					Automator()->helpers->recipe->wp->options->pro->all_wp_post_types(
						__( 'Post type', 'uncanny-automator-pro' ),
						'WPSPOSTTYPES',
						array(
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->get_action_meta(),
							'is_any'       => false,
							'endpoint'     => 'select_all_post_of_selected_post_type_no_all',
						)
					),

					Automator()->helpers->recipe->field->select_field( $this->get_action_meta(), __( 'Post', 'uncanny-automator-pro' ) ),
					array(
						'input_type'     => 'text',
						'option_code'    => 'MEDIA_ID',
						'required'       => true,
						'supports_token' => true,
						'label'          => esc_html__( 'Media library ID/URL', 'uncanny-automator' ),
						'description'    => esc_html__( 'Enter the ID or URL of an image in the media library. If the item does not exist in the media library, the action will not run.' ),
					),

				),

			),

		);

		return Automator()->utilities->keep_order_of_options( $options );

	}


	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$media = isset( $parsed['MEDIA_ID'] ) ? sanitize_text_field( $parsed['MEDIA_ID'] ) : '';

		$post_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		try {

			$this->set_media( $media, $post_id );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

			return;

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Set the Post Thumbnail using Media URL or Media ID.
	 *
	 * @param mixed $media The Media ID or the Media URL.
	 * @param int $post_id
	 *
	 * @return Boolean True on success. Otherwise, throws Exception.
	 *
	 * @throws Exception
	 */
	protected function set_media( $media = '', $post_id = 0 ) {

		if ( ! is_numeric( $media ) ) {
			// Convert to ID if non-numeric.
			$media = attachment_url_to_postid( $media, $post_id );
		}

		return $this->set_media_using_id( $media, $post_id );

	}


	/**
	 * Set the Post Thumbnail using Media ID.
	 *
	 * @param int $media_id
	 * @param int $post_id
	 *
	 * @return Boolean True on success. Otherwise, throws Exception.
	 *
	 * @throws Exception
	 */
	protected function set_media_using_id( $media_id = 0, $post_id = 0 ) {

		// Complete with error if media is not found.
		if ( empty( wp_get_attachment_image_src( $media_id ) ) ) {

			throw new \Exception( sprintf( 'Error: Cannot find media object using the Media ID: %d', $media_id ), 400 );

		}

		// Try setting the post thumbnail.
		$post_thumbnail = set_post_thumbnail( $post_id, $media_id );

		// Complete with error if there are any issues.
		if ( false === $post_thumbnail ) {

			throw new \Exception( 'The function `set_post_thumbnail` has returned false. The media is already assigned to the post or there was an unexpected error.', 400 );

		}

		return true;

	}

}
