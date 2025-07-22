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
add_action(
	'convertkit_api_refresh_token',
	function ( $result, $client_id, $previous_access_token ) {

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
					'date'          => $settings['date'],
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

			// Break out of the loop now the credentials have been updated.
			break;
		}

	},
	10,
	3
);
