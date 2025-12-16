<?php
/**
 * ConvertKit Resource class.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * Abstract class defining variables and functions for a ConvertKit API Resource
 * (forms, sequences, tags).
 *
 * @since   1.7.0
 */
class Integrate_ConvertKit_WPForms_Resource extends ConvertKit_Resource_V4 {

	/**
	 * The API class
	 *
	 * @var     bool|Integrate_ConvertKit_WPForms_API
	 */
	public $api = false;

	/**
	 * Constructor.
	 *
	 * @since   1.7.0
	 *
	 * @param   Integrate_ConvertKit_WPForms_API $api_instance   API Instance.
	 * @param   string                           $account_id     WPForms Account ID.
	 */
	public function __construct( $api_instance, $account_id = '' ) {

		// Initialize the API using the supplied Integrate_ConvertKit_WPForms_API instance.
		$this->api = $api_instance;

		// Append the account ID to the settings key, so that multiple connections can each
		// have their own cached resources specific to that account.
		if ( $account_id ) {
			$this->settings_name .= '_' . $account_id;
		}

		// Get last query time and existing resources.
		$this->last_queried = get_option( $this->settings_name . '_last_queried' );
		$this->resources    = get_option( $this->settings_name );

	}

	/**
	 * Fetches resources (custom fields, forms, sequences or tags) from the API, storing them in the options table
	 * with a last queried timestamp.
	 *
	 * If the refresh results in a 401, removes the access and refresh tokens from the connection.
	 *
	 * @since   1.8.9
	 *
	 * @return  WP_Error|array
	 */
	public function refresh() {

		// Call parent refresh method.
		$result = parent::refresh();

		// If an error occured, maybe delete credentials from the Plugin's settings
		// if the error is a 401 unauthorized.
		if ( is_wp_error( $result ) ) {
			integrate_convertkit_wpforms_maybe_delete_credentials( $result, INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID, $this->api->access_token() );
		}

		return $result;

	}

}
