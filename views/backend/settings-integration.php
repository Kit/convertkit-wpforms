<?php
/**
 * Outputs settings fields at WPForms > Settings > Integrations > ConvertKit.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

?>

<a href="<?php echo esc_url( $api->get_oauth_url( admin_url( 'admin.php?page=wpforms-settings&view=integrations' ) ) ); ?>" class="wpforms-btn wpforms-btn-md wpforms-btn-orange">
	<?php esc_html_e( 'Connect to ConvertKit', 'integrate-convertkit-wpforms' ); ?>
</a>
