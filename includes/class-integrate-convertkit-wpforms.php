<?php
/**
 * ConvertKit WPForms class.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * Class ConvertKit WPForms
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */
class Integrate_ConvertKit_WPForms extends WPForms_Provider {

	/**
	 * Holds API connections to ConvertKit.
	 *
	 * @since   1.5.0
	 *
	 * @var     array
	 */
	public $api = array();

	/**
	 * Holds the review request class.
	 *
	 * @since   1.5.5
	 *
	 * @var     bool|ConvertKit_Review_Request
	 */
	private $review_request = false;

	/**
	 * Holds the ConvertKit registration URL.
	 *
	 * @since   1.5.0
	 *
	 * @var     string
	 */
	private $register_url = 'https://app.kit.com/users/signup?utm_source=wordpress&utm_content=convertkit-wpforms';

	/**
	 * Initialize ConvertKit as a WPForms Provider.
	 *
	 * @since   1.5.0
	 */
	public function init() {

		// Define Provider details.
		$this->version  = INTEGRATE_CONVERTKIT_WPFORMS_VERSION;
		$this->name     = 'Kit';
		$this->slug     = 'convertkit';
		$this->priority = 14;
		$this->icon     = INTEGRATE_CONVERTKIT_WPFORMS_URL . 'resources/backend/images/logo-square.jpg';

		// Initialize classes.
		$this->review_request = new ConvertKit_Review_Request( 'Kit for WPForms', 'integrate-convertkit-wpforms', INTEGRATE_CONVERTKIT_WPFORMS_PATH );

		// Run update routine.
		add_action( 'init', array( $this, 'update' ) );

		if ( is_admin() ) {
			add_action( 'init', array( $this, 'maybe_display_notice' ) );
			add_action( 'init', array( $this, 'maybe_get_and_store_access_token' ) );
			add_action( "wp_ajax_wpforms_settings_provider_disconnect_{$this->slug}", array( $this, 'delete_resource_cache' ), 1 );
			add_action( 'wpforms_settings_enqueue', array( $this, 'enqueue_settings_assets' ) );
			add_action( 'wpforms_builder_enqueues', array( $this, 'enqueue_builder_assets' ) );
			add_filter( "wpforms_providers_provider_settings_formbuilder_display_content_default_screen_{$this->slug}", array( $this, 'builder_settings_default_content' ) );
		}

	}

	/**
	 * Add provider to the Settings Integrations tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array $active   Array of active connections.
	 * @param array $settings Array of all connections settings.
	 */
	public function integrations_tab_options( $active, $settings ) {

		// If no Kit accounts connected to WPForms, don't modify the UI.
		if ( ! array_key_exists( $this->slug, $settings ) ) {
			parent::integrations_tab_options( $active, $settings );
			return;
		}
		if ( ! count( $settings[ $this->slug ] ) ) {
			parent::integrations_tab_options( $active, $settings );
			return;
		}

		// Initialize API to generate OAuth URL.
		$api = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI
		);

		// Fetch UI.
		ob_start();
		parent::integrations_tab_options( $active, $settings );
		$html = ob_get_clean();

		// Inject Reconnect button for each Kit account.
		$reconnect_button = sprintf(
			'<a href="%s" class="%s-reconnect">%s</a>',
			esc_url( $api->get_oauth_url( admin_url( 'admin.php?page=wpforms-settings&view=integrations' ) ) ),
			esc_attr( $this->slug ),
			esc_html__( 'Reconnect', 'integrate-convertkit-wpforms' )
		);
		$html             = str_replace( '</a></span></li>', '</a></span>' . $reconnect_button . '</li>', $html );

		// Output UI.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	}

	/**
	 * Runs update/upgrade routines between Plugin versions.
	 *
	 * @since   1.5.0
	 */
	public function update() {

		$setup = new Integrate_ConvertKit_WPForms_Setup();
		$setup->update();

	}

	/**
	 * Processes and submits a WPForms Form entry to Kit,
	 * based on the Form's settings at Marketing > Kit.
	 *
	 * @since   1.5.0
	 *
	 * @param   array $fields    List of fields with their data and settings.
	 * @param   array $entry     Submitted entry values.
	 * @param   array $form_data Form data and settings.
	 * @param   int   $entry_id  Saved entry ID.
	 */
	public function process_entry( $fields, $entry, $form_data, $entry_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		// Only run if this form has a connection for this provider.
		if ( empty( $form_data['providers'][ $this->slug ] ) ) {
			return;
		}

		// Iterate through each ConvertKit connection.  A WPForms Form can have one or more
		// connection to a provider (i.e. ConvertKit) configured at Marketing > ConvertKit.
		foreach ( $form_data['providers'][ $this->slug ] as $connection ) {
			// Skip if the list_id (ConvertKit Form ID) isn't specified, as we cannot
			// subscribe the email address otherwise.
			if ( empty( $connection['list_id'] ) ) {
				wpforms_log(
					'Kit',
					__( 'No Form ID was specified.', 'integrate-convertkit-wpforms' ),
					array(
						'type'    => array( 'provider', 'error' ),
						'parent'  => $entry_id,
						'form_id' => $form_data['id'],
					)
				);
				continue;
			}

			// Load ConvertKit API instance for this WPForms connection.
			$api = $this->get_api_instance( $connection['account_id'] );

			// If an error occured, log it and continue to the next connection.
			if ( is_wp_error( $api ) ) {
				wpforms_log(
					'Kit',
					$api->get_error_message(),
					array(
						'type'    => array( 'provider', 'error' ),
						'parent'  => $entry_id,
						'form_id' => $form_data['id'],
					)
				);
				continue;
			}

			// Load resource classes with this API instance.
			$resource_forms = new Integrate_ConvertKit_WPForms_Resource_Forms( $api, $connection['account_id'] );
			$resource_tags  = new Integrate_ConvertKit_WPForms_Resource_Tags( $api, $connection['account_id'] );

			// Iterate through the WPForms Form field to ConvertKit field mappings, to build
			// the API query to subscribe to the ConvertKit Form.
			$args = array();
			foreach ( $connection['fields'] as $convertkit_field => $wpforms_field ) {
				// Skip if no ConvertKit mapping specified for this WPForms Form field.
				if ( empty( $wpforms_field ) ) {
					continue;
				}

				// Fetch the field's value from the WPForms Form entry.
				$value = $this->get_entry_field_value( $wpforms_field, $fields );

				// Depending on the field name, store the value in the $args array.
				switch ( $convertkit_field ) {
					/**
					 * Email
					 * Name
					 */
					case 'email':
					case 'name':
						$args[ $convertkit_field ] = $value;
						break;

					/**
					 * Tag
					 */
					case 'tag':
						// The WPForms field value might be any one of the following, depending on the WPForms field and its configuration:
						// - an integer (Tag ID) on a single line e.g. the mapped field is a <select> with separate <option> values defined
						// - a string (Tag Name) on a single line e.g. the mapped field is a <select> where values match <option> labels
						// - integers (Tag IDs), one on each line, separated by a newline e.g. the mapped field is a checkbox where values differ from labels
						// - strings (Tag Names), one on each line, separated by a newline e.g. the mapped field is a checkbox where values match labels.
						// We need to build an array of Tag IDs from the value.

						// Don't do anything if the value is empty.
						if ( empty( $value ) ) {
							break;
						}

						// Fetch tags, so we can convert any tag names to their tag IDs
						// for submission to form_subscribe().
						$api_tags = $resource_tags->get();

						// Define an array for Tag IDs to be stored in.
						$args['tags'] = array();

						// Iterate through the submitted value(s), to build an array of Tag IDs.
						foreach ( explode( "\n", $value ) as $tag ) {
							// Clean up any trailing spaces that might exist due to input.
							$tag = trim( $tag );

							// If the tag is a number, check it exists.
							if ( is_numeric( $tag ) && array_key_exists( (int) $tag, $api_tags ) ) {
								$args['tags'][] = (int) $tag;
								continue;
							}

							// The tag is a string, or a number that is not a tag ID; attempt to find its ID.
							foreach ( $api_tags as $tag_id => $api_tag ) {
								if ( $api_tag['name'] === $tag ) {
									$args['tags'][] = (int) $tag_id;
									continue;
								}
							}
						}

						// If no tags were assigned, remove the tag field.
						if ( count( $args['tags'] ) === 0 ) {
							unset( $args['tags'] );
						}
						break;

					/**
					 * Custom Fields
					 */
					default:
						// Sanity check this field is a ConvertKit Custom Field.
						if ( strpos( $convertkit_field, 'custom_field_' ) === false ) {
							break;
						}

						// Define custom field array if not yet defined.
						if ( ! array_key_exists( 'fields', $args ) ) {
							$args['fields'] = array();
						}

						// Add custom field.
						$args['fields'][ str_replace( 'custom_field_', '', $convertkit_field ) ] = $value;
						break;
				}
			}

			// Skip if no email address field was mapped.
			if ( ! array_key_exists( 'email', $args ) ) {
				continue;
			}

			// Skip if the email address field is blank.
			if ( empty( $args['email'] ) ) {
				continue;
			}

			// Maintain backward compatibility for form fields that specify tags or custom fields using CSS classes.
			// @link https://www.billerickson.net/setup-convertkit-wordpress-form/#custom-fields-and-tags.
			foreach ( $form_data['fields'] as $i => $field ) {
				if ( empty( $field['css'] ) ) {
					continue;
				}

				$value   = ! empty( $fields[ $i ]['value_raw'] ) ? $fields[ $i ]['value_raw'] : $fields[ $i ]['value'];
				$classes = explode( ' ', $field['css'] );
				foreach ( $classes as $class ) {

					// Custom Fields.
					if ( false !== strpos( $class, 'ck-custom-' ) ) {
						$key                    = str_replace( 'ck-custom-', '', $class );
						$args['fields'][ $key ] = $value;
					}

					// Tags.
					if ( 'ck-tag' === $class ) {
						$args['tags'][] = $value;
					}
				}
			}

			// Filter for customizing arguments.
			// @link https://www.billerickson.net/code/integrate-convertkit-wpforms-custom-fields/.
			$args = apply_filters( 'be_convertkit_form_args', $args, $fields, $form_data );

			// Filter for limiting integration.
			// @link https://www.billerickson.net/code/integrate-convertkit-wpforms-conditional-processing/.
			if ( ! apply_filters( 'be_convertkit_process_form', true, $fields, $form_data ) ) {
				continue;
			}

			// Determine resource type, resource ID and subscriber state to use.
			$resource         = $this->get_resource_type_and_id( $connection['list_id'] );
			$subscriber_state = $this->get_initial_subscriber_state( $resource['type'] );

			// Subscribe the email address.
			$subscriber = $api->create_subscriber(
				$args['email'],
				( isset( $args['name'] ) ? $args['name'] : '' ),
				$subscriber_state,
				( isset( $args['fields'] ) ? $args['fields'] : array() )
			);

			// If the API response is an error, log it as an error.
			if ( is_wp_error( $subscriber ) ) {
				wpforms_log(
					'Kit',
					sprintf(
						'API Error: %s',
						$subscriber->get_error_message()
					),
					array(
						'type'    => array( 'provider', 'error' ),
						'parent'  => $entry_id,
						'form_id' => $form_data['id'],
					)
				);

				return;
			}

			// Email subscribed to ConvertKit successfully; request a review.
			// This can safely be called multiple times, as the review request
			// class will ensure once a review request is dismissed by the user,
			// it is never displayed again.
			if ( $this->review_request ) {
				$this->review_request->request_review();
			}

			// If the subscribe setting isn't 'subscribe', add the subscriber to the resource type.
			if ( $resource['type'] !== 'subscribe' ) {
				// Add the subscriber to the resource type (form, tag etc).
				switch ( $resource['type'] ) {

					/**
					 * Form
					 */
					case 'form':
						// For Legacy Forms, a different endpoint is used.
						if ( $resource_forms->is_legacy( $resource['id'] ) ) {
							$response = $api->add_subscriber_to_legacy_form( $resource['id'], $subscriber['subscriber']['id'] );
						} else {
							// Add subscriber to form.
							$response = $api->add_subscriber_to_form(
								$resource['id'],
								$subscriber['subscriber']['id'],
								$this->get_referrer_url( $form_data )
							);
						}
						break;

					/**
					 * Sequence
					 */
					case 'sequence':
						// Add subscriber to sequence.
						$response = $api->add_subscriber_to_sequence( $resource['id'], $subscriber['subscriber']['id'] );
						break;

					/**
					 * Tag
					 */
					case 'tag':
						// Add subscriber to tag.
						$response = $api->tag_subscriber( $resource['id'], $subscriber['subscriber']['id'] );
						break;

					/**
					 * Unsupported resource type
					 */
					default:
						$response = new WP_Error(
							'integrate_convertkit_wpforms_process_entry_resource_invalid',
							sprintf(
								/* translators: Resource type */
								esc_html__( 'The resource type %s is unsupported.', 'integrate-convertkit-wpforms' ),
								$resource['type']
							)
						);
						break;

				}

				// If the API response is an error, log it as an error.
				if ( is_wp_error( $response ) ) {
					wpforms_log(
						'Kit',
						sprintf(
							'API Error: %s',
							$response->get_error_message()
						),
						array(
							'type'    => array( 'provider', 'error' ),
							'parent'  => $entry_id,
							'form_id' => $form_data['id'],
						)
					);
				}
			}

			// Assign tags to the subscriber, if any exist.
			if ( isset( $args['tags'] ) ) {
				foreach ( $args['tags'] as $tag_id ) {
					// Assign tag to subscriber.
					$response = $api->tag_subscriber( $tag_id, $subscriber['subscriber']['id'] );

					// If the API response is an error, log it as an error.
					if ( is_wp_error( $response ) ) {
						wpforms_log(
							'Kit',
							sprintf(
								'API Error: %s',
								$response->get_error_message()
							),
							array(
								'type'    => array( 'provider', 'error' ),
								'parent'  => $entry_id,
								'form_id' => $form_data['id'],
							)
						);
					}
				}
			}

			// Log successful API response.
			wpforms_log(
				'Kit',
				$subscriber,
				array(
					'type'    => array( 'provider', 'log' ),
					'parent'  => $entry_id,
					'form_id' => $form_data['id'],
				)
			);
		}

	}

	/**
	 * Gets the referrer URL to send to the `form_subscribe` API method.
	 *
	 * Falls back to the action's AJAX URL if the Post ID the form was
	 * embedded in cannot be determined.
	 *
	 * @since   1.7.9
	 *
	 * @param   array $form_data Form data and settings.
	 * @return  string
	 */
	private function get_referrer_url( $form_data ) {

		// If the form data includes the referer, return that URL
		// as it will include any UTM parameters.
		if ( array_key_exists( 'entry_meta', $form_data ) && array_key_exists( 'referer', $form_data['entry_meta'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return esc_url( $form_data['entry_meta']['referer'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// If the request includes the page_url, return that URL.
		if ( array_key_exists( 'page_url', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return esc_url( sanitize_text_field( wp_unslash( $_REQUEST['page_url'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Return the AJAX URL.
		return home_url( add_query_arg( null, null ) );

	}

	/**
	 * Returns the subscriber state to use when creating a subscriber.
	 *
	 * @since   1.7.3
	 *
	 * @param   string $resource_type  Resource Type (subscriber,form,tag,sequence).
	 * @return  string                  Subscriber state
	 */
	private function get_initial_subscriber_state( $resource_type ) {

		return ( $resource_type === 'form' ? 'inactive' : 'active' );

	}

	/**
	 * Returns an array comprising of the resource type and ID for the given list ID setting.
	 *
	 * @since   1.7.3
	 *
	 * @param   string $setting    Setting.
	 * @return  array
	 */
	private function get_resource_type_and_id( $setting ) {

		if ( $setting === 'subscribe' ) {
			return array(
				'type' => 'subscribe',
				'id'   => 0,
			);
		}

		list( $resource_type, $resource_id ) = explode( ':', $setting );

		return array(
			'type' => $resource_type,
			'id'   => absint( $resource_id ),
		);

	}

	/**
	 * Returns the value for the given field in a WPForms form entry.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $field      Field Name.
	 * @param   array  $fields     Fields and their submitted form values.
	 * @return  bool|string             Field Value
	 */
	private function get_entry_field_value( $field, $fields ) {

		$field = explode( '.', $field );
		$id    = $field[0];

		// Determine the field ID's key that stores the submitted value for this field.
		$key = 'value';
		if ( ! empty( $field[1] ) ) {
			$key = $field[1];
		} elseif ( array_key_exists( 'value_raw', $fields[ $id ] ) ) {
			// Some fields, such as checkboxes, radio buttons and select fields, may
			// have a different value defined vs. the label. Using 'value_raw' will
			// always fetch the value, if "Show Values" is enabled in WPForms,
			// falling back to the label if "Show Values" is disabled.
			$key = 'value_raw';
		}

		// Check if mapped form field has a value.
		if ( empty( $fields[ $id ][ $key ] ) ) {
			return false;
		}

		return $fields[ $id ][ $key ];

	}

	/**
	 * Output fields at WPForms > Settings > Integrations > Kit,
	 * allowing the user to enter their ConvertKit API Key.
	 *
	 * @since   1.5.0
	 */
	public function integrations_tab_new_form() {

		// Initialize API to generate OAuth URL.
		$api = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI
		);

		require INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/views/backend/settings-integration.php';

	}

	/**
	 * Output groups for this provider.
	 *
	 * @since   1.5.0
	 *
	 * @param string $connection_id WPForms ConvertKit Connection ID.
	 * @param array  $connection    WPForms ConvertKit Connection Settings (API Key, Secret etc).
	 * @return string
	 */
	public function output_groups( $connection_id = '', $connection = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		// ConvertKit doesn't have a concept of Groups, so we don't output anything for configuring this.
		return '';

	}

	/**
	 * Outputs instructions for connecting a form to a ConvertKit account when editing the WPForms Form
	 * at WPForms > Add/Edit a Form > Marketing > ConvertKit.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $content    Content.
	 * @return  string              Content
	 */
	public function builder_settings_default_content( $content ) {

		ob_start();
		?>
		<p>
			<a href="<?php echo esc_url( $this->register_url ); ?>" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Try Kit for Free', 'integrate-convertkit-wpforms' ); ?>
			</a>
		</p>
		<?php

		return $content . ob_get_clean();

	}

	/**
	 * Outputs a <select> dropdown of Kit Accounts, allowing the user
	 * to choose which Kit Account to use for sending form submissions to.
	 *
	 * @since 1.8.4
	 *
	 * @param string $connection_id Unique connection ID.
	 * @param array  $connection    Array of connection data.
	 * @return string
	 */
	public function output_accounts( $connection_id = '', $connection = array() ) {

		// Get all registered providers in WPForms.
		$providers = wpforms_get_providers_options();

		// Show a message if no Kit providers were registered, with a button linking to the OAuth flow.
		if ( empty( $providers[ $this->slug ] ) ) {
			// Initialize API to generate OAuth URL.
			$api = new Integrate_ConvertKit_WPForms_API(
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
				INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI
			);

			return sprintf(
				'<div class="wpforms-connection-block"><a href="%s" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" data-provider="convertkit" target="_blank" rel="noopener noreferrer">%s</a></div>',
				esc_url( $api->get_oauth_url( admin_url( 'admin.php?page=wpforms-settings&view=integrations&convertkit-modal=1' ) ) ),
				esc_html__( 'Connect to Kit', 'integrate-convertkit-wpforms' )
			);
		}

		// Return the <select> dropdown of Kit Accounts.
		return parent::output_accounts( $connection_id, $connection );

	}

	/**
	 * Outputs a <select> dropdown of ConvertKit Forms, allowing the user
	 * to choose which ConvertKit Form to send form submissions to.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $connection_id WPForms ConvertKit Connection ID.
	 * @param   array  $connection    WPForms ConvertKit Connection Settings (API Key, Secret etc).
	 * @return  WP_Error|string
	 */
	public function output_lists( $connection_id = '', $connection = array() ) {

		// Don't output a dropdown list if no connection exists.
		if ( empty( $connection_id ) || empty( $connection['account_id'] ) ) {
			return '';
		}

		// Get API instance.
		$api = $this->get_api_instance( $connection['account_id'] );

		// Bail if we couldn't fetch the API instance.
		if ( is_wp_error( $api ) ) {
			return '';
		}

		// Fetch Forms.
		// We use refresh() to ensure we get the latest data, as we're in the admin interface
		// and need to populate the select dropdown.
		$forms = new Integrate_ConvertKit_WPForms_Resource_Forms( $api, $connection['account_id'] );
		$forms->refresh();

		// Fetch Sequences.
		// We use refresh() to ensure we get the latest data, as we're in the admin interface
		// and need to populate the select dropdown.
		$sequences = new Integrate_ConvertKit_WPForms_Resource_Sequences( $api, $connection['account_id'] );
		$sequences->refresh();

		// Fetch Tags.
		// We use refresh() to ensure we get the latest data, as we're in the admin interface
		// and need to populate the select dropdown.
		$tags = new Integrate_ConvertKit_WPForms_Resource_Tags( $api, $connection['account_id'] );
		$tags->refresh();

		// Get the selected ConvertKit subscribe setting, if one was already defined.
		$value = ! empty( $connection['list_id'] ) ? $connection['list_id'] : '';

		// Output <select> dropdown.
		ob_start();
		require INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/views/backend/settings-form-marketing-forms-dropdown.php';
		return ob_get_clean();

	}

	/**
	 * Enqueue CSS for the integration screen.
	 *
	 * @since   1.7.0
	 */
	public function enqueue_settings_assets() {

		// Enqueue CSS.
		wp_enqueue_style( 'integrate-convertkit-wpforms-admin', INTEGRATE_CONVERTKIT_WPFORMS_URL . 'resources/backend/css/admin.css', array(), INTEGRATE_CONVERTKIT_WPFORMS_VERSION );

	}

	/**
	 * Enqueue JS for the form builder.
	 *
	 * @since 1.8.4
	 */
	public function enqueue_builder_assets() {

		wp_enqueue_script( 'integrate-convertkit-wpforms-builder', INTEGRATE_CONVERTKIT_WPFORMS_URL . 'resources/backend/js/form-builder.js', array(), INTEGRATE_CONVERTKIT_WPFORMS_VERSION, true );

	}

	/**
	 * Displays a notice on the integration screen, depending on whether OAuth succeeded or not.
	 *
	 * @since   1.7.0
	 */
	public function maybe_display_notice() {

		// Display success message if required.
		if ( array_key_exists( 'success', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			\WPForms\Admin\Notice::success(
				esc_html__( 'Kit: Account connected successfully.', 'integrate-convertkit-wpforms' )
			);
		}

		// Display error message if required.
		if ( array_key_exists( 'error_description', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			\WPForms\Admin\Notice::error(
				sprintf(
					'%s %s',
					esc_html__( 'Kit: ', 'integrate-convertkit-wpforms' ),
					sanitize_text_field( wp_unslash( $_REQUEST['error_description'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
				)
			);
		}

	}

	/**
	 * Requests an access token via OAuth, if an authorization code and verifier are included in the request.
	 *
	 * @since   1.7.0
	 */
	public function maybe_get_and_store_access_token() {

		// Bail if we're not on the integration screen.
		if ( ! array_key_exists( 'page', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) !== 'wpforms-settings' ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! array_key_exists( 'view', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( $_REQUEST['view'] !== 'integrations' ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		// Bail if no authorization code is included in the request.
		if ( ! array_key_exists( 'code', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		// Sanitize token.
		$authorization_code = sanitize_text_field( wp_unslash( $_REQUEST['code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		// Exchange the authorization code and verifier for an access token.
		$api    = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI
		);
		$result = $api->get_access_token( $authorization_code );

		// Redirect with an error if we could not fetch the access token.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				$this->get_integrations_url(
					array(
						'error' => $result->get_error_code(),
					)
				)
			);
			exit();
		}

		// Re-initialize the API with the tokens.
		$api = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI,
			sanitize_text_field( $result['access_token'] ),
			sanitize_text_field( $result['refresh_token'] )
		);

		// Fetch account.
		$account = $api->get_account();

		// Redirect with an error if we could not fetch the account.
		if ( is_wp_error( $account ) ) {
			wp_safe_redirect(
				$this->get_integrations_url(
					array(
						'error' => $account->get_error_code(),
					)
				)
			);
			exit();
		}

		// Update the provider's settings and return its unique ID.
		wpforms_update_providers_options(
			$this->slug,
			array(
				'access_token'  => sanitize_text_field( $result['access_token'] ),
				'refresh_token' => sanitize_text_field( $result['refresh_token'] ),
				'token_expires' => ( $result['created_at'] + $result['expires_in'] ),
				'label'         => $account['account']['name'],
				'date'          => time(),
			),
			'kit-' . $account['account']['id']
		);

		// If this request is served in a popup window (i.e. from the form builder),
		// serve a view that will close the popup.
		if ( array_key_exists( 'convertkit-modal', $_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			require INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/views/backend/close-modal.php';
			exit();
		}

		// Reload the integrations screen, which will now show the connection.
		wp_safe_redirect(
			$this->get_integrations_url(
				array(
					'success' => '1',
				)
			)
		);
		exit();

	}

	/**
	 * Deletes cached resources when a ConvertKit account is disconnected in WPForms
	 * by the user clicking `Disconnect`.
	 *
	 * @since   1.7.0
	 */
	public function delete_resource_cache() {

		// Run a security check.
		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ) {
			return;
		}

		// Check for permissions.
		if ( ! wpforms_current_user_can() ) {
			return;
		}

		// Bail if no provider supplied.
		if ( empty( $_POST['provider'] ) || empty( $_POST['key'] ) ) {
			return;
		}

		// Sanitize data.
		$account_id = sanitize_text_field( wp_unslash( $_POST['key'] ) );

		// Get API instance.
		$api = $this->get_api_instance( $account_id );

		// Delete cached resources.
		$resource_forms         = new Integrate_ConvertKit_WPForms_Resource_Forms( $api, $account_id );
		$resource_sequences     = new Integrate_ConvertKit_WPForms_Resource_Sequences( $api, $account_id );
		$resource_tags          = new Integrate_ConvertKit_WPForms_Resource_Tags( $api, $account_id );
		$resource_custom_fields = new Integrate_ConvertKit_WPForms_Resource_Custom_Fields( $api, $account_id );
		$resource_forms->delete();
		$resource_sequences->delete();
		$resource_tags->delete();
		$resource_custom_fields->delete();
	}

	/**
	 * Returns available field mappings between a WPForms Form and ConvertKit
	 * Form Fields (First Name, Email), Custom Fields and Tags.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $connection_id      WPForms ConvertKit Connection ID (connection_ID, where ID is defined in api_auth()).
	 * @param   string $account_id         WPForms ConvertKit Account ID (the ID defined in api_auth()).
	 * @param   string $list_id            WPForms ConvertKit Form ID (the ConvertKit Form selected to send entries to).
	 * @return  WP_Error|array
	 */
	public function api_fields( $connection_id = '', $account_id = '', $list_id = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		$provider_fields = array(
			array(
				'name'       => __( 'Kit: Email', 'integrate-convertkit-wpforms' ),
				'field_type' => 'email',
				'req'        => '1',
				'tag'        => 'email',
			),
			array(
				'name'       => __( 'Kit: First Name', 'integrate-convertkit-wpforms' ),
				'field_type' => 'text',
				'tag'        => 'name',
			),
			array(
				'name'       => __( 'Kit: Tag', 'integrate-convertkit-wpforms' ),
				'field_type' => 'text',
				'tag'        => 'tag',
			),
		);

		// Get API instance.
		$api = $this->get_api_instance( $account_id );

		// Just return fields if we couldn't fetch the API instance.
		if ( is_wp_error( $api ) ) {
			return $provider_fields;
		}

		// Fetch Custom Fields.
		$resource_custom_fields = new Integrate_ConvertKit_WPForms_Resource_Custom_Fields( $api, $account_id );
		$custom_fields          = $resource_custom_fields->refresh();

		// Just return fields if no custom fields exist in ConvertKit.
		if ( ! count( $custom_fields ) ) {
			return $provider_fields;
		}

		// Add Custom Fields to available field mappings.
		foreach ( $custom_fields as $custom_field ) {
			$provider_fields[] = array(
				'name'       => sprintf(
					/* translators: ConvertKit Custom Field label */
					__( 'Kit: Custom Field: %s', 'integrate-convertkit-wpforms' ),
					$custom_field['label']
				),
				'field_type' => 'text',
				'tag'        => 'custom_field_' . $custom_field['key'],
			);
		}

		return $provider_fields;

	}

	/**
	 * Fetches the API for the given WPForms Account ID.
	 *
	 * If it has not been initialized, performs initialization of the ConvertKit API
	 * class with the account's API Key and Secret.
	 *
	 * @since   1.5.0
	 *
	 * @param   string $account_id         WPForms ConvertKit Account ID (the ID defined in api_auth()).
	 * @return  WP_Error|Integrate_ConvertKit_WPForms_API
	 */
	private function get_api_instance( $account_id ) {

		// Return API instance if already configured.
		if ( array_key_exists( $account_id, $this->api ) ) {
			return $this->api[ $account_id ];
		}

		// Get all registered providers in WPForms.
		$providers = wpforms_get_providers_options();

		// Bail if no ConvertKit providers were registered.
		if ( ! array_key_exists( $this->slug, $providers ) ) {
			return $this->error( __( 'No Kit connections exist.', 'integrate-convertkit-wpforms' ) );
		}

		// Bail if the requested connection does not exist.
		if ( ! array_key_exists( $account_id, $providers[ $this->slug ] ) ) {
			return $this->error(
				sprintf(
					/* translators: WPForms ConvertKit Account ID */
					__( 'The Kit connection with ID %s was unregistered.', 'integrate-convertkit-wpforms' ),
					$account_id
				)
			);
		}

		// Setup the API instance for this connection.
		$this->api[ $account_id ] = new Integrate_ConvertKit_WPForms_API(
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID,
			INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI,
			$providers[ $this->slug ][ $account_id ]['access_token'],
			$providers[ $this->slug ][ $account_id ]['refresh_token']
		);

		// Return instance.
		return $this->api[ $account_id ];

	}

	/**
	 * Returns the URL for the WPForms > Settings > Integrations screen.
	 *
	 * @since   1.7.0
	 *
	 * @param   array $args   Optional URL arguments to include in the URL.
	 * @return  string
	 */
	private function get_integrations_url( $args = array() ) {

		return add_query_arg(
			array_merge(
				array(
					'page' => 'wpforms-settings',
					'view' => 'integrations',
				),
				$args
			),
			admin_url( 'admin.php' )
		);

	}

}

new Integrate_ConvertKit_WPForms();
