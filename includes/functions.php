<?php
/**
 * Integrate ConvertKit WPForms functions.
 *
 * @package Integrate_ConvertKit_WPForms
 * @author Integrate_ConvertKit_WPForms
 */

/**
 * Saves the new access token, refresh token and its expiry when the API
 * class automatically refreshes an outdated access token.
 *
 * @since   1.7.0
 *
 * @param   array  $result                  New Access Token, Refresh Token and Expiry.
 * @param   string $client_id               OAuth Client ID used for the Access and Refresh Tokens.
 * @param   string $previous_access_token   Existing (expired) Access Token.
 */
function integrate_convertkit_wpforms_maybe_update_credentials( $result, $client_id, $previous_access_token = '' ) {

	// Don't save these credentials if they're not for this Client ID.
	// They're for another ConvertKit Plugin that uses OAuth.
	if ( $client_id !== INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID ) {
		return;
	}

	// Get all registered providers in WPForms.
	$providers = wpforms_get_providers_options();

	// Bail if no ConvertKit providers were registered.
	if ( ! array_key_exists( 'convertkit', $providers ) ) {
		return;
	}

	// Iterate through providers to find the specific connection containing the now expired Access and Refresh Tokens.
	foreach ( $providers['convertkit'] as $id => $settings ) {
		// Skip if this isn't the connection.
		if ( $settings['access_token'] !== $previous_access_token ) {
			continue;
		}

		// Store the new credentials.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => sanitize_text_field( $result['access_token'] ),
				'refresh_token' => sanitize_text_field( $result['refresh_token'] ),
				'token_expires' => ( time() + $result['expires_in'] ),
				'label'         => $settings['label'],
				'date'          => time(),
			),
			$id
		);

		// Clear any existing scheduled WordPress Cron event for this connection.
		wp_clear_scheduled_hook(
			'integrate_convertkit_wpforms_refresh_token',
			array(
				$id,
			)
		);

		// Schedule a WordPress Cron event to refresh the token on expiry for this connection.
		wp_schedule_single_event(
			( time() + $result['expires_in'] ),
			'integrate_convertkit_wpforms_refresh_token',
			array(
				$id,
			)
		);

		// Remove any existing persistent notice.
		$admin_notices = Integrate_ConvertKit_WPForms_Admin_Notices::get_instance();
		$admin_notices->delete( 'authorization_failed' );

		// Break out of the loop now the credentials have been updated.
		break;
	}

}

/**
 * Deletes the stored access token, refresh token and its expiry from the Plugin settings,
 * and clears any existing scheduled WordPress Cron event to refresh the token on expiry,
 * when either:
 * - The access token is invalid
 * - The access token expired, and refreshing failed
 *
 * @since   1.8.9
 *
 * @param   WP_Error $result                  Error result.
 * @param   string   $client_id               OAuth Client ID used for the Access and Refresh Tokens.
 * @param   string   $invalid_access_token    Existing (invalid) Access Token.
 */
function integrate_convertkit_wpforms_maybe_delete_credentials( $result, $client_id, $invalid_access_token = '' ) {

	// Don't save these credentials if they're not for this Client ID.
	// They're for another ConvertKit Plugin that uses OAuth.
	if ( $client_id !== INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID ) {
		return;
	}

	// Get all registered providers in WPForms.
	$providers = wpforms_get_providers_options();

	// Bail if no ConvertKit providers were registered.
	if ( ! array_key_exists( 'convertkit', $providers ) ) {
		return;
	}

	// Iterate through providers to find the specific connection containing the now invalid Access Token.
	foreach ( $providers['convertkit'] as $id => $settings ) {
		// Skip if this isn't the connection.
		if ( $settings['access_token'] !== $invalid_access_token ) {
			continue;
		}

		// Persist an error notice in the WordPress Administration until the user fixes the problem.
		$admin_notices = Integrate_ConvertKit_WPForms_Admin_Notices::get_instance();
		$admin_notices->add( 'authorization_failed' );

		// Remove the invalid tokens from the connection.
		// Keep the connection so the user doesn't lose settings on WPForms Forms.
		// They can use the Reconnect link at WPForms > Settings > Integrations > Kit > Reconnect.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => '',
				'refresh_token' => '',
				'token_expires' => 0,
				'label'         => $settings['label'],
				'date'          => time(),
			),
			$id
		);

		// Clear any existing scheduled WordPress Cron event for this connection.
		wp_clear_scheduled_hook(
			'integrate_convertkit_wpforms_refresh_token',
			array(
				$id,
			)
		);

		// Break out of the loop now the credentials have been updated.
		break;
	}

}

// Update Access Token when refreshed by the API class.
add_action( 'convertkit_api_refresh_token', 'integrate_convertkit_wpforms_maybe_update_credentials', 10, 3 );

// Delete credentials if the API class uses a invalid access token.
// This prevents the Plugin making repetitive API requests that will 401.
add_action( 'convertkit_api_access_token_invalid', 'integrate_convertkit_wpforms_maybe_delete_credentials', 10, 3 );
