<?php
/**
 * ConvertKit API class for WPForms.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * ConvertKit API class for WPForms.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */
class Integrate_ConvertKit_WPForms_API extends ConvertKit_API_V4 {

	/**
	 * Holds the log class for writing to the log file
	 *
	 * @var bool|ConvertKit_Log
	 */
	public $log = false;

	/**
	 * Holds an array of error messages, localized to the plugin
	 * using this API class.
	 *
	 * @var bool|array
	 */
	public $error_messages = false;

	/**
	 * Sets up the API with the required credentials.
	 *
	 * @since   1.4.1
	 *
	 * @param   string      $client_id         OAuth Client ID.
	 * @param   string      $redirect_uri      OAuth Redirect URI.
	 * @param   bool|string $access_token      ConvertKit OAuth Access Token.
	 * @param   bool|string $refresh_token     ConvertKit OAuth Refresh Token.
	 * @param   bool|object $debug             Save data to log.
	 * @param   bool|string $context           Context of originating request.
	 */
	public function __construct( $client_id, $redirect_uri, $access_token = false, $refresh_token = false, $debug = false, $context = false ) {

		// Set API credentials, debugging and logging class.
		$this->client_id      = $client_id;
		$this->redirect_uri   = $redirect_uri;
		$this->access_token   = $access_token;
		$this->refresh_token  = $refresh_token;
		$this->debug          = $debug;
		$this->context        = $context;
		$this->plugin_name    = ( defined( 'INTEGRATE_CONVERTKIT_WPFORMS_NAME' ) ? INTEGRATE_CONVERTKIT_WPFORMS_NAME : false );
		$this->plugin_path    = ( defined( 'INTEGRATE_CONVERTKIT_WPFORMS_PATH' ) ? INTEGRATE_CONVERTKIT_WPFORMS_PATH : false );
		$this->plugin_url     = ( defined( 'INTEGRATE_CONVERTKIT_WPFORMS_URL' ) ? INTEGRATE_CONVERTKIT_WPFORMS_URL : false );
		$this->plugin_version = ( defined( 'INTEGRATE_CONVERTKIT_WPFORMS_VERSION' ) ? INTEGRATE_CONVERTKIT_WPFORMS_VERSION : false );

		// Setup logging class if the required parameters exist.
		if ( $this->debug && $this->plugin_path !== false ) {
			$this->log = new ConvertKit_Log( $this->plugin_path );
		}

		// Define translatable / localized error strings.
		// WordPress requires that the text domain be a string (e.g. 'integrate-convertkit-wpforms') and not a variable,
		// otherwise localization won't work.
		$this->error_messages = array(
			'form_subscribe_form_id_empty'                 => __( 'form_subscribe(): the form_id parameter is empty.', 'integrate-convertkit-wpforms' ),
			'form_subscribe_email_empty'                   => __( 'form_subscribe(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),

			'sequence_subscribe_sequence_id_empty'         => __( 'sequence_subscribe(): the sequence_id parameter is empty.', 'integrate-convertkit-wpforms' ),
			'sequence_subscribe_email_empty'               => __( 'sequence_subscribe(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),

			'tag_subscribe_tag_id_empty'                   => __( 'tag_subscribe(): the tag_id parameter is empty.', 'integrate-convertkit-wpforms' ),
			'tag_subscribe_email_empty'                    => __( 'tag_subscribe(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),

			'get_subscriber_by_email_email_empty'          => __( 'get_subscriber_by_email(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),
			/* translators: Email Address */
			'get_subscriber_by_email_none'                 => __( 'get_subscriber_by_email(): No subscriber(s) exist in Kit matching the email address %s.', 'integrate-convertkit-wpforms' ),

			'get_subscriber_by_id_subscriber_id_empty'     => __( 'get_subscriber_by_id(): the subscriber_id parameter is empty.', 'integrate-convertkit-wpforms' ),

			'get_subscriber_tags_subscriber_id_empty'      => __( 'get_subscriber_tags(): the subscriber_id parameter is empty.', 'integrate-convertkit-wpforms' ),

			'unsubscribe_email_empty'                      => __( 'unsubscribe(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),

			'broadcast_delete_broadcast_id_empty'          => __( 'broadcast_delete(): the broadcast_id parameter is empty.', 'integrate-convertkit-wpforms' ),

			'get_all_posts_posts_per_request_bound_too_low' => __( 'get_all_posts(): the posts_per_request parameter must be equal to or greater than 1.', 'integrate-convertkit-wpforms' ),
			'get_all_posts_posts_per_request_bound_too_high' => __( 'get_all_posts(): the posts_per_request parameter must be equal to or less than 50.', 'integrate-convertkit-wpforms' ),

			'get_posts_page_parameter_bound_too_low'       => __( 'get_posts(): the page parameter must be equal to or greater than 1.', 'integrate-convertkit-wpforms' ),
			'get_posts_per_page_parameter_bound_too_low'   => __( 'get_posts(): the per_page parameter must be equal to or greater than 1.', 'integrate-convertkit-wpforms' ),
			'get_posts_per_page_parameter_bound_too_high'  => __( 'get_posts(): the per_page parameter must be equal to or less than 50.', 'integrate-convertkit-wpforms' ),

			'subscriber_authentication_send_code_email_empty' => __( 'subscriber_authentication_send_code(): the email parameter is empty.', 'integrate-convertkit-wpforms' ),
			'subscriber_authentication_send_code_redirect_url_empty' => __( 'subscriber_authentication_send_code(): the redirect_url parameter is empty.', 'integrate-convertkit-wpforms' ),
			'subscriber_authentication_send_code_redirect_url_invalid' => __( 'subscriber_authentication_send_code(): the redirect_url parameter is not a valid URL.', 'integrate-convertkit-wpforms' ),
			'subscriber_authentication_send_code_response_token_missing' => __( 'subscriber_authentication_send_code(): the token parameter is missing from the API response.', 'integrate-convertkit-wpforms' ),

			'subscriber_authentication_verify_token_empty' => __( 'subscriber_authentication_verify(): the token parameter is empty.', 'integrate-convertkit-wpforms' ),
			'subscriber_authentication_verify_subscriber_code_empty' => __( 'subscriber_authentication_verify(): the subscriber_code parameter is empty.', 'integrate-convertkit-wpforms' ),
			'subscriber_authentication_verify_response_error' => __( 'The entered code is invalid. Please try again, or click the link sent in the email.', 'integrate-convertkit-wpforms' ),

			'profiles_signed_subscriber_id_empty'          => __( 'profiles(): the signed_subscriber_id parameter is empty.', 'integrate-convertkit-wpforms' ),

			/* translators: HTTP method */
			'request_method_unsupported'                   => __( 'API request method %s is not supported in ConvertKit_API class.', 'integrate-convertkit-wpforms' ),
			'request_rate_limit_exceeded'                  => __( 'Kit API Error: Rate limit hit.', 'integrate-convertkit-wpforms' ),
			'request_internal_server_error'                => __( 'Kit API Error: Internal server error.', 'integrate-convertkit-wpforms' ),
			'request_bad_gateway'                          => __( 'Kit API Error: Bad gateway.', 'integrate-convertkit-wpforms' ),
			'response_type_unexpected'                     => __( 'Kit API Error: The response is not of the expected type array.', 'integrate-convertkit-wpforms' ),
		);

	}

	/**
	 * Returns the access token.
	 *
	 * @since   1.8.9
	 *
	 * @return  string
	 */
	public function access_token() {

		return $this->access_token;

	}

}
