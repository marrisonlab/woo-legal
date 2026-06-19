<?php
/**
 * Impostazioni di recesso per-prodotto.
 *
 * Aggiunge un tab "Recesso" nella pagina di modifica prodotto WooCommerce
 * con la possibilità di escludere il prodotto dal diritto di recesso
 * ai sensi dell'art. 59 D.Lgs. 206/2005.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Product_Settings {

	private static ?WLR_Product_Settings $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_filter( 'woocommerce_product_data_tabs',    [ $this, 'add_tab' ] );
		add_action( 'woocommerce_product_data_panels',  [ $this, 'render_panel' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_meta' ] );
		add_action( 'admin_head',                       [ $this, 'tab_icon_css' ] );
	}

	// -------------------------------------------------------------------------
	// Tab prodotto
	// -------------------------------------------------------------------------

	public function add_tab( array $tabs ): array {
		$tabs['wlr_return'] = [
			'label'    => __( 'Recesso', 'woo-legal-returns' ),
			'target'   => 'wlr_return_product_data',
			'class'    => [],
			'priority' => 80,
		];
		return $tabs;
	}

	public function tab_icon_css(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		echo '<style>
		#woocommerce-product-data ul.wc-tabs li.wlr_return_tab a::before {
			font-family: Dashicons;
			content: "\f334";
		}
		</style>';
	}

	public function render_panel(): void {
		global $post;
		$product_id = (int) $post->ID;
		$no_return  = 'yes' === get_post_meta( $product_id, '_wlr_no_return', true );
		$reason     = (string) get_post_meta( $product_id, '_wlr_no_return_reason', true );
		?>
		<div id="wlr_return_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field" style="padding:10px 12px 0;">
					<strong><?php esc_html_e( 'Diritto di recesso (art. 59 D.Lgs. 206/2005)', 'woo-legal-returns' ); ?></strong><br>
					<span class="description">
						<?php esc_html_e( 'Per impostazione predefinita il cliente può esercitare il recesso entro 14 giorni. Seleziona la casella solo se questo prodotto rientra in una delle categorie di esclusione previste dalla legge.', 'woo-legal-returns' ); ?>
					</span>
				</p>

				<?php
				woocommerce_wp_checkbox( [
					'id'          => '_wlr_no_return',
					'label'       => __( 'Escludi dal diritto di recesso', 'woo-legal-returns' ),
					'description' => __( 'Il cliente non potrà inviare una richiesta di recesso per questo prodotto.', 'woo-legal-returns' ),
					'value'       => $no_return ? 'yes' : 'no',
					'cbvalue'     => 'yes',
					'checked_value' => 'yes',
				] );

				woocommerce_wp_select( [
					'id'          => '_wlr_no_return_reason',
					'label'       => __( 'Motivazione esclusione', 'woo-legal-returns' ),
					'description' => __( 'Mostrata al cliente come spiegazione dell\'esclusione.', 'woo-legal-returns' ),
					'options'     => array_merge(
						[ '' => __( '— Seleziona motivazione —', 'woo-legal-returns' ) ],
						self::get_exclusion_reasons()
					),
					'value'       => $reason,
				] );
				?>
			</div>
		</div>

		<script>
		jQuery( function ( $ ) {
			var $check     = $( '#_wlr_no_return' );
			var $reasonRow = $( '#_wlr_no_return_reason' ).closest( '.form-field' );

			function sync() {
				$reasonRow.toggle( $check.is( ':checked' ) );
			}

			$check.on( 'change', sync );
			sync();
		} );
		</script>
		<?php
	}

	public function save_meta( int $post_id ): void {
		$no_return = isset( $_POST['_wlr_no_return'] ) && 'yes' === $_POST['_wlr_no_return'] ? 'yes' : 'no';
		update_post_meta( $post_id, '_wlr_no_return', $no_return );

		$reason = sanitize_key( $_POST['_wlr_no_return_reason'] ?? '' );
		update_post_meta( $post_id, '_wlr_no_return_reason', $reason );
	}

	// -------------------------------------------------------------------------
	// Helper statici (usati da AJAX e validazione)
	// -------------------------------------------------------------------------

	public static function is_no_return( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_wlr_no_return', true );
	}

	public static function get_exclusion_reason_label( int $product_id ): string {
		$key     = (string) get_post_meta( $product_id, '_wlr_no_return_reason', true );
		$reasons = self::get_exclusion_reasons();
		return $reasons[ $key ] ?? __( 'Escluso dal diritto di recesso (art. 59 D.Lgs. 206/2005)', 'woo-legal-returns' );
	}

	/**
	 * Categorie di esclusione ex art. 59 D.Lgs. 206/2005.
	 *
	 * @return array<string,string>
	 */
	public static function get_exclusion_reasons(): array {
		return [
			'custom'     => __( 'Bene confezionato su misura o chiaramente personalizzato (lett. c)', 'woo-legal-returns' ),
			'perishable' => __( 'Bene che rischia di deteriorarsi o scadere rapidamente (lett. d)', 'woo-legal-returns' ),
			'sealed'     => __( 'Bene sigillato aperto dopo la consegna, non idoneo per motivi igienici (lett. e)', 'woo-legal-returns' ),
			'mixed'      => __( 'Bene che dopo la consegna risulta inscindibilmente mescolato ad altri (lett. f)', 'woo-legal-returns' ),
			'digital'    => __( 'Contenuto digitale su supporto non materiale con esecuzione iniziata (lett. m)', 'woo-legal-returns' ),
			'other'      => __( 'Altra causa ex art. 59 D.Lgs. 206/2005', 'woo-legal-returns' ),
		];
	}
}
