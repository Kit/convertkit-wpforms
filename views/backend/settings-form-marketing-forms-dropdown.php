<?php
/**
 * Outputs the Kit Form dropdown field when adding/editing a WPForms Form at Marketing > Kit.
 *
 * @package ConvertKit_WPForms
 * @author ConvertKit
 */

?>
<div class="wpforms-provider-lists wpforms-connection-block">
<?php
if ( ! empty( $error_message ) ) {
	printf(
		'<div class="wpforms-error wpforms-alert wpforms-alert-error"><p>%s</p></div>',
		esc_html( $error_message )
	);
}
?>

	<h4><?php esc_html_e( 'Kit Form', 'integrate-convertkit-wpforms' ); ?></h4>

	<select name="providers[<?php echo esc_attr( $this->slug ); ?>][<?php echo esc_attr( $connection_id ); ?>][list_id]" size="1">
		<option <?php selected( 'subscribe', $value ); ?> value="subscribe" data-preserve-on-refresh="1">
			<?php esc_html_e( 'Subscribe', 'integrate-convertkit-wpforms' ); ?>
		</option>

		<optgroup label="<?php esc_attr_e( 'Forms', 'integrate-convertkit-wpforms' ); ?>" id="convertkit-wpforms-forms" data-option-value-prefix="form:">
			<?php
			if ( $forms->exist() ) {
				foreach ( $forms->get() as $form ) {
					printf(
						'<option value="%s"%s>%s [%s]</option>',
						esc_attr( 'form:' . $form['id'] ),
						selected( 'form:' . $form['id'], $value, false ),
						esc_attr( $form['name'] ),
						( ! empty( $form['format'] ) ? esc_attr( $form['format'] ) : 'inline' )
					);
				}
			}
			?>
		</optgroup>

		<optgroup label="<?php esc_attr_e( 'Sequences', 'integrate-convertkit-wpforms' ); ?>" id="convertkit-wpforms-sequences" data-option-value-prefix="sequence:">
			<?php
			if ( $sequences->exist() ) {
				foreach ( $sequences->get() as $sequence ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( 'sequence:' . $sequence['id'] ),
						selected( 'sequence:' . $sequence['id'], $value, false ),
						esc_attr( $sequence['name'] )
					);
				}
			}
			?>
		</optgroup>

		<optgroup label="<?php esc_attr_e( 'Tags', 'integrate-convertkit-wpforms' ); ?>" id="convertkit-wpforms-tags" data-option-value-prefix="tag:">
			<?php
			if ( $tags->exist() ) {
				foreach ( $tags->get() as $convertkit_tag ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( 'tag:' . $convertkit_tag['id'] ),
						selected( 'tag:' . $convertkit_tag['id'], $value, false ),
						esc_attr( $convertkit_tag['name'] )
					);
				}
			}
			?>
		</optgroup>
	</select>

	<p class="note">
		<code><?php esc_html_e( 'Subscribe', 'integrate-convertkit-wpforms' ); ?></code>: <?php esc_html_e( 'Subscribes the email address to Kit', 'integrate-convertkit-wpforms' ); ?>
		<br />
		<code><?php esc_html_e( 'Form', 'integrate-convertkit-wpforms' ); ?></code>: <?php esc_html_e( 'Subscribes the email address to Kit, and adds the subscriber to the Kit Form', 'integrate-convertkit-wpforms' ); ?>
		<br />
		<code><?php esc_html_e( 'Tag', 'integrate-convertkit-wpforms' ); ?></code>: <?php esc_html_e( 'Subscribes the email address to Kit, tagging the subscriber', 'integrate-convertkit-wpforms' ); ?>
		<br />
		<code><?php esc_html_e( 'Sequence', 'integrate-convertkit-wpforms' ); ?></code>: <?php esc_html_e( 'Subscribes the email address to Kit, and adds the subscriber to the Kit sequence', 'integrate-convertkit-wpforms' ); ?>
	</p>
</div>
