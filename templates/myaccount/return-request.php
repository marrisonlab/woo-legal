<?php
/**
 * Template: Area cliente – Form nuova richiesta di reso (modulo recesso UE).
 *
 * Variabili disponibili:
 * @var WC_Order[] $orders             Ordini eleggibili al reso.
 * @var int        $selected_order_id  Ordine preselezionato (opzionale).
 * @var array      $reasons            Motivi di reso.
 * @var string     $back_url           URL torna indietro.
 * @var bool       $is_guest           True se l'utente non è loggato.
 * @var string     $order_key          Chiave ordine (solo ospiti).
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wlr-return-form-wrap">

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>" class="wlr-back-link">
			&larr; <?php esc_html_e( 'Torna ai miei resi', 'woo-legal-returns' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'Modulo di Recesso – Direttiva UE Diritti dei Consumatori', 'woo-legal-returns' ); ?></h3>

	<div class="wlr-legal-notice">
		<p>
			<strong><?php esc_html_e( 'INFORMAZIONI SUL DIRITTO DI RECESSO', 'woo-legal-returns' ); ?></strong><br>
			<?php
			printf(
				/* translators: 1: giorni 2: blog name */
				esc_html__( 'Ai sensi della Direttiva UE 2011/83/UE come modificata dalla Direttiva 2023/2673 (D.Lgs. 209/2025, art. 54-bis Codice del Consumo), hai il diritto di recedere dal presente contratto entro %1$d giorni dalla ricezione dei beni, senza dover fornire alcuna giustificazione. Per esercitare il diritto di recesso, compila il presente modulo online. Riceverai una ricevuta di ricezione immediata via email.', 'woo-legal-returns' ),
				WLR_RETURN_DAYS,
				'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
			);
			?>
		</p>
	</div>

	<?php if ( empty( $orders ) ) : ?>
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
			<select name="order_id" id="wlr_order_id" class="woocommerce-Input" required>
				<option value=""><?php esc_html_e( '— Seleziona un ordine —', 'woo-legal-returns' ); ?></option>
				<?php foreach ( $orders as $order ) :
					$date_completed = $order->get_date_completed() ?? $order->get_date_paid() ?? $order->get_date_created();
					$deadline       = clone $date_completed;
					$deadline->modify( '+' . WLR_RETURN_DAYS . ' days' );
				?>
					<option value="<?php echo esc_attr( $order->get_id() ); ?>"
						<?php selected( $selected_order_id, $order->get_id() ); ?>>
						<?php
						printf(
							/* translators: 1: order number, 2: total, 3: deadline date */
							esc_html__( '#%1$s – %2$s (recesso entro il %3$s)', 'woo-legal-returns' ),
							$order->get_order_number(),
							$order->get_formatted_order_total(),
							date_i18n( get_option( 'date_format' ), $deadline->getTimestamp() )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
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
				<?php esc_html_e( 'Motivo del recesso', 'woo-legal-returns' ); ?> <abbr class="required" title="required">*</abbr>
			</label>
			<select name="reason" id="wlr_reason" class="woocommerce-Input" required>
				<option value=""><?php esc_html_e( '— Seleziona il motivo —', 'woo-legal-returns' ); ?></option>
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
		<?php $current_user = wp_get_current_user(); ?>
		<div class="wlr-requester-info">
			<h4><?php esc_html_e( 'Dati del richiedente', 'woo-legal-returns' ); ?></h4>
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
				<?php echo esc_html( $current_user->display_name ); ?><br>
				<?php echo esc_html( $current_user->user_email ); ?>
			</p>
			<?php endif; ?>
		</div>

		<!-- Dichiarazione di recesso (Allegato I Direttiva 2011/83/UE) -->
		<div class="wlr-declaration-box">
			<p>
				<strong><?php esc_html_e( 'Dichiarazione di recesso', 'woo-legal-returns' ); ?></strong><br>
				<?php
				$display_name = $is_guest ? __( '[nome del consumatore]', 'woo-legal-returns' ) : $current_user->display_name;
				printf(
					esc_html__( 'Io/Noi (*) Vi notifico/notichiamo (*) con la presente di recedere dal mio/nostro (*) contratto di vendita dei seguenti beni (*) / fornitura del seguente servizio (*). Ricevuto il (*): [data ordine]. Nome del consumatore: %s. Firma (solo in caso di notifica su supporto cartaceo): ___________. Data: %s.', 'woo-legal-returns' ),
					esc_html( $display_name ),
					esc_html( date_i18n( get_option( 'date_format' ) ) )
				);
				?>
			</p>
			<p><small><?php esc_html_e( '(*) Cancellare la dicitura inutile.', 'woo-legal-returns' ); ?></small></p>
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

		<div id="wlr-form-messages" style="display:none;"></div>

	</form>

	<!-- Template JS per la lista prodotti -->
	<script type="text/template" id="wlr-items-template">
		<h4><?php esc_html_e( 'Prodotti da rendere', 'woo-legal-returns' ); ?></h4>
		<table class="wlr-items-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Seleziona', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Prodotto', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Qt. ordinata', 'woo-legal-returns' ); ?></th>
					<th><?php esc_html_e( 'Qt. da rendere', 'woo-legal-returns' ); ?></th>
				</tr>
			</thead>
			<tbody id="wlr-items-tbody"></tbody>
		</table>
	</script>

	<?php endif; ?>

</div>
