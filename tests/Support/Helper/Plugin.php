<?php
namespace Tests\Support\Helper;

/**
 * Helper methods and actions related to the ConvertKit Plugin,
 * which are then available using $I->{yourFunctionName}.
 *
 * @since   1.4.0
 */
class Plugin extends \Codeception\Module
{
	/**
	 * Helper method to activate the ConvertKit Plugin, checking
	 * it activated and no errors were output.
	 *
	 * @since   1.4.0
	 *
	 * @param   AcceptanceTester $I     AcceptanceTester.
	 */
	public function activateConvertKitPlugin($I)
	{
		$I->activateThirdPartyPlugin($I, 'integrate-convertkit-wpforms');
	}

	/**
	 * Helper method to deactivate the ConvertKit Plugin, checking
	 * it activated and no errors were output.
	 *
	 * @since   1.4.0
	 *
	 * @param   AcceptanceTester $I     AcceptanceTester.
	 */
	public function deactivateConvertKitPlugin($I)
	{
		$I->deactivateThirdPartyPlugin($I, 'integrate-convertkit-wpforms');
	}

	/**
	 * Helper method to determine that the order of the Form resources in the given
	 * select element are in the expected alphabetical order.
	 *
	 * @since   1.5.1
	 *
	 * @param   AcceptanceTester $I                 AcceptanceTester.
	 * @param   string           $selectElement     <select> element.
	 * @param   bool|array       $prependOptions    Option elements that should appear before the resources.
	 */
	public function checkSelectFormOptionOrder($I, $selectElement, $prependOptions = false)
	{
		// Define options.
		$options = [
			'AAA Test', // First item.
			'WooCommerce Product Form', // Last item.
		];

		// Prepend options, such as 'Default' and 'None' to the options, if required.
		if ( $prependOptions ) {
			$options = array_merge( $prependOptions, $options );
		}

		// Check order.
		$I->checkSelectOptionOrder($I, $selectElement, $options);
	}

	/**
	 * Helper method to determine the order of <option> values for the given select element
	 * and values.
	 *
	 * @since   1.5.1
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 * @param   string           $selectElement <select> element.
	 * @param   array            $values        <option> values.
	 */
	public function checkSelectOptionOrder($I, $selectElement, $values)
	{
		foreach ( $values as $i => $value ) {
			// Define the applicable CSS selector.
			if ( $i === 0 ) {
				$nth = 'first-child';
			} elseif ( $i + 1 === count( $values ) ) {
				$nth = 'last-child';
			} else {
				$nth = 'nth-child(' . ( $i + 1 ) . ')';
			}

			$I->assertEquals(
				$I->grabTextFrom('select' . $selectElement . ' option:' . $nth),
				$value
			);
		}
	}

	/**
	 * Helper method to determine that the options table has the expected values created
	 * for a review request notification to be displayed in the WordPress Admin.
	 *
	 * @since   1.5.5
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 */
	public function reviewRequestExists($I)
	{
		$I->seeOptionInDatabase('integrate-convertkit-wpforms-review-request');
		$I->dontSeeOptionInDatabase('integrate-convertkit-wpforms-review-dismissed');
	}

	/**
	 * Helper method to determine that the options table does not have a review request
	 * value specified.
	 *
	 * @since   1.5.5
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 */
	public function reviewRequestDoesNotExist($I)
	{
		$I->dontSeeOptionInDatabase('integrate-convertkit-wpforms-review-request');
		$I->dontSeeOptionInDatabase('integrate-convertkit-wpforms-review-dismissed');
	}

	/**
	 * Check that the given Page does output the Creator Network Recommendations
	 * script.
	 *
	 * @since   1.5.8
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 * @param   int              $pageID        Page ID.
	 */
	public function seeCreatorNetworkRecommendationsScript($I, $pageID)
	{
		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded.
		$I->seeInSource('recommendations.js');
	}

	/**
	 * Check that the given Page does not output the Creator Network Recommendations
	 * script.
	 *
	 * @since   1.5.8
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 * @param   int              $pageID        Page ID.
	 */
	public function dontSeeCreatorNetworkRecommendationsScript($I, $pageID)
	{
		// Load the Page on the frontend site.
		$I->amOnPage('/?p=' . $pageID);

		// Check that no PHP warnings or notices were output.
		$I->checkNoWarningsAndNoticesOnScreen($I);

		// Confirm the recommendations script was not loaded.
		$I->dontSeeInSource('recommendations.js');
	}

	/**
	 * Checks that the resources are cached with the correct key for the given
	 * WPForms Account ID.
	 *
	 * @since   1.7.0
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 * @param   string           $accountID     Account ID.
	 */
	public function seeCachedResourcesInDatabase($I, $accountID)
	{
		$I->seeOptionInDatabase('integrate_convertkit_wpforms_forms_' . $accountID);
		$I->seeOptionInDatabase('integrate_convertkit_wpforms_tags_' . $accountID);
		$I->seeOptionInDatabase('integrate_convertkit_wpforms_custom_fields_' . $accountID);
	}

	/**
	 * Checks that the resources are not cached for the given
	 * WPForms Account ID.
	 *
	 * @since   1.7.0
	 *
	 * @param   AcceptanceTester $I             AcceptanceTester.
	 * @param   string           $accountID     Account ID.
	 */
	public function dontSeeCachedResourcesInDatabase($I, $accountID)
	{
		$I->dontSeeOptionInDatabase('integrate_convertkit_wpforms_forms_' . $accountID);
		$I->dontSeeOptionInDatabase('integrate_convertkit_wpforms_tags_' . $accountID);
		$I->dontSeeOptionInDatabase('integrate_convertkit_wpforms_custom_fields_' . $accountID);
	}
}
