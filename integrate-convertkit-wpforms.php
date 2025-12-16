<?php
/**
 * Kit (formerly ConvertKit) for WPForms Plugin.
 *
 * @package ConvertKit
 * @author ConvertKit
 *
 * @wordpress-plugin
 * Plugin Name: Kit (formerly ConvertKit) for WPForms
 * Plugin URI:  https://kit.com
 * Description: Create Kit signup forms using WPForms
 * Version:     1.8.9
 * Author:      Kit
 * Author URI:  https://kit.com
 * Text Domain: integrate-convertkit-wpforms
 * Domain Path: /languages
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define ConverKit Plugin paths and version number.
define( 'INTEGRATE_CONVERTKIT_WPFORMS_NAME', 'ConvertKitWPForms' ); // Used for user-agent in API class.
define( 'INTEGRATE_CONVERTKIT_WPFORMS_FILE', plugin_basename( __FILE__ ) );
define( 'INTEGRATE_CONVERTKIT_WPFORMS_URL', plugin_dir_url( __FILE__ ) );
define( 'INTEGRATE_CONVERTKIT_WPFORMS_PATH', __DIR__ );
define( 'INTEGRATE_CONVERTKIT_WPFORMS_VERSION', '1.8.9' );
define( 'INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_CLIENT_ID', '147qqKJeENYp5MqgL6AOShDDcLK3UQeClmcIV1ij3gI' );
define( 'INTEGRATE_CONVERTKIT_WPFORMS_OAUTH_REDIRECT_URI', 'https://app.kit.com/wordpress/redirect' );

// Load shared classes, if they have not been included by another ConvertKit Plugin.
if ( ! trait_exists( 'ConvertKit_API_Traits' ) ) {
	require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/vendor/convertkit/convertkit-wordpress-libraries/src/class-convertkit-api-traits.php';
}
if ( ! class_exists( 'ConvertKit_API_V4' ) ) {
	require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/vendor/convertkit/convertkit-wordpress-libraries/src/class-convertkit-api-v4.php';
}
if ( ! class_exists( 'ConvertKit_Log' ) ) {
	require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/vendor/convertkit/convertkit-wordpress-libraries/src/class-convertkit-log.php';
}
if ( ! class_exists( 'ConvertKit_Resource_V4' ) ) {
	require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/vendor/convertkit/convertkit-wordpress-libraries/src/class-convertkit-resource-v4.php';
}
if ( ! class_exists( 'ConvertKit_Review_Request' ) ) {
	require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/vendor/convertkit/convertkit-wordpress-libraries/src/class-convertkit-review-request.php';
}

// Load required functions.
require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/includes/class-integrate-convertkit-wpforms-admin-notices.php';
require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/includes/functions.php';
require_once INTEGRATE_CONVERTKIT_WPFORMS_PATH . '/includes/cron-functions.php';

/**
 * Load the class
 */
function integrate_convertkit_wpforms() {

	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-api.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-creator-network-recommendations.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-resource.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-resource-custom-fields.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-resource-forms.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-resource-sequences.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-resource-tags.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms-setup.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-integrate-convertkit-wpforms.php';

}
add_action( 'wpforms_loaded', 'integrate_convertkit_wpforms' );
