<?php
/**
 * Email all'admin: nuova richiesta di reso ricevuta.
 *
 * @var array $data  Dati email (vedi WLR_Emails::get_email_data).
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', __( 'Nuova richiesta di reso', 'woo-legal-returns' ), null );
?>

<p><?php esc_html_e( 'È stata ricevuta una nuova richiesta di recesso.', 'woo-legal-returns' ); ?></p>

<table class="td" cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Richiesta #', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $return_id ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Cliente', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;">
			<?php echo esc_html( $customer->display_name ); ?>
			(<?php echo esc_html( $customer_email ); ?>)
		</td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Totale ordine', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $reason ); ?></td>
	</tr>
	<?php if ( $notes ) : ?>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Note cliente', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $notes ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary">
		<?php esc_html_e( 'Gestisci richiesta', 'woo-legal-returns' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', null ); ?>
