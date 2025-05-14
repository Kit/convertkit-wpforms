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

		// Call parent initialization function.
		parent::init();

	}

}
