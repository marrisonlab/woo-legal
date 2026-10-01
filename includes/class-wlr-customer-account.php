<?php
/**
 * WooCommerce My Account integration and public withdrawal submission flow.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Customer_Account {

	public const ENDPOINT = 'resi';

	private static ?WLR_Customer_Account $instance = null;

	private array $rendered_return_prompts = array();
	private int $eligible_order_pages      = 1;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wlr_submit_return', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_wlr_submit_return', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_wlr_get_order_items', array( $this, 'handle_get_order_items' ) );
		add_action( 'wp_ajax_nopriv_wlr_get_order_items', array( $this, 'handle_get_order_items' ) );
		add_action( 'wp_ajax_wlr_refresh_nonce', array( $this, 'refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_wlr_refresh_nonce', array( $this, 'refresh_nonce' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'maybe_render_withdrawal_notice' ), 8 );
		add_action( 'woocommerce_before_customer_login_form', array( $this, 'maybe_inject_guest_return_form' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'maybe_add_return_button_to_order_page' ) );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'render_checkout_notice' ) );
		add_shortcode( 'wlr_checkout_notice', array( $this, 'render_checkout_notice_shortcode' ) );
		add_shortcode( 'wlr_return_form', array( $this, 'render_public_return_shortcode' ) );
		add_shortcode( 'wlr_withdrawal_link', array( $this, 'render_withdrawal_link_shortcode' ) );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_block_checkout_notice' ) );
	}

	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_var( array $vars ): array {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public function add_menu_item( array $items ): array {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Resi & Recesso', 'woo-legal-returns' );
			}
		}

		return $new_items;
	}

	public function enqueue_assets(): void {
		$has_return_shortcode = $this->is_shortcode_context( array( 'wlr_return_form', 'wlr_withdrawal_link' ) );

		if ( ! is_account_page() && ! is_wc_endpoint_url( 'view-order' ) && ! $this->is_public_guest_return_request() && ! $has_return_shortcode ) {
			if ( is_checkout() || is_product() ) {
				wp_enqueue_style( 'wlr-frontend', WLR_PLUGIN_URL . 'assets/css/wlr-frontend.css', array(), WLR_VERSION );
			}
			return;
		}
		$this->enqueue_form_assets();
	}

	public function enqueue_form_assets(): void {
		wp_enqueue_style( 'wlr-frontend', WLR_PLUGIN_URL . 'assets/css/wlr-frontend.css', array(), WLR_VERSION );
		wp_enqueue_script( 'wlr-frontend', WLR_PLUGIN_URL . 'assets/js/wlr-frontend.js', array( 'jquery' ), WLR_VERSION, true );
		wp_localize_script(
			'wlr-frontend',
			'wlrData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wlr_submit_return' ),
				'i18n'    => array(
					'selectOrder'  => __( 'Seleziona un ordine per vedere i prodotti disponibili.', 'woo-legal-returns' ),
					'submitBtn'    => __( 'Invia richiesta di recesso', 'woo-legal-returns' ),
					'confirmBtn'   => __( 'Conferma e invia dichiarazione', 'woo-legal-returns' ),
					'editBtn'      => __( 'Modifica i dati', 'woo-legal-returns' ),
					'submitting'   => __( 'Invio in corso...', 'woo-legal-returns' ),
					'errorGeneric' => __( 'Si e verificato un errore. Riprova.', 'woo-legal-returns' ),
					'loadingItems' => __( 'Caricamento prodotti...', 'woo-legal-returns' ),
					'guestEmail'   => __( 'Inserisci l\'email usata per l\'acquisto prima di caricare i prodotti.', 'woo-legal-returns' ),
				),
			)
		);
	}

	public function render_endpoint(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only endpoint routing.
		if ( isset( $_GET['nuovo'] ) || isset( $_GET['ordine'] ) ) {
			$this->render_new_return_form();
			return;
		}

		$this->render_returns_list();
	}

	public function maybe_inject_guest_return_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only guest return link.
		$order_id  = absint( wp_unslash( $_GET['ordine'] ?? 0 ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id || ! $order_key ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return;
		}

		echo '<style>#customer_login,.woocommerce-ResetPassword{display:none!important}</style>';
		wp_enqueue_style( 'wlr-frontend' );
		wp_enqueue_script( 'wlr-frontend' );

		if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' . esc_html__( 'Il periodo indicativo di recesso risulta scaduto per questo ordine.', 'woo-legal-returns' ) . '</p>';
			return;
		}

		if ( WLR_Post_Type::get_blocking_return_by_order( $order_id ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' . esc_html__( 'Hai gia inviato una richiesta di reso per questo ordine.', 'woo-legal-returns' ) . '</p>';
			return;
		}

		$this->render_new_return_form();
	}

	private function render_returns_list(): void {
		$customer_id = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page                = max( 1, absint( wp_unslash( $_GET['wlr_page'] ?? 1 ) ) );
		$returns             = WLR_Post_Type::get_returns_for_customer( $customer_id, $page );
		$eligible_orders     = $this->get_eligible_orders( $customer_id );
		$has_eligible_orders = ! empty( $eligible_orders ) || $this->eligible_order_pages > 1;

		wc_get_template(
			'myaccount/returns.php',
			array(
				'returns'             => $returns,
				'has_eligible_orders' => $has_eligible_orders,
				'new_return_url'      => add_query_arg( 'nuovo', '1', wc_get_account_endpoint_url( self::ENDPOINT ) ),
				'page'                => $page,
				'has_next_page'       => ! empty( WLR_Post_Type::get_returns_for_customer( $customer_id, $page + 1 ) ),
			),
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
	}

	private function render_new_return_form( bool $allow_public_lookup = false ): void {
		$this->enqueue_form_assets();
		$is_guest    = ! is_user_logged_in();
		$customer_id = get_current_user_id();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only form prefill.
		$order_id  = absint( wp_unslash( $_GET['ordine'] ?? 0 ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$orders             = array();
		$allow_guest_lookup = $allow_public_lookup && $is_guest && apply_filters( 'wlr_allow_guest_email_order_lookup', true );

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && 0 === (int) $order->get_customer_id() && $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
				$is_guest = true;
			}
			if ( $order && WLR_Post_Type::is_within_return_window( $order ) ) {
				if ( $is_guest && $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
					$orders = array( $order );
				} elseif ( ! $is_guest && (int) $order->get_customer_id() === $customer_id ) {
					$orders = array( $order );
				}
			}
		} elseif ( ! $is_guest ) {
			$orders = $this->get_eligible_orders( $customer_id );
		}

		if ( $is_guest ) {
			$back_url = ( $order_id && $order_key )
				? add_query_arg( 'key', rawurlencode( $order_key ), wc_get_endpoint_url( 'order-received', $order_id, wc_get_checkout_url() ) )
				: self::get_withdrawal_page_url();
		} else {
			$back_url = wc_get_account_endpoint_url( self::ENDPOINT );
		}

		wc_get_template(
			'myaccount/return-request.php',
			array(
				'orders'             => $orders,
				'selected_order_id'  => $order_id,
				'reasons'            => $this->get_return_reasons(),
				'back_url'           => $back_url,
				'is_guest'           => $is_guest,
				'order_key'          => $order_key,
				'allow_guest_lookup' => $allow_guest_lookup,
				'order_pages'        => $this->eligible_order_pages,
			),
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
	}

	public function handle_submit(): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		try {
			check_ajax_referer( 'wlr_submit_return', 'nonce' );
			if ( ! WC()->session ) {
				WC()->initialize_session();
			}
			if ( ! is_user_logged_in() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			if ( empty( $_POST['confirm_withdrawal'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Devi confermare la dichiarazione di recesso per procedere.', 'woo-legal-returns' ) ) );
				return;
			}

			$order_id = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded, then item IDs and quantities are validated against the WooCommerce order.
			$items   = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
			$payload = array(
				'order_id'      => $order_id,
				'customer_id'   => is_user_logged_in() ? get_current_user_id() : 0,
				'reason'        => sanitize_key( wp_unslash( $_POST['reason'] ?? '' ) ),
				'notes'         => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
				'items'         => is_array( $items ) ? $items : array(),
				'customer_name' => sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ),
				'order_key'     => sanitize_text_field( wp_unslash( $_POST['order_key'] ?? '' ) ),
				'guest_email'   => sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) ),
			);

			$confirm_token = sanitize_text_field( wp_unslash( $_POST['confirmation_token'] ?? '' ) );
			if ( '' === $confirm_token ) {
				$validation = WLR_Post_Type::validate_return_request( $payload );
				if ( is_wp_error( $validation ) ) {
					wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
					return;
				}

				$token                    = wp_generate_password( 32, false, false );
				$payload['actor']         = $this->get_confirmation_actor();
				$payload['request_token'] = $token;
				set_transient( 'wlr_pending_return_' . $token, $payload, 30 * MINUTE_IN_SECONDS );

				wp_send_json_success(
					array(
						'needs_confirmation' => true,
						'token'              => $token,
						'summary'            => $this->build_confirmation_summary( $validation ),
					)
				);
				return;
			}

			$pending = get_transient( 'wlr_pending_return_' . $confirm_token );
			if ( ! is_array( $pending ) || ! isset( $pending['actor'] ) || ! hash_equals( $pending['actor'], $this->get_confirmation_actor() ) ) {
				wp_send_json_error( array( 'message' => __( 'La conferma e scaduta. Ricontrolla i dati e riprova.', 'woo-legal-returns' ) ) );
				return;
			}

			$result = WLR_Post_Type::create_return( $pending );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				return;
			}

			if ( is_user_logged_in() ) {
				$redirect = wc_get_account_endpoint_url( self::ENDPOINT );
			} elseif ( ! empty( $pending['order_key'] ) ) {
				$redirect = add_query_arg( 'key', rawurlencode( $pending['order_key'] ), wc_get_endpoint_url( 'order-received', (int) $pending['order_id'], wc_get_checkout_url() ) );
			} else {
				$redirect = add_query_arg( 'wlr_return_sent', '1', self::get_withdrawal_page_url() );
			}

			wp_send_json_success(
				array(
					'message'   => get_post_meta( $result, '_wlr_receipt_sent', true )
						? __( 'Dichiarazione registrata. La ricevuta è stata affidata al servizio email.', 'woo-legal-returns' )
						: __( 'Dichiarazione registrata. La ricevuta email è in attesa di invio; il venditore può reinviarla.', 'woo-legal-returns' ),
					'return_id' => $result,
					'redirect'  => $redirect,
				)
			);
		} catch ( Throwable $e ) {
			$this->log_error( 'handle_submit error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
				wp_send_json_error( array( 'message' => __( 'Invio non completato. Riprova o contatta il venditore.', 'woo-legal-returns' ) ) );
		}
	}

	public function handle_get_order_items(): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		try {
			check_ajax_referer( 'wlr_submit_return', 'nonce' );

			$order_id = absint( $_POST['order_id'] ?? 0 );
			$order    = $order_id ? wc_get_order( $order_id ) : null;
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Ordine non trovato.', 'woo-legal-returns' ) ) );
				return;
			}

			$access = WLR_Post_Type::validate_order_access(
				$order,
				get_current_user_id(),
				array(
					'order_key'   => sanitize_text_field( wp_unslash( $_POST['order_key'] ?? '' ) ),
					'guest_email' => sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $access ) ) {
				wp_send_json_error( array( 'message' => $access->get_error_message() ) );
				return;
			}

			if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
				wp_send_json_error( array( 'message' => __( 'Il periodo indicativo di recesso risulta scaduto per questo ordine.', 'woo-legal-returns' ) ) );
				return;
			}

			if ( WLR_Post_Type::get_blocking_return_by_order( $order_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Hai gia inviato una richiesta di recesso per questo ordine.', 'woo-legal-returns' ) ) );
				return;
			}

			$items     = array();
			$available = WLR_Post_Type::get_returnable_items( $order );
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$product   = $item->get_product();
				$no_return = WLR_Product_Settings::is_item_excluded( $item, $order );
				if ( ! $no_return && empty( $available[ $item_id ] ) ) {
					continue;
				}

				$items[] = array(
					'item_id'          => (int) $item_id,
					'name'             => $item->get_name(),
					'qty'              => $available[ $item_id ] ?? (int) $item->get_quantity(),
					'sku'              => $item->get_meta( '_wlr_product_sku' ) ? (string) $item->get_meta( '_wlr_product_sku' ) : ( $product ? $product->get_sku() : '' ),
					'no_return'        => $no_return,
					'no_return_reason' => $no_return ? WLR_Product_Settings::get_status_label( WLR_Product_Settings::get_item_status( $item ) ) : '',
				);
			}

			wp_send_json_success( array( 'items' => $items ) );
		} catch ( Throwable $e ) {
			$this->log_error( 'handle_get_order_items error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array( 'message' => __( 'Caricamento non completato. Riprova o contatta il venditore.', 'woo-legal-returns' ) ) );
		}
	}

	public function maybe_add_return_button_to_order_page( WC_Order $order ): void {
		if ( $this->has_rendered_return_prompt( $order->get_id() ) || ! $this->order_has_items( $order ) ) {
			return;
		}

		if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
			return;
		}

		if ( WLR_Post_Type::get_blocking_return_by_order( $order->get_id() ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' . esc_html__( 'Hai gia aperto una richiesta di recesso per questo ordine.', 'woo-legal-returns' ) . '</p>';
			return;
		}

		echo '<div class="wlr-return-order-action"><a href="' . esc_url( self::get_order_return_url( $order ) ) . '" class="button wlr-btn-return-order">' . esc_html__( 'Recedere dal contratto qui', 'woo-legal-returns' ) . '</a></div>';
	}

	public function maybe_render_withdrawal_notice( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_items( $order ) ) {
			return;
		}

		if ( $this->is_public_guest_return_request() ) {
			$this->render_public_guest_return_form( $order );
			$this->mark_return_prompt_rendered( $order_id );
			return;
		}

		wc_get_template(
			'myaccount/withdrawal-notice.php',
			array(
				'order'       => $order,
				'return_days' => WLR_RETURN_DAYS,
				'return_url'  => self::get_order_return_url( $order ),
				'is_guest'    => 0 === (int) $order->get_customer_id(),
				'order_key'   => $order->get_order_key(),
			),
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
		$this->mark_return_prompt_rendered( $order_id );
	}

	public function render_checkout_notice(): void {
		$opts = get_option( 'wlr_setup_options', array() );
		if ( empty( $opts['checkout_notice_enabled'] ) ) {
			return;
		}

		$html = $this->get_checkout_notice_html( (string) ( $opts['checkout_notice_text'] ?? '' ) );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function render_checkout_notice_shortcode(): string {
		wp_enqueue_style( 'wlr-frontend', WLR_PLUGIN_URL . 'assets/css/wlr-frontend.css', array(), WLR_VERSION );
		$opts = get_option( 'wlr_setup_options', array() );
		if ( empty( $opts['checkout_notice_enabled'] ) ) {
			return '';
		}

		return $this->get_checkout_notice_html( (string) ( $opts['checkout_notice_text'] ?? '' ) );
	}

	public function render_block_checkout_notice( string $content ): string {
		return $this->render_checkout_notice_shortcode() . $content;
	}

	public function get_return_reasons(): array {
		return array(
			'mind_changed'  => __( 'Ho cambiato idea (diritto di recesso)', 'woo-legal-returns' ),
			'wrong_item'    => __( 'Prodotto non corrispondente alla descrizione', 'woo-legal-returns' ),
			'defective'     => __( 'Prodotto difettoso o danneggiato', 'woo-legal-returns' ),
			'wrong_size'    => __( 'Taglia / misura errata', 'woo-legal-returns' ),
			'late_delivery' => __( 'Consegna in ritardo oltre i termini', 'woo-legal-returns' ),
			'other'         => __( 'Altro motivo', 'woo-legal-returns' ),
		);
	}

	public static function get_order_return_url( WC_Order $order ): string {
		$args = array( 'ordine' => $order->get_id() );

		if ( 0 === (int) $order->get_customer_id() ) {
			$args['key']        = $order->get_order_key();
			$args['wlr_return'] = '1';
			$page_id            = absint( get_option( 'wlr_withdrawal_page_id', 0 ) );
			if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
				return add_query_arg( $args, self::get_withdrawal_page_url() );
			}

			return add_query_arg(
				$args,
				wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() )
			);
		}

		return add_query_arg( $args, wc_get_account_endpoint_url( self::ENDPOINT ) );
	}

	public function render_public_return_shortcode(): string {
		$this->enqueue_form_assets();
		ob_start();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice after redirect.
		if ( ! empty( $_GET['wlr_return_sent'] ) ) {
			echo '<div class="woocommerce-message">' . esc_html__( 'Dichiarazione registrata. Conserva la ricevuta inviata via email; in caso di mancata ricezione contatta il venditore.', 'woo-legal-returns' ) . '</div>';
		}
		$this->render_new_return_form( true );
		return (string) ob_get_clean();
	}

	public function render_withdrawal_link_shortcode( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'label' => __( 'Recedere dal contratto qui', 'woo-legal-returns' ),
				'class' => 'button wlr-withdrawal-link',
			),
			$atts,
			'wlr_withdrawal_link'
		);

		return sprintf(
			'<a href="%1$s" class="%2$s">%3$s</a>',
			esc_url( is_user_logged_in() ? self::get_account_returns_url() : self::get_withdrawal_page_url() ),
			esc_attr( (string) $atts['class'] ),
			esc_html( (string) $atts['label'] )
		);
	}

	public static function get_account_returns_url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( self::ENDPOINT );
		}

		return home_url( '/' );
	}

	public static function get_withdrawal_page_url(): string {
		$page_id = absint( get_option( 'wlr_withdrawal_page_id', 0 ) );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return self::get_account_returns_url();
	}

	public static function ensure_withdrawal_page(): int {
		$page_id = absint( get_option( 'wlr_withdrawal_page_id', 0 ) );
		if ( $page_id && get_post( $page_id ) ) {
			return $page_id;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Diritto di recesso', 'woo-legal-returns' ),
				'post_content' => "<!-- wp:paragraph -->\n<p>Puoi esercitare il diritto di recesso compilando il modulo qui sotto.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[wlr_return_form]\n<!-- /wp:shortcode -->",
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'wlr_withdrawal_page_id', (int) $page_id );
			return (int) $page_id;
		}

		return 0;
	}

	private function get_eligible_orders( int $customer_id ): array {
		$orders                     = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => WLR_Post_Type::get_eligible_order_statuses(),
				'limit'       => 50,
				'page'        => max( 1, absint( $_GET['wlr_orders_page'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
				'paginate'    => true,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$this->eligible_order_pages = max( 1, (int) $orders->max_num_pages );

		WLR_Post_Type::prime_order_returns( array_map( static fn( $order ) => $order->get_id(), $orders->orders ) );
		return array_filter(
			$orders->orders,
			fn( $order ) => $order instanceof WC_Order
				&& WLR_Post_Type::is_within_return_window( $order )
				&& ! WLR_Post_Type::get_blocking_return_by_order( $order->get_id() )
				&& $this->order_has_returnable_physical_items( $order )
		);
	}

	private function order_has_returnable_physical_items( WC_Order $order ): bool {
		return ! empty( WLR_Post_Type::get_returnable_items( $order ) );
	}

	private function order_has_items( WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				return true;
			}
		}

		return false;
	}

	private function build_confirmation_summary( array $validation ): string {
		/** @var WC_Order $order */
		$order    = $validation['order'];
		$items    = $validation['items'];
		$deadline = $validation['deadline'];
		$reasons  = $this->get_return_reasons();

		ob_start();
		?>
		<div class="wlr-confirmation-box">
			<h4><?php esc_html_e( 'Rivedi e conferma la dichiarazione', 'woo-legal-returns' ); ?></h4>
			<p><?php esc_html_e( 'La richiesta sara registrata solo dopo la conferma finale.', 'woo-legal-returns' ); ?></p>
			<table class="wlr-confirmation-table">
				<tr><th><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></th><td>#<?php echo esc_html( $order->get_order_number() ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'woo-legal-returns' ); ?></th><td><?php echo esc_html( $validation['customer_email'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Nome', 'woo-legal-returns' ); ?></th><td><?php echo esc_html( $validation['customer_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Dichiarazione', 'woo-legal-returns' ); ?></th><td><?php echo esc_html( WLR_Post_Type::get_declaration_text() ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Note', 'woo-legal-returns' ); ?></th><td><?php echo esc_html( $validation['notes'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th><td><?php echo esc_html( $reasons[ $validation['reason'] ] ?? $validation['reason'] ); ?></td></tr>
				<?php if ( ! empty( $deadline['may_be_expired'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Verifica termini', 'woo-legal-returns' ); ?></th><td><?php esc_html_e( 'La finestra indicativa risulta oltre i 14 giorni e sara verificata dal venditore rispetto alla data effettiva di ricezione.', 'woo-legal-returns' ); ?></td></tr>
				<?php endif; ?>
			</table>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<li><?php echo esc_html( $item['name'] . ' x ' . (int) $item['qty'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function get_checkout_notice_html( string $text ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$opts     = get_option( 'wlr_setup_options', array() );
		$page_id  = (int) ( $opts['precontractual_page_id'] ?? 0 );
		$info_url = $page_id ? get_permalink( $page_id ) : '';

		if ( $info_url && false === stripos( $text, '<a ' ) ) {
			$text .= ' <a href="' . esc_url( $info_url ) . '" target="_blank" rel="noopener">Maggiori informazioni</a>';
		}

		$safe_html = wp_kses(
			$text,
			array(
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
				'strong' => array(),
				'em'     => array(),
			)
		);

		return '<div class="wlr-checkout-notice" style="margin-bottom:16px;padding:12px 16px;background:#e8f4fd;border-left:4px solid #2271b1;font-size:.875rem;">' . $safe_html . '</div>';
	}

	private function is_public_guest_return_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing protected by WooCommerce order key.
		return is_wc_endpoint_url( 'order-received' ) && ! empty( $_GET['wlr_return'] );
	}

	private function is_shortcode_context( array $shortcodes ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || '' === (string) $post->post_content ) {
			return false;
		}

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	private function render_public_guest_return_form( WC_Order $order ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only guest return link.
		$order_id  = absint( wp_unslash( $_GET['ordine'] ?? 0 ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $order_id !== $order->get_id() || ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			echo '<p class="woocommerce-error">' . esc_html__( 'Link di recesso non valido per questo ordine.', 'woo-legal-returns' ) . '</p>';
			return;
		}

		if ( WLR_Post_Type::get_blocking_return_by_order( $order->get_id() ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' . esc_html__( 'Hai gia inviato una richiesta di reso per questo ordine.', 'woo-legal-returns' ) . '</p>';
			return;
		}

		echo '<div class="wlr-public-return-form">';
		$this->render_new_return_form();
		echo '</div>';
	}

	private function has_rendered_return_prompt( int $order_id ): bool {
		return ! empty( $this->rendered_return_prompts[ $order_id ] );
	}

	private function mark_return_prompt_rendered( int $order_id ): void {
		$this->rendered_return_prompts[ $order_id ] = true;
	}

	private function log_error( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'woo-legal-returns' ) );
		}
	}

	public function refresh_nonce(): void {
		nocache_headers();
		if ( ! WC()->session ) {
			WC()->initialize_session();
		}
		if ( ! is_user_logged_in() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wlr_submit_return' ) ) );
	}

	private function get_confirmation_actor(): string {
		if ( is_user_logged_in() ) {
			return wp_hash( get_current_user_id() . ':' . wp_get_session_token() );
		}
		return wp_hash( 'guest:' . ( WC()->session ? WC()->session->get_customer_id() : '' ) );
	}
}
