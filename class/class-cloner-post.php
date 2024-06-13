<?php
/**
 * This class contains functionality pertaining to a WP post object
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * Creates object instance for a post object and it's related functions
 */
class Cloner_Post extends Cloner {
	/**
	 * Class constructor
	 */
	public function __construct() {
		// Unlink a post if deleted.
		add_action( 'before_delete_post', [ $this, 'unlink_deleted' ], 10, 1 );

		// Changes post canonical URL.
		add_filter( 'get_canonical_url', [ $this, 'edit_canonical_url' ], 10, 2 );
		add_filter( 'wpseo_canonical', [ $this, 'edit_canonical_url' ], 10, 2 );
	}

	/**
	 * 'before_delete_post' action hook callback
	 * Unlinks deleted post
	 *
	 * @param int $post_id the ID of deleted post.
	 */
	public function unlink_deleted( $post_id ) {
		$rid = get_post_meta( $post_id, '_rid', true );
		if ( ! $rid ) {
			return;
		}
		
		$rel_args = [
			'target-post' => $post_id,
			'target-blog' => get_current_blog_id(),
		];
		$rel_obj = new Cloner_Relationship( $rel_args );
		$rel_obj->remove_target_post();
	}

	/**
	 * 'get_canonical_url' filter hook callback
	 * 'wpseo_canonical' filter hook callback
	 * Ensures the correct main site canonical URL is applied
	 *
	 * @param string  $url the original canonical URL.
	 * @param WP_Post $post the current post object.
	 */
	public function edit_canonical_url( $url, $post ) {
		// Ignore for admin.
		if ( is_admin() ) {
			return $url;
		}

		// Get post ID (different object for Yoast).
		$post_id = false;
		if ( $post instanceof WP_Post ) {
			$post_id = $post->ID;
		} elseif ( isset( $post->model->object_id ) ) {
			$post_id = $post->model->object_id;
		}
		
		// Early return if no ID.
		if ( ! $post_id ) {
			return $url;
		}

		// Check Yoast canonical and allow it to override a main site related post.
		$canonical = get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
		if ( $canonical ) {
			return $canonical;
		}

		// Get main site ID.
		$options   = get_site_option( 'cty_cloner_options' );
		$main_site = isset( $options['main_site'] ) ? intval( $options['main_site'] ) : false;
		if ( ! $main_site || $main_site === get_current_blog_id() ) {
			return $url;
		}

		// Check for relationships.
		$cloner    = new Cloner_Relationship();
		$rel_array = $cloner->get_relationship( $post_id, get_current_blog_id() );
		if ( ! $rel_array ) {
			return $url;
		}

		// Get permalink from original post.
		$relationship = $rel_array['relationship'];
		$key          = array_key_first( $relationship );
		if ( isset( $relationship[ $key ][ $main_site ] ) ) {
			$blog_post = $this->get_blog_post( $relationship[ $key ][ $main_site ], $main_site );
			$url       = $blog_post ? $blog_post->permalink : $url;
		}

		return $url;
	}
}