<?php

namespace Uncanny_Automator_Pro;

/**
 * Class LD_MARKTOPICNOTCOMPLETE
 *
 * @package Uncanny_Automator_Pro
 */
class LD_MARKTOPICNOTCOMPLETE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $action_code;
	private $action_meta;
	private $action_integration;
	private $quiz_list;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MARKTOPICNOTCOMPLETE';
		$this->action_meta = 'LDTOPIC';
		$this->define_action();
	}


	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'is_pro'             => true,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( __( 'Mark {{a topic:%1$s}} not complete for the user', 'uncanny-automator-pro' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => __( 'Mark {{a topic}} not complete for the user', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mark_not_complete_a_topic' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$course_options = Automator()->helpers->recipe->options->wp_query( $args, false, __( 'Any course', 'uncanny-automator' ) );

		$args = array(
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$lesson_options = Automator()->helpers->recipe->options->wp_query( $args, false, __( 'Any lesson', 'uncanny-automator' ) );

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->select_field_ajax(
							'LDCOURSE',
							__( 'Course', 'uncanny-automator' ),
							$course_options,
							'',
							'',
							false,
							true,
							array(
								'target_field' => 'LDLESSON',
								'endpoint'     => 'select_lesson_from_course_MARKTOPICNOTCOMPLETE',
							)
						),
						Automator()->helpers->recipe->field->select_field_ajax(
							'LDLESSON',
							__( 'Lesson', 'uncanny-automator' ),
							$lesson_options,
							'',
							'',
							false,
							true,
							array(
								'target_field' => 'LDTOPIC',
								'endpoint'     => 'select_topic_from_lesson_MARKTOPICNOTCOMPLETE',
							)
						),
						Automator()->helpers->recipe->field->select_field( 'LDTOPIC', __( 'Topic', 'uncanny-automator' ) ),
					),
				),
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function mark_not_complete_a_topic( $user_id, $action_data, $recipe_id, $args ) {

		$topic_id = $action_data['meta'][ $this->action_meta ];

		//Mark complete a lesson
		$course_id = learndash_get_course_id( $topic_id );

		//Mark complete a topic quiz
		$topic_quiz_list = learndash_get_lesson_quiz_list( $topic_id, $user_id, $course_id );
		if ( $topic_quiz_list ) {
			foreach ( $topic_quiz_list as $ql ) {
				$this->quiz_list[ $ql['post']->ID ] = 0;
			}
		}

		$this->mark_quiz_incomplete( $user_id, $course_id );

		learndash_process_mark_incomplete( $user_id, $course_id, $topic_id, false );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}


	/**
	 * @param      $user_id
	 * @param null $course_id
	 */
	public function mark_quiz_incomplete( $user_id, $course_id = null ) {

		if ( ! empty( $this->quiz_list ) ) {
			foreach ( $this->quiz_list as $quiz_id => $quiz ) {
				learndash_delete_quiz_progress( $user_id, $quiz_id );
			}
		}
	}

}
