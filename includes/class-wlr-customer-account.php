<?php
/**
 * Integrazione nell'area "Il mio account" di WooCommerce.
 *
 * Aggiunge il tab "Resi" con elenco richieste e form per nuova richiesta.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Customer_Account {

	const ENDPOINT = 'resi';

	private static ?WLR_Customer_Account $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', [ $this, 'add_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );
		add_filter( 'woocommerce_get_query_vars', [ $this, 'add_query_var' ] );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render_endpoint' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wlr_submit_return',         [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_nopriv_wlr_submit_return',  [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_wlr_get_order_items',        [ $this, 'handle_get_order_items' ] );
		add_action( 'wp_ajax_nopriv_wlr_get_order_items', [ $this, 'handle_get_order_items' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'maybe_render_withdrawal_notice' ] );
		add_action( 'woocommerce_before_customer_login_form', [ $this, 'maybe_inject_guest_return_form' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'maybe_add_return_button_to_order_page' ] );
	}

	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_var( array $vars ): array {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public function add_menu_item( array $items ): array {
		$new_items = [];
		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Resi & Recesso', 'woo-legal-returns' );
			}
		}
		return $new_items;
	}

	public function enqueue_assets(): void {
		if ( ! is_account_page() && ! is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}
		wp_enqueue_style(
			'wlr-frontend',
			WLR_PLUGIN_URL . 'assets/css/wlr-frontend.css',
			[],
			WLR_VERSION
		);
		wp_enqueue_script(
			'wlr-frontend',
			WLR_PLUGIN_URL . 'assets/js/wlr-frontend.js',
			[ 'jquery' ],
			WLR_VERSION,
			true
		);
		wp_localize_script(
			'wlr-frontend',
			'wlrData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wlr_submit_return' ),
				'i18n'    => [
					'confirmSubmit'  => __( 'Sei sicuro di voler inviare la richiesta di reso?', 'woo-legal-returns' ),
					'submitting'     => __( 'Invio in corso…', 'woo-legal-returns' ),
					'errorGeneric'   => __( 'Si è verificato un errore. Riprova.', 'woo-legal-returns' ),
				],
			]
		);
	}

	/**
	 * Renderizza l'endpoint dell'account.
	 */
	public function render_endpoint(): void {
		if ( isset( $_GET['nuovo'] ) || isset( $_GET['ordine'] ) ) {
			$this->render_new_return_form();
		} else {
			$this->render_returns_list();
		}
	}

	/**
	 * Inietta il form di reso per gli ospiti nella pagina di login di My Account.
	 * Nasconde la login form via CSS inline e mostra il nostro form al suo posto.
	 */
	public function maybe_inject_guest_return_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		$order_id  = absint( $_GET['ordine'] ?? 0 );
		$order_key = sanitize_text_field( $_GET['key'] ?? '' );

		if ( ! $order_id || ! $order_key ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return;
		}

		// Nasconde la login/register form che WooCommerce sta per stampare.
		echo '<style>#customer_login,.woocommerce-ResetPassword{display:none!important}</style>';

		// Enqueue assets (non siamo in is_account_page per i logged-out).
		wp_enqueue_style( 'wlr-frontend' );
		wp_enqueue_script( 'wlr-frontend' );

		if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' .
				esc_html(
					sprintf(
						/* translators: %d: giorni */
						__( 'Il periodo di recesso di %d giorni è scaduto per questo ordine.', 'woo-legal-returns' ),
						WLR_RETURN_DAYS
					)
				) . '</p>';
			return;
		}

		if ( WLR_Post_Type::get_return_by_order( $order_id ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' .
				esc_html__( 'Hai già inviato una richiesta di reso per questo ordine.', 'woo-legal-returns' ) .
				'</p>';
			return;
		}

		$this->render_new_return_form();
	}

	/**
	 * Lista delle richieste di reso del cliente.
	 */
	private function render_returns_list(): void {
		$customer_id          = get_current_user_id();
		$returns              = WLR_Post_Type::get_returns_for_customer( $customer_id );
		$eligible_orders      = $this->get_eligible_orders( $customer_id );
		$has_eligible_orders  = ! empty( $eligible_orders );

		wc_get_template(
			'myaccount/returns.php',
			[
				'returns'             => $returns,
				'has_eligible_orders' => $has_eligible_orders,
				'new_return_url'      => wc_get_account_endpoint_url( self::ENDPOINT ) . '?nuovo=1',
			],
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Form per nuova richiesta di reso (registrati e ospiti).
	 */
	private function render_new_return_form(): void {
		$is_guest    = ! is_user_logged_in();
		$customer_id = get_current_user_id();
		$order_id    = absint( $_GET['ordine'] ?? 0 );
		$order_key   = sanitize_text_field( $_GET['key'] ?? '' );
		$orders      = [];

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && WLR_Post_Type::is_within_return_window( $order ) ) {
				if ( $is_guest ) {
					// Ospite: verifica order_key.
					if ( $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
						$orders = [ $order ];
					}
				} elseif ( (int) $order->get_customer_id() === $customer_id ) {
					$orders = [ $order ];
				}
			}
		} elseif ( ! $is_guest ) {
			// Solo per registrati: carica tutti gli ordini eleggibili.
			$orders = $this->get_eligible_orders( $customer_id );
		}

		$back_url = $is_guest
			? wc_get_endpoint_url( 'order-received', $order_id, wc_get_checkout_url() ) . '?key=' . urlencode( $order_key )
			: wc_get_account_endpoint_url( self::ENDPOINT );

		wc_get_template(
			'myaccount/return-request.php',
			[
				'orders'            => $orders,
				'selected_order_id' => $order_id,
				'reasons'           => $this->get_return_reasons(),
				'back_url'          => $back_url,
				'is_guest'          => $is_guest,
				'order_key'         => $order_key,
			],
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Gestisce la submit AJAX del form di reso (utenti registrati e ospiti).
	 */
	public function handle_submit(): void {
		check_ajax_referer( 'wlr_submit_return', 'nonce' );

		$order_id    = absint( $_POST['order_id'] ?? 0 );
		$reason      = sanitize_text_field( $_POST['reason'] ?? '' );
		$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );
		$items_raw   = json_decode( stripslashes( $_POST['items'] ?? '[]' ), true );

		if ( ! $order_id || ! $reason ) {
			wp_send_json_error( [ 'message' => __( 'Compila tutti i campi obbligatori.', 'woo-legal-returns' ) ] );
		}

		if ( is_user_logged_in() ) {
			$customer_id = get_current_user_id();
			$extra       = [];
		} else {
			// Ospite: autenticazione via order_key + billing_email.
			$order_key   = sanitize_text_field( $_POST['order_key'] ?? '' );
			$guest_email = sanitize_email( $_POST['guest_email'] ?? '' );

			if ( ! $order_key || ! $guest_email ) {
				wp_send_json_error( [ 'message' => __( 'Inserisci la chiave ordine e la tua email per procedere.', 'woo-legal-returns' ) ] );
			}

			$customer_id = 0;
			$extra       = [
				'order_key'   => $order_key,
				'guest_email' => $guest_email,
			];
		}

		$result = WLR_Post_Type::create_return(
			array_merge(
				[
					'order_id'    => $order_id,
					'customer_id' => $customer_id,
					'reason'      => $reason,
					'notes'       => $notes,
					'items'       => is_array( $items_raw ) ? $items_raw : [],
				],
				$extra
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Invia notifiche email (try/catch: errori email non rompono la risposta AJAX).
		try {
			do_action( 'wlr_return_created', $result, $order_id, $customer_id );
		} catch ( \Throwable $e ) {
			error_log( '[WLR] Errore invio email su wlr_return_created: ' . $e->getMessage() );
		}

		$redirect = is_user_logged_in()
			? wc_get_account_endpoint_url( self::ENDPOINT )
			: wc_get_endpoint_url( 'order-received', $order_id, wc_get_checkout_url() ) . '?key=' . urlencode( $extra['order_key'] ?? '' );

		wp_send_json_success(
			[
				'message'    => __( 'Richiesta di reso inviata con successo!', 'woo-legal-returns' ),
				'return_id'  => $result,
				'redirect'   => $redirect,
			]
		);
	}

	/**
	 * AJAX: restituisce i prodotti fisici di un ordine (per la selezione item nel form).
	 * Supporta sia utenti registrati che ospiti (via order_key).
	 */
	public function handle_get_order_items(): void {
		check_ajax_referer( 'wlr_submit_return', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Ordine non trovato.', 'woo-legal-returns' ) ] );
		}

		if ( is_user_logged_in() ) {
			// Registrato: verifica ownership.
			if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
				wp_send_json_error( [ 'message' => __( 'Non autorizzato.', 'woo-legal-returns' ) ] );
			}
		} else {
			// Ospite: verifica order_key.
			$order_key = sanitize_text_field( $_POST['order_key'] ?? '' );
			if ( ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
				wp_send_json_error( [ 'message' => __( 'Chiave ordine non valida.', 'woo-legal-returns' ) ] );
			}
		}

		$items = [];
		foreach ( $order->get_items() as $item_id => $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product || $product->is_virtual() || $product->is_downloadable() ) {
				continue;
			}
			$items[] = [
				'item_id' => $item_id,
				'name'    => $item->get_name(),
				'qty'     => $item->get_quantity(),
				'sku'     => $product->get_sku(),
			];
		}

		wp_send_json_success( [ 'items' => $items ] );
	}

	/**
	 * Aggiunge il pulsante di recesso nella pagina dettaglio ordine (My Account → view-order).
	 * Non si attiva sulla thank-you page (gestita da maybe_render_withdrawal_notice).
	 *
	 * @param \WC_Order $order
	 */
	public function maybe_add_return_button_to_order_page( \WC_Order $order ): void {
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$has_physical = false;
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
				$has_physical = true;
				break;
			}
		}
		if ( ! $has_physical ) {
			return;
		}

		if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
			return;
		}

		if ( WLR_Post_Type::get_return_by_order( $order->get_id() ) ) {
			echo '<p class="woocommerce-message woocommerce-message--info">' .
				esc_html__( 'Hai già aperto una richiesta di recesso per questo ordine.', 'woo-legal-returns' ) .
			'</p>';
			return;
		}

		$return_url = add_query_arg(
			[ 'ordine' => $order->get_id() ],
			wc_get_account_endpoint_url( self::ENDPOINT )
		);

		echo '<div class="wlr-return-order-action">' .
			'<a href="' . esc_url( $return_url ) . '" class="button wlr-btn-return-order">' .
			esc_html__( 'Recedere dal contratto qui', 'woo-legal-returns' ) .
			'</a>' .
		'</div>';
	}

	/**
	 * Mostra avviso diritto di recesso nella pagina di ringraziamento.
	 *
	 * @param int $order_id
	 */
	public function maybe_render_withdrawal_notice( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Mostra solo per acquisti di prodotti fisici (esclude download/virtuali puri).
		$has_physical = false;
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
				$has_physical = true;
				break;
			}
		}

		if ( ! $has_physical ) {
			return;
		}

		$is_guest  = ! is_user_logged_in();
		$order_key = $order->get_order_key();

		if ( $is_guest ) {
			$return_url = add_query_arg(
				[ 'ordine' => $order_id, 'key' => $order_key ],
				get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . self::ENDPOINT . '/'
			);
		} else {
			$return_url = wc_get_account_endpoint_url( self::ENDPOINT ) . '?ordine=' . $order_id;
		}

		wc_get_template(
			'myaccount/withdrawal-notice.php',
			[
				'order'       => $order,
				'return_days' => WLR_RETURN_DAYS,
				'return_url'  => $return_url,
				'is_guest'    => $is_guest,
				'order_key'   => $order_key,
			],
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Ordini del cliente eleggibili al reso (completati negli ultimi 14 giorni).
	 *
	 * @param int $customer_id
	 * @return \WC_Order[]
	 */
	private function get_eligible_orders( int $customer_id ): array {
		$all_orders = wc_get_orders(
			[
				'customer_id' => $customer_id,
				'status'      => [ 'completed', 'processing' ],
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
			]
		);

		return array_filter(
			$all_orders,
			fn( $order ) => WLR_Post_Type::is_within_return_window( $order )
				&& ! WLR_Post_Type::get_return_by_order( $order->get_id() )
		);
	}

	/**
	 * Motivi di reso conformi alla normativa UE.
	 *
	 * @return array<string, string>
	 */
	public function get_return_reasons(): array {
		return [
			'mind_changed'      => __( 'Ho cambiato idea (diritto di recesso ex art. 52 Cod. Consumo)', 'woo-legal-returns' ),
			'wrong_item'        => __( 'Prodotto non corrispondente alla descrizione', 'woo-legal-returns' ),
			'defective'         => __( 'Prodotto difettoso o danneggiato', 'woo-legal-returns' ),
			'wrong_size'        => __( 'Taglia / misura errata', 'woo-legal-returns' ),
			'late_delivery'     => __( 'Consegna in ritardo oltre i termini', 'woo-legal-returns' ),
			'other'             => __( 'Altro motivo', 'woo-legal-returns' ),
		];
	}
}
