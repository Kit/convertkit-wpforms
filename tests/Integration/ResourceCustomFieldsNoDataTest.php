<?php

namespace Tests;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Tests for the Integrate_ConvertKit_WPForms_Resource_Custom_Fields class when no data is present in the API.
 *
 * @since   1.8.9
 */
class ResourceCustomFieldsNoDataTest extends \Codeception\TestCase\WPTestCase
{
	/**
	 * The testing implementation.
	 *
	 * @var \WpunitTester
	 */
	protected $tester;

	/**
	 * Holds the API instance.
	 *
	 * @since   1.8.9
	 *
	 * @var     Integrate_ConvertKit_WPForms_API
	 */
	private $api;

	/**
	 * Holds the ConvertKit Resource class.
	 *
	 * @since   1.8.9
	 *
	 * @var     Integrate_ConvertKit_WPForms_Resource_Custom_Fields
	 */
	private $resource;

	/**
	 * Holds the WPForms Account ID to store the Access Token and Refresh Token in WPForms's settings.
	 *
	 * @since   1.8.9
	 *
	 * @var     string
	 */
	protected $wpforms_account_id = 'kit-unit-test';

	/**
	 * Performs actions before each test.
	 *
	 * @since   1.8.9
	 */
	public function setUp(): void
	{
		parent::setUp();

		// Activate Plugin, to include the Plugin's constants in tests.
		activate_plugins('wpforms-lite/wpforms.php');
		activate_plugins('convertkit-wpforms/integrate-convertkit-wpforms.php');

		// Include classes from /includes to test, as they won't be loaded by the Plugin
		// because WPForms is not active.
		require_once 'includes/class-integrate-convertkit-wpforms-api.php';
		require_once 'includes/class-integrate-convertkit-wpforms-resource-custom-fields.php';

		// Storing Access Token and Refresh Token in WPForms's settings.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN_NO_DATA'],
				'refresh_token' => $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN_NO_DATA'],
				'token_expires' => time() + 10000,
				'label'         => 'ConvertKit WordPress',
				'date'          => time(),
			),
			$this->wpforms_account_id
		);

		// Initialize the API instance.
		$this->api = new \Integrate_ConvertKit_WPForms_API(
			$_ENV['CONVERTKIT_OAUTH_CLIENT_ID'],
			$_ENV['CONVERTKIT_OAUTH_REDIRECT_URI'],
			$_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN_NO_DATA'],
			$_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN_NO_DATA']
		);

		// Initialize the resource class we want to test with the API instance and WPForms Account ID.
		$this->resource = new \Integrate_ConvertKit_WPForms_Resource_Custom_Fields( $this->api, $this->wpforms_account_id );
		$this->assertNotInstanceOf(\WP_Error::class, $this->resource->resources);

		// Initialize the resource class, fetching resources from the API and caching them in the options table.
		$result = $this->resource->init();

		// Confirm calling init() didn't result in an error.
		$this->assertNotInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Performs actions after each test.
	 *
	 * @since   1.8.9
	 */
	public function tearDown(): void
	{
		// Disable integration, removing Access Token and Refresh Token from Plugin's settings.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => '',
				'refresh_token' => '',
				'token_expires' => 0,
				'label'         => 'ConvertKit WordPress',
				'date'          => time(),
			),
			$this->wpforms_account_id
		);

		// Delete Resources from options table.
		delete_option($this->resource->settings_name . '_' . $this->wpforms_account_id);
		delete_option($this->resource->settings_name . '_' . $this->wpforms_account_id . '_last_queried');

		// Destroy the classes we tested.
		unset($this->api);
		unset($this->resource);

		parent::tearDown();
	}

	/**
	 * Test that the refresh() function performs as expected.
	 *
	 * @since   1.8.9
	 */
	public function testRefresh()
	{
		// Confirm that no resources exist in the stored options table data.
		$result = $this->resource->refresh();
		$this->assertNotInstanceOf(\WP_Error::class, $result);
		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test that the expiry timestamp is set and returns the expected value.
	 *
	 * @since   1.8.9
	 */
	public function testExpiry()
	{
		// Define the expected expiry date based on the resource class' $cache_duration setting.
		$expectedExpiryDate = date('Y-m-d', time() + $this->resource->cache_duration);

		// Fetch the actual expiry date set when the resource class was initialized.
		$expiryDate = date('Y-m-d', $this->resource->last_queried + $this->resource->cache_duration);

		// Confirm both dates match.
		$this->assertEquals($expectedExpiryDate, $expiryDate);
	}

	/**
	 * Test that the get() function performs as expected.
	 *
	 * @since   1.8.9
	 */
	public function testGet()
	{
		// Confirm that no resources exist in the stored options table data.
		$result = $this->resource->get();
		$this->assertNotInstanceOf(\WP_Error::class, $result);
		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test that the count() function returns the number of resources.
	 *
	 * @since   1.8.9
	 */
	public function testCount()
	{
		$result = $this->resource->get();
		$this->assertEquals($this->resource->count(), count($result));
	}

	/**
	 * Test that the exist() function performs as expected.
	 *
	 * @since   1.8.9
	 */
	public function testExist()
	{
		// Confirm that the function returns false, because resources do not exist.
		$result = $this->resource->exist();
		$this->assertSame($result, false);
	}
}
