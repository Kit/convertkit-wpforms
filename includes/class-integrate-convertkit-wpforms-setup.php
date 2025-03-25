<?php
/**
 * Plugin activation, update and deactivation class.
 *
 * @package Integrate_ConvertKit_WPForms
 * @author  ConvertKit
 */

/**
 * Runs any steps required on plugin activation, update and deactivation.
 *
 * @package Integrate_ConvertKit_WPForms
 * @author  ConvertKit
 * @version 1.5.0
 */
class Integrate_ConvertKit_WPForms_Setup {

	/**
	 * Runs routines when the Plugin version has been updated.
	 *
	 * @since   1.5.0
	 */
	public function update() {

		// Get installed Plugin version.
		$current_version = get_option( 'integrate_convertkit_wpforms_version' );

		// If the version number matches the plugin version, no update routines
		// need to run.
		if ( $current_version === INTEGRATE_CONVERTKIT_WPFORMS_VERSION ) {
			return;
		}

		/**
		 * 1.5.0: Migrate API settings from individual forms to integrations.
		 */
		if ( ! $current_version || version_compare( $current_version, '1.5.0', '<' ) ) {
			$this->migrate_settings_from_forms_to_integrations();
		}

		/**
		 * 1.7.0: Get Access token for API version 4.0 using a v3 API Key and Secret.
		 */
		if ( ! $current_version || version_compare( $current_version, '1.7.0', '<' ) ) {
			$this->maybe_get_access_tokens_by_api_keys_and_secrets();
		}

		/**
		 * 1.7.2: Migrate Form settings.
		 */
		if ( ! $current_version || version_compare( $current_version, '1.7.2', '<' ) ) {
			$this->migrate_form_settings();
		}

		// Update the installed version number in the options table.
		update_option( 'integrate_convertkit_wpforms_version', INTEGRATE_CONVERTKIT_WPFORMS_VERSION );

	}

	/**
	 * 1.7.2: Prefix any connection form settings with `form:`, now that
	 * the Plugin supports adding a subscriber to a Form, Tag or Sequence.
	 *
	 * @since   1.7.2
	 */
	private function migrate_form_settings() {

		// Bail if the WPForms handler class isn't available.
		if ( ! class_exists( 'WPForms_Form_Handler' ) ) {
			return;
		}

		// Get all forms.
		$form_handler = new WPForms_Form_Handler();
		$forms        = $form_handler->get();

		// Bail if no WPForms Forms exist.
		if ( ! is_array( $forms ) ) {
			return;
		}
		if ( count( $forms ) === 0 ) {
			return;
		}

		// Iterate through forms.
		foreach ( $forms as $form ) {
			// Flag to denote no changes made to the form.
			$form_configuration_changed = false;

			// Decode settings into array.
			$data = wpforms_decode( $form->post_content );

			// Skip if no ConvertKit provider configured for this form.
			if ( ! array_key_exists( 'providers', $data ) ) {
				continue;
			}
			if ( ! array_key_exists( 'convertkit', $data['providers'] ) ) {
				continue;
			}

			// Iterate through ConvertKit providers.
			foreach ( $data['providers']['convertkit'] as $connection_id => $connection ) {
				// Skip if no list_id specified.
				if ( ! array_key_exists( 'list_id', $connection ) ) {
					continue;
				}

				// Skip values that are blank i.e. no ConvertKit Form ID specified.
				if ( empty( $connection['list_id'] ) ) {
					continue;
				}

				// Skip values that are non-numeric i.e. the `form:` prefix was already added.
				// This should never happen as this routine runs once, but this is a sanity check.
				if ( ! is_numeric( $connection['list_id'] ) ) {
					continue;
				}

				// Prefix the ConvertKit Form ID with `form:`.
				$data['providers']['convertkit'][ $connection_id ]['list_id'] = 'form:' . $connection['list_id'];

				// Flag that this form needs to be saved.
				$form_configuration_changed = true;
			}

			// If no changes made to the form configuration, move to the next form.
			if ( ! $form_configuration_changed ) {
				continue;
			}

			// Save data back to the form.
			$form_handler->update( $form->ID, $data );
		}

	}

	/**
	 * 1.7.0: Iterates through existing connections, fetching an Access Token, Refresh Token and Expiry for v4 API use
	 * where an existing connection specifies a v3 API Key and Secret.
	 *
	 * @since   1.7.0
	 */
	private function maybe_get_access_tokens_by_api_keys_and_secrets() {

		// Get all registered providers in WPForms.
		$providers = wpforms_get_providers_options();

		// Bail if no ConvertKit providers were registered.
		if ( ! array_key_exists( 'convertkit', $providers ) ) {
			return;
		}

		// Iterate through providers.
		foreach ( $providers['convertkit'] as $id => $settings ) {
			// If no API Key specified for this provider, it's already using an Access Token.
			if ( ! array_key_exists( 'api_key', $settings ) ) {
				continue;
			}

			// Get Access Token by API Key and Secret.
			$api    = new Integrate_ConvertKit_WPForms_API(
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI
			);
			$result = $api->get_access_token_by_api_key_and_secret(
				$settings['api_key'],
				$settings['api_secret'],
				get_site_url()
			);

			// Bail if an error occured.
			if ( is_wp_error( $result ) ) {
				continue;
			}

			// Re-initialize the API with the tokens.
			$api = new Integrate_ConvertKit_WPForms_API(
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI,
				sanitize_text_field( $result['oauth']['access_token'] ),
				sanitize_text_field( $result['oauth']['refresh_token'] )
			);

			// Fetch account.
			$account = $api->get_account();

			// Store the new credentials.
			wpforms_update_providers_options(
				'convertkit',
				array(
					'access_token'  => sanitize_text_field( $result['oauth']['access_token'] ),
					'refresh_token' => sanitize_text_field( $result['oauth']['refresh_token'] ),
					'token_expires' => sanitize_text_field( $result['oauth']['expires_at'] ),
					'label'         => ( is_wp_error( $account ) ? '' : $account['account']['name'] ),
					'date'          => time(),
				),
				$id
			);
		}

	}

	/**
	 * 1.5.0+: For each WPForms Form that has ConvertKit settings defined (API Key, Form ID etc), migrate
	 * those settings to a provider connection (WPForms > ), and configures the WPForms Form to use the
	 * integration connection instead of the form settings.
	 *
	 * @since   1.5.0
	 */
	private function migrate_settings_from_forms_to_integrations() {

		// Bail if the WPForms handler class isn't available.
		if ( ! class_exists( 'WPForms_Form_Handler' ) ) {
			return;
		}

		// Get all forms.
		$form_handler = new WPForms_Form_Handler();
		$forms        = $form_handler->get();

		// Bail if no WPForms Forms exist.
		if ( ! is_array( $forms ) ) {
			return;
		}
		if ( count( $forms ) === 0 ) {
			return;
		}

		// For each form, inspect its settings to determine if < 1.5.0 ConvertKit settings exist in the Form.
		foreach ( $forms as $form ) {
			// Decode settings into array.
			$data = wpforms_decode( $form->post_content );

			// Skip if this Form already has a ConvertKit provider configured.
			// This shouldn't be the case for upgrades to 1.5.0+, but it's a useful
			// sanity check.
			if ( array_key_exists( 'providers', $data ) && array_key_exists( 'convertkit', $data['providers'] ) ) {
				continue;
			}

			// Bail if the API Key or Form ID settings don't exist or are empty.
			if ( ! array_key_exists( 'be_convertkit_api', $data['settings'] ) ) {
				continue;
			}
			if ( empty( $data['settings']['be_convertkit_api'] ) ) {
				continue;
			}
			if ( ! array_key_exists( 'be_convertkit_form_id', $data['settings'] ) ) {
				continue;
			}
			if ( empty( $data['settings']['be_convertkit_form_id'] ) ) {
				continue;
			}

			// Search WPForms provider accounts to see if an account exists for this ConvertKit API Key.
			$account_id = $this->wpforms_get_provider_account_by_api_key( $data['settings']['be_convertkit_api'] );

			// If no account exists, register a new ConvertKit provider account using this API Key.
			if ( ! $account_id ) {
				$account_id = $this->wpforms_register_account( $data['settings']['be_convertkit_api'] );
			}

			// Define the ConvertKit provider settings in the form.
			$data['providers']['convertkit'] = array(
				'connection_' . uniqid() => array(
					'connection_name' => 'Kit',
					'account_id'      => $account_id,
					'list_id'         => $data['settings']['be_convertkit_form_id'],
					'fields'          => array(
						'email' => $data['settings']['be_convertkit_field_email'] . '.value.email',
						'name'  => $data['settings']['be_convertkit_field_first_name'] . '.value.text',
					),
				),
			);

			// Remove deprecated ConvertKit form settings.
			unset(
				$data['settings']['be_convertkit_api'],
				$data['settings']['be_convertkit_form_id'],
				$data['settings']['be_convertkit_field_first_name'],
				$data['settings']['be_convertkit_field_email']
			);

			// Save data back to the form.
			$form_handler->update( $form->ID, $data );
		}

	}

	/**
	 * Returns the account ID for the ConvertKit provider registered in WPForms
	 * for the given ConvertKit API Key.
	 *
	 * Returns false if no provider is registered with the given API Key.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $api_key    ConvertKit API Key.
	 * @return  bool|string         false | Account ID.
	 */
	private function wpforms_get_provider_account_by_api_key( $api_key ) {

		// Get all ConvertKit connections that are registered.
		$connections = wpforms_get_providers_options( 'convertkit' );

		// If no existing connections exist, register one now and return its ID.
		if ( ! $connections ) {
			return false;
		}

		foreach ( $connections as $id => $connection ) {
			// If the connection's API Key matches the one we want to register a connection for,
			// we can use the existing connection.
			if ( $connection['api_key'] === $api_key ) {
				return $id;
			}
		}

		// If here, we have no existing ConvertKit connection for this API Key.
		return false;

	}

	/**
	 * Returns a ConvertKit provider account in WPForms for the given ConvertKit API Key.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $api_key    ConvertKit API Key.
	 * @return  string              Account ID
	 */
	private function wpforms_register_account( $api_key ) {

		// wpforms_update_providers_options() doesn't return the generated ID
		// for a new provider account, so we mimic how they generate an ID
		// and return it.
		$id = uniqid();

		wpforms_update_providers_options(
			'convertkit',
			array(
				'api_key'    => $api_key,
				'api_secret' => '',
				'label'      => 'Kit',
				'date'       => strtotime( 'now' ),
			),
			$id
		);

		return $id;

	}

}
