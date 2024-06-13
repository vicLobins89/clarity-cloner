<?php
/**
 * This class creates required metaboxes in edit screens
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * This is a setup class which instantiates everything else required for the plugin to work
 */
class Cloner_Metaboxes extends Cloner {
	/**
	 * Contains array of all related sites.
	 * @var array
	 */
	private $related_sites;

	/**
	 * Contains blog ID for the 'main' or canonical site.
	 * @var int
	 */
	private $main_site;

	/**
	 * Contains array of all enabled post types.
	 * @var array
	 */
	private $enabled_cpts;

	/**
	 * Contains current blog ID.
	 * @var int
	 */
	private $current_site;

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Only initialise on enabled sites/post types.
		add_action( 'admin_init', [ $this, 'initialise_meta_box' ] );

		// Ajax callback to process the cloner actions.
		add_action( 'wp_ajax_cty-cloner', [ $this, 'process_cloner_actions' ] );
		add_action( 'wp_ajax_cty-cloner-search', [ $this, 'process_cloner_actions' ] );
	}

	/**
	 * 'admin_init' action hook callback
	 * Checks if site and post type is enabled
	 */
	public function initialise_meta_box() {
		// Get options.
		$options = get_site_option( 'cty_cloner_options' );
		if ( ! $options || empty( $options ) ) {
			return;
		}

		// Setup class props.
		$this->related_sites = isset( $options['related_sites'] ) ? $options['related_sites'] : [];
		$this->enabled_cpts  = isset( $options['enabled_cpts'] ) ? $options['enabled_cpts'] : [];
		if (
			! $this->related_sites ||
			! $this->enabled_cpts
		) {
			return;
		}

		// If site is enabled.
		$this->current_site = get_current_blog_id();
		if ( in_array( $this->current_site, $this->related_sites ) ) {
			// Enqueue scripts.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Add meta boxes.
			add_action( 'add_meta_boxes', [ $this, 'add_cloner_meta_box' ] );
		}
	}

	/**
	 * 'admin_enqueue_scripts' action hook callback
	 * Enqueues required scripts
	 *
	 * @param string $hook hook suffix for the current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		$allowed = [ 'post.php', 'post-new.php' ];
		if ( ! in_array( $hook, $allowed ) ) {
			return;
		}

		// Scripts.
		$data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce_action ),
		];
		wp_register_script( 'cty_cloner_script', CTY_CLONER_URI . '/js/scripts.js', [], '1.0', true );
		wp_localize_script( 'cty_cloner_script', 'cty_cloner', $data );
		wp_enqueue_script( 'cty_cloner_script' );
	}

	/**
	 * 'wp_ajax_cty-cloner' action hook callback
	 * Processes ajax data and triggers required cloner actions i.e. copy/overwrite/unlink
	 */
	public function process_cloner_actions() {
		// Verify nonce.
		$this->verify_nonce();

		// Setup response array.
		$response['success'] = false;

		if ( isset( $_REQUEST['data'] ) && isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
			$data   = json_decode( wp_unslash( $_REQUEST['data'] ), true );

			if ( $action === 'cty-cloner-search' ) {
				$searched_posts      = $this->search_posts( $data );
				$response['success'] = true;
				$response['posts']   = $searched_posts;
			} else {
				foreach ( $data as &$metabox ) {
					$metabox = $this->do_metabox_action( $metabox );
				}
	
				$response['success'] = true;
				$response['data']    = $data;
			}
		} else {
			$response['message'] = __( 'Data not provided.', 'cty' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * 'add_meta_boxes' action hook callback
	 * Adds meta boxes for each enabled post type and related site.
	 */
	public function add_cloner_meta_box() {
		// Loop through enabled post types and add meta boxes for each.
		foreach ( $this->enabled_cpts as $screen ) {
			// Loop through related sites and add 1 panel for each.
			foreach ( $this->related_sites as $blog_id ) {
				// If on current site, skip meta box - we don't need to clone a post to itself.
				if ( intval( $blog_id ) === $this->current_site ) {
					continue;
				}

				$site_name = get_blog_details( $blog_id )->blogname;
				$cb_args   = [
					'blog_id' => intval( $blog_id ),
				];
				add_meta_box(
					'cty_cloner_id_' . $blog_id,
					__( 'Clone to', 'cty' ) . ': ' . $site_name,
					[ $this, 'cloner_meta_box_cb' ],
					$screen,
					'advanced',
					'default',
					$cb_args
				);
			}
		}
	}

	/**
	 * Callback for 'add_meta_box'
	 * Renders HTML for meta boxes
	 *
	 * @param WP_Post $post the current post object.
	 * @param array   $args additional variables set up above.
	 */
	public function cloner_meta_box_cb( $post, $args ) {
		$rel_args     = [
			'source-post' => $post->ID,
			'source-blog' => $this->current_site,
			'target-blog' => $args['args']['blog_id'],
			'target-post' => false,
		];
		
		// Create/update relationship fields.
		$rel_obj      = new Cloner_Relationship( $rel_args );
		$rel_array    = $rel_obj->get_relationship();
		if ( $rel_array ) {
			$relationship = $rel_array['relationship'];
			$key          = array_key_first( $relationship );
			if ( isset( $relationship[ $key ][ $rel_args['target-blog'] ] ) ) {
				$rel_args['target-post'] = $relationship[ $key ][ $rel_args['target-blog'] ];
			}
		}
		
		// Render HTML.
		$this->render_meta_box( $rel_args );
	}

	/**
	 * Renders the markup HTML for a meta box with given arguments
	 *
	 * @param array $args source/target args for a relationship.
	 * @param bool  $echo whether to echo or return markup.
	 */
	private function render_meta_box( $args, $echo = true ) {
		ob_start();
		?>
		<div
			class="cty-meta-box"
			data-source-blog="<?php echo esc_attr( $args['source-blog'] ); ?>"
			data-source-post="<?php echo esc_attr( $args['source-post'] ); ?>"
			data-target-blog="<?php echo esc_attr( $args['target-blog'] ); ?>"
		>
			<?php
			if ( ! $args['target-post'] ) :
				?>
				<p><strong><?php esc_html_e( 'Related post not found', 'cty' ); ?></strong></p>

				<div class="cty-meta-box__inner">
					<label for="cloner_action_ignore_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							id="cloner_action_ignore_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="ignore"
							checked
						/>
						<span><?php esc_html_e( 'Keep post not related', 'cty' ); ?></span>
					</label>
				</div>

				<div class="cty-meta-box__inner">
					<label for="cloner_action_copy_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							id="cloner_action_copy_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="copy"
						/>
						<span><?php esc_html_e( 'Create new post', 'cty' ); ?></span>
					</label>
				</div>

				<div class="cty-meta-box__inner">
					<label for="cloner_action_link_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							class="cloner_action_link"
							id="cloner_action_link_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="link"
						/>
						<span><?php esc_html_e( 'Link existing post', 'cty' ); ?></span>
					</label>
				</div>

				<div class="cty-search" style="display:none;margin-top:1rem;">
					<input type="search" name="cty_link_search" id="cty_link_search_<?php echo esc_attr( $args['target-blog'] ); ?>" placeholder="<?php esc_html_e( 'Search...', 'cty' ); ?>">
					
					<div class="cty-search__inner" style="margin-top:1rem;"></div>
				</div>
				<?php
			else :
				?>
				<p>
					<strong><?php esc_html_e( 'Related post', 'cty' ); ?>:</strong>
					<?php
					$blog_post     = $this->get_blog_post( $args['target-post'], $args['target-blog'] );
					$post_statuses = get_post_statuses();
					$post_status   = $blog_post->post_status === 'trash' ? 
						__( 'Bin', 'cty' ) : 
						$post_statuses[ $blog_post->post_status ];
					printf(
						'<a href="%s" target="_blank">%s</a> <span>(%s: %s)</span>',
						$blog_post->post_edit_link,
						$blog_post->post_title,
						$post_status,
						$blog_post->post_date_gmt
					);
					?>
				</p>

				<input
					type="hidden"
					name="cloner_target_post[<?php echo esc_attr( $args['target-blog'] ); ?>]"
					value="<?php echo esc_attr( $args['target-post'] ); ?>"
				/>

				<div class="cty-meta-box__inner">
					<label for="cloner_action_ignore_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							id="cloner_action_ignore_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="ignore"
							checked
						/>
						<span><?php esc_html_e( 'Do not change related post', 'cty' ); ?></span>
					</label>
				</div>

				<div class="cty-meta-box__inner">
					<label for="cloner_action_overwrite_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							id="cloner_action_overwrite_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="overwrite"
						/>
						<span><?php esc_html_e( 'Overwrite related post', 'cty' ); ?></span>
					</label>
				</div>
				
				<div class="cty-meta-box__inner">
					<label for="cloner_action_unlink_<?php echo esc_attr( $args['target-blog'] ); ?>">
						<input
							type="radio"
							name="cloner_action[<?php echo esc_attr( $args['target-blog'] ); ?>]"
							id="cloner_action_unlink_<?php echo esc_attr( $args['target-blog'] ); ?>"
							value="unlink"
						/>
						<span><?php esc_html_e( 'Unlink related post', 'cty' ); ?></span>
					</label>
				</div>
				<?php
			endif;
			?>
		</div>
		<?php
		if ( $echo ) {
			ob_flush();
		} else {
			return ob_get_clean();
		}
	}

	/**
	 * Helper function to search for target blog posts in order to link existing
	 *
	 * @param array $data source/target vars to search.
	 */
	private function search_posts( $data ) {
		if ( ! $data['targetBlog'] || ! $data['searchTerm'] ) {
			return null;
		}

		// Switch to target blog and search for post.
		$post_list = '';
		switch_to_blog( $data['targetBlog'] );

		$query_args = [
			'post_type'      => get_post_type( $data['sourcePost'] ),
			'posts_per_page' => 20,
			'post_status'    => 'any',
			's'              => $data['searchTerm'],
			'fields'         => 'ids',
		];

		$post_query = new \WP_Query( $query_args );

		if ( $post_query->have_posts() ) {
			foreach ( $post_query->posts as $post_id ) {
				$post_list .= $this->render_post_item( $post_id, $data['targetBlog'] );
			}
		}

		// Restore and return.
		restore_current_blog();
		return $post_list;
	}

	/**
	 * Helper function to perform required metabox actions
	 *
	 * @param array $metabox the ajax fields set from a meta box.
	 */
	private function do_metabox_action( $metabox ) {
		// Create relationship object to run updates to it.
		$rel_obj = new Cloner_Relationship( $metabox );
		$copier  = new Cloner_Copier( $metabox );

		$action = $metabox['cloner-action'] ?? 'ignore';
		switch ( $action ) {
			case 'copy':
			case 'overwrite':
				$metabox['target-post'] = $copier->copy_post();
				$rel_obj->update_props( $metabox );
				$rel_obj->add_target_post();
				break;
			case 'link':
				$rel_obj->update_props( $metabox );
				$rel_obj->add_target_post();
				break;
			case 'unlink':
				$rel_obj->remove_target_post();
				$metabox['target-post'] = false;
				$rel_obj->update_props( $metabox );
				break;
			default:
				// Do nothing.
				return $metabox;
		}

		// Add HTML for XHR return.
		$metabox['html'] = $this->render_meta_box( $metabox, false );

		return $metabox;
	}

	/**
	 * Renderer for simple <li> post list items
	 *
	 * @param int $post_id the post to render.
	 * @param int $target_blog needed for input field name.
	 */
	private function render_post_item( $post_id, $target_blog ) {
		$title = get_the_title( $post_id );
		ob_start();
		?>
		<label class="cty-search__item">
			<input
				type="radio"
				name="cloner_target_post[<?php echo esc_attr( $target_blog ); ?>]"
				value="<?php echo esc_attr( $post_id ); ?>"
			/>
			<span><?php echo esc_html( $title ); ?> (<?php echo esc_attr( $post_id ); ?>)</span>
		</label>
		<br>
		<?php
		return ob_get_clean();
	}
}