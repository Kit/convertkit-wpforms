<?php

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Tests that the Plugin works when configuring and using WPForms.
 *
 * @since   1.5.0
 */
class FormCest
{
	/**
	 * Holds the WPForms Account ID with the ConvertKit API connection
	 * for the test.
	 *
	 * @since   1.7.2
	 *
	 * @var     int
	 */
	public $accountID = 0;

	/**
	 * Run common actions before running the test functions in this class.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function _before(EndToEndTester $I)
	{
		$I->activateConvertKitPlugin($I);
		$I->activateThirdPartyPlugin($I, 'wpforms-lite');
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Submitting the Form on the frontend web site results in the email address subscribing to the ConvertKit Form.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormToConvertKitFormMapping(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			$_ENV['CONVERTKIT_API_FORM_NAME']
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Load page with Form so grabFromCurrentUrl() returns correct URL.
		$I->amOnPage('/?p=' . $pageID);

		// Check that the subscriber has the expected form and referrer value set.
		$I->apiCheckSubscriberHasForm(
			$I,
			$subscriberID,
			$_ENV['CONVERTKIT_API_FORM_ID'],
			$_ENV['WORDPRESS_URL'] . $I->grabFromCurrentUrl()
		);
	}

	/**
	 * Test that the connection can be added in the WPForms Form Builder,
	 * if no connection exists at WPForms > Settings > Integrations.
	 *
	 * @since   1.8.4
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testAddNewConnectionInFormBuilder(EndToEndTester $I)
	{
		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Click Marketing icon.
		$I->waitForElementVisible('.wpforms-panel-providers-button');
		$I->click('.wpforms-panel-providers-button');

		// Click Kit tab.
		$I->click('#wpforms-panel-providers a.wpforms-panel-sidebar-section-convertkit');

		// Click Add New Connection.
		$I->click('Add New Connection');

		// Define name for connection.
		$I->waitForElementVisible('.jconfirm-content');
		$I->fillField('#provider-connection-name', 'Kit');
		$I->click('OK');

		// Wait for Connect to Kit button to display, as no connections exist at WPForms > Settings > Integrations.
		$I->waitForElementVisible('a[data-provider="convertkit"]');

		// Click the button and confirm the OAuth popup displays.
		$I->click('a[data-provider="convertkit"]');

		// Switch to next browser tab, as the link opens in a new tab.
		$I->switchToNextTab();

		// Confirm the Kit login screen loaded.
		$I->waitForElementVisible('input[name="user[email]"]');

		// Add the provider tokens programmatically, as if the user completed OAuth.
		$accountID = $I->setupWPFormsIntegration($I);

		// Close tab, which will trigger the form builder to save and reload, showing the new connection.
		$I->closeTab();
		$I->wait(3);

		// Wait for save to complete.
		$I->waitForElementVisible('#wpforms-save:not(:disabled)');

		// Click Add New Connection.
		$I->click('Add New Connection');

		// Define name for connection.
		$I->waitForElementVisible('.jconfirm-content');
		$I->fillField('#provider-connection-name', 'Kit');
		$I->click('OK');

		// Get the connection ID to confirm the connection was added.
		$I->waitForElementVisible('.wpforms-provider-connections .wpforms-provider-connection');
		$connectionID = $I->grabAttributeFrom('.wpforms-provider-connections .wpforms-provider-connection', 'data-connection_id');

		// Confirm the connection was added.
		$I->waitForElementVisible('div[data-connection_id="' . $connectionID . '"] .wpforms-provider-fields', 30);

		// Click Save.
		$I->click('#wpforms-save');

		// Wait for save to complete.
		$I->waitForElementVisible('#wpforms-save:not(:disabled)');
	}

	/**
	 * Tests that the connection configured when editing a WPForms Form at Marketing > Kit is retained when:
	 * - Connects to Kit at Settings > Integrations,
	 * - Configures the connection at WPForms Form > Marketing > Kit,
	 * - Disconnects from Kit at Settings > Integrations,
	 * - Connects (again) to the same Kit at Settings > Integrations
	 * - Observe the connection at WPForms Form > Marketing > Kit is retained.
	 *
	 * @since   1.7.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testConnectionRetainedWhenAccountReconnected(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$formID = $this->_wpFormsSetupFormOnly(
			$I,
			$_ENV['CONVERTKIT_API_FORM_NAME']
		);

		// Disconnect from Kit at Settings > Integrations.
		$I->deleteWPFormsIntegration($I);

		// Connect to Kit at Settings > Integrations.
		$I->setupWPFormsIntegration($I);

		// Edit the WPForms Form, confirming the connection still exists.
		$I->amOnAdminPage('admin.php?page=wpforms-builder&view=providers&form_id=' . $formID);
		$I->waitForElementVisible('div[data-provider="convertkit"]');
		$I->see('Select Account');
		$I->see('Kit Form');
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Submitting the Form on the frontend web site results in the email address subscribing to the ConvertKit Legacy Form.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormToConvertKitLegacyFormMapping(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			$_ENV['CONVERTKIT_API_LEGACY_FORM_NAME']
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress
		);

		// Check API to confirm subscriber was sent.
		$I->apiCheckSubscriberExists($I, $emailAddress, 'First');
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Submitting the Form on the frontend web site results in the email address subscribing to the ConvertKit Tag.
	 *
	 * @since   1.7.2
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormToConvertKitTagMapping(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			$_ENV['CONVERTKIT_API_TAG_NAME']
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tag set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Submitting the Form on the frontend web site results in the email address subscribing to the ConvertKit Sequence.
	 *
	 * @since   1.7.2
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormToConvertKitSequenceMapping(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			$_ENV['CONVERTKIT_API_SEQUENCE_NAME']
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tag set.
		$I->apiCheckSubscriberHasSequence($I, $subscriberID, $_ENV['CONVERTKIT_API_SEQUENCE_ID']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Submitting the Form on the frontend web site results in the email address subscribing only (with no form/tag/sequence).
	 *
	 * @since   1.7.2
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormSubscribeOnly(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe'
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Adding a field whose value will be a valid ConvertKit Tag ID.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithTagID(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				$_ENV['CONVERTKIT_API_TAG_ID'],
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				$_ENV['CONVERTKIT_API_TAG_ID'],
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tag set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid API Key and valid Form ID,
	 * - Adding a field whose value will be an invalid ConvertKit Tag ID.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithInvalidTagID(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				'1111', // A fake Tag ID.
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				'1111',
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Confirm no tags were added to the subscriber, as the submitted tag doesn't exist in ConvertKit.
		$I->apiCheckSubscriberHasNoTags($I, $subscriberID);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Adding a field whose values will be valid ConvertKit Tag IDs.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithTagIDs(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				$_ENV['CONVERTKIT_API_TAG_ID'],
				$_ENV['CONVERTKIT_API_TAG_ID_2'],
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				$_ENV['CONVERTKIT_API_TAG_ID'],
				$_ENV['CONVERTKIT_API_TAG_ID_2'],
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tags set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID_2']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Adding a field whose value will be a valid ConvertKit Tag Name.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithTagName(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				$_ENV['CONVERTKIT_API_TAG_NAME'],
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				$_ENV['CONVERTKIT_API_TAG_NAME'],
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tag set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid API Key and valid Form ID,
	 * - Adding a field whose value will be an invalid ConvertKit Tag Name.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithInvalidTagName(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				'fake-tag-name', // A fake Tag Name.
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				'fake-tag-name', // A fake Tag Name.
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Confirm no tags were added to the subscriber, as the submitted tag doesn't exist in ConvertKit.
		$I->apiCheckSubscriberHasNoTags($I, $subscriberID);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid ConvertKit Connection,
	 * - Adding a field whose values will be valid ConvertKit Tag Names.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithTagNames(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			[
				$_ENV['CONVERTKIT_API_TAG_NAME'],
				$_ENV['CONVERTKIT_API_TAG_NAME_2'],
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			[
				$_ENV['CONVERTKIT_API_TAG_NAME'],
				$_ENV['CONVERTKIT_API_TAG_NAME_2'],
			]
		);

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress, 'First');

		// Check API to confirm subscriber has Tags set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID_2']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid API Key and valid Form ID,
	 * - Adding a field whose value will be stored against a ConvertKit Custom Field.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithCustomField(EndToEndTester $I)
	{
		// Setup WPForms Form and configuration for this test.
		$pageID = $this->_wpFormsSetupForm(
			$I,
			'Subscribe',
			false,
			[  // Custom Fields.
				$_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME'] => 'Comment or Message', // ConvertKit Custom Field --> WPForms Field Name mapping.
			]
		);

		// Define email address for this test.
		$emailAddress = $I->generateEmailAddress();

		// Complete and submit WPForms Form.
		$this->_wpFormsCompleteAndSubmitForm(
			$I,
			$pageID,
			$emailAddress,
			false,
			'Notes'
		);

		// Check API to confirm subscriber was sent and data mapped to fields correctly.
		$I->apiCheckSubscriberExists(
			$I,
			$emailAddress,
			'First',
			[
				$_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME'] => 'Notes',
			]
		);
	}

	/**
	 * Maps the given resource name to the created WPForms Form,
	 * embeds the shortcode on a new Page, returning the Page ID.
	 *
	 * @since   1.7.2
	 *
	 * @param   EndToEndTester $I             Tester.
	 * @param   string         $optionName    <select> option name.
	 * @param   bool|array     $tags          Values to use for tags.
	 * @param   bool|array     $customFields  Custom field key / value pairs.
	 * @return  int                             Page ID
	 */
	private function _wpFormsSetupForm(EndToEndTester $I, $optionName, $tags = false, $customFields = false)
	{
		// Create Form.
		$wpFormsID = $this->_wpFormsSetupFormOnly($I, $optionName, $tags, $customFields);

		// Create a Page with the WPForms shortcode as its content.
		return $I->createPageWithWPFormsShortcode($I, $wpFormsID);
	}

	/**
	 * Maps the given resource name to the created WPForms Form,
	 * embeds the shortcode on a new Page, returning the Form ID.
	 *
	 * @since   1.7.8
	 *
	 * @param   EndToEndTester $I             Tester.
	 * @param   string         $optionName    <select> option name.
	 * @param   bool|array     $tags          Values to use for tags.
	 * @param   bool|array     $customFields  Custom field key / value pairs.
	 * @return  int                             Form ID
	 */
	private function _wpFormsSetupFormOnly(EndToEndTester $I, $optionName, $tags = false, $customFields = false)
	{
		// Define connection with valid API credentials.
		$this->accountID = $I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm(
			$I,
			$tags
		);

		// Configure ConvertKit on Form.
		$I->configureConvertKitSettingsOnForm(
			$I,
			$wpFormsID,
			$optionName,
			'Name (First)',
			'Email',
			( $customFields ? $customFields : false ),
			( $tags ? 'Tag ID' : false ) // Name of Tag Field in WPForms.
		);

		// Check that the resources are cached with the correct key.
		$I->seeCachedResourcesInDatabase($I, $this->accountID);

		return $wpFormsID;
	}

	/**
	 * Fills out the WPForms Form on the given WordPress Page ID,
	 * and submits it, confirming them form submitted without errors.
	 *
	 * @since   1.7.3
	 *
	 * @param   EndToEndTester $I             Tester.
	 * @param   int            $pageID        Page ID.
	 * @param   string         $emailAddress  Email Address.
	 * @param   bool|array     $tags          Tag checkbox value(s) to select.
	 * @param   bool|string    $customField   Custom field value to enter.
	 */
	private function _wpFormsCompleteAndSubmitForm(EndToEndTester $I, int $pageID, string $emailAddress, $tags = false, $customField = false)
	{
		// Logout as the WordPress Administrator.
		$I->logOut();

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', 'First');
		$I->fillField('input.wpforms-field-name-last', 'Last');
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);

		// Select Tag ID(s) if defined.
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$I->checkOption('.wpforms-field-checkbox input[value="' . $tag . '"]');
			}
		}

		// Complete textarea if custom field value defined.
		if ( $customField ) {
			$I->fillField('.wpforms-field-textarea textarea', $customField);
		}

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm submission was successful.
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');

		// Check that a review request was created.
		$I->reviewRequestExists($I);

		// Disconnect the account.
		$I->disconnectAccount($I, $this->accountID);

		// Check that the resources are no longer cached under the given account ID.
		$I->dontSeeCachedResourcesInDatabase($I, $this->accountID);
	}

	/**
	 * Deactivate and reset Plugin(s) after each test, if the test passes.
	 * We don't use _after, as this would provide a screenshot of the Plugin
	 * deactivation and not the true test error.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function _passed(EndToEndTester $I)
	{
		$I->deactivateConvertKitPlugin($I);

		// We don't use deactivateThirdPartyPlugin(), as this checks for PHP warnings/errors.
		// WPForms throws a 502 bad gateway on deactivation, which is outside of our control
		// and would result in the test not completing.
		$I->amOnPluginsPage();
		$I->deactivatePlugin('wpforms-lite');
	}
}
