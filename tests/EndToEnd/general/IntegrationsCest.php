<?php

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Tests that the ConvertKit Integration options work at WPForms > Settings > Integrations
 *
 * @since   1.5.0
 */
class IntegrationsCest
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
	 * Test that adding a Kit account to the Kit integration sections
	 * works when connecting, reconnecting and disconnecting.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testAddIntegrationWithValidCredentials(EndToEndTester $I)
	{
		// Load WPForms > Settings > Integrations.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrations');

		// Expand ConvertKit integration section.
		$I->click('#wpforms-integration-convertkit');

		// Click Add New Account button.
		$I->click('#wpforms-integration-convertkit a[data-provider="convertkit"]');

		// Check that a link to the OAuth auth screen exists.
		$I->seeInSource('<a href="https://app.kit.com/oauth/authorize?client_id=' . $_ENV['CONVERTKIT_OAUTH_CLIENT_ID'] . '&amp;response_type=code&amp;redirect_uri=' . urlencode( $_ENV['KIT_OAUTH_REDIRECT_URI'] ) );

		// Check the state parameter returns to the integrations screen, with a nonce.
		$I->waitForElementVisible('.wpforms-settings-provider-accounts-connect a');
		$I->apiCheckOAuthURLReturnsToIntegrationsScreen($I, $I->grabAttributeFrom('.wpforms-settings-provider-accounts-connect a', 'href'));

		// Click Connect to Kit button.
		$I->click('Connect to Kit');

		// Confirm the ConvertKit hosted OAuth login screen is displayed.
		$I->waitForElementVisible('body.sessions');
		$I->seeInSource('oauth/authorize?client_id=' . $_ENV['CONVERTKIT_OAUTH_CLIENT_ID']);

		// Act as if we completed OAuth.
		$I->setupWPFormsIntegration($I);

		// Re-load the integrations screen.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrations');

		// Confirm that the 'Connected' element is visible.
		$I->seeElementInDOM('#wpforms-integration-convertkit .wpforms-settings-provider-info .connected-indicator');
		$I->click('#wpforms-integration-convertkit');
		$I->wait(3);
		$I->waitForElementVisible('#wpforms-integration-convertkit .wpforms-settings-provider-accounts-list');
		$I->see('Connected on:');

		// Confirm that the Access Token and Refresh Token were saved to the database.
		// This sanity checks that we didn't accidentally save the API Key to the API Secret field as we did in 1.5.7 and lower.
		$I->assertTrue($I->checkWPFormsIntegrationExists($I, $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'], $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN']));

		// Confirm that the connection can be reconnected.
		$I->seeElementInDOM('a.convertkit-reconnect');
		$reconnectURL = $I->grabAttributeFrom('a.convertkit-reconnect', 'href');
		$I->assertStringContainsString(
			'https://app.kit.com/oauth/authorize?client_id=' . $_ENV['CONVERTKIT_OAUTH_CLIENT_ID'] . '&response_type=code&redirect_uri=' . urlencode( $_ENV['KIT_OAUTH_REDIRECT_URI'] ),
			$reconnectURL
		);
		$I->apiCheckOAuthURLReturnsToIntegrationsScreen($I, $reconnectURL);
	}

	/**
	 * Test that adding a ConvertKit account to the ConvertKit integration sections
	 * shows the expected error message when supplying invalid API credentials.
	 *
	 * @since   1.5.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testAddIntegrationWithInvalidAPICredentials(EndToEndTester $I)
	{
		// Define OAuth error code and description.
		$error            = 'access_denied';
		$errorDescription = 'The resource owner or authorization server denied the request.';

		// Act as if OAuth failed i.e. the user didn't authenticate.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrations&error=' . $error . '&error_description=' . urlencode($errorDescription));

		// Confirm error notification is displayed.
		$I->seeElement('div.notice.notice-error');
		$I->see($errorDescription);
	}

	/**
	 * Test that an error notification is displayed when the API credentials are invalid.
	 *
	 * @since   1.8.9
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testInvalidCredentials(EndToEndTester $I)
	{
		// Define connection with invalid API credentials.
		$I->setupWPFormsIntegration(
			$I,
			accessToken: 'fakeAccessToken',
			refreshToken: 'fakeRefreshToken'
		);

		// Setup WPForms Form and configuration for this test.
		// Create Form.
		$wpFormsID = $I->createWPFormsForm($I);

		// Load WPForms Editor.
		$I->amOnAdminPage('admin.php?page=wpforms-builder&view=fields&form_id=' . $wpFormsID);

		// Click Marketing icon.
		$I->waitForElementVisible('.wpforms-panel-providers-button');
		$I->click('.wpforms-panel-providers-button');

		// Click ConvertKit tab.
		$I->click('#wpforms-panel-providers a.wpforms-panel-sidebar-section-convertkit');

		// Click Add New Connection.
		$I->click('Add New Connection');

		// Define name for connection.
		$I->waitForElementVisible('.jconfirm-content');
		$I->fillField('#provider-connection-name', 'Kit');
		$I->click('OK');

		// Get the connection ID.
		$I->waitForElementVisible('.wpforms-provider-connections .wpforms-provider-connection');
		$connectionID = $I->grabAttributeFrom('.wpforms-provider-connections .wpforms-provider-connection', 'data-connection_id');

		// Specify field values.
		$I->waitForElementVisible('div[data-connection_id="' . $connectionID . '"] .wpforms-provider-fields', 30);

		// Navigate to the WordPress Admin.
		$I->amOnAdminPage('index.php');

		// Check that a notice is displayed that the API credentials are invalid.
		$I->seeErrorNotice($I, 'Kit for WPForms: Authorization failed. Please reconnect your Kit account.');
	}

	/**
	 * Test that the credentials and resources are deleted on disconnect.
	 *
	 * @since   1.9.2
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testCredentialsAndResourcesAreDeletedOnDisconnect(EndToEndTester $I)
	{
		// Define a random account ID.
		$accountID = 'kit-' . wp_generate_password( 10, false );

		// Fake the API Key, Access and Refresh Tokens; if we revoke the tokens used for tests, future tests will fail.
		$I->setupWPFormsIntegration(
			$I,
			accessToken: 'fakeAccessToken',
			refreshToken: 'fakeRefreshToken',
			apiKey: 'fakeAPIKey',
			apiSecret: 'fakeAPISecret',
			accountID: $accountID
		);

		// Load WPForms > Settings > Integrations.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrations');

		// Expand Kit integration section.
		$I->click('#wpforms-integration-convertkit');

		// Disconnect the connection to Kit.
		$I->waitForElementVisible('a[data-provider="convertkit"]');
		$I->click('Disconnect');

		// Confirm that we want to disconnect.
		$I->waitForElementVisible('.jconfirm-box');
		$I->click('.jconfirm-box button.btn-confirm');

		// Confirm no connection is listed.
		$I->wait(3);
		$I->dontSee('Connected on:');

		// Check connection is removed from the settings.
		// Clicking 'Disconnect' in WPForms removes the connection from the settings,
		// including any credentials within that connection.
		$providers = $I->grabOptionFromDatabase('wpforms_providers');
		$I->assertArrayHasKey('convertkit', $providers);
		$I->assertCount(0, $providers['convertkit']);

		// Check cached resources are removed from the database on disconnection.
		$I->dontSeeCachedResourcesInDatabase($I, $accountID);
	}

	/**
	 * Test that an authorization code is not exchanged for an access token when the request
	 * is unauthenticated, as admin-ajax.php runs `init` for logged out requests.
	 *
	 * @since   2.0.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testAuthorizationCodeNotExchangedWhenUnauthenticated(EndToEndTester $I)
	{
		// Setup Kit connection.
		$accountID = $I->setupWPFormsIntegration($I);

		// Logout.
		$I->logOut();

		// Attempt to exchange an authorization code without being logged in.
		$I->amOnPage('/wp-admin/admin-ajax.php?action=x&page=wpforms-settings&view=integrations&code=fakeAuthorizationCode');
		$I->amOnPage('/wp-admin/admin-ajax.php?action=x&page=wpforms-settings&view=integrate-convertkit-wpforms-oauth-invalid&code=fakeAuthorizationCode');

		// Confirm the authorization code was not exchanged, and no connection was added.
		$I->apiCheckAuthorizationCodeNotExchanged($I);
		$this->_checkOnlyConnectionIs($I, $accountID);
	}

	/**
	 * Test that an authorization code is not exchanged for an access token when an
	 * Administrator loads the integrations screen without a valid nonce, such as from
	 * a malicious link.
	 *
	 * @since   2.0.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testAuthorizationCodeNotExchangedWithoutNonce(EndToEndTester $I)
	{
		// Setup Kit connection.
		$accountID = $I->setupWPFormsIntegration($I);

		// Attempt to exchange an authorization code without a nonce.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrations&code=fakeAuthorizationCode');

		// Attempt to exchange an authorization code with an invalid nonce, confirming an error is displayed.
		$I->amOnAdminPage('admin.php?page=wpforms-settings&view=integrate-convertkit-wpforms-oauth-invalid&code=fakeAuthorizationCode');
		$I->see('The authorization request could not be verified. Please click Connect to Kit again.');

		// Confirm the authorization code was not exchanged, and no connection was added.
		$I->apiCheckAuthorizationCodeNotExchanged($I);
		$this->_checkOnlyConnectionIs($I, $accountID);
	}

	/**
	 * Checks the given WPForms Account ID is the only Kit connection, and its
	 * credentials were not changed.
	 *
	 * @since   2.0.0
	 *
	 * @param   EndToEndTester $I          Tester.
	 * @param   string         $accountID  WPForms Account ID.
	 */
	private function _checkOnlyConnectionIs(EndToEndTester $I, $accountID)
	{
		$providers = $I->grabOptionFromDatabase('wpforms_providers');
		$I->assertEquals([ $accountID ], array_keys($providers['convertkit']));
		$I->assertEquals($_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'], $providers['convertkit'][ $accountID ]['access_token']);
		$I->assertEquals($_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN'], $providers['convertkit'][ $accountID ]['refresh_token']);
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
		$I->deactivateThirdPartyPlugin($I, 'wpforms-lite');
	}
}
