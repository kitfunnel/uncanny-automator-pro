<?php

namespace Uncanny_Automator_Pro;

/**
 * Class Copy_Recipe_Parts
 *
 * @package Uncanny_Automator
 */
class Copy_Recipe_Parts_Pro extends \Uncanny_Automator\Copy_Recipe_Parts {


	/**
	 * Copy_Recipe_Parts constructor.
	 */
	public function __construct() {

		// Copy recipe conditions
		add_action( 'automator_recipe_copy_action_conditions', array( $this, 'copy_conditions' ), 10, 3 );

		add_action( 'automator_recipe_duplicated', array( $this, 'copy_loops' ), 10, 2 );
	}

	/**
	 * Code moved from Free
	 *
	 * @param $content
	 * @param $post_id
	 * @param $new_post_id
	 *
	 * @return void
	 */
	public function copy_conditions( $content, $post_id = 0, $new_post_id = 0 ) {

		if ( empty( $content ) ) {
			return;
		}

		// decode into array/object
		$content = json_decode( $content );

		// Delete existing content
		delete_post_meta( $new_post_id, 'actions_conditions' );

		global $wpdb;

		foreach ( $content as $k => $condition ) {

			if ( ! isset( $condition->actions ) ) {
				continue;
			}

			$current_parent = $condition->parent_id;

			// If the current post type is uo-recipe, then update the parent
			// Possible post types are uo-recipe and uo-loops
			if ( 'uo-recipe' === get_post_type( $current_parent ) ) {
				$condition->parent_id = $new_post_id;
			}

			foreach ( $condition->actions as $kk => $action_id ) {
				// Use `automator_duplicated_from` meta to figure out the new action ID
				// Since the action conditions are stored at the Recipe level by the Action ID
				// We have to do a lookup based on the old Action ID and the new recipe post parent ID
				$qry = $wpdb->prepare(
					"SELECT pm.post_id
						FROM $wpdb->postmeta pm
						JOIN $wpdb->posts p
						ON p.ID = pm.post_id AND p.post_parent = %d
						WHERE pm.meta_value = %d
						AND pm.meta_key = %s;",
					$new_post_id,
					$action_id,
					'automator_duplicated_from'
				);

				$new_action_id = $wpdb->get_var( $qry ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( is_numeric( $new_action_id ) ) {
					//unset( $condition->actions[ $kk ] ); // Remove old action ID
					$condition->actions[ $kk ] = $new_action_id; // Add new action ID
				}
			}
		}

		$content = wp_json_encode( $content );

		// any remaining meta
		add_post_meta( $new_post_id, 'actions_conditions', $content );
	}

	/**
	 * @param $new_recipe_id
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function copy_loops( $new_recipe_id, $recipe_id ) {

		// Copy loops
		$recipe_loops = get_posts(
			array(
				'post_parent'    => $recipe_id,
				'post_type'      => 'uo-loop',
				'post_status'    => array( 'draft', 'publish' ),
				'order_by'       => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => '99999', //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);

		if ( empty( $recipe_loops ) ) {
			return;
		}

		$loop_id_mappable = array();

		foreach ( $recipe_loops as $loop ) {

			$old_loop_id = $loop->ID;

			$new_loop_id = $this->copy( $loop->ID, $new_recipe_id );

			$loop_filters = get_posts(
				array(
					'post_parent'    => $old_loop_id,
					'post_type'      => 'uo-loop-filter',
					'post_status'    => array( 'draft', 'publish' ),
					'order_by'       => 'ID',
					'order'          => 'ASC',
					'posts_per_page' => '99999', //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				)
			);

			$loop_id_mappable[ $old_loop_id ] = $new_loop_id;

			// Copy loop filters.
			foreach ( $loop_filters as $filter ) {
				$this->copy( $filter->ID, $new_loop_id );
			}

			// Copy loop actions.
			$loop_actions = get_posts(
				array(
					'post_parent'    => $old_loop_id,
					'post_type'      => 'uo-action',
					'post_status'    => array( 'draft', 'publish' ),
					'order_by'       => 'ID',
					'order'          => 'ASC',
					'posts_per_page' => '99999', //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				)
			);

			foreach ( $loop_actions as $action ) {
				$new_action_id = $this->copy( $action->ID, $new_loop_id );
			}

			// Update the recipe post meta.
			$loop_action_conditions = get_post_meta( $new_recipe_id, 'actions_conditions', true );
			$loop_action_conditions = json_decode( $loop_action_conditions );

			global $wpdb;

			foreach ( $loop_action_conditions as $key => $action_condition ) {

				// Let's replace the parent_id with the new loop ID
				if ( isset( $loop_id_mappable[ $action_condition->parent_id ] ) ) {
					$action_condition->parent_id = $loop_id_mappable[ $action_condition->parent_id ];
				}

				foreach ( $action_condition->actions as $kk => $action_id ) {
					// Use `automator_duplicated_from` meta to figure out the new action ID
					// Since the action conditions are stored at the Recipe level by the Action ID
					// We have to do a lookup based on the old Action ID and the new recipe post parent ID
					$qry = $wpdb->prepare(
						"SELECT pm.post_id
						FROM $wpdb->postmeta pm
						JOIN $wpdb->posts p
						ON p.ID = pm.post_id AND p.post_parent = %d
						WHERE pm.meta_value = %d
						AND pm.meta_key = %s;",
						$action_condition->parent_id,
						$action_id,
						'automator_duplicated_from'
					);

					$new_action_id = $wpdb->get_var( $qry ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( is_numeric( $new_action_id ) ) {
						//unset( $condition->actions[ $kk ] ); // Remove old action ID
						$action_condition->actions[ $kk ] = $new_action_id; // Add new action ID
					}
				}
			}

			update_post_meta( $new_recipe_id, 'actions_conditions', wp_json_encode( $loop_action_conditions ) );
		}
	}
}
