<?php
/**
 * This is an abstract wrapper class which contain some inherited props and methods
 *
 * @package cty_cloner
 */

namespace cty_cloner;

/**
 * Wrapper class init
 */
abstract class Cloner {
	/**
	 * Contains the wpdb table name.
	 * @var string
	 */
	protected $table_name;

	/**
	 * Associative array of notices to be displayed on admin_notices hook.
	 * @var array
	 */
	protected $notices;

	/**
	 * Nonce action string
	 *
	 * @var string $nonce_action
	 */
	protected $nonce_action = 'cty_cloner_nonce_action';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Set table name var.
		global $wpdb;
		$this->table_name = $wpdb->base_prefix . 'relationships';

		// Setup notices action.
		add_action( 'admin_notices', [ $this, 'display_notice' ] );
		add_action( 'network_admin_notices', [ $this, 'display_notice' ] );
	}

	/**
	 * Displays a WP admin notice
	 */
	public function display_notice() {
		if ( ! $this->notices ) {
			return;
		}

		ob_start();
		foreach ( $this->notices as $type => $notice ) {
			$dismissable = $notice['is-dismissable'] ?? false;
			$classes  = 'notice notice-' . $type;
			$classes .= $dismissable ? ' is-dismissible' : '';
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<p><?php echo esc_html( $notice['content'] ); ?></p>
			</div>
			<?php
		}

		echo wp_kses_post( ob_get_clean() );
	}

	/**
	 * Verifies nonce in ajax call and returns json response
	 */
	protected function verify_nonce() {
		// phpcs:ignore
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], $this->nonce_action ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Invalid request. Unable to verify nonce.', 'cty' ),
			];
			echo wp_json_encode( $response );
			wp_die();
		}
	}

	/**
	 * Return post object from target site
	 *
	 * @param int $post_id the post to get.
	 * @param int $blog_id which site to get it from.
	 */
	public function get_blog_post( $post_id, $blog_id ) {
		switch_to_blog( $blog_id );
		$post_object                 = get_post( $post_id );
		$post_object->permalink      = get_permalink( $post_object );
		$post_object->post_edit_link = get_edit_post_link( $post_object );
		$post_object                 = apply_filters( 'cty_cloner_post', $post_object );
		restore_current_blog();
		return $post_object;
	}

	/**
	 * Debugger
	 *
	 * @param mixed  $data any data we want to debug.
	 * @param string $type 'display' or 'log' the results.
	 * @param bool   $exit do we want to exit the function.
	 */
	public function debug( $data, $type = 'display', $exit = false ) {
		if ( empty( $data ) ) {
			return;
		}

		if ( $type === 'display' ) {
			echo '<pre>';
			var_dump( $data );
			echo '</pre>';
		} else {
			error_log( 'CTY_CLONER: ' . print_r( $data, true ) );
		}

		if ( $exit ) {
			exit();
		}
	}
}