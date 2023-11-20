<?php
namespace Uncanny_Automator_Pro\Loops\Filter\Base;

use WP_Error;

abstract class Loop_Filter {

	/**
	 * Prevents the child classes from accidentally overwriting this property.
	 *
	 * @var mixed[]
	 */
	private $fields = array();

	/**
	 * Prevents child class from accidentally overwriting this property.
	 *
	 * @var int[]
	 */
	private $users = array();

	/**
	 * The parsed fields.
	 *
	 * @var mixed[]
	 */
	private $parsed_fields = array();

	/**
	 * The unique filter meta.
	 *
	 * @var string Defaults to null.
	 */
	protected $filter_meta = null;

	/**
	 * The integration code.
	 *
	 * @var string Defaults to null.
	 */
	protected $integration_code = null;

	/**
	 * The filter sentence.
	 *
	 * @var string
	 */
	protected $sentence = '';

	/**
	 * The dynamic sentence.
	 *
	 * @var string
	 */
	protected $sentence_readable = '';

	/**
	 * The users fetching function callback.
	 *
	 * @var callable
	 */
	protected $entities_fetching_func = null;

	/**
	 * The loop type. Defaults to "users".
	 *
	 * @var string
	 */
	protected $loop_type = 'users';

	/**
	 * Abstract method setup.
	 *
	 * Developers needs to overwrite this method to setup the filter object.
	 *
	 * @return void
	 */
	abstract public function setup();

	/**
	 * Setups the filter.
	 *
	 * @param int $filter_id Defaults to null.
	 * @param mixed[] $args Defaults to empty array,
	 *
	 * @return void
	 */
	final public function __construct( $filter_id = null, $args = array() ) {

		if ( ! empty( $filter_id ) && ! empty( $args ) ) {
			$this->parse_fields( $filter_id, $args );
		}

		$this->setup();

		automator_pro_loop_filters()->register_filter(
			array(
				'integration'       => $this->get_integration(),
				'loop_type'         => $this->get_loop_type(),
				'meta'              => $this->get_meta(),
				'sentence'          => $this->get_sentence(),
				'sentence_readable' => $this->get_sentence_readable(),
				'fields'            => $this->get_fields(),
			)
		);

	}

	/**
	 * Parses the fields value before they are read by set_users callable parameter.
	 *
	 * @param int $filter_id
	 * @param mixed[] $args The process args.
	 *
	 * @return self
	 */
	public function parse_fields( $filter_id, $args ) {

		$fields = get_post_meta( $filter_id, 'fields', true );

		if ( ! is_string( $fields ) ) {
			$fields = '';
		}

		$fields_arr = (array) json_decode( $fields, true );

		$parse_pairs = array();

		foreach ( $fields_arr as $code => $field ) {

			if ( ! is_array( $field ) ) {
				continue; // Skip
			}

			$parse_pairs[ $code ] = Automator()->parse->text(
				$field['value'],
				$args['recipe_id'],
				$args['user_id'],
				$args
			);

		}

		$this->parsed_fields = $parse_pairs;

		return $this;

	}

	/**
	 * Each filters belong to a specific integration.
	 *
	 * @param string $integration_code
	 *
	 * @return void
	 */
	public function set_integration( $integration_code ) {

		if ( empty( $integration_code ) ) {
			throw new \Exception( "Loop filter's integration code should not be empty", 400 );
		}

		$this->integration_code = $integration_code;

	}

	/**
	 * Sets the filter's sentence. Sentence are displayed during filters selection.
	 *
	 * @param string $sentence
	 *
	 * @return void
	 */
	public function set_sentence( $sentence = '' ) {

		if ( empty( $sentence ) ) {
			throw new \Exception( "Loop filter's sentence should not be empty", 400 );
		}

		$this->sentence = $sentence;
	}

	/**
	 * Sets the filter's readable sentence. Readable sentence are displayed when the field values are saved.
	 *
	 * @param string $sentence_readable
	 *
	 * @return void
	 */
	public function set_sentence_readable( $sentence_readable = '' ) {

		if ( empty( $sentence_readable ) ) {
			throw new \Exception( "Loop filter's readable sentence should not be empty", 400 );
		}

		$this->sentence_readable = $sentence_readable;
	}

	/**
	 * Sets the meta for a specific filter.
	 *
	 * @param string $filter_meta
	 *
	 * @return void
	 */
	public function set_meta( $filter_meta ) {

		if ( empty( $filter_meta ) ) {
			throw new \Exception( "Loop filter's meta should not be empty", 400 );
		}

		$this->filter_meta = $filter_meta;

	}

	/**
	 * @param callable $fields_callback
	 *
	 * @return void
	 */
	public function set_fields( callable $fields_callback ) {

		$fields = call_user_func_array( $fields_callback, array() );

		$this->fields = Automator()->utilities->keep_order_of_options( (array) $fields );

	}

	/**
	 * @param callable $entities_fetching_func
	 *
	 * @return true|WP_Error
	 */
	public function set_entities( callable $entities_fetching_func ) {

		if ( ! is_callable( $entities_fetching_func ) ) {
			return new WP_Error(
				421,
				'Argument 1 of the method set_entities expects a callable parameter.'
			);
		}

		$this->entities_fetching_func = $entities_fetching_func;

		return true;

	}

	/**
	 * @param string $loop_type
	 *
	 * @return true|WP_Error
	 */
	public function set_loop_type( $loop_type = 'users' ) {

		if ( empty( $loop_type ) ) {
			return new WP_Error(
				421,
				'Loop type must not be empty'
			);
		}

		$this->loop_type = $loop_type;

		return true;

	}

	/**
	 * @return string
	 */
	public function get_sentence() {
		return $this->sentence;
	}

	/**
	 * @return string
	 */
	public function get_sentence_readable() {
		return $this->sentence_readable;
	}

	/**
	 * @return string
	 */
	public function get_meta() {
		return $this->filter_meta;
	}

	/**
	 * @return mixed[]
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * @return int[]|WP_Error Returns an array of user IDs. Otherwise, returns WP_Error
	 */
	public function get_entities() {

		$result = call_user_func_array(
			$this->entities_fetching_func,
			array(
				'fields' => $this->parsed_fields,
			)
		);

		if ( ! is_array( $result ) ) {
			return new WP_Error(
				421,
				'Callback argument to the set_users method must return an array'
			);
		}

		$this->users = $result;

		return $this->users;

	}

	/**
	 * @return string
	 */
	public function get_integration() {
		return $this->integration_code;
	}

	/**
	 * @return string
	 */
	public function get_loop_type() {
		return $this->loop_type;
	}

}
