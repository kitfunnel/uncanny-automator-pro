<?php

namespace Uncanny_Automator_Pro;

use Groundhogg\DB\Tags;
use Groundhogg\Plugin;

/**
 * Class GH_USER_HAS_A_TAG
 *
 * @package Uncanny_Automator_Pro
 */
class GH_USER_HAS_A_TAG extends Action_Condition {

	/**
	 * Define_condition
	 *
	 * @return void
	 */
	public function define_condition() {

		$this->integration = 'GH';
		/*translators: Token */
		$this->name = __( 'A user has {{a tag}}', 'uncanny-automator-pro' );
		$this->code = 'USER_HAS_TAG';
		// translators: A token matches a value
		$this->dynamic_name  = sprintf( esc_html__( 'A user has {{a tag:%1$s}}', 'uncanny-automator-pro' ), 'GH_TAGS' );
		$this->is_pro        = true;
		$this->requires_user = true;
	}

	/**
	 * Fields
	 *
	 * @return array
	 */
	public function fields() {
		$tags_field_args = array(
			'option_code'           => 'GH_TAGS',
			'label'                 => esc_html__( 'Tag', 'uncanny-automator-pro' ),
			'required'              => true,
			'options'               => $this->gh_tags_options(),
			'supports_custom_value' => true,
		);

		return array(
			// Tag field
			$this->field->select_field_args( $tags_field_args ),
		);
	}

	/**
	 * gh_tags_options
	 *
	 * @return array[]
	 */
	public function gh_tags_options() {
		if ( ! class_exists( '\Groundhogg\DB\Tags' ) ) {
			return array();
		}
		$tags     = new Tags();
		$all_tags = array();
		foreach ( $tags->get_tags() as $tag ) {
			$all_tags[] = array(
				'value' => $tag->tag_id,
				'text'  => $tag->tag_name,
			);
		}

		return $all_tags;
	}

	/**
	 * Evaluate_condition
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function evaluate_condition() {
		$parsed_tag      = $this->get_parsed_option( 'GH_TAGS' );
		$contact_has_tag = Plugin::$instance->utils->get_contact( absint( $this->user_id ), true )->has_tag( $parsed_tag );
		// Check if the user has a tag here
		if ( false === (bool) $contact_has_tag ) {
			$message = sprintf( __( 'User does not have the following tag: %s', 'uncanny-automator-pro' ), $this->get_option( 'GH_TAGS_readable' ) );
			$this->condition_failed( $message );
		}
	}
}
