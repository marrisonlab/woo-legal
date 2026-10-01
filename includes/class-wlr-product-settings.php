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
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_field' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot_item' ), 10, 4 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'snapshot_block_order' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_order_facts' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_facts' ), 20 );
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
		if ( ! isset( $_POST['_wlr_withdrawal_status'] ) ) {
			return;
		}

		$status = isset( $_POST['_wlr_withdrawal_status'] )
			? sanitize_key( wp_unslash( $_POST['_wlr_withdrawal_status'] ) )
			: '';

		if ( '' === $status ) {
			delete_post_meta( $post_id, self::META_STATUS );
			delete_post_meta( $post_id, self::LEGACY_NO_RETURN );
			delete_post_meta( $post_id, self::LEGACY_REASON );
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
					<option value="" <?php selected( '', $current ); ?>><?php esc_html_e( 'Eredita dalla categoria superiore', 'woo-legal-returns' ); ?></option>
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

		if ( '' === $status || ! self::is_valid_status( $status ) ) {
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

	public function notice_shortcode( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		wp_enqueue_style( 'wlr-frontend', WLR_PLUGIN_URL . 'assets/css/wlr-frontend.css', array(), WLR_VERSION );
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

			$product_id = $item->get_product_id();
			$status     = self::get_item_status( $item );

			if ( self::is_item_excluded( $item, $order ) ) {
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
		if ( ! self::is_excluded_status( $status ) && 'early_service' !== $status ) {
			return '';
		}

		$title = __( 'Condizioni ed eccezioni al diritto di recesso', 'woo-legal-returns' );
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
		usort(
			$terms,
			static function ( $a, $b ) {
				$depth = count( get_ancestors( $b->term_id, 'product_cat' ) ) - count( get_ancestors( $a->term_id, 'product_cat' ) );
				return $depth ? $depth : ( $a->term_id <=> $b->term_id );
			}
		);

		foreach ( $terms as $term ) {
			$candidates = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'product_cat' ) );
			foreach ( $candidates as $candidate_id ) {
				$status = (string) get_term_meta( (int) $candidate_id, self::TERM_META_STATUS, true );
				if ( self::is_valid_status( $status ) ) {
					return $status;
				}
			}
		}

		return '';
	}

	public function render_variation_field( $loop, $data, $variation ): void {
		unset( $data );
		wp_nonce_field( 'wlr_save_variation_status', 'wlr_variation_status_nonce', false );
		woocommerce_wp_select(
			array(
				'id'            => 'wlr_variation_status_' . $loop,
				'name'          => 'wlr_variation_status[' . $loop . ']',
				'label'         => __( 'Recesso della variazione', 'woo-legal-returns' ),
				'value'         => self::get_raw_product_status( $variation->ID ),
				'options'       => array( '' => __( 'Eredita dal prodotto', 'woo-legal-returns' ) ) + self::get_status_options(),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	public function save_variation_field( int $variation_id, int $loop ): void {
		if ( ! current_user_can( 'edit_product', $variation_id )
			|| ! isset( $_POST['wlr_variation_status_nonce'], $_POST['wlr_variation_status'][ $loop ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wlr_variation_status_nonce'] ) ), 'wlr_save_variation_status' ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_POST['wlr_variation_status'][ $loop ] ) );
		if ( '' === $status ) {
			delete_post_meta( $variation_id, self::META_STATUS );
			delete_post_meta( $variation_id, self::LEGACY_NO_RETURN );
			delete_post_meta( $variation_id, self::LEGACY_REASON );
		} elseif ( self::is_valid_status( $status ) ) {
			update_post_meta( $variation_id, self::META_STATUS, $status );
		}
	}

	public function snapshot_item( $item, $key = '', $values = array(), $order = null ): void {
		unset( $key, $values, $order );
		if ( ! $item instanceof WC_Order_Item_Product || $item->get_meta( self::META_STATUS ) ) {
			return;
		}
		$product = $item->get_product();
		$status  = $product ? self::get_product_withdrawal_status( $product->get_id() ) : 'standard';
		$item->update_meta_data( self::META_STATUS, $status );
		$type = 'digital_content' === $status ? 'digital' : ( in_array( $status, array( 'early_service', 'dated_service' ), true ) ? 'service' : ( $product && $product->is_virtual() ? ( $product->is_downloadable() ? 'digital' : 'service' ) : 'goods' ) );
		$item->update_meta_data( '_wlr_contract_type', $type );
		$item->update_meta_data( '_wlr_product_sku', $product ? $product->get_sku() : '' );
	}

	public function snapshot_block_order( WC_Order $order ): void {
		foreach ( $order->get_items() as $item ) {
			$this->snapshot_item( $item );
			$item->save();
		}
	}

	public static function get_item_status( WC_Order_Item_Product $item ): string {
		$status = (string) $item->get_meta( self::META_STATUS );
		if ( self::is_valid_status( $status ) ) {
			return $status;
		}
		$product = $item->get_product();
		return $product ? self::get_product_withdrawal_status( $product->get_id() ) : 'standard';
	}

	public static function get_item_contract_type( WC_Order_Item_Product $item ): string {
		$type = (string) $item->get_meta( '_wlr_contract_type' );
		if ( in_array( $type, array( 'goods', 'digital', 'service' ), true ) ) {
			return $type;
		}
		$status = self::get_item_status( $item );
		if ( 'digital_content' === $status ) {
			return 'digital';
		}
		if ( in_array( $status, array( 'early_service', 'dated_service' ), true ) ) {
			return 'service';
		}
		$product = $item->get_product();
		return $product && $product->is_virtual() ? ( $product->is_downloadable() ? 'digital' : 'service' ) : 'goods';
	}

	public static function is_item_excluded( WC_Order_Item_Product $item, WC_Order $order ): bool {
		$status = self::get_item_status( $item );
		if ( 'digital_content' === $status ) {
			return 'yes' === $order->get_meta( '_wlr_consent_digital_accepted' ) && 'yes' === $order->get_meta( '_wlr_consent_digital_loss_acknowledged' ) && 'yes' === $item->get_meta( '_wlr_execution_started' ) && 'yes' === $item->get_meta( '_wlr_confirmation_provided' );
		}
		if ( 'early_service' === $status ) {
			return 'yes' === $order->get_meta( '_wlr_consent_service_accepted' ) && 'yes' === $order->get_meta( '_wlr_consent_service_loss_acknowledged' ) && 'yes' === $item->get_meta( '_wlr_service_completed' );
		}
		if ( in_array( $status, array( 'sealed_hygiene', 'sealed_media', 'mixed_goods' ), true ) ) {
			return 'yes' === $item->get_meta( '_wlr_exception_condition_met' );
		}
		return self::is_excluded_status( $status );
	}

	public function render_order_facts( WC_Order $order ): void {
		wp_nonce_field( 'wlr_order_facts', 'wlr_order_facts_nonce' );
		echo '<div class="wlr-order-facts"><h4>' . esc_html__( 'Recesso: fatti verificati', 'woo-legal-returns' ) . '</h4>';
		echo '<p><label>' . esc_html__( 'Ricezione dell’ultimo bene (data effettiva)', 'woo-legal-returns' ) . ' <input type="date" name="wlr_received_date" value="' . esc_attr( $order->get_meta( '_wlr_received_date' ) ) . '"></label></p>';
		echo '<p><label><input type="checkbox" name="wlr_information_complete" value="yes" ' . checked( 'yes', $order->get_meta( '_wlr_withdrawal_information_complete' ), false ) . '> ' . esc_html__( 'Informativa sul recesso completa e fornita prima della conclusione del contratto', 'woo-legal-returns' ) . '</label></p>';
		echo '<p><label>' . esc_html__( 'Festività applicabili (date YYYY-MM-DD, una per riga)', 'woo-legal-returns' ) . '<textarea name="wlr_deadline_holidays">' . esc_textarea( implode( "\n", (array) $order->get_meta( '_wlr_deadline_holidays' ) ) ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="wlr_calendar_verified" value="yes" ' . checked( 'yes', $order->get_meta( '_wlr_deadline_calendar_verified' ), false ) . '> ' . esc_html__( 'Calendario delle festività verificato per questo ordine', 'woo-legal-returns' ) . '</label></p>';
		foreach ( $order->get_items() as $id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$status = self::get_item_status( $item );
			$fields = array();
			if ( 'digital_content' === $status ) {
				$fields['_wlr_execution_started']     = __( 'Esecuzione digitale iniziata', 'woo-legal-returns' );
				$fields['_wlr_confirmation_provided'] = __( 'Conferma del consenso e della perdita del diritto fornita su supporto durevole', 'woo-legal-returns' );
			} elseif ( 'early_service' === $status ) {
				$fields['_wlr_service_completed'] = __( 'Servizio interamente eseguito', 'woo-legal-returns' );
			} elseif ( in_array( $status, array( 'sealed_hygiene', 'sealed_media', 'mixed_goods' ), true ) ) {
				$fields['_wlr_exception_condition_met'] = __( 'Apertura del sigillo / mescolamento verificato', 'woo-legal-returns' );
			}
			foreach ( $fields as $key => $label ) {
				echo '<p><label><input type="hidden" name="wlr_item_facts[' . esc_attr( $id ) . '][' . esc_attr( $key ) . ']" value="no"><input type="checkbox" name="wlr_item_facts[' . esc_attr( $id ) . '][' . esc_attr( $key ) . ']" value="yes" ' . checked( 'yes', $item->get_meta( $key ), false ) . '> ' . esc_html( $item->get_name() . ': ' . $label ) . '</label></p>';
			}
		}
		echo '</div>';
	}

	public function save_order_facts( int $order_id ): void {
		if ( ! current_user_can( 'edit_shop_order', $order_id ) || ! isset( $_POST['wlr_order_facts_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wlr_order_facts_nonce'] ) ), 'wlr_order_facts' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$date   = sanitize_text_field( wp_unslash( $_POST['wlr_received_date'] ?? '' ) );
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		$order->update_meta_data( '_wlr_received_date', $parsed && $date === $parsed->format( 'Y-m-d' ) ? $date : '' );
		$order->update_meta_data( '_wlr_withdrawal_information_complete', isset( $_POST['wlr_information_complete'] ) ? 'yes' : 'no' );
		$holidays = preg_split( '/[\s,;]+/', sanitize_textarea_field( wp_unslash( $_POST['wlr_deadline_holidays'] ?? '' ) ) );
		$holidays = array_values(
			array_filter(
				$holidays,
				static function ( $holiday ) {
					$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $holiday );
					return $date && $holiday === $date->format( 'Y-m-d' );
				}
			)
		);
		$order->update_meta_data( '_wlr_deadline_holidays', $holidays );
		$order->update_meta_data( '_wlr_deadline_calendar_verified', isset( $_POST['wlr_calendar_verified'] ) ? 'yes' : 'no' );
		foreach ( $order->get_items() as $id => $item ) {
			foreach ( array( '_wlr_execution_started', '_wlr_confirmation_provided', '_wlr_service_completed', '_wlr_exception_condition_met' ) as $key ) {
				if ( isset( $_POST['wlr_item_facts'][ $id ][ $key ] ) ) {
					$value = sanitize_key( wp_unslash( $_POST['wlr_item_facts'][ $id ][ $key ] ) );
					$item->update_meta_data( $key, 'yes' === $value ? 'yes' : 'no' );
				}
			}
			$item->save();
		}
		$order->save();
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
