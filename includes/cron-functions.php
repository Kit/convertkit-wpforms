<?php
/**
 * Integrate ConvertKit WPForms Cron functions.
 *
 * @package Integrate_ConvertKit_WPForms
 * @author Integrate_ConvertKit_WPForms
 */

/**
 * Refresh the OAuth access token, triggered by WordPress' Cron.
 *
 * @since   1.8.5
 *
 * @param   string $wpforms_provider_id   WPForms Provider ID, storing existing credentials.
 */
function integrate_convertkit_wpforms_refresh_token( $wpforms_provider_id ) {

	// Bail if WPForms is not loaded.
	if ( ! function_exists( 'wpforms_get_providers_options' ) ) {
		return;
	}

	// Get all registered providers in WPForms.
	$providers = wpforms_get_providers_options();

	// Bail if no ConvertKit providers were registered.
	if ( ! array_key_exists( 'convertkit', $providers ) ) {
		return;
	}

	// Bail if the connection doesn't exist in WPForms.
	if ( ! array_key_exists( $wpforms_provider_id, $providers['convertkit'] ) ) {
		return;
	}

	// Initialize the API.
	$api = new Integrate_ConvertKit_WPForms_API(
		INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
		INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI,
		$providers['convertkit'][ $wpforms_provider_id ]['access_token'],
		$providers['convertkit'][ $wpforms_provider_id ]['refresh_token']
	);

	// Refresh the token.
	// The convertkit_api_refresh_token action will be triggered, which is listened to
	// in functions.php to update the credentials in WPForms.
	$api->refresh_token();

}

// Register action to run above function; this action is created by WordPress' wp_schedule_event() function
// in update_credentials() in the ConvertKit_Settings class.
add_action( 'integrate_convertkit_wpforms_refresh_token', 'integrate_convertkit_wpforms_refresh_token' );
