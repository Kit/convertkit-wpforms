<?php
/**
 * ConvertKit WPForms Creator Network Recommendations class.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * Registers Creator Network Recommendations settings when editing a form at Settings > ConvertKit,
 * and outputs the JS embed (if enabled) on the WPForms Form.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */
class Integrate_ConvertKit_WPForms_Creator_Network_Recommendations {

	/**
	 * The slug / key that holds providers for this integration.
	 *
	 * @since   1.5.8
	 *
	 * @var     string
	 */
	private $slug = 'convertkit';

	/**
	 * Holds the key to store the Provider Connection ID.
	 *
	 * @since   1.5.8
	 *
	 * @var     string
	 */
	private $connection_id_key = 'convertkit_connection_id';

	/**
	 * Holds the key to store the Creator Network Recommendations setting and JS URL.
	 *
	 * @since   1.5.8
	 *
	 * @var     string
	 */
	private $creator_network_recommendations_script_key = 'convertkit_wpforms_creator_network_recommendations';

	/**
	 * Holds the URL to the WPForms Integrations screen.
	 *
	 * @since   1.5.8
	 *
	 * @var     string
	 */
	private $integrations_link = 'admin.php?page=wpforms-settings&view=integrations';

	/**
	 * Holds the URL the user needs to visit on the ConvertKit app to upgrade their account.
	 *
	 * @since   1.5.8
	 *
	 * @var     string
	 */
	private $convertkit_billing_url = 'https://app.kit.com/account_settings/billing/?utm_source=wordpress&utm_content=convertkit-wpforms';

	/**
	 * Constructor
	 *
	 * @since   1.5.8
	 */
	public function __construct() {

		add_filter( 'wpforms_builder_settings_sections', array( $this, 'settings_section' ), 20, 1 );
		add_filter( 'wpforms_form_settings_panel_content', array( $this, 'settings_section_content' ), 20 );
		add_action( 'wpforms_frontend_js', array( $this, 'maybe_enqueue_creator_network_recommendations_script' ) );

	}

	/**
	 * Registers a ConvertKit settings section when editing a WPForms Form at Settings > Convertkit.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $sections   Settings sections.
	 * @return  array               Settings sections
	 */
	public function settings_section( $sections ) {

		$sections[ $this->slug ] = __( 'Kit', 'integrate-convertkit-wpforms' );
		return $sections;

	}

	/**
	 * Outputs fields for the ConvertKit settings section when editing a WPForms Form at Settings > Convertkit.
	 *
	 * @since   1.5.8
	 *
	 * @param   WPForms_Builder_Panel_Settings $instance   WPForms Form Builder Settings Instance.
	 */
	public function settings_section_content( $instance ) {

		echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-' . esc_attr( $this->slug ) . '">';
		echo '<div class="wpforms-panel-content-section-title">' . esc_html__( 'Kit', 'integrate-convertkit-wpforms' ) . '</div>';
		echo '<div class="wpforms-panel-fields-group">';
		echo '<div class="wpforms-panel-fields-group-title">' . esc_html__( 'Creator Network Recommendations', 'integrate-convertkit-wpforms' ) . '</div>';

		// If AJAX is disabled on this form, it must be enabled.
		if ( ! $this->form_ajax_enabled( $instance->form_data ) ) {
			// Output warning notice.
			$this->settings_section_notice(
				esc_html__( 'The "Enable AJAX form submission" setting must be enabled on this form at Settings > General for the Creator Network Recommendations feature.', 'integrate-convertkit-wpforms' )
			);

			// Close div and return.
			return $this->settings_section_close_and_return();
		}

		// If no provider is specified for ConvertKit at WPForms > Settings > Integrations > Kit,
		// don't show an option.
		if ( ! $this->has_provider() ) {
			// Output warning notice.
			$this->settings_section_notice(
				esc_html__( 'Please connect your Kit account on the', 'integrate-convertkit-wpforms' ),
				admin_url( $this->integrations_link ),
				esc_html__( 'integrations screen', 'integrate-convertkit-wpforms' )
			);

			// Close div and return.
			return $this->settings_section_close_and_return();
		}

		// If a connection is specified, query API to fetch Creator Network Recommendations script.
		if ( $this->form_has_connection( $instance->form_data ) ) {
			$result = $this->get_creator_network_recommendations_script( $this->form_get_connection( $instance->form_data ), true );

			// If an error occured, show it.
			if ( is_wp_error( $result ) ) {
				// Output warning notice.
				$this->settings_section_notice(
					$result->get_error_message(),
					admin_url( $this->integrations_link ),
					esc_html__( 'Fix settings', 'integrate-convertkit-wpforms' )
				);
			}

			// If the result is false, the Creator Network is disabled.
			if ( ! $result ) {
				// Output warning notice.
				$this->settings_section_notice(
					esc_html__( 'Creator Network Recommendations requires a', 'integrate-convertkit-wpforms' ),
					$this->convertkit_billing_url,
					esc_html__( 'paid Kit Plan', 'integrate-convertkit-wpforms' )
				);
			}
		}

		// Output settings.
		wpforms_panel_field(
			'select',
			'settings',
			$this->connection_id_key,
			$instance->form_data,
			__( 'Select Account', 'integrate-convertkit-wpforms' ),
			array(
				'options'     => $this->get_providers(),
				'placeholder' => __( '-- Select Account --', 'integrate-convertkit-wpforms' ),
			)
		);

		wpforms_panel_field(
			'toggle',
			'settings',
			$this->creator_network_recommendations_script_key,
			$instance->form_data,
			esc_html__( 'Enable Creator Network Recommendations', 'integrate-convertkit-wpforms' ),
			array(
				'tooltip' => esc_html__( 'If enabled, displays the Creator Network Recommendations modal when this form is submitted.', 'integrate-convertkit-wpforms' ),
			)
		);

		$this->settings_section_close_and_return();

	}

	/**
	 * Outputs a warning notice on the settings section screen.
	 *
	 * @since   1.5.8
	 *
	 * @param   string      $text       Text to display in notice.
	 * @param   bool|string $link       URL to link the $link_text to.
	 * @param   bool|string $link_text  Link Text.
	 */
	public function settings_section_notice( $text, $link = false, $link_text = false ) {

		// Output alert div.
		echo '<div class="wpforms-alert wpforms-alert-warning wpforms-alert-warning-wide">';

		// If no link specified, just output the text.
		if ( ! $link ) {
			echo esc_html( $text );
		} else {
			printf(
				'%s <a href="%s" target="_blank">%s</a>',
				esc_html( $text ),
				esc_url( $link ),
				esc_html( $link_text )
			);
		}

		// Close alert div.
		echo '</div>';

	}

	/**
	 * Outputs closing div elements for the settings section.
	 *
	 * @since   1.5.8
	 */
	public function settings_section_close_and_return() {

		// Close .wpforms-panel-fields-group.
		echo '</div>';

		// Close .wpforms-panel-content-section-convertkit.
		echo '</div>';

	}

	/**
	 * Enqueues the Creator Network Recommendations script, if the WPForms form
	 * has the 'Enable Creator Network Recommendations' setting enabled.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $forms      WPForms Forms that have been output on the page.
	 */
	public function maybe_enqueue_creator_network_recommendations_script( $forms ) {

		// Iterate through the form(s) output on the page, to determine if the recommendations
		// script is required.
		foreach ( $forms as $form ) {
			// Bail if AJAX submission is disabled; we can't show the Creator Network Recommendations
			// if the page reloads on form submission.
			if ( ! $this->form_ajax_enabled( $form ) ) {
				continue;
			}

			// Bail if Creator Network Recommendations are disabled.
			if ( ! $this->form_creator_network_recommendations_enabled( $form ) ) {
				continue;
			}

			// Bail if no connection exists.
			$connection = $this->form_get_connection( $form );
			echo '<pre>' . print_r( $form, true ) . '</pre>';
			var_dump( $connection );

			// Fetch Creator Network Recommendations script URL.
			$script_url = $this->get_creator_network_recommendations_script( $this->form_get_connection( $form ) );
			var_dump( $script_url );

			// Bail if an error occured fetching the script, or no script exists,
			// because Creator Network Recommendations are not enabled on the
			// ConvertKit account.
			if ( is_wp_error( $script_url ) ) {
				continue;
			}
			if ( ! $script_url ) {
				continue;
			}

			// Enqueue script.
			wp_enqueue_script( 'convertkit-wpforms-creator-network-recommendations', $script_url, array(), INTEGRATE_CONVERTKIT_WPFORMS_VERSION, true );

			// With the script loaded, we don't need to continue iteration and load it again.
			break;
		}

	}

	/**
	 * Returns all providers registered at
	 * WPForms > Settings > Integrations > ConvertKit.
	 *
	 * @since   1.5.8
	 */
	private function get_providers() {

		// Bail if no providers exist for this integration.
		if ( ! $this->has_provider() ) {
			return false;
		}

		// Get providers registered at WPForms > Settings > Integrations.
		$providers = wpforms_get_providers_options();

		// Build array compatible with wpforms_panel_field().
		$connections = array();
		foreach ( $providers[ $this->slug ] as $account_id => $details ) {
			$connections[ $account_id ] = sprintf(
				'%s: %s: %s',
				$details['label'],
				esc_html__( 'Connected on', 'integrate-convertkit-wpforms' ),
				gmdate( 'dS F, Y', $details['date'] )
			);
		}

		return $connections;

	}

	/**
	 * Checks if this integration has a provider registered at
	 * WPForms > Settings > Integrations > ConvertKit.
	 *
	 * @since   1.5.8
	 */
	private function has_provider() {

		// Get providers registered at WPForms > Settings > Integrations.
		$providers = wpforms_get_providers_options();

		// Bail if no providers exist.
		if ( empty( $providers ) ) {
			return false;
		}

		if ( ! array_key_exists( $this->slug, $providers ) ) {
			return false;
		}

		if ( empty( $providers[ $this->slug ] ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Returns the given provider registered at
	 * WPForms > Settings > Integrations > ConvertKit.
	 *
	 * @since   1.5.8
	 *
	 * @param   string $account_id     Account ID.
	 * @return  bool|array
	 */
	private function get_provider( $account_id ) {

		// Get all registered providers in WPForms.
		$providers = wpforms_get_providers_options();

		// Bail if no ConvertKit providers were registered.
		if ( ! array_key_exists( $this->slug, $providers ) ) {
			return false;
		}

		// Bail if the requested connection does not exist.
		if ( ! array_key_exists( $account_id, $providers[ $this->slug ] ) ) {
			return false;
		}

		return $providers[ $this->slug ][ $account_id ];

	}

	/**
	 * Returns whether the Connection setting is defined
	 * on the given WPForms Form.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $form   WPForms Form.
	 * @return  bool            Connection exists
	 */
	private function form_has_connection( $form ) {

		if ( ! array_key_exists( $this->connection_id_key, $form['settings'] ) ) {
			return false;
		}

		if ( empty( $form['settings'][ $this->connection_id_key ] ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Returns the Connection setting for the given WPForms Form.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $form   WPForms Form.
	 * @return  bool|string     Connection
	 */
	private function form_get_connection( $form ) {

		if ( ! $this->form_has_connection( $form ) ) {
			return false;
		}

		return $form['settings'][ $this->connection_id_key ];

	}

	/**
	 * Returns Creator Network Recommendations setting on the given WPForms Form.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $form   WPForms Form.
	 * @return  bool            Creator Network Recommendations enabled
	 */
	private function form_creator_network_recommendations_enabled( $form ) {

		if ( ! array_key_exists( $this->creator_network_recommendations_script_key, $form['settings'] ) ) {
			return false;
		}

		return (bool) $form['settings'][ $this->creator_network_recommendations_script_key ];

	}

	/**
	 * Returns if AJAX submission is enabled on the given WPForms Form.
	 *
	 * @since   1.5.8
	 *
	 * @param   array $form   WPForms Form.
	 * @return  bool            AJAX submission enabled
	 */
	private function form_ajax_enabled( $form ) {

		if ( ! array_key_exists( 'ajax_submit', $form['settings'] ) ) {
			return false;
		}

		return (bool) $form['settings']['ajax_submit'];

	}

	/**
	 * Fetches the Creator Network Recommendations script from the database, falling
	 * back to an API query if the database doesn't have a copy of it stored.
	 *
	 * @since   1.5.8
	 *
	 * @param   string $account_id  Provider Account ID.
	 * @param   bool   $force        If enabled, queries the API instead of checking the cached data.
	 *
	 * @return  WP_Error|bool|string
	 */
	private function get_creator_network_recommendations_script( $account_id, $force = true ) {

		// Get Creator Network Recommendations script URL.
		if ( ! $force ) {
			$script_url = get_option( $this->creator_network_recommendations_script_key . '_' . $account_id );
			if ( $script_url ) {
				return $script_url;
			}
		}

		// Get provider.
		$provider = $this->get_provider( $account_id );
		if ( ! $provider ) {
			return new WP_Error(
				'integrate_convertkit_wpforms_settings_connection_missing',
				__( 'The account specified in the Form\'s Settings > Kit does not exist', 'integrate-convertkit-wpforms' )
			);
		}

		// No cached script, or we're forcing an API query; fetch from the API.
		$api = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI,
			$provider['access_token'],
			$provider['refresh_token']
		);

		// Sanity check that we're using the ConvertKit WordPress Libraries 1.3.7 or higher.
		// If another ConvertKit Plugin is active and out of date, its libraries might
		// be loaded that don't have this method.
		if ( ! method_exists( $api, 'recommendations_script' ) ) { // @phpstan-ignore-line Older WordPress Libraries won't have this method.
			delete_option( $this->creator_network_recommendations_script_key . '_' . $account_id );
			return false;
		}

		// Get script from API.
		$result = $api->recommendations_script();

		// Bail if an error occured.
		if ( is_wp_error( $result ) ) {
			delete_option( $this->creator_network_recommendations_script_key . '_' . $account_id );
			return $result;
		}

		// Bail if not enabled.
		if ( ! $result['enabled'] ) {
			delete_option( $this->creator_network_recommendations_script_key . '_' . $account_id );
			return false;
		}

		// Store script URL, as Creator Network Recommendations are available on this account.
		update_option( $this->creator_network_recommendations_script_key . '_' . $account_id, $result['embed_js'] );

		// Return.
		return $result['embed_js'];

	}

}

new Integrate_ConvertKit_WPForms_Creator_Network_Recommendations();
