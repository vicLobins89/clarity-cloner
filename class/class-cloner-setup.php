<?php
/**
 * This class initialises the plugin and does the setup legwork
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * This is a setup, it creates custom DB tables and settings pages
 */
class Cloner_Setup extends Cloner {
	/**
	 * Class constructor
	 */
	public function __construct( $plugin_file ) {
		// Init parent.
		parent::__construct();

		// Plugin activation hook.
		register_activation_hook( $plugin_file, [ $this, 'activate_plugin' ] );

		// Sets up network settings pages.
		add_action( 'network_admin_menu', [ $this, 'setup_admin_settings' ] );

		// Save options.
		add_action( 'network_admin_edit_cty-save', [ $this, 'save_admin_settings' ] );
	}

	/**
	 * Ensures required DB tables are setup on plugin activation
	 */
	public function activate_plugin() {
		// Only run if super admin.
		if ( ! is_super_admin( get_current_user_id() ) ) {
			$this->notices = [
				'error' => [
					'content'        => __( 'You do not have sufficient priveleges to use this plugin!', 'cty' ),
					'is-dismissable' => false,
				],
			];
			return;
		}

		// Do table check/create.
		if ( current_user_can( 'administrator' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$sql_create =
			'CREATE TABLE ' . $this->table_name . '(
				`rid` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`type` TEXT NOT NULL,
				`relationship` LONGTEXT NOT NULL
			);';

			maybe_create_table( $this->table_name, $sql_create );
		}
	}

	/**
	 * 'network_admin_menu' action hook callback
	 * Sets up an admin settings page
	 */
	public function setup_admin_settings() {
		add_menu_page(
			__( 'Clarity Cloner', 'cty' ),
			__( 'Clarity Cloner', 'cty' ),
			'manage_network_options',
			'cty-cloner',
			[ $this, 'cty_page_cb' ],
			'dashicons-networking'
		);

		// Display updated notice.
		if ( isset( $_GET['cloner-updated'] ) ) {
			$this->notices = [
				'success' => [
					'content'        => __( 'Settings updated!', 'cty' ),
					'is-dismissable' => true,
				],
			];
		}
	}

	/**
	 * Callback for add_menu_page function above
	 * Retrieves/renders/saves the options
	 */
	public function cty_page_cb() {
		// Get options/vars.
		$options       = get_site_option( 'cty_cloner_options' );
		$related_sites = isset( $options['related_sites'] ) ? $options['related_sites'] : [];
		$main_site     = isset( $options['main_site'] ) ? $options['main_site'] : '';
		$enabled_cpts  = isset( $options['enabled_cpts'] ) ? $options['enabled_cpts'] : [];
		$sites         = get_sites( [ 'fields' => 'ids' ] );
		$all_cpts      = $this->get_all_cpts( $sites );

		ob_start();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Options', 'cty' ); ?></h1>
			<form method="post" action="<?php echo add_query_arg( 'action', 'cty-save', 'edit.php' ) ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Main Site', 'cty' ); ?>
						</th>
						<td>
							<fieldset>
								<?php
								foreach ( $sites as $blog_id ) :
									$site_name = get_blog_details( $blog_id )->blogname;
									?>
									<label for="main_site_<?php echo esc_attr( $blog_id ); ?>">
										<input
											name="main_site"
											class="regular-text"
											type="radio"
											id="main_site_<?php echo esc_attr( $blog_id ); ?>"
											value="<?php echo esc_attr( $blog_id ); ?>"
											<?php checked( $blog_id, $main_site ); ?>
										/>
										<span><?php echo esc_html( $site_name ); ?></span>
									</label>
									<br>
									<?php
								endforeach;
								?>
								<p class="description"><?php esc_html_e( 'This site will be set as the "canonical" any time content is copied.', 'cty' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Related Sites', 'cty' ); ?></th>
						<td>
							<fieldset>
								<?php
								foreach ( $sites as $blog_id ) :
									$site_name = get_blog_details( $blog_id )->blogname;
									?>
									<label for="related_sites_<?php echo esc_attr( $blog_id ); ?>">
										<input
											name="related_sites[]"
											class="regular-text"
											type="checkbox"
											id="related_sites_<?php echo esc_attr( $blog_id ); ?>"
											value="<?php echo esc_attr( $blog_id ); ?>"
											<?php checked( in_array( $blog_id, $related_sites ) ); ?>
										/>
										<span><?php echo esc_html( $site_name ); ?></span>
									</label>
									<br>
									<?php
								endforeach;
								?>
								<p class="description"><?php esc_html_e( 'Choose the sites you want to be able to copy content to.', 'cty' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled Post Types', 'cty' ); ?></th>
						<td>
							<fieldset>
								<?php
								foreach ( $all_cpts as $slug => $label ) :
									?>
									<label for="enabled_cpts_<?php echo esc_attr( $slug ); ?>">
										<input
											name="enabled_cpts[]"
											class="regular-text"
											type="checkbox"
											id="enabled_cpts_<?php echo esc_attr( $slug ); ?>"
											value="<?php echo esc_attr( $slug ); ?>"
											<?php checked( in_array( $slug, $enabled_cpts ) ); ?>
										/>
										<span><?php echo esc_html( $label ); ?></span>
									</label>
									<br>
									<?php
								endforeach;
								?>
								<p class="description"><?php esc_html_e( 'Choose the post types you want to be able to copy to other sites.', 'cty' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		ob_flush();
	}

	/**
	 * 'network_admin_edit_cty-save' action hook callback
	 * Saves our plugin settings
	 */
	public function save_admin_settings() {
		// Nonce security check.
		check_admin_referer( $this->nonce_action );

		// Get POST values.
		$related_sites = isset( $_POST[ 'related_sites' ] ) ? array_map( 'sanitize_text_field', $_POST[ 'related_sites' ] ) : [];
		$main_site     = isset( $_POST[ 'main_site' ] ) ? sanitize_text_field( $_POST[ 'main_site' ] ) : '1';
		$enabled_cpts  = isset( $_POST[ 'enabled_cpts' ] ) ? array_map( 'sanitize_text_field', $_POST[ 'enabled_cpts' ] ) : [];

		// Update.
		$options = [
			'related_sites' => $related_sites,
			'main_site'     => $main_site,
			'enabled_cpts'  => $enabled_cpts,
		];
		update_site_option( 'cty_cloner_options', $options );

		// Redirect back to options page.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'           => 'cty-cloner',
					'cloner-updated' => true,
				],
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns registered, public CPTs for all sites
	 *
	 * @param array $sites array of sites IDs to get CPTs for.
	 */
	private function get_all_cpts( $sites = [] ) {
		if ( ! $sites ) {
			$sites = get_sites( [ 'fields' => 'ids' ] );
		}

		$cpts = [
			'post' => __( 'Post', 'cty' ),
			'page' => __( 'Page', 'cty' ),
		];
		foreach ( $sites as $blog_id ) {
			switch_to_blog( $blog_id );
			$args       = [
				'public'   => true,
				'_builtin' => false
			];
			$output     = 'objects';
			$post_types = get_post_types( $args, $output );

			foreach ( $post_types as $slug => $object ) {
				if ( ! isset( $cpts[ $slug ] ) ) {
					$cpts[ $slug ] = $object->labels->singular_name;
				}
			}
			restore_current_blog();
		}

		return $cpts;
	}
}