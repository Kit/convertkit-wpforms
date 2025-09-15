<?php

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Tests that the Creator Network Recommendations settings work with a WPForms Form
 *
 * @since   1.5.8
 */
class RecommendationsCest
{
	/**
	 * Run common actions before running the test functions in this class.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function _before(EndToEndTester $I)
	{
		$I->activateConvertKitPlugin($I);
		$I->activateThirdPartyPlugin($I, 'wpforms-lite');
	}

	/**
	 * Tests that the 'Enable Creator Network Recommendations' option on a Form's settings
	 * is not displayed when no credentials are specified at WPForms > Settings > Integrations > Kit.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreatorNetworkRecommendationsOptionWhenNoCredentials(EndToEndTester $I)
	{
		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Confirm that the Form Settings display the expected error message.
		$I->seeWPFormsSettingMessage(
			$I,
			wpFormID: $wpFormsID,
			message: 'Please connect your Kit account on the <a href="' . $_ENV['WORDPRESS_URL'] . '/wp-admin/admin.php?page=wpforms-settings&amp;view=integrations" target="_blank">integrations screen</a>'
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded.
		$I->dontSeeCreatorNetworkRecommendationsScript($I, $pageID);
	}

	/**
	 * Tests that the 'Enable Creator Network Recommendations' option on a Form's settings
	 * is not displayed when invalid API Key and Secret are specified at WPForms > Settings > Integrations > Kit.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreatorNetworkRecommendationsOptionWhenInvalidCredentials(EndToEndTester $I)
	{
		// Setup Plugin with invalid API Key and Secret.
		$accountID = $I->setupWPFormsIntegration(
			$I,
			accessToken: 'fakeAccessToken',
			refreshToken: 'fakeRefreshToken'
		);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Enable Creator Network Recommendations on the form's settings using the account specified.
		$I->enableWPFormsSettingCreatorNetworkRecommendations(
			$I,
			wpFormID: $wpFormsID,
			accountID: $accountID
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded, as the API Key and Secret are invalid.
		$I->dontSeeCreatorNetworkRecommendationsScript($I, $pageID);
	}

	/**
	 * Tests that the 'Enable Creator Network Recommendations' option on a Form's settings
	 * is not displayed when valid API Key and Secret are specified at WPForms > Settings > Integrations > Kit.
	 * but the ConvertKit account does not have the Creator Network enabled.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreatorNetworkRecommendationsOptionWhenDisabledOnConvertKitAccount(EndToEndTester $I)
	{
		// Setup Plugin with API Key and Secret for ConvertKit Account that does not have the Creator Network enabled.
		$accountID = $I->setupWPFormsIntegration(
			$I,
			accessToken: $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN_NO_DATA'],
			refreshToken: $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN_NO_DATA'],
			accountID: $_ENV['CONVERTKIT_API_ACCOUNT_ID_NO_DATA']
		);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Enable Creator Network Recommendations on the form's settings using the account specified.
		$I->enableWPFormsSettingCreatorNetworkRecommendations($I, $wpFormsID, $accountID);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded.
		$I->dontSeeCreatorNetworkRecommendationsScript($I, $pageID);
	}

	/**
	 * Tests that the 'Enable Creator Network Recommendations' option on a Form's settings
	 * is displayed and saves correctly when valid API Key and Secret are specified at WPForms > Settings > Integrations > Kit,
	 * and the ConvertKit account has the Creator Network enabled.  Viewing and submitting the Form does not
	 * display the Creator Network Recommendations modal, because the form submission will reload the page,
	 * which isn't supported right now.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreatorNetworkRecommendationsWithAJAXDisabled(EndToEndTester $I)
	{
		// Setup Plugin.
		$accountID = $I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Enable Creator Network Recommendations on the form's settings.
		$I->enableWPFormsSettingCreatorNetworkRecommendations(
			$I,
			wpFormID: $wpFormsID,
			accountID: $accountID
		);

		// Disable AJAX.
		$I->disableAJAXFormSubmissionSetting($I, $wpFormsID);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded.
		$I->dontSeeCreatorNetworkRecommendationsScript($I, $pageID);
	}

	/**
	 * Tests that the 'Enable Creator Network Recommendations' option on a Form's settings
	 * is displayed and saves correctly when valid API Key and Secret are specified at WPForms > Settings > Integrations > Kit,
	 * and the ConvertKit account has the Creator Network enabled.  Viewing and submitting the Form then correctly
	 * displays the Creator Network Recommendations modal.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCreatorNetworkRecommendationsWithAJAXEnabled(EndToEndTester $I)
	{
		// Setup Plugin.
		$accountID = $I->setupWPFormsIntegration($I);

		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Enable Creator Network Recommendations on the form's settings.
		$I->enableWPFormsSettingCreatorNetworkRecommendations(
			$I,
			wpFormID: $wpFormsID,
			accountID: $accountID
		);

		// Create a Page with the WPForms shortcode as its content.
		$pageID = $I->createPageWithWPFormsShortcode($I, $wpFormsID);

		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was loaded.
		$I->seeCreatorNetworkRecommendationsScript($I, $pageID);

		// Define Name and Email Address for this Test.
		$firstName    = 'First';
		$lastName     = 'Last';
		$emailAddress = $I->generateEmailAddress();

		// Complete Form Fields.
		$I->fillField('input.wpforms-field-name-first', $firstName);
		$I->fillField('input.wpforms-field-name-last', $lastName);
		$I->fillField('.wpforms-field-email input[type=email]', $emailAddress);

		// Submit Form.
		$I->wait(2);
		$I->click('Submit');

		// Wait for Creator Network Recommendations modal to display.
		$I->waitForElementVisible('.formkit-modal');
		$I->switchToIFrame('.formkit-modal iframe');
		$I->waitForElementVisible('main[data-component="Page"]');
		$I->switchToIFrame();

		// Close the modal.
		$I->click('.formkit-modal button.formkit-close');

		// Confirm that the underlying WPForms Form submitted successfully.
		$I->waitForElementNotVisible('.formkit-modal');
		$I->waitForElementVisible('.wpforms-confirmation-scroll');
		$I->seeInSource('Thanks for contacting us! We will be in touch with you shortly.');
	}

	/**
	 * Deactivate and reset Plugin(s) after each test, if the test passes.
	 * We don't use _after, as this would provide a screenshot of the Plugin
	 * deactivation and not the true test error.
	 *
	 * @since   1.5.8
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function _passed(EndToEndTester $I)
	{
		$I->deactivateConvertKitPlugin($I);
		$I->deactivateThirdPartyPlugin($I, 'wpforms-lite');
	}
}
