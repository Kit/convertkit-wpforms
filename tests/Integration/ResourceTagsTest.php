<?php

namespace Tests;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Tests for the Integrate_ConvertKit_WPForms_Resource_Tags class when data is present in the API.
 *
 * @since   1.8.9
 */
class ResourceTagsTest extends \Codeception\TestCase\WPTestCase
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
	 * @var     Integrate_ConvertKit_WPForms_Resource_Tags
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
		require_once 'includes/class-integrate-convertkit-wpforms-resource-tags.php';

		// Storing Access Token and Refresh Token in WPForms's settings.
		wpforms_update_providers_options(
			'convertkit',
			array(
				'access_token'  => $_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'],
				'refresh_token' => $_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN'],
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
			$_ENV['CONVERTKIT_OAUTH_ACCESS_TOKEN'],
			$_ENV['CONVERTKIT_OAUTH_REFRESH_TOKEN']
		);

		// Initialize the resource class we want to test with the API instance and WPForms Account ID.
		$this->resource = new \Integrate_ConvertKit_WPForms_Resource_Tags( $this->api, $this->wpforms_account_id );
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
		// Confirm that the data is stored in the options table and includes some expected keys.
		$result = $this->resource->refresh();
		$this->assertIsArray($result);
		$this->assertArrayHasKey('id', reset($result));
		$this->assertArrayHasKey('name', reset($result));
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
	 * Tests that the get() function returns resources in alphabetical ascending order
	 * by default.
	 *
	 * @since   1.8.9
	 */
	public function testGet()
	{
		// Call resource class' get() function.
		$result = $this->resource->get();

		// Assert result is an array.
		$this->assertIsArray($result);

		// Assert top level array keys are preserved.
		$this->assertArrayHasKey(array_key_first($this->resource->resources), $result);
		$this->assertArrayHasKey(array_key_last($this->resource->resources), $result);

		// Assert resource within results has expected array keys.
		$this->assertArrayHasKey('id', reset($result));
		$this->assertArrayHasKey('name', reset($result));

		// Assert order of data is in ascending alphabetical order.
		$this->assertEquals('gravityforms-tag-1', reset($result)[ $this->resource->order_by ]);
		$this->assertEquals('wpforms', end($result)[ $this->resource->order_by ]);
	}

	/**
	 * Tests that the get() function returns resources in alphabetical descending order
	 * when a valid order_by and order properties are defined.
	 *
	 * @since   1.8.9
	 */
	public function testGetWithValidOrderByAndOrder()
	{
		// Define order_by and order.
		$this->resource->order_by = 'name';
		$this->resource->order    = 'desc';

		// Call resource class' get() function.
		$result = $this->resource->get();

		// Assert result is an array.
		$this->assertIsArray($result);

		// Assert top level array keys are preserved.
		$this->assertArrayHasKey(array_key_first($this->resource->resources), $result);
		$this->assertArrayHasKey(array_key_last($this->resource->resources), $result);

		// Assert resource within results has expected array keys.
		$this->assertArrayHasKey('id', reset($result));
		$this->assertArrayHasKey('name', reset($result));

		// Assert order of data is in ascending alphabetical order.
		$this->assertEquals('wpforms', reset($result)[ $this->resource->order_by ]);
		$this->assertEquals('gravityforms-tag-1', end($result)[ $this->resource->order_by ]);
	}

	/**
	 * Tests that the get() function returns resources in their original order
	 * when populated with Forms and an invalid order_by value is specified.
	 *
	 * @since   1.8.9
	 */
	public function testGetWithInvalidOrderBy()
	{
		// Define order_by with an invalid value (i.e. an array key that does not exist).
		$this->resource->order_by = 'invalid_key';

		// Call resource class' get() function.
		$result = $this->resource->get();

		// Assert result is an array.
		$this->assertIsArray($result);

		// Assert top level array keys are preserved.
		$this->assertArrayHasKey(array_key_first($this->resource->resources), $result);
		$this->assertArrayHasKey(array_key_last($this->resource->resources), $result);

		// Assert resource within results has expected array keys.
		$this->assertArrayHasKey('id', reset($result));
		$this->assertArrayHasKey('name', reset($result));

		// Assert order of data has not changed.
		$this->assertEquals('wpforms', reset($result)['name']);
		$this->assertEquals('wordpress', end($result)['name']);
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
		// Confirm that the function returns true, because resources exist.
		$result = $this->resource->exist();
		$this->assertSame($result, true);
	}
}
