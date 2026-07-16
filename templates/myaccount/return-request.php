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
						__( '#%1$s - %2$s (recesso entro il %3$s)', 'woo-legal-returns' ),
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
		<?php $wlr_current_user = wp_get_current_user(); ?>
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
				$display_name = $is_guest ? __( '[nome del consumatore]', 'woo-legal-returns' ) : $wlr_current_user->display_name;
				printf(
					/* translators: 1: consumer name, 2: current date. */
					esc_html__( 'Io/Noi (*) Vi notifico/notichiamo (*) con la presente di recedere dal mio/nostro (*) contratto di vendita dei seguenti beni (*) / fornitura del seguente servizio (*). Ricevuto il (*): [data ordine]. Nome del consumatore: %1$s. Firma (solo in caso di notifica su supporto cartaceo): ___________. Data: %2$s.', 'woo-legal-returns' ),
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

	<?php do_action( 'wlr_after_return_form' ); ?>

</div>
