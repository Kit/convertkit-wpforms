/**
 * Kit Oauth Popup.
 *
 * @since 1.8.4
 *
 * @package Integrate_ConvertKit_WPForms
 * @author ConvertKit
 */

/**
 * Show and hide the Kit OAuth popup window.
 *
 * @since 1.8.4
 */
const IntegrateConvertKitWPFormsOauth = function () {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.8.4
	 *
	 * @type {Object}
	 */
	const app = {

		/**
		 * If the OAuth popup is open.
		 *
		 * @since 1.8.4
		 */
		isOpened : false,

		/**
		 * Initialize.
		 *
		 * @since 1.8.4
		 */
		init: function () {

			// Show the OAuth popup window when the user clicks the "Connect to Kit" button
			// when editing a WPForms Form at Marketing > Kit.
			document.addEventListener(
				'click',
				function ( e ) {
					if ( e.target.matches( 'a[data-provider="convertkit"]' ) ) {
						app.showWindow( e );
					}
				}
			);

		},

		/**
		 * Show the OAuth popup window.
		 *
		 * @since 1.8.4
		 *
		 * @param {Event} e Click event.
		 */
		showWindow: function ( e ) {

			e.preventDefault();

			if ( app.isOpened ) {
				return;
			}

			// Define popup width, height and positioning.
			const 	width  = 640,
					height = 750,
					top    = ( window.screen.height - height ) / 2,
					left   = ( window.screen.width - width ) / 2;

			// Open popup.
			const kitPopup = window.open(
				e.target.href,
				'convertkit_popup_window',
				'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' + width + ',height=' + height + ',top=' + top + ',left=' + left
			);

			// Center popup and focus.
			kitPopup.moveTo( left, top );
			kitPopup.focus();

			// Mark popup as opened.
			app.isOpened = true;

			// Refresh the form builder when the popup is closed using self.close().
			// Won't fire if the user closes the popup manually, which is fine because that means
			// they didn't complete the steps, so refreshing wouldn't show anything new.
			// The onbeforeunload would seem suitable here, but it fires whenever the popup window's
			// document changes (e.g. as the user steps through OAuth flow), and doesn't fire when
			// the window is closed.
			// See https://stackoverflow.com/questions/9388380/capture-the-close-event-of-popup-window-in-javascript/48240128#48240128.
			const checkWindowClosed = setInterval(
				function () {
					if ( kitPopup.closed ) {
						clearInterval( checkWindowClosed );

						// Save the form builder and reload, to reflect the changes.
						WPFormsBuilder.formSave( false ).done(
							function () {
								WPFormsBuilder.setCloseConfirmation( false );
								WPFormsBuilder.showLoadingOverlay();
								window.location.reload();
							}
						);

						app.isOpened = false;
					}
				},
				1000
			);

		}
	};

	// Provide access to public functions/properties.
	return app;
};

// Initialize.
IntegrateConvertKitWPFormsOauth.init();
