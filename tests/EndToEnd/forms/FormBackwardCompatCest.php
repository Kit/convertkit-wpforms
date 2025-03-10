<?php

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Tests that the Plugin works when configuring and using WPForms
 * with tag and custom field CSS classes, to ensure backward
 * compatibility is maintained with 1.4.* and older.
 *
 * @since   1.5.0
 */
class FormBackwardCompatCest
{
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
	 * - Registering a ConvertKit integration connection,
	 * - Creating a WPForms Form,
	 * - Adding a field with the `ck-tag` CSS class, whose value will be a valid ConvertKit Tag ID.
	 * - Submitting the Form on the frontend web site
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithTagClass(EndToEndTester $I)
	{
		// Define connection with valid API credentials.
		$I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Configure ConvertKit API on Form.
		$I->configureConvertKitSettingsOnForm(
			$I,
			$wpFormsID,
			$_ENV['CONVERTKIT_API_FORM_NAME'],
			'Name (First)',
			'Email'
		);

		// Add `ck-tag` CSS class on Tag Field.
		$I->configureWPFormsBackwardCompatClasses(
			$I,
			$wpFormsID,
			false,
			true
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Define Name and Email Address for this Test.
		$firstName    = 'First';
		$lastName     = 'Last';
		$emailAddress = $I->generateEmailAddress();

		// Logout as the WordPress Administrator.
		$I->logOut();

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', $firstName);
		$I->fillField('input.wpforms-field-name-last', $lastName);
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);
		$I->fillField('.ck-tag input[type=text]', $_ENV['CONVERTKIT_API_TAG_ID']);

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm submission was successful.
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');

		// Check API to confirm subscriber was sent.
		$subscriberID = $I->apiCheckSubscriberExists($I, $emailAddress);

		// Check API to confirm subscriber has Tag set.
		$I->apiCheckSubscriberHasTag($I, $subscriberID, $_ENV['CONVERTKIT_API_TAG_ID']);
	}

	/**
	 * Test that the Plugin works when:
	 * - Registering a ConvertKit integration connection,
	 * - Creating a WPForms Form,
	 * - Adding a field with the `ck-tag` CSS class, whose value will be an invalid ConvertKit Tag ID.
	 * - Submitting the Form on the frontend web site
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithInvalidTag(EndToEndTester $I)
	{
		// Define connection with valid API credentials.
		$I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Configure ConvertKit API on Form.
		$I->configureConvertKitSettingsOnForm(
			$I,
			$wpFormsID,
			$_ENV['CONVERTKIT_API_FORM_NAME'],
			'Name (First)',
			'Email'
		);

		// Add `ck-tag` CSS class on Tag Field.
		$I->configureWPFormsBackwardCompatClasses(
			$I,
			$wpFormsID,
			false,
			true
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Define Name and Email Address for this Test.
		$firstName    = 'First';
		$lastName     = 'Last';
		$emailAddress = $I->generateEmailAddress();

		// Logout as the WordPress Administrator.
		$I->logOut();

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', $firstName);
		$I->fillField('input.wpforms-field-name-last', $lastName);
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);
		$I->fillField('.ck-tag input[type=text]', '1111');

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm submission was successful.
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');

		// Check API to confirm subscriber was sent.
		$I->apiCheckSubscriberExists($I, $emailAddress, $firstName);
	}

	/**
	 * Test that the Plugin works when:
	 * - Registering a ConvertKit integration connection,
	 * - Creating a WPForms Form,
	 * - Adding a field with the `ck-custom-notes` CSS class, whose value will be the Custom Field's value,
	 * - Submitting the Form on the frontend web site
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithCustomField(EndToEndTester $I)
	{
		// Define connection with valid API credentials.
		$I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Configure ConvertKit API on Form.
		$I->configureConvertKitSettingsOnForm(
			$I,
			$wpFormsID,
			$_ENV['CONVERTKIT_API_FORM_NAME'],
			'Name (First)',
			'Email'
		);

		// Add `ck-custom-{name}` CSS class on Field.
		$I->configureWPFormsBackwardCompatClasses(
			$I,
			$wpFormsID,
			$_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME']
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Define Name and Email Address for this Test.
		$firstName    = 'First';
		$lastName     = 'Last';
		$emailAddress = $I->generateEmailAddress();
		$customFields = [
			$_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME'] => 'Notes',
		];

		// Logout as the WordPress Administrator.
		$I->logOut();

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', $firstName);
		$I->fillField('input.wpforms-field-name-last', $lastName);
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);
		$I->fillField('.ck-custom-' . $_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME'] . ' textarea', $customFields[ $_ENV['CONVERTKIT_API_CUSTOM_FIELD_NAME'] ]);

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm submission was successful.
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');

		// Check API to confirm subscriber was sent and data mapped to fields correctly.
		$I->apiCheckSubscriberExists($I, $emailAddress, $firstName, $customFields);
	}

	/**
	 * Test that the Plugin works when:
	 * - Creating a WPForms Form,
	 * - Adding a valid API Key and valid Form ID,
	 * - Adding a field whose value will be stored against a ConvertKit Custom Field.
	 * - Submitting the Form on the frontend web site results works.
	 *
	 * @since   1.4.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreateFormWithInvalidCustomField(EndToEndTester $I)
	{
		// Define connection with valid API credentials.
		$I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Configure ConvertKit API on Form.
		$I->configureConvertKitSettingsOnForm(
			$I,
			$wpFormsID,
			$_ENV['CONVERTKIT_API_FORM_NAME'],
			'Name (First)',
			'Email'
		);

		// Add `ck-custom-{name}` CSS class on Field.
		$I->configureWPFormsBackwardCompatClasses(
			$I,
			$wpFormsID,
			'fakeCustomFieldName'
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Define Name and Email Address for this Test.
		$firstName    = 'First';
		$lastName     = 'Last';
		$emailAddress = $I->generateEmailAddress();
		$customFields = [
			'fakeCustomFieldName' => 'Notes',
		];

		// Logout as the WordPress Administrator.
		$I->logOut();

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', $firstName);
		$I->fillField('input.wpforms-field-name-last', $lastName);
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);
		$I->fillField('.ck-custom-fakeCustomFieldName textarea', $customFields['fakeCustomFieldName']);

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm submission was successful.
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');

		// Check API to confirm subscriber was sent.
		$I->apiCheckSubscriberExists($I, $emailAddress, $firstName);
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
