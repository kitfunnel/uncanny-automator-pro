<?php

namespace Uncanny_Automator_Pro;

use LLMS_Course;
use LLMS_Section;

/**
 * Class LF_MARKCOURSEDONE
 *
 * @package Uncanny_Automator_Pro
 */
class LF_MARKCOURSEDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LF';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'LFMARKCOURSEDONE-A';
		$this->action_meta = 'LFCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/lifterlms/' ),
			'is_pro'             => true,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LifterLMS */
			'sentence'           => sprintf( __( 'Mark {{a course:%1$s}} complete for the user', 'uncanny-automator-pro' ), $this->action_meta ),
			/* translators: Action - LifterLMS */
			'select_option_name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator-pro' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lf_mark_course_done' ),
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
				'options' => array(
					Automator()->helpers->recipe->lifterlms->options->all_lf_courses( __( 'Course', 'uncanny-automator-pro' ), $this->action_meta, false ),
				),
			)
		);
	}

	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function lf_mark_course_done( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! function_exists( 'llms_mark_complete' ) ) {
			$error_message = 'The function llms_mark_complete does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$course_id = $action_data['meta'][ $this->action_meta ];
		// Get all lessons of section.
		$course   = new LLMS_Course( $course_id );
		$sections = $course->get_sections();

		if ( ! empty( $sections ) ) {
			foreach ( $sections as $section ) {
				// Get all lessons of section.
				$section = new LLMS_Section( $section->id );
				$lessons = $section->get_lessons();
				if ( ! empty( $lessons ) ) {
					foreach ( $lessons as $lesson ) {
						llms_mark_complete( $user_id, $lesson->id, 'lesson' );
					}
				}
				llms_mark_complete( $user_id, $section->id, 'section' );
			}
		}

		llms_mark_complete( $user_id, $course_id, 'course' );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
