<?php
/**
 * Template: Avviso diritto di recesso nella thank-you page.
 *
 * Variabili disponibili:
 * @var WC_Order $order
 * @var int      $return_days
 * @var string   $return_url
 * @var bool     $is_guest
 * @var string   $order_key
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wlr-withdrawal-notice">
	<h3><?php esc_html_e( 'Diritto di Recesso', 'woo-legal-returns' ); ?></h3>
	<p>
		<?php
		printf(
			/* translators: 1: numero giorni */
			esc_html__( 'Hai %d giorni di tempo dalla ricezione dei beni per esercitare il diritto di recesso, senza dover fornire alcuna motivazione (Direttiva UE 2011/83/UE come modificata dalla Direttiva 2023/2673, recepita con D.Lgs. 209/2025 – art. 54-bis Codice del Consumo).', 'woo-legal-returns' ),
			$return_days
		);
		?>
	</p>
	<p>
		<a href="<?php echo esc_url( $return_url ); ?>" class="button">
			<?php esc_html_e( 'Recedere dal contratto qui', 'woo-legal-returns' ); ?>
		</a>
	</p>
	<?php if ( $is_guest ) : ?>
	<p><small>
		<?php esc_html_e( 'Non hai un account? Puoi comunque aprire una richiesta di reso: ti verrà chiesta la tua email di acquisto per verificare l\'identità.', 'woo-legal-returns' ); ?>
	</small></p>
	<?php endif; ?>
</div>
