<?php

namespace Uncanny_Automator_Pro;

/**
 * Class WPJM_APPLICATIONRECEIVEDFORJOB
 *
 * @package Uncanny_Automator_Pro
 */
class WPJM_APPLICATIONRECEIVEDFORJOB {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPJM';

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
		$this->trigger_code = 'WPJMAPPLICATIONRECEIVED';
		$this->trigger_meta = 'WPJMSPECIFICJOB';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-job-manager/' ),
			'is_pro'              => true,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Job Manager */
			'sentence'            => sprintf( esc_attr__( 'An application is received for {{a job:%1$s}} of {{a specific type:%2$s}}', 'uncanny-automator-pro' ), $this->trigger_meta, 'WPJMSPECIFICJOBTYPE:' . $this->trigger_meta ),
			/* translators: Logged-in trigger - WP Job Manager */
			'select_option_name'  => esc_attr__( 'An application is received for {{a job}} of {{a specific type}}', 'uncanny-automator-pro' ),
			'action'              => 'new_job_application',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array(
				$this,
				'application_received_for_job',
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
				'options_group' => array(
					$this->trigger_meta => array(
						Automator()->helpers->recipe->wp_job_manager->options->list_wpjm_jobs(
							null,
							$this->trigger_meta,
							array(
								'token'        => false,
								'is_ajax'      => true,
								'target_field' => 'WPJMSPECIFICJOBTYPE',
								'endpoint'     => 'select_specific_job_type',
							)
						),
						/* translators: Noun */
						Automator()->helpers->recipe->field->select_field( 'WPJMSPECIFICJOBTYPE', esc_attr__( 'Job type', 'uncanny-automator' ) ),
					),
				),
			)
		);
	}

	/**
	 * @param $application_id
	 * @param $job_id
	 */
	public function application_received_for_job( $application_id, $job_id ) {
		if ( ! is_numeric( $application_id ) || ! is_numeric( $job_id ) ) {
			return;
		}

		$application = get_post( $application_id );
		$user_id     = $application->post_author;

		if ( 0 === absint( $user_id ) ) {
			return;
		}
		$type_ids  = array();
		$job       = get_post( $job_id );
		$job_terms = wpjm_get_the_job_types( $job );

		foreach ( $job_terms as $term ) {
			$type_ids[] = absint( $term->term_id );
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_job       = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_job_type  = Automator()->get->meta_from_recipes( $recipes, 'WPJMSPECIFICJOBTYPE' );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];

				if (
					(
						absint( $required_job[ $recipe_id ][ $trigger_id ] ) === absint( $job_id ) ||
						intval( '-1' ) === intval( $required_job[ $recipe_id ][ $trigger_id ] )
					)
					&&
					(
						intval( '-1' ) === intval( $required_job_type[ $recipe_id ][ $trigger_id ] ) ||
						in_array( absint( $required_job_type[ $recipe_id ][ $trigger_id ] ), $type_ids, true )
					)
				) {
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

							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['trigger_log_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = $this->trigger_meta;
							$trigger_meta['meta_value'] = maybe_serialize( wpjm_get_the_job_title( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = $this->trigger_meta . '_ID';
							$trigger_meta['meta_value'] = maybe_serialize( $job_id );
							Automator()->insert_trigger_meta( $trigger_meta );

							foreach ( $job_terms as $term ) {
								$name = $term->name;
							}

							// Get the job categories.
							$categories = Automator()->helpers->recipe->wp_job_manager->pro->get_job_categories( $job_id );

							// Insert categories as meta.
							if ( ! empty( $categories ) ) {
								$trigger_meta['meta_key']   = 'WPJMJOBCATEGORIES';
								$trigger_meta['meta_value'] = implode( ', ', $categories );
								Automator()->insert_trigger_meta( $trigger_meta );
							}

							$trigger_meta['meta_key']   = 'WPJMSPECIFICJOBTYPE';
							$trigger_meta['meta_value'] = maybe_serialize( $name );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBLOCATION';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_job_location( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBDESCRIPTION';
							$trigger_meta['meta_value'] = maybe_serialize( wpjm_get_the_job_description( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONOWNERID';
							$trigger_meta['meta_value'] = get_post_field( 'post_author', $job_id );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONCANDIDATEID';
							$trigger_meta['meta_value'] = get_post_meta( $application_id, '_candidate_user_id', true );
							Automator()->insert_trigger_meta( $trigger_meta );

							$method = get_the_job_application_method( $job_id );
							if ( ! empty( $method ) ) {
								if ( 'email' === $method->type ) {
									$method = $method->email;
								} elseif ( 'url' === $method->type ) {
									$method = $method->url;
								}
							}

							$trigger_meta['meta_key']   = 'WPJMJOBAPPURL';
							$trigger_meta['meta_value'] = maybe_serialize( $method );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBCOMPANYNAME';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_name( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBWEBSITE';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_website( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBTAGLINE';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_tagline( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBVIDEO';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_video( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBTWITTER';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_twitter( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBLOGOURL';
							$trigger_meta['meta_value'] = maybe_serialize( get_the_company_logo( $job_id ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							$author          = get_post_field( 'post_author', $job_id );
							$author_username = get_the_author_meta( 'user_login', $author );
							$author_fname    = get_the_author_meta( 'first_name', $author );
							$author_lname    = get_the_author_meta( 'last_name', $author );
							$author_email    = get_the_author_meta( 'user_email', $author );

							$trigger_meta['meta_key']   = 'WPJMJOBOWNERNAME';
							$trigger_meta['meta_value'] = maybe_serialize( $author_username );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBOWNEREMAIL';
							$trigger_meta['meta_value'] = maybe_serialize( $author_email );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBOWNERFIRSTNAME';
							$trigger_meta['meta_value'] = maybe_serialize( $author_fname );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBOWNERLASTNAME';
							$trigger_meta['meta_value'] = maybe_serialize( $author_lname );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONNAME';
							$trigger_meta['meta_value'] = maybe_serialize( $application->post_title );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONMESSAGE';
							$trigger_meta['meta_value'] = maybe_serialize( $application->post_content );
							Automator()->insert_trigger_meta( $trigger_meta );

							$attachment = get_post_meta( $application_id, '_attachment' );

							if ( ! empty( $attachment ) ) {
								$attachment = maybe_unserialize( $attachment );
							}

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONCV';
							$trigger_meta['meta_value'] = maybe_serialize( $attachment );
							Automator()->insert_trigger_meta( $trigger_meta );

							$candidate_email = get_post_meta( $application->ID, '_candidate_email', true );
							if ( empty( $candidate_email ) ) {
								$author = get_user_by( 'ID', $application->post_author );
								if ( $author instanceof \WP_User ) {
									$candidate_email = $author->user_email;
								}
							}

							$trigger_meta['meta_key']   = 'WPJMAPPLICATIONEMAIL';
							$trigger_meta['meta_value'] = maybe_serialize( $candidate_email );
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}
