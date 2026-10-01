<?php
/**
 * Template: Area cliente – Form nuova richiesta di reso (modulo recesso UE).
 *
 * Variabili disponibili:
 *
 * @var WC_Order[] $orders             Ordini eleggibili al reso.
 * @var int        $selected_order_id  Ordine preselezionato (opzionale).
 * @var array      $reasons            Motivi di reso.
 * @var string     $back_url           URL torna indietro.
 * @var bool       $is_guest           True se l'utente non è loggato.
 * @var string     $order_key          Chiave ordine (solo ospiti).
 * @var bool       $allow_guest_lookup True se il modulo pubblico accetta numero ordine + email.
 */

defined( 'ABSPATH' ) || exit;

$allow_guest_lookup = ! empty( $allow_guest_lookup );
$order_pages        = $order_pages ?? 1;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
$order_page = max( 1, absint( wp_unslash( $_GET['wlr_orders_page'] ?? 1 ) ) );
?>

<div class="wlr-return-form-wrap">

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>" class="wlr-back-link">
			&larr; <?php esc_html_e( 'Torna ai miei resi', 'woo-legal-returns' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'Modulo di Recesso – Direttiva UE Diritti dei Consumatori', 'woo-legal-returns' ); ?></h3>
	<noscript><p><?php esc_html_e( 'La funzione digitale richiede JavaScript. Puoi usare il modulo tipo stampabile o inviare una dichiarazione al venditore tramite i contatti indicati nell’informativa sul recesso.', 'woo-legal-returns' ); ?></p></noscript>
	<?php if ( $order_pages > 1 && ! $is_guest ) : ?>
	<nav aria-label="<?php esc_attr_e( 'Pagine degli ordini', 'woo-legal-returns' ); ?>">
		<?php
		if ( $order_page > 1 ) :
			?>
			<a class="button" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'nuovo'           => 1,
						'wlr_orders_page' => $order_page - 1,
					),
					wc_get_account_endpoint_url( WLR_Customer_Account::ENDPOINT )
				)
			);
			?>
			"><?php esc_html_e( 'Ordini più recenti', 'woo-legal-returns' ); ?></a><?php endif; ?>
		<?php
		if ( $order_page < $order_pages ) :
			?>
			<a class="button" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'nuovo'           => 1,
						'wlr_orders_page' => $order_page + 1,
					),
					wc_get_account_endpoint_url( WLR_Customer_Account::ENDPOINT )
				)
			);
			?>
			"><?php esc_html_e( 'Ordini precedenti', 'woo-legal-returns' ); ?></a><?php endif; ?>
	</nav>
	<?php endif; ?>

	<div class="wlr-legal-notice">
		<p>
			<strong><?php esc_html_e( 'INFORMAZIONI SUL DIRITTO DI RECESSO', 'woo-legal-returns' ); ?></strong><br>
			<?php
			printf(
				/* translators: 1: giorni 2: blog name */
				esc_html__( 'Puoi esercitare il diritto di recesso senza fornire una giustificazione. Il termine ordinario è di %1$d giorni dalla ricezione dei beni o dalla conclusione del contratto per servizi e contenuti digitali, salvo le eccezioni previste dalla normativa. Dopo la conferma finale registriamo la dichiarazione e inviamo una ricevuta via email.', 'woo-legal-returns' ),
				(int) WLR_RETURN_DAYS,
				'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
			);
			?>
		</p>
	</div>

	<?php if ( empty( $orders ) && ! $allow_guest_lookup ) : ?>
		<div class="woocommerce-message woocommerce-message--info">
			<p><?php esc_html_e( 'Non hai ordini idonei al recesso in questo momento. Il periodo di recesso di 14 giorni potrebbe essere scaduto.', 'woo-legal-returns' ); ?></p>
		</div>
	<?php else : ?>

	<form id="wlr-return-form" method="post">

		<?php wp_nonce_field( 'wlr_submit_return', 'wlr_nonce' ); ?>

		<?php if ( $is_guest ) : ?>
		<input type="hidden" name="order_key" id="wlr_order_key" value="<?php echo esc_attr( $order_key ); ?>">
		<?php endif; ?>

		<!-- Selezione ordine -->
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="wlr_order_id">
				<?php esc_html_e( 'Ordine da rendere', 'woo-legal-returns' ); ?> <abbr class="required" title="required">*</abbr>
			</label>
			<?php if ( $allow_guest_lookup ) : ?>
				<input type="number" name="order_id" id="wlr_order_id" class="woocommerce-Input"
					min="1" required value="<?php echo esc_attr( $selected_order_id ); ?>"
					placeholder="<?php esc_attr_e( 'Numero ordine', 'woo-legal-returns' ); ?>">
				<button type="button" class="button wlr-load-order-items">
					<?php esc_html_e( 'Carica prodotti', 'woo-legal-returns' ); ?>
				</button>
				<small><?php esc_html_e( 'Inserisci numero ordine ed email di acquisto per caricare i prodotti recedibili.', 'woo-legal-returns' ); ?></small>
			<?php else : ?>
			<select name="order_id" id="wlr_order_id" class="woocommerce-Input" required>
				<option value=""><?php esc_html_e( '— Seleziona un ordine —', 'woo-legal-returns' ); ?></option>
				<?php
				foreach ( $orders as $return_order ) :
					$deadline      = WLR_Post_Type::get_order_deadline_info( $return_order );
					$deadline_text = ! empty( $deadline['timestamp'] )
						? date_i18n( get_option( 'date_format' ), (int) $deadline['timestamp'] )
						: __( 'da verificare', 'woo-legal-returns' );
					$option_text   = sprintf(
						/* translators: 1: order number, 2: total, 3: deadline date. */
						! empty( $deadline['reliable'] ) ? __( '#%1$s - %2$s (termine verificato: %3$s)', 'woo-legal-returns' ) : __( '#%1$s - %2$s (termine indicativo: %3$s)', 'woo-legal-returns' ),
						$return_order->get_order_number(),
						wp_strip_all_tags( $return_order->get_formatted_order_total() ),
						$deadline_text
					);
					?>
					<option value="<?php echo esc_attr( $return_order->get_id() ); ?>"
						<?php selected( $selected_order_id, $return_order->get_id() ); ?>>
						<?php echo esc_html( $option_text ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
		</p>

		<!-- Prodotti da rendere -->
		<div id="wlr-items-container">
			<p class="wlr-items-placeholder">
				<em><?php esc_html_e( 'Seleziona un ordine per scegliere i prodotti da rendere.', 'woo-legal-returns' ); ?></em>
			</p>
		</div>

		<!-- Motivo principale -->
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="wlr_reason">
				<?php esc_html_e( 'Motivo del recesso (facoltativo)', 'woo-legal-returns' ); ?>
			</label>
			<select name="reason" id="wlr_reason" class="woocommerce-Input">
				<option value=""><?php esc_html_e( 'Nessuna motivazione', 'woo-legal-returns' ); ?></option>
				<?php foreach ( $reasons as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<!-- Note aggiuntive -->
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="wlr_notes">
				<?php esc_html_e( 'Note aggiuntive (opzionale)', 'woo-legal-returns' ); ?>
			</label>
			<textarea name="notes" id="wlr_notes" class="woocommerce-Input" rows="4" maxlength="1000"></textarea>
		</p>

		<!-- Dati del richiedente -->
		<?php $wlr_current_user = wp_get_current_user(); ?>
		<div class="wlr-requester-info">
			<h4><?php esc_html_e( 'Dati del richiedente', 'woo-legal-returns' ); ?></h4>
			<p><label for="wlr_customer_name"><?php esc_html_e( 'Nome e cognome', 'woo-legal-returns' ); ?> *</label>
			<input type="text" id="wlr_customer_name" name="customer_name" required maxlength="200" autocomplete="name" value="<?php echo esc_attr( $is_guest ? '' : $wlr_current_user->display_name ); ?>"></p>
			<?php if ( $is_guest ) : ?>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="wlr_guest_email">
					<?php esc_html_e( 'La tua email di acquisto', 'woo-legal-returns' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="email" name="guest_email" id="wlr_guest_email" class="woocommerce-Input"
					required placeholder="<?php esc_attr_e( 'email usata al momento dell\'acquisto', 'woo-legal-returns' ); ?>">
				<small><?php esc_html_e( 'Viene utilizzata per verificare che la richiesta provenga dal titolare dell\'ordine.', 'woo-legal-returns' ); ?></small>
			</p>
			<?php else : ?>
			<p>
				<?php echo esc_html( $wlr_current_user->display_name ); ?><br>
				<?php echo esc_html( $wlr_current_user->user_email ); ?>
			</p>
			<?php endif; ?>
		</div>

		<!-- Dichiarazione di recesso (Allegato I Direttiva 2011/83/UE) -->
		<div class="wlr-declaration-box">
			<p>
				<strong><?php esc_html_e( 'Dichiarazione di recesso', 'woo-legal-returns' ); ?></strong><br>
				<?php
				echo esc_html( WLR_Post_Type::get_declaration_text() );
				?>
			</p>
		</div>

		<p>
			<label class="wlr-checkbox-label">
				<input type="checkbox" name="confirm_withdrawal" required value="1">
				<?php esc_html_e( 'Confermo di voler esercitare il diritto di recesso e di aver letto le istruzioni per la restituzione dei beni.', 'woo-legal-returns' ); ?>
			</label>
		</p>

		<input type="hidden" name="action" value="wlr_submit_return">
		<input type="hidden" name="nonce" id="wlr_ajax_nonce" value="">

		<p>
			<button type="submit" class="button wlr-btn-primary" id="wlr-submit-btn">
				<?php esc_html_e( 'Invia richiesta di recesso', 'woo-legal-returns' ); ?>
			</button>
		</p>

		<div id="wlr-form-messages" role="status" aria-live="polite" tabindex="-1" style="display:none;"></div>

	</form>

	<!-- Template JS per la lista prodotti -->
	<script type="text/template" id="wlr-items-template">
		<h4><?php esc_html_e( 'Prodotti da rendere', 'woo-legal-returns' ); ?></h4>
		<table class="wlr-items-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Seleziona', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Prodotto', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Qt. disponibile', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Qt. da rendere', 'woo-legal-returns' ); ?></th>
				</tr>
			</thead>
			<tbody id="wlr-items-tbody"></tbody>
		</table>
	</script>

	<?php endif; ?>

	<?php do_action( 'wlr_after_return_form' ); ?>

</div>
