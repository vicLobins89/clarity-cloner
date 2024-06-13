<?php
/**
 * This is a worker class designed to copy an entire post from one subsite to another.
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * Creates object instance for a post copier
 */
final class Cloner_Copier extends Cloner {
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
	 * @param array $args source/target variables needed for cloning posts.
	 */
	public function __construct( $args ) {
		$this->source_post = $args['source-post'] ?? false;
		$this->source_blog = $args['source-blog'] ?? false;
		$this->target_post = $args['target-post'] ?? false;
		$this->target_blog = $args['target-blog'] ?? false;
	}

	/**
	 * Copy post action
	 */
	public function copy_post() {
		if (
			! isset( $this->source_blog ) ||
			! isset( $this->source_post ) ||
			! isset( $this->target_blog )
		) {
			return false;
		}

		// Collect source post data.
		$source_post       = get_post( $this->source_post );
		$source_meta       = get_post_custom( $this->source_post );
		$source_thumb_url  = get_the_post_thumbnail_url( $this->source_post, 'full' );
		$source_tax        = get_object_taxonomies( [ 'post_type' => $source_post->post_type ] );
		$source_taxonomies = [];
		foreach ( $source_tax as $tax ) {
			$source_taxonomies[ $tax ] = wp_get_object_terms( $this->source_post, $tax, [ 'fields' => 'names' ] );
		}

		// Start target post array to insert.
		$post_args = [];
		
		// If target post is set, add ID to overwrite.
		if ( isset( $this->target_post ) ) {
			$post_args['ID'] = $this->target_post;
		}
		
		// Loop through allowed source post args and add to new args.
		$exclude = [ 'ID', 'guid' ];
		foreach ( $source_post as $key => $value ) {
			if ( ! in_array( $key, $exclude ) && ! isset( $post_args[ $key ] ) ) {
				$post_args[ $key ] = $value;
			}
		}
		
		// Swith to target blog.
		switch_to_blog( $this->target_blog );

		// Cleanup post content.
		$post_args['post_content'] = $this->cleanup_post_content( $post_args['post_content'] );

		// Add post.
		$target_post_id = wp_insert_post( $post_args );

		// Upload and save featured image.
		if ( $source_thumb_url ) {
			$this->upload_image( $source_thumb_url, $target_post_id, true );
		}

		// Save post meta.
		$this->cleanup_post_meta( $source_meta, $target_post_id );

		// Save taxonomies.
		if ( ! empty( $source_taxonomies ) ) {
			foreach ( $source_taxonomies as $tax => $terms ) {
				wp_set_object_terms( $target_post_id, $terms, $tax );
			}
		}

		// Restore blog.
		restore_current_blog();

		return $target_post_id;
	}

	/**
	 * This function takes the post content and prepares it for inserting into a new post
	 * - check ACF blocks and fix references to images/files
	 *
	 * @param string $content the post_content string.
	 */
	private function cleanup_post_content( $content ) {
		$content_clean = $content;
		
		// Scans content for ACF JSON strings and re-links images/posts.
		$content_clean = $this->copy_acf_blocks( $content_clean );

		return $content_clean;
	}

	/**
	 * Helper function to parse each block in post content and handle image/file upload
	 *
	 * @param string $content the full post content string.
	 */
	private function copy_acf_blocks( $content ) {
		// This matches and isolates all blocks in the content and pushes to an array.
		$regex = '/<!-- wp:acf.* {(.*)} \/?-->/';
		preg_match_all( $regex, $content, $matches );

		// $matches[1] is the 2nd grouping in our regex. ie the json array of ACF data.
		if ( empty( $matches[1] ) ) {
			return $content;
		}

		foreach ( $matches[1] as $match ) {
			// Parse each matched json string.
			$json_string = '{' . $match . '}';
			$parsed_data = json_decode( $json_string, true );

			if ( isset( $parsed_data['data'] ) ) {
				foreach ( $parsed_data['data'] as $key => $value ) {
					if ( substr( $key, 0, 1 ) === '_' ) {
						// Get the type of ACF field.
						$acf_field = get_field_object( $value );
						$old_value = $parsed_data['data'][ ltrim( $key, '_' ) ];
						$new_value = $old_value;

						switch ( $acf_field['type'] ) {
							case 'file':
							case 'image':
								// Upload and return ID.
								if ( is_numeric( $old_value ) ) {
									// Get GUID of post attachment.
									$db_args   = [
										'select' => 'guid',
										'from'   => 'posts',
										'where'  => [
											'ID'        => $old_value,
											'post_type' => 'attachment',
										],
									];
									$image_url = $this->return_db_var( $db_args, $this->source_blog );
									$new_value = $this->upload_image( $image_url );
								}
								break;
						}

						$parsed_data['data'][ ltrim( $key, '_' ) ] = $new_value;
					}
				}
			}

			// Recode data to JSON.
			$encoded_string = wp_json_encode( $parsed_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
			$encoded_string = addslashes( $encoded_string );
			$encoded_string = ltrim( $encoded_string, '{' );
			$encoded_string = rtrim( $encoded_string, '}' );

			// Replace every match with new string.
			$content = str_replace( $match, $encoded_string, $content );
		}

		return $content;
	}

	/**
	 * Checks post meta for ACF image/file fields and reuploads to target site
	 *
	 * @param array $source_meta all post meta values.
	 * @param int   $target_post_id where to save the post meta.
	 */
	private function cleanup_post_meta( $source_meta, $target_post_id ) {
		// We've already done the featured image, so unset.
		unset( $source_meta['_thumbnail_id'] );

		// Loop through all meta keys/values.
		foreach ( $source_meta as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				// Return if ACF is not enabled.
				if ( ! function_exists( 'get_field_object' ) ) {
					continue;
				}

				// Check if meta is ACF post object and upload file.
				if ( is_numeric( $meta_value ) && $meta_value > 0 ) {
					// Switch back to source site and check if this meta is an ACF object.
					switch_to_blog( $this->source_blog );

					$acf_object = get_field_object( $meta_key, $this->source_post );
					if ( $acf_object ) {
						if ( in_array( $acf_object['type'], [ 'file', 'image' ], true ) ) {
							$meta_post = get_post( $meta_value );
							if ( ! empty( $meta_post ) && $meta_post->post_type === 'attachment' ) {
								$attachment_url = wp_get_attachment_url( $meta_post->ID );
							}
						}
					}

					// Restore.
					restore_current_blog();

					// Upload file and return ID.
					if ( isset( $attachment_url ) ) {
						$meta_value = $this->upload_image( $attachment_url, $target_post_id );
					}
				}

				// Copy over meta to target.
				$meta_update = update_post_meta( $target_post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Helper to use wpdb return a field.
	 *
	 * @param array  $args option to help fetch the field.
	 * @param int    $blog_id which blog to fetch from.
	 * @param string $output the type of output to return (OBJECT, ARRAY_A, ARRAY_N).
	 */
	private function return_db_var( array $args, $blog_id = 1, $output = '' ) {
		global $wpdb;

		// Bail out.
		if ( ! $args ) {
			return;
		}

		$from_string  = $wpdb->get_blog_prefix( $blog_id ) . $args['from'];
		$where_string = "WHERE "; // phpcs:ignore
		$count        = 0;
		foreach ( $args['where'] as $key => $value ) {
			if ( $count > 0 ) {
				$where_string .= ' AND ';
			}
			$where_string .= "{$key} = '{$value}'";
			$count++;
		}

		$query = "SELECT {$args['select']} FROM {$from_string} {$where_string}";

		if ( ! $output ) {
			return $wpdb->get_var( $query ); // phpcs:ignore
		} else {
			return $wpdb->get_row( $query, $output ); // phpcs:ignore
		}
	}

	/**
	 * Function to upload image using a filepath
	 *
	 * @param string $image the full url of new image to upload.
	 * @param int    $parent_id the post id to attach the image to.
	 * @param bool   $is_featured do we want to make the image a featured image of the post.
	 * @param bool   $return_url return the URL of the image not the ID.
	 */
	public function upload_image( $image, $parent_id = 0, $is_featured = false, $return_url = false ) {
		// Remove any parameters from URL.
		$image = strtok( $image, '?' );

		// Firstly check if there is already an attachment for the image.
		$attachments = get_posts(
			[
				'post_type'   => 'attachment',
				'numberposts' => 1,
				'meta_key'    => '_cty_original_file_src',
				'meta_value'  => $image,
			]
		);

		if ( ! empty( $attachments ) ) {
			// Attachment for the image already exists, so set the attachment ID.
			$attach_id = $attachments[0]->ID;
		} else {
			// Check if the img url is the same as the site domain and see if it already has an attachment.
			$site_url = str_replace( [ 'http://', 'https://', 'www.' ], '', home_url() );
			if ( strpos( $image, $site_url ) !== false ) {
				$attach_id = attachment_url_to_postid( $image );
			}
		}

		// If no attach ID found, download the file and set meta data etc.
		if ( ! isset( $attach_id ) || ! $attach_id || $attach_id === 0 ) {
			$get      = wp_remote_request( $image );
			$type     = wp_remote_retrieve_header( $get, 'content-type' );
			$modified = wp_remote_retrieve_header( $get, 'last-modified' );
			$status   = wp_remote_retrieve_response_code( $get );

			// Return false is there's no type, if the type is .exe or if the file is 404.
			if ( ! $type || strpos( $type, 'exe' ) === true || $status == '404' ) {
				return false;
			}

			// Try to get filename from headers.
			$file_string = wp_remote_retrieve_header( $get, 'content-disposition' );
			$regex       = '/(?<=filename=").*(?=")/';
			preg_match( $regex, $file_string, $matches );

			if ( ! empty( $matches ) ) {
				$basename = $matches[0];
			} else {
				$basename = basename( $image );
			}

			$mirror = wp_upload_bits(
				$basename,
				null,
				wp_remote_retrieve_body( $get ),
				$modified ? gmdate( 'Y/m', strtotime( $modified ) ) : null
			);

			// Debug if error and return original image.
			if ( ! empty( $mirror['error'] ) ) {
				$this->debug( 'Upload error:', 'log' );
				$this->debug( $mirror['error'], 'log' );
				$this->debug( $image, 'log' );
				return $image;
			}

			$attachment = [
				'post_title'     => $basename,
				'post_mime_type' => $type,
				'post_status'    => 'inherit',
			];

			$attach_id = wp_insert_attachment( $attachment, $mirror['file'], $parent_id );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata( $attach_id, $mirror['file'] );

			if ( ! isset( $attach_data ) ) {
				$attach_data = wp_get_attachment_metadata( $attach_id );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}

			update_post_meta( $attach_id, '_cty_original_file_src', $image );
		}

		if ( $is_featured ) {
			set_post_thumbnail( $parent_id, $attach_id );
		}

		return $return_url ? wp_get_attachment_url( $attach_id ) : $attach_id;
	}
}