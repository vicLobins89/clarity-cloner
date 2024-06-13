<?php
/**
 * This class allows us to create and manipulate relatiships between sites and related posts
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * Creates object instance for a relationship
 */
class Cloner_Relationship extends Cloner {
	/**
	 * Contains source post ID.
	 * @var int
	 */
	private $source_post = false;

	/**
	 * Contains source blog ID.
	 * @var int
	 */
	private $source_blog = false;

	/**
	 * Contains target blog ID.
	 * @var int
	 */
	private $target_blog = false;

	/**
	 * Contains target blog ID.
	 * @var int
	 */
	private $target_post = false;

	/**
	 * Class constructor
	 *
	 * @param array  $args source or target variables.
	 */
	public function __construct( $args = [] ) {
		// Init parent.
		parent::__construct();

		// Set up props.
		$this->update_props( $args );
	}

	/**
	 * Updates object props.
	 *
	 * @param array $args source or target variables.
	 */
	public function update_props( $args ) {
		$this->source_post = $args['source-post'] ?? false;
		$this->source_blog = $args['source-blog'] ?? false;
		$this->target_blog = $args['target-blog'] ?? false;
		$this->target_post = $args['target-post'] ?? false;
	}

	/**
	 * Gets Relationship ID from a given blog ID
	 *
	 * @param int $post_id the post for which to get the RID.
	 * @param int $blog_id which site to get the RID from.
	 */
	private function get_rid( $post_id, $blog_id ) {
		// Make sure were on the correct site.
		$is_switched = false;
		if ( intval( $blog_id ) !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$is_switched = true;
		}

		// Get Relationship ID.
		$rid = get_post_meta( $post_id, '_rid', true );
		if ( ! $rid ) {
			return;
		}

		// Restore blog.
		if ( $is_switched ) {
			restore_current_blog();
		}

		return $rid;
	}

	/**
	 * Returns relationship array for given post and blog IDs
	 */
	public function get_relationship( $source_post = false, $source_blog = false ) {
		// Initial var checks.
		if ( ! $source_post && $this->source_post ) {
			$source_post = $this->source_post;
		}

		if ( ! $source_blog && $this->source_blog ) {
			$source_blog = $this->source_blog;
		}

		if ( ! $source_post || ! $source_blog ) {
			return;
		}

		$relationship = [];

		// Get Relationship ID.
		$rid = $this->get_rid( $source_post, $source_blog );
		if ( ! $rid ) {
			return $relationship;
		}

		// Get relationship array.
		global $wpdb;
		$sql    = "SELECT `relationship` FROM {$this->table_name} WHERE `rid` = %d";
		$sql    = $wpdb->prepare( $sql, $rid );
		$result = $wpdb->get_var( $sql );
		if ( $result ) {
			$relationship = [
				'rid'          => $rid,
				'relationship' => maybe_unserialize( $result )
			];
		}

		return $relationship;
	}

	/**
	 * Updates current relationship or creates new if not set.
	 *
	 * @param int $main_site make sure whatever site is set as main is set as primary key.
	 */
	public function add_target_post() {
		if (
			! $this->source_post || 
			! $this->source_blog ||
			! $this->target_post ||
			! $this->target_blog
		) {
			return;
		}

		// Get relationship.
		global $wpdb;
		$rel_array = $this->get_relationship( $this->source_post, $this->source_blog );

		if ( $rel_array ) {
			// Update.
			$rid          = $rel_array['rid'];
			$relationship = $rel_array['relationship'];
			$key          = array_key_first( $relationship );

			// Add target post.
			if ( ! isset( $relationship[ $key ][ $this->target_blog ] ) ) {
				$relationship[ $key ][ $this->target_blog ] = $this->target_post;
			}

			// Update row.
			$sql    = "UPDATE {$this->table_name} SET `relationship` = %s WHERE `rid` = %d";
			$sql    = $wpdb->prepare( $sql, maybe_serialize( $relationship ), $rid );
			$result = $wpdb->query( $sql );
		} else {
			// Create.
			$key          = $this->source_blog . ':' . $this->source_post;
			$relationship = [
				$key => [
					$this->source_blog => $this->source_post,
					$this->target_blog => $this->target_post,
				],
			];

			// Create row.
			$sql    = "INSERT INTO {$this->table_name} (`type`, `relationship`) VALUES (%s, %s)";
			$sql    = $wpdb->prepare( $sql, 'post', maybe_serialize( $relationship ) );
			$result = $wpdb->query( $sql );
			$rid    = $wpdb->insert_id;
		}

		// Update post meta.
		if ( $rid ) {
			update_post_meta( $this->source_post, '_rid', $rid );
			switch_to_blog( $this->target_blog );
			update_post_meta( $this->target_post, '_rid', $rid );
			restore_current_blog();
		}
	}

	/**
	 * Removes target post from relationship array
	 */
	public function remove_target_post() {
		if ( ! $this->target_post || ! $this->target_blog ) {
			return;
		}

		// Get rid/relationship array.
		$rel_array = $this->get_relationship( $this->target_post, $this->target_blog );
		if ( ! $rel_array ) {
			return;
		}
		
		// Remove from array.
		$rid          = $rel_array['rid'];
		$relationship = $rel_array['relationship'];
		$key          = array_key_first( $relationship );
		unset( $relationship[ $key ][ $this->target_blog ] );
		
		// Re-save array.
		global $wpdb;
		$sql    = "UPDATE {$this->table_name} SET `relationship` = %s WHERE `rid` = %d";
		$sql    = $wpdb->prepare( $sql, maybe_serialize( $relationship ), $rid );
		$result = $wpdb->query( $sql );

		// Update post meta.
		switch_to_blog( $this->target_blog );
		delete_post_meta( $this->target_post, '_rid' );
		restore_current_blog();
	}
}