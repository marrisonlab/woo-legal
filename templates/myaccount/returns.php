<?php
/**
 * Template: Area cliente – Elenco richieste di reso.
 *
 * Variabili disponibili:
 * @var WP_Post[] $returns        Richieste di reso del cliente.
 * @var string    $new_return_url URL per aprire una nuova richiesta.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wlr-account-returns">

	<p class="wlr-intro">
		<?php
		printf(
			/* translators: %d: numero di giorni */
			esc_html__( 'Ai sensi della Direttiva UE 2011/83/UE come modificata dalla Direttiva 2023/2673, recepita in Italia con il D.Lgs. 209/2025 (art. 54-bis Codice del Consumo), hai diritto di recedere dal contratto entro %d giorni dalla ricezione dei beni, senza necessità di indicare alcun motivo.', 'woo-legal-returns' ),
			WLR_RETURN_DAYS
		);
		?>
	</p>

	<?php if ( $has_eligible_orders ) : ?>
	<p>
		<a href="<?php echo esc_url( $new_return_url ); ?>" class="button wlr-btn-primary">
			<?php esc_html_e( 'Recedere dal contratto qui', 'woo-legal-returns' ); ?>
		</a>
	</p>
	<?php else : ?>
	<p class="woocommerce-message woocommerce-message--info">
		<?php
		printf(
			/* translators: %d: numero di giorni */
			esc_html__( 'Non hai ordini idonei al recesso. Il periodo di %d giorni dalla ricezione degli articoli è scaduto oppure hai già aperto una richiesta per tutti gli ordini recenti.', 'woo-legal-returns' ),
			WLR_RETURN_DAYS
		);
		?>
	</p>
	<?php endif; ?>

	<?php if ( empty( $returns ) ) : ?>
		<p class="woocommerce-message woocommerce-message--info">
			<?php esc_html_e( 'Non hai ancora effettuato richieste di reso.', 'woo-legal-returns' ); ?>
		</p>
	<?php else : ?>

		<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Richiesta #', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Data', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Stato', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $returns as $return ) :
					$order_id   = (int) get_post_meta( $return->ID, '_wlr_order_id', true );
					$order      = wc_get_order( $order_id );
					$reason     = get_post_meta( $return->ID, '_wlr_reason', true );
					$created_at = get_post_meta( $return->ID, '_wlr_created_at', true );
					$reasons    = ( WLR_Customer_Account::instance() )->get_return_reasons();
					$status     = $return->post_status;
				?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Richiesta #', 'woo-legal-returns' ); ?>">
						<strong>#<?php echo esc_html( $return->ID ); ?></strong>
					</td>
					<td data-title="<?php esc_attr_e( 'Ordine', 'woo-legal-returns' ); ?>">
						<?php if ( $order ) : ?>
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
								#<?php echo esc_html( $order->get_order_number() ); ?>
							</a>
						<?php else : ?>
							#<?php echo esc_html( $order_id ); ?>
						<?php endif; ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Data', 'woo-legal-returns' ); ?>">
						<?php echo esc_html( $created_at ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '—' ); ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Stato', 'woo-legal-returns' ); ?>">
						<span class="wlr-badge <?php echo esc_attr( WLR_Post_Type::get_status_class( $status ) ); ?>">
							<?php echo esc_html( WLR_Post_Type::get_status_label( $status ) ); ?>
						</span>
					</td>
					<td data-title="<?php esc_attr_e( 'Motivo', 'woo-legal-returns' ); ?>">
						<?php echo esc_html( $reasons[ $reason ] ?? $reason ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

</div>
