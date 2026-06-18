<?php
/**
 * Template admin: Dettaglio richiesta di reso.
 *
 * Variabili: $return_id, $post, $order, $customer, $items, $reason, $history, $created_at.
 */

defined( 'ABSPATH' ) || exit;

$back_url = admin_url( 'admin.php?page=wlr-returns' );
?>

<div class="wrap wlr-admin-wrap">
	<h1>
		<?php
		printf(
			esc_html__( 'Richiesta di Reso #%d', 'woo-legal-returns' ),
			$return_id
		);
		?>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			<?php esc_html_e( '&larr; Torna all\'elenco', 'woo-legal-returns' ); ?>
		</a>
	</h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Stato aggiornato con successo.', 'woo-legal-returns' ); ?></p></div>
	<?php endif; ?>

	<div class="wlr-detail-grid">

		<!-- Colonna sinistra: dati richiesta -->
		<div class="wlr-detail-main">

			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Riepilogo richiesta', 'woo-legal-returns' ); ?></h2>
				<div class="inside">
					<table class="wlr-meta-table">
						<tr>
							<th><?php esc_html_e( 'Richiesta #', 'woo-legal-returns' ); ?></th>
							<td><strong><?php echo esc_html( $return_id ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Stato attuale', 'woo-legal-returns' ); ?></th>
							<td>
								<span class="wlr-badge <?php echo esc_attr( WLR_Post_Type::get_status_class( $post->post_status ) ); ?>">
									<?php echo esc_html( WLR_Post_Type::get_status_label( $post->post_status ) ); ?>
								</span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Data richiesta', 'woo-legal-returns' ); ?></th>
							<td><?php echo esc_html( $created_at ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $created_at ) ) : '—' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th>
							<td><?php echo esc_html( $reasons[ $reason ] ?? $reason ); ?></td>
						</tr>
						<?php if ( $post->post_content ) : ?>
						<tr>
							<th><?php esc_html_e( 'Note cliente', 'woo-legal-returns' ); ?></th>
							<td><?php echo wp_kses_post( nl2br( $post->post_content ) ); ?></td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</div>

			<!-- Ordine -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></h2>
				<div class="inside">
					<?php if ( $order ) : ?>
					<p>
						<strong>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">
								#<?php echo esc_html( $order->get_order_number() ); ?>
							</a>
						</strong>
						— <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
						— <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
					</p>
					<table class="wlr-order-items-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Prodotto', 'woo-legal-returns' ); ?></th>
								<th><?php esc_html_e( 'SKU', 'woo-legal-returns' ); ?></th>
								<th><?php esc_html_e( 'Qt.', 'woo-legal-returns' ); ?></th>
								<th><?php esc_html_e( 'Prezzo', 'woo-legal-returns' ); ?></th>
								<th><?php esc_html_e( 'Qt. richiesta reso', 'woo-legal-returns' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$requested_items = [];
							foreach ( $items as $ri ) {
								$requested_items[ $ri['item_id'] ] = $ri['qty'];
							}
							foreach ( $order->get_items() as $item_id => $item ) :
								/** @var WC_Order_Item_Product $item */
								$product = $item->get_product();
							?>
							<tr>
								<td>
									<?php if ( $product ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>" target="_blank">
											<?php echo esc_html( $item->get_name() ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $item->get_name() ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $product ? $product->get_sku() : '—' ); ?></td>
								<td><?php echo esc_html( $item->get_quantity() ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
								<td>
									<?php echo isset( $requested_items[ $item_id ] ) ? esc_html( $requested_items[ $item_id ] ) : '—'; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else : ?>
					<p><?php esc_html_e( 'Ordine non trovato.', 'woo-legal-returns' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Storico stati -->
			<?php if ( ! empty( $history ) ) : ?>
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Storico modifiche', 'woo-legal-returns' ); ?></h2>
				<div class="inside">
					<ul class="wlr-history-list">
						<?php foreach ( array_reverse( $history ) as $entry ) :
							$admin_user = get_userdata( $entry['user_id'] ?? 0 );
						?>
						<li>
							<strong><?php echo esc_html( WLR_Post_Type::get_status_label( $entry['status'] ) ); ?></strong>
							— <?php echo esc_html( $entry['date'] ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $entry['date'] ) ) : '—' ); ?>
							<?php if ( $admin_user ) : ?>
								— <em><?php echo esc_html( $admin_user->display_name ); ?></em>
							<?php endif; ?>
							<?php if ( $entry['note'] ) : ?>
								<br><small><?php echo wp_kses_post( nl2br( $entry['note'] ) ); ?></small>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>

		</div>

		<!-- Colonna destra: cliente + azioni -->
		<div class="wlr-detail-sidebar">

			<!-- Cliente -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Cliente', 'woo-legal-returns' ); ?></h2>
				<div class="inside">
					<?php if ( $customer ) : ?>
					<p>
						<strong>
							<a href="<?php echo esc_url( get_edit_user_link( $customer->ID ) ); ?>">
								<?php echo esc_html( $customer->display_name ); ?>
							</a>
						</strong><br>
						<a href="mailto:<?php echo esc_attr( $customer->user_email ); ?>">
							<?php echo esc_html( $customer->user_email ); ?>
						</a>
					</p>
					<?php if ( $order ) : ?>
					<p>
						<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
					</p>
					<?php endif; ?>
					<?php else : ?>
					<p><?php esc_html_e( 'Cliente non trovato.', 'woo-legal-returns' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Aggiorna stato -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Aggiorna stato', 'woo-legal-returns' ); ?></h2>
				<div class="inside">

					<!-- Form fallback no-JS -->
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wlr-update-status-form">
						<?php wp_nonce_field( 'wlr_update_status_post' ); ?>
						<input type="hidden" name="action" value="wlr_update_status">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( $return_id ); ?>">

						<p>
							<label for="wlr_new_status"><strong><?php esc_html_e( 'Nuovo stato', 'woo-legal-returns' ); ?></strong></label>
							<select name="status" id="wlr_new_status" class="widefat">
								<?php foreach ( WLR_Post_Type::STATUSES as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"
										<?php selected( $post->post_status, $slug ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>

						<p>
							<label for="wlr_admin_note"><strong><?php esc_html_e( 'Nota (visibile al cliente via email)', 'woo-legal-returns' ); ?></strong></label>
							<textarea name="note" id="wlr_admin_note" class="widefat" rows="3"></textarea>
						</p>

						<p>
							<button type="submit" class="button button-primary" id="wlr-save-status-btn">
								<?php esc_html_e( 'Salva modifiche', 'woo-legal-returns' ); ?>
							</button>
						</p>
					</form>

					<div id="wlr-admin-messages"></div>

				</div>
			</div>

			<!-- Elimina richiesta -->
			<div class="postbox">
				<h2 class="hndle"><?php esc_html_e( 'Elimina richiesta', 'woo-legal-returns' ); ?></h2>
				<div class="inside">
					<p style="color:#666;font-size:12px;margin-top:0;">
						<?php esc_html_e( 'Sposta la richiesta nel cestino. Reversibile dal cestino di WordPress.', 'woo-legal-returns' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						onsubmit="return confirm('<?php echo esc_js( __( 'Sei sicuro di voler eliminare questa richiesta di reso?', 'woo-legal-returns' ) ); ?>')">
						<?php wp_nonce_field( 'wlr_delete_return_' . $return_id ); ?>
						<input type="hidden" name="action" value="wlr_delete_return">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( $return_id ); ?>">
						<button type="submit" class="button button-link-delete" style="color:#b32d2e;">
							<?php esc_html_e( 'Sposta nel cestino', 'woo-legal-returns' ); ?>
						</button>
					</form>
				</div>
			</div>

		</div>

	</div>
</div>
