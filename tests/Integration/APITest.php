<?php

namespace Tests;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Tests for the Integrate_ConvertKit_WPForms_API class.
 *
 * @since   1.5.1
 */
class APITest extends WPTestCase
{
	/**
	 * The testing implementation.
	 *
	 * @var \WpunitTester.
	 */
	protected $tester;

	/**
	 * Holds the ConvertKit API class.
	 *
	 * @since   1.5.1
	 *
	 * @var     Integrate_ConvertKit_WPForms_API
	 */
	private $api;

	/**
	 * Performs actions before each test.
	 *
	 * @since   1.5.1
	 */
	public function setUp(): void
	{
		parent::setUp();

		// Activate Plugin, to include the Plugin's constants in tests.
		activate_plugins('wpforms-lite/wpforms.php');
		activate_plugins('convertkit-wpforms/integrate-convertkit-wpforms.php');

		// Include class from /includes to test, as they won't be loaded by the Plugin
		// because WPForms is not active.
		require_once 'includes/class-integrate-convertkit-wpforms-api.php';

		// Initialize the classes we want to test.
		$this->api = new \Integrate_ConvertKit_WPForms_API(
			$_ENV['CONVERTKIT_OAUTH_CLIENT_ID'],
			$_ENV['KIT_OAUTH_REDIRECT_URI'],
			$_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'],
			$_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN']
		);
	}

	/**
	 * Performs actions after each test.
	 *
	 * @since   1.5.1
	 */
	public function tearDown(): void
	{
		// Destroy the classes we tested.
		unset($this->api);

		parent::tearDown();
	}

	/**
	 * Test that the Access Token is refreshed when a call is made to the API
	 * using an expired Access Token, and that the new tokens are saved in
	 * the Plugin settings.
	 *
	 * @since   1.7.0
	 */
	public function testAccessTokenRefreshedAndSavedWhenExpired()
	{
		// Add connection with "expired" token.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'],
				'refresh_token' => $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN'],
				'token_expires' => time(),
				'label'         => 'ConvertKit WordPress',
				'date'          => time(),
			),
			'wpunittest1234'
		);

		// Filter requests to mock the token expiry and refreshing the token.
		add_filter( 'pre_http_request', array( $this, 'mockAccessTokenExpiredResponse' ), 10, 3 );
		add_filter( 'pre_http_request', array( $this, 'mockTokenResponse' ), 10, 3 );

		// Run request, which will trigger the above filters as if the token expired and refreshes automatically.
		$result = $this->api->get_account();

		// Confirm "new" tokens now exist in the Plugin's settings, which confirms the `convertkit_api_refresh_token` hook was called when
		// the tokens were refreshed.
		$providers = wpforms_get_providers_options();
		$this->assertArrayHasKey('convertkit', $providers);

		// Get first integration for ConvertKit, and confirm it has the expected array structure and values.
		$account = reset( $providers['convertkit'] );
		$this->assertArrayHasKey('access_token', $account);
		$this->assertArrayHasKey('refresh_token', $account);
		$this->assertArrayHasKey('label', $account);
		$this->assertArrayHasKey('date', $account);
		$this->assertEquals($_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'], $account['access_token']);
		$this->assertEquals($_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN'], $account['refresh_token']);
	}

	/**
	 * Test that a WordPress Cron event is created when an access token is obtained.
	 *
	 * @since   1.8.5
	 */
	public function testCronEventCreatedWhenAccessTokenObtained()
	{
		// Mock request as if the API returned an access and refresh token when a request
		// was made to refresh the token.
		add_filter( 'pre_http_request', array( $this, 'mockTokenResponse' ), 10, 3 );

		// Run request, as if the access token was obtained successfully.
		$result = $this->api->get_access_token( 'mockAuthCode' );

		// Confirm the Cron event to refresh the access token was created, and the timestamp to
		// run the refresh token call matches the expiry of the access token.
		$nextScheduledTimestamp = wp_next_scheduled( 'integrate_convertkit_wpforms_refresh_token' );
		$this->assertGreaterThanOrEqual( $nextScheduledTimestamp, time() + 10000 );
	}

	/**
	 * Test that a WordPress Cron event is created when an access token is refreshed.
	 *
	 * @since   1.8.5
	 */
	public function testCronEventCreatedWhenTokenRefreshed()
	{
		// Mock request as if the API returned an access and refresh token when a request
		// was made to refresh the token.
		add_filter( 'pre_http_request', array( $this, 'mockTokenResponse' ), 10, 3 );

		// Run request, as if the token was refreshed.
		$result = $this->api->refresh_token();

		// Confirm the Cron event to refresh the access token was created, and the timestamp to
		// run the refresh token call matches the expiry of the access token.
		$nextScheduledTimestamp = wp_next_scheduled( 'integrate_convertkit_wpforms_refresh_token' );
		$this->assertGreaterThanOrEqual( $nextScheduledTimestamp, time() + 10000 );
	}

	/**
	 * Mocks an API response as if the Access Token expired.
	 *
	 * @since   1.7.0
	 *
	 * @param   mixed  $response       HTTP Response.
	 * @param   array  $parsed_args    Request arguments.
	 * @param   string $url            Request URL.
	 * @return  mixed
	 */
	public function mockAccessTokenExpiredResponse( $response, $parsed_args, $url )
	{
		// Only mock requests made to the /account endpoint.
		if ( strpos( $url, 'https://api.kit.com/v4/account' ) === false ) {
			return $response;
		}

		// Remove this filter, so we don't end up in a loop when retrying the request.
		remove_filter( 'pre_http_request', array( $this, 'mockAccessTokenExpiredResponse' ) );

		// Return a 401 unauthorized response with the errors body as if the API
		// returned "The access token expired".
		return array(
			'headers'       => array(),
			'body'          => wp_json_encode(
				array(
					'errors' => array(
						'The access token expired',
					),
				)
			),
			'response'      => array(
				'code'    => 401,
				'message' => 'The access token expired',
			),
			'cookies'       => array(),
			'http_response' => null,
		);
	}

	/**
	 * Mocks an API response as if a refresh token was used to fetch new tokens.
	 *
	 * @since   1.7.0
	 *
	 * @param   mixed  $response       HTTP Response.
	 * @param   array  $parsed_args    Request arguments.
	 * @param   string $url            Request URL.
	 * @return  mixed
	 */
	public function mockTokenResponse( $response, $parsed_args, $url )
	{
		// Only mock requests made to the /token endpoint.
		if ( strpos( $url, 'https://api.kit.com/oauth/token' ) === false ) {
			return $response;
		}

		// Remove this filter, so we don't end up in a loop when retrying the request.
		remove_filter( 'pre_http_request', array( $this, 'mockTokenResponse' ) );

		// Return a mock access and refresh token for this API request, as calling
		// refresh_token results in a new access and refresh token being provided,
		// which would result in other tests breaking due to changed tokens.
		return array(
			'headers'       => array(),
			'body'          => wp_json_encode(
				array(
					'access_token'  => $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'],
					'refresh_token' => $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN'],
					'token_type'    => 'bearer',
					'created_at'    => 1735660800, // When the access token was created.
					'expires_in'    => 10000, // When the access token will expire, relative to the time the request was made.
					'scope'         => 'public',
				)
			),
			'response'      => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'       => array(),
			'http_response' => null,
		);
	}

	/**
	 * Test that the User Agent string is in the expected format and
	 * includes the Plugin's name and version number.
	 *
	 * @since   1.5.1
	 */
	public function testUserAgent()
	{
		// When an API call is made, inspect the user-agent argument.
		add_filter(
			'http_request_args',
			function($args, $url) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$this->assertStringContainsString(
					INTEGRATE_CONVERTKIT_WPFORMS_NAME . '/' . INTEGRATE_CONVERTKIT_WPFORMS_VERSION,
					$args['user-agent']
				);
				return $args;
			},
			10,
			2
		);

		// Perform a request.
		$result = $this->api->get_account();
	}
}
