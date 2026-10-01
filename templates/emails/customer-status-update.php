<?php
/**
 * Email al cliente: aggiornamento stato richiesta di reso.
 *
 * @var array $data Dati email + new_status, new_status_label, admin_note.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', __( 'Aggiornamento richiesta di reso', 'woo-legal-returns' ), null );
?>

<p>
	<?php
	printf(
		/* translators: %s: customer display name. */
		esc_html__( 'Gentile %s,', 'woo-legal-returns' ),
		esc_html( $customer->display_name )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: 1: return request ID, 2: order number. */
		esc_html__( 'La tua richiesta di reso #%1$d per l\'ordine #%2$s è stata aggiornata.', 'woo-legal-returns' ),
		(int) $return_id,
		esc_html( $order_number )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: new return request status. */
		esc_html__( 'Nuovo stato: %s', 'woo-legal-returns' ),
		'<strong>' . esc_html( $new_status_label ) . '</strong>'
	);
	?>
</p>

<?php if ( ! empty( $admin_note ) ) : ?>
<p>
	<strong><?php esc_html_e( 'Messaggio dal negozio:', 'woo-legal-returns' ); ?></strong><br>
	<?php echo wp_kses_post( nl2br( $admin_note ) ); ?>
</p>
<?php endif; ?>

<?php if ( 'wlr-approved' === $new_status ) : ?>
<p><?php esc_html_e( 'Restituisci i beni entro 14 giorni dalla comunicazione del recesso. Il rimborso è dovuto entro 14 giorni da tale comunicazione; salvo ritiro offerto dal venditore, può essere trattenuto fino al ricevimento dei beni o della prova di spedizione, se precedente.', 'woo-legal-returns' ); ?></p>
<?php elseif ( 'wlr-refunded' === $new_status ) : ?>
<p><?php esc_html_e( 'Il rimborso è stato elaborato. I tempi di accredito dipendono dal tuo istituto bancario.', 'woo-legal-returns' ); ?></p>
<?php endif; ?>

<p>
	<a href="<?php echo esc_url( $account_url ); ?>" class="button button-primary">
		<?php esc_html_e( 'Visualizza i tuoi resi', 'woo-legal-returns' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', null ); ?>
