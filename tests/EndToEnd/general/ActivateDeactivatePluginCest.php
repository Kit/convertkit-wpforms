<?php

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Tests Plugin activation and deactivation.
 *
 * @since   1.4.0
 */
class ActivateDeactivatePluginCest
{
	/**
	 * Test that activating the Plugin and the WPForms Plugins works
	 * with no errors.
	 *
	 * @since   1.4.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testPluginActivationDeactivation(EndToEndTester $I)
	{
		$I->activateConvertKitPlugin($I);
		$I->activateThirdPartyPlugin($I, 'wpforms-lite');
		$I->deactivateConvertKitPlugin($I);
		$I->deactivateThirdPartyPlugin($I, 'wpforms-lite');
	}

	/**
	 * Test that activating the Plugin, without activating the WPForms Plugin, works
	 * with no errors.
	 *
	 * @since   1.4.0
	 *
	 * @param   EndToEndTester $I  Tester.
	 */
	public function testPluginActivationDeactivationWithoutWPForms(EndToEndTester $I)
	{
		$I->activateConvertKitPlugin($I);
		$I->deactivateConvertKitPlugin($I);
	}
}
