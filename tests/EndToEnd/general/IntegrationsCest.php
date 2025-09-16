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

		// Check that a link to the OAuth auth screen exists and includes the state parameter.
		$I->seeInSource('<a href="https://app.kit.com/oauth/authorize?client_id=' . $_ENV['CONVERTKIT_OAUTH_CLIENT_ID'] . '&amp;response_type=code&amp;redirect_uri=' . urlencode( $_ENV['KIT_OAUTH_REDIRECT_URI'] ) );
		$I->seeInSource(
			'&amp;state=' . $I->apiEncodeState(
				$_ENV['WORDPRESS_URL'] . '/wp-admin/admin.php?page=wpforms-settings&view=integrations',
				$_ENV['CONVERTKIT_OAUTH_CLIENT_ID']
			)
		);

		// Click Connect to Kit button.
		$I->waitForElementVisible('.wpforms-settings-provider-accounts-connect a');
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
		$I->assertStringContainsString(
			'&state=' . $I->apiEncodeState(
				$_ENV['WORDPRESS_URL'] . '/wp-admin/admin.php?page=wpforms-settings&view=integrations',
				$_ENV['CONVERTKIT_OAUTH_CLIENT_ID']
			),
			$reconnectURL
		);

		// Confirm that the connection can be disconnected.
		$I->click('Disconnect');

		// Confirm that we want to disconnect.
		$I->waitForElementVisible('.jconfirm-box');
		$I->click('.jconfirm-box button.btn-confirm');

		// Confirm no connection is listed.
		$I->wait(3);
		$I->dontSee('Connected on:');
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
