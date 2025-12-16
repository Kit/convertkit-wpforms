<?php
/**
 * ConvertKit WPForms Admin Notices class.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * Add and remove persistent error messages across all
 * WordPress Administration screens.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */
class Integrate_ConvertKit_WPForms_Admin_Notices {

	/**
	 * Holds the class object.
	 *
	 * @since   1.8.9
	 *
	 * @var     object
	 */
	private static $instance;

	/**
	 * The key prefix to use for stored notices
	 *
	 * @since   1.8.9
	 *
	 * @var     string
	 */
	private $key_prefix = 'integrate-convertkit-wpforms-admin-notices';

	/**
	 * Register output function to display persistent notices
	 * in the WordPress Administration, if any exist.
	 *
	 * @since   1.8.9
	 */
	public function __construct() {

		add_action( 'admin_notices', array( $this, 'output' ) );

	}

	/**
	 * Output persistent notices in the WordPress Administration
	 *
	 * @since   1.8.9
	 */
	public function output() {

		// Don't output if we don't have the required capabilities to fix the issue.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Bail if no notices exist.
		$notices = get_option( $this->key_prefix );
		if ( ! $notices ) {
			return;
		}

		// Output notices.
		foreach ( $notices as $notice ) {
			switch ( $notice ) {
				case 'authorization_failed':
					$output = sprintf(
						'%s %s',
						esc_html__( 'Kit for WPForms: Authorization failed. Please', 'integrate-convertkit-wpforms' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=wpforms-settings&view=integrations' ) ),
							esc_html__( 'reconnect your Kit account.', 'integrate-convertkit-wpforms' )
						)
					);
					break;

				default:
					$output = '';

					/**
					 * Define the text to output in an admin error notice.
					 *
					 * @since   1.8.9
					 *
					 * @param   string  $notice     Admin notice name.
					 */
					$output = apply_filters( 'integrate_convertkit_wpforms_admin_notices_output_' . $notice, $output );
					break;
			}

			// If no output defined, skip.
			if ( empty( $output ) ) {
				continue;
			}
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo wp_kses(
						$output,
						wp_kses_allowed_html( 'post' )
					);
					?>
				</p>
			</div>
			<?php
		}

	}

	/**
	 * Add a persistent notice for output in the WordPress Administration.
	 *
	 * @since   1.8.9
	 *
	 * @param   string $notice     Notice name.
	 * @return  bool                Notice saved successfully
	 */
	public function add( $notice ) {

		// If no other persistent notices exist, add one now.
		if ( ! $this->exist() ) {
			return update_option( $this->key_prefix, array( $notice ) );
		}

		// Fetch existing persistent notices.
		$notices = $this->get();

		// Add notice to existing notices.
		$notices[] = $notice;

		// Remove any duplicate notices.
		$notices = array_values( array_unique( $notices ) );

		// Update and return.
		return update_option( $this->key_prefix, $notices );

	}

	/**
	 * Returns all notices stored in the options table.
	 *
	 * @since   1.8.9
	 *
	 * @return  array
	 */
	public function get() {

		// Fetch all notices from the options table.
		return get_option( $this->key_prefix );

	}

	/**
	 * Whether any persistent notices are stored in the option table.
	 *
	 * @since   1.8.9
	 *
	 * @return  bool
	 */
	public function exist() {

		if ( ! $this->get() ) {
			return false;
		}

		return true;

	}

	/**
	 * Delete all persistent notices.
	 *
	 * @since   1.8.9
	 *
	 * @param   string $notice     Notice name.
	 * @return  bool                Success
	 */
	public function delete( $notice ) {

		// If no persistent notices exist, there's nothing to delete.
		if ( ! $this->exist() ) {
			return false;
		}

		// Fetch existing persistent notices.
		$notices = $this->get();

		// Remove notice from existing notices.
		$index = array_search( $notice, $notices, true );
		if ( $index !== false ) {
			unset( $notices[ $index ] );
		}

		// Update and return.
		return update_option( $this->key_prefix, $notices );

	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @since   1.8.9
	 *
	 * @return  object Class.
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

}

new Integrate_ConvertKit_WPForms_Admin_Notices();
