<?php
/**
 * Product and category withdrawal settings.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Product_Settings {

	private const META_STATUS      = '_wlr_withdrawal_status';
	private const LEGACY_NO_RETURN = '_wlr_no_return';
	private const LEGACY_REASON    = '_wlr_no_return_reason';
	private const TERM_META_STATUS = '_wlr_withdrawal_status';

	private static ?WLR_Product_Settings $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_meta' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'create_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'admin_head', array( $this, 'tab_icon_css' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_notice' ), 18 );
		add_shortcode( 'wlr_withdrawal_notice', array( $this, 'notice_shortcode' ) );
	}

	public function add_tab( array $tabs ): array {
		$tabs['wlr_return'] = array(
			'label'    => __( 'Recesso', 'woo-legal-returns' ),
			'target'   => 'wlr_return_product_data',
			'class'    => array(),
			'priority' => 80,
		);
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
		$own_status = self::get_raw_product_status( $product_id );
		$effective  = self::get_product_withdrawal_status( $product_id );
		$inherited  = self::get_inherited_status( $product_id );
		$options    = self::get_status_options();
		?>
		<div id="wlr_return_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field" style="padding:10px 12px 0;">
					<strong><?php esc_html_e( 'Diritto di recesso', 'woo-legal-returns' ); ?></strong><br>
					<span class="description">
						<?php esc_html_e( 'Imposta come il prodotto rientra nel diritto di recesso. Le categorie possono fornire un valore predefinito; il singolo prodotto puo sempre sovrascriverlo.', 'woo-legal-returns' ); ?>
					</span>
				</p>

				<p class="form-field _wlr_withdrawal_status_field">
					<label for="_wlr_withdrawal_status"><?php esc_html_e( 'Stato recesso', 'woo-legal-returns' ); ?></label>
					<select id="_wlr_withdrawal_status" name="_wlr_withdrawal_status" class="select short">
						<option value="" <?php selected( '', $own_status ); ?>>
							<?php
							if ( $inherited ) {
								printf(
									/* translators: %s: inherited status label. */
									esc_html__( 'Eredita dalla categoria: %s', 'woo-legal-returns' ),
									esc_html( self::get_status_label( $inherited ) )
								);
							} else {
								esc_html_e( 'Eredita dalla categoria: standard', 'woo-legal-returns' );
							}
							?>
						</option>
						<?php foreach ( $options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $own_status, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="description">
						<?php
						printf(
							/* translators: %s: effective status label. */
							esc_html__( 'Stato effettivo: %s.', 'woo-legal-returns' ),
							esc_html( self::get_status_label( $effective ) )
						);
						?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ),
				'woocommerce_save_data'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$status = isset( $_POST['_wlr_withdrawal_status'] )
			? sanitize_key( wp_unslash( $_POST['_wlr_withdrawal_status'] ) )
			: '';

		if ( '' === $status ) {
			delete_post_meta( $post_id, self::META_STATUS );
			return;
		}

		if ( self::is_valid_status( $status ) ) {
			update_post_meta( $post_id, self::META_STATUS, $status );
			update_post_meta( $post_id, self::LEGACY_NO_RETURN, self::is_excluded_status( $status ) ? 'yes' : 'no' );
		}
	}

	public function render_category_field( $term ): void {
		$current = (string) get_term_meta( $term->term_id, self::TERM_META_STATUS, true );
		?>
		<tr class="form-field term-wlr-withdrawal-status-wrap">
			<th scope="row">
				<label for="_wlr_withdrawal_status"><?php esc_html_e( 'Stato recesso', 'woo-legal-returns' ); ?></label>
			</th>
			<td>
				<?php wp_nonce_field( 'wlr_save_category_status', 'wlr_category_status_nonce' ); ?>
				<select name="_wlr_withdrawal_status" id="_wlr_withdrawal_status" style="width:30em;max-width:100%;">
					<option value="" <?php selected( '', $current ); ?>><?php esc_html_e( 'Nessun valore: standard', 'woo-legal-returns' ); ?></option>
					<?php foreach ( self::get_status_options() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Valore predefinito per i prodotti in questa categoria e nelle sottocategorie. Il singolo prodotto puo sovrascriverlo.', 'woo-legal-returns' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	public function save_category_field( int $term_id ): void {
		if ( ! isset( $_POST['wlr_category_status_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['wlr_category_status_nonce'] ) ),
				'wlr_save_category_status'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$status = isset( $_POST['_wlr_withdrawal_status'] )
			? sanitize_key( wp_unslash( $_POST['_wlr_withdrawal_status'] ) )
			: '';

		if ( '' === $status || ! self::is_valid_status( $status ) || 'standard' === $status ) {
			delete_term_meta( $term_id, self::TERM_META_STATUS );
			return;
		}

		update_term_meta( $term_id, self::TERM_META_STATUS, $status );
	}

	public function render_product_notice(): void {
		if ( ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}

		echo wp_kses_post( self::get_product_notice_html( $product->get_id() ) );
	}

	public function notice_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wlr_withdrawal_notice'
		);

		$product_id = absint( $atts['id'] );
		if ( ! $product_id && is_product() ) {
			$product_id = (int) get_the_ID();
		}

		return self::get_product_notice_html( $product_id );
	}

	public static function get_status_options(): array {
		return array(
			'standard'        => __( 'Standard: diritto di recesso ordinario', 'woo-legal-returns' ),
			'digital_content' => __( 'Contenuto digitale: esclusione con consenso espresso al checkout', 'woo-legal-returns' ),
			'early_service'   => __( 'Servizio iniziato prima del termine: consenso/pro-rata al checkout', 'woo-legal-returns' ),
			'dated_service'   => __( 'Servizio con data o periodo specifico: esclusione', 'woo-legal-returns' ),
			'custom_made'     => __( 'Bene su misura o personalizzato: esclusione', 'woo-legal-returns' ),
			'perishable'      => __( 'Bene deperibile o a rapida scadenza: esclusione', 'woo-legal-returns' ),
			'sealed_hygiene'  => __( 'Bene sigillato aperto, igiene/salute: esclusione', 'woo-legal-returns' ),
			'sealed_media'    => __( 'Audio/video/software sigillato aperto: esclusione', 'woo-legal-returns' ),
			'mixed_goods'     => __( 'Bene mescolato inscindibilmente dopo consegna: esclusione', 'woo-legal-returns' ),
			'newspapers'      => __( 'Giornali, periodici o riviste: esclusione', 'woo-legal-returns' ),
			'other_exception' => __( 'Altra eccezione prevista dalla normativa', 'woo-legal-returns' ),
		);
	}

	public static function is_valid_status( string $status ): bool {
		return array_key_exists( $status, self::get_status_options() );
	}

	public static function get_status_label( string $status ): string {
		$options = self::get_status_options();
		return $options[ $status ] ?? __( 'Standard: diritto di recesso ordinario', 'woo-legal-returns' );
	}

	public static function is_excluded_status( string $status ): bool {
		return in_array(
			$status,
			array(
				'digital_content',
				'dated_service',
				'custom_made',
				'perishable',
				'sealed_hygiene',
				'sealed_media',
				'mixed_goods',
				'newspapers',
				'other_exception',
			),
			true
		);
	}

	public static function product_requires_checkout_consent( int $product_id, string $type ): bool {
		$status = self::get_product_withdrawal_status( $product_id );

		if ( 'digital' === $type ) {
			return 'digital_content' === $status;
		}

		if ( 'service' === $type ) {
			return 'early_service' === $status;
		}

		return false;
	}

	public static function get_product_withdrawal_status( int $product_id ): string {
		$product_id = self::resolve_variation_parent_for_inheritance( $product_id );

		$own = self::get_raw_product_status( $product_id );
		if ( self::is_valid_status( $own ) ) {
			return $own;
		}

		if ( 'yes' === get_post_meta( $product_id, self::LEGACY_NO_RETURN, true ) ) {
			$legacy_reason = (string) get_post_meta( $product_id, self::LEGACY_REASON, true );
			return self::map_legacy_reason( $legacy_reason );
		}

		$inherited = self::get_inherited_status( $product_id );
		return $inherited ? $inherited : 'standard';
	}

	public static function is_no_return( int $product_id ): bool {
		return self::is_excluded_status( self::get_product_withdrawal_status( $product_id ) );
	}

	public static function get_exclusion_reason_label( int $product_id ): string {
		$status = self::get_product_withdrawal_status( $product_id );
		if ( ! self::is_excluded_status( $status ) ) {
			return '';
		}

		return self::get_status_label( $status );
	}

	public static function get_excluded_items_in_order( int $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$excluded = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$product_id   = $product->get_id();
			$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			$check_id     = $variation_id ? $variation_id : $product_id;
			$status       = self::get_product_withdrawal_status( $check_id );

			if ( self::is_excluded_status( $status ) ) {
				$excluded[] = array(
					'item_id'    => (int) $item_id,
					'product_id' => $product_id,
					'status'     => $status,
					'label'      => self::get_status_label( $status ),
					'name'       => $item->get_name(),
					'qty'        => (int) $item->get_quantity(),
				);
			}
		}

		return $excluded;
	}

	public static function get_product_notice_html( int $product_id ): string {
		if ( ! $product_id ) {
			return '';
		}

		$status = self::get_product_withdrawal_status( $product_id );
		if ( ! self::is_excluded_status( $status ) ) {
			return '';
		}

		$title = __( 'Diritto di recesso non applicabile a questo prodotto', 'woo-legal-returns' );
		if ( 'digital_content' === $status ) {
			$title = __( 'Contenuto digitale con consenso espresso', 'woo-legal-returns' );
		}

		return sprintf(
			'<div class="wlr-excluded-product-notice"><strong>%1$s</strong><br><span>%2$s</span></div>',
			esc_html( $title ),
			esc_html( self::get_status_label( $status ) )
		);
	}

	private static function get_raw_product_status( int $product_id ): string {
		return (string) get_post_meta( $product_id, self::META_STATUS, true );
	}

	private static function resolve_variation_parent_for_inheritance( int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variation' ) ) {
			$own = self::get_raw_product_status( $product_id );
			if ( self::is_valid_status( $own ) ) {
				return $product_id;
			}

			$parent_id = $product->get_parent_id();
			return $parent_id ? $parent_id : $product_id;
		}

		return $product_id;
	}

	private static function get_inherited_status( int $product_id ): string {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		foreach ( $terms as $term ) {
			$candidates = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'product_cat' ) );
			foreach ( $candidates as $candidate_id ) {
				$status = (string) get_term_meta( (int) $candidate_id, self::TERM_META_STATUS, true );
				if ( self::is_valid_status( $status ) && 'standard' !== $status ) {
					return $status;
				}
			}
		}

		return '';
	}

	private static function map_legacy_reason( string $reason ): string {
		$map = array(
			'custom'     => 'custom_made',
			'perishable' => 'perishable',
			'sealed'     => 'sealed_hygiene',
			'mixed'      => 'mixed_goods',
			'digital'    => 'digital_content',
			'other'      => 'other_exception',
		);

		return $map[ $reason ] ?? 'other_exception';
	}
}
