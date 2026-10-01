<?php
/**
 * Email al cliente: conferma ricezione richiesta di reso.
 *
 * @var array $data  Dati email (vedi WLR_Emails::get_email_data).
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', __( 'Richiesta di reso ricevuta', 'woo-legal-returns' ), null );
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
		/* translators: 1: order number, 2: return request ID. */
		esc_html__( 'Abbiamo ricevuto la tua richiesta di recesso per l\'ordine #%1$s. La tua richiesta è stata registrata con il numero #%2$d.', 'woo-legal-returns' ),
		esc_html( $order_number ),
		(int) $return_id
	);
	?>
</p>

<h2><?php esc_html_e( 'Riepilogo richiesta', 'woo-legal-returns' ); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Richiesta #', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $return_id ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;">#<?php echo esc_html( $order_number ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Stato', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $status_label ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $reason_label ); ?></td>
	</tr>
	<tr>
		<th style="text-align:left;border:1px solid #e5e5e5;"><?php esc_html_e( 'Data richiesta', 'woo-legal-returns' ); ?></th>
		<td style="border:1px solid #e5e5e5;"><?php echo esc_html( $created_at ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '—' ); ?></td>
	</tr>
</table>

<p>
	<?php esc_html_e( 'Il rimborso è dovuto entro 14 giorni dalla comunicazione del recesso. Per i beni, salvo ritiro offerto dal venditore, il rimborso può essere trattenuto fino al ricevimento dei beni o della prova di spedizione, se precedente.', 'woo-legal-returns' ); ?>
</p>

<!-- RICEVUTA UFFICIALE – Art. 54-bis D.Lgs. 209/2025 -->
<div style="border:2px solid #e5e5e5;padding:16px 20px;background:#f9f9f9;margin:24px 0;">

	<p style="margin-top:0;font-weight:bold;text-transform:uppercase;font-size:12px;letter-spacing:.5px;">
		<?php esc_html_e( 'Ricevuta di ricezione – Dichiarazione di recesso', 'woo-legal-returns' ); ?>
	</p>

	<p>
		<?php
		printf(
			/* translators: 1: customer display name, 2: order number. */
			wp_kses_post( __( 'La presente email conferma che <strong>%1$s</strong> ha esercitato il diritto di recesso dal contratto di vendita relativo all\'ordine n. <strong>#%2$s</strong>.', 'woo-legal-returns' ) ),
			esc_html( $customer->display_name ),
			esc_html( $order_number )
		);
		?>
	</p>

	<?php
	if ( ! empty( $items ) ) :
		?>
	<p><strong><?php esc_html_e( 'Prodotti oggetto del recesso:', 'woo-legal-returns' ); ?></strong></p>
	<ul style="margin:0 0 12px 20px;padding:0;">
		<?php
		foreach ( $items as $ri ) :
			$item_name = $ri['name'] ?? ( '#' . $ri['item_id'] );
			?>
		<li><?php echo esc_html( $item_name ) . ' &times; ' . (int) $ri['qty']; ?></li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

	<table style="width:100%;border-collapse:collapse;font-size:13px;">
		<tr>
			<td style="padding:4px 8px 4px 0;color:#555;width:50%;"><strong><?php esc_html_e( 'Motivo dichiarato:', 'woo-legal-returns' ); ?></strong></td>
			<td style="padding:4px 0;"><?php echo esc_html( $reason_label ); ?></td>
		</tr>
		<tr>
			<td style="padding:4px 8px 4px 0;color:#555;"><strong><?php esc_html_e( 'Data e ora di trasmissione:', 'woo-legal-returns' ); ?></strong></td>
			<td style="padding:4px 0;"><?php echo esc_html( $submitted_at_utc . ' UTC' ); ?></td>
		</tr>
		<tr>
			<td style="padding:4px 8px 4px 0;color:#555;"><strong><?php esc_html_e( 'Numero richiesta:', 'woo-legal-returns' ); ?></strong></td>
			<td style="padding:4px 0;">#<?php echo esc_html( $return_id ); ?></td>
		</tr>
		<?php if ( ! empty( $receipt_hash ) ) : ?>
		<tr>
			<td style="padding:4px 8px 4px 0;color:#555;"><strong><?php esc_html_e( 'Hash ricevuta:', 'woo-legal-returns' ); ?></strong></td>
			<td style="padding:4px 0;font-family:monospace;"><?php echo esc_html( $receipt_hash ); ?></td>
		</tr>
		<?php endif; ?>
	</table>
	<p><strong><?php esc_html_e( 'Dichiarazione confermata:', 'woo-legal-returns' ); ?></strong> <?php echo esc_html( $declaration ); ?></p>
	<?php
	if ( ! empty( $trader ) ) :
		?>
		<p><strong><?php esc_html_e( 'Destinatario:', 'woo-legal-returns' ); ?></strong> <?php echo esc_html( implode( ', ', array_filter( $trader ) ) ); ?></p><?php endif; ?>
	<p><strong><?php esc_html_e( 'Email indicata:', 'woo-legal-returns' ); ?></strong> <?php echo esc_html( $customer_email ); ?></p>
	<?php
	if ( $notes ) :
		?>
		<p><strong><?php esc_html_e( 'Note:', 'woo-legal-returns' ); ?></strong> <?php echo nl2br( esc_html( $notes ) ); ?></p><?php endif; ?>

	<p style="margin-bottom:0;font-size:12px;color:#666;border-top:1px solid #e5e5e5;padding-top:10px;margin-top:12px;">
		<?php esc_html_e( 'Questa email costituisce conferma dell\'avvenuta ricezione della dichiarazione di recesso ai sensi dell\'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025, attuativo della Direttiva UE 2023/2673).', 'woo-legal-returns' ); ?>
	</p>

</div>

<p>
	<a href="<?php echo esc_url( $account_url ); ?>" class="button button-primary">
		<?php esc_html_e( 'Visualizza i tuoi resi', 'woo-legal-returns' ); ?>
	</a>
</p>

<?php do_action( 'woocommerce_email_footer', null ); ?>
