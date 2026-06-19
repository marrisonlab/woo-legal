<?php
/**
 * Dashboard admin per la gestione delle richieste di reso.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Admin {

	private static ?WLR_Admin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'admin_menu',        [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wlr_update_status', [ $this, 'handle_update_status' ] );
		add_action( 'admin_post_wlr_update_status', [ $this, 'handle_update_status_post' ] );
		add_action( 'admin_post_wlr_delete_return', [ $this, 'handle_delete_return' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Richieste di Reso', 'woo-legal-returns' ),
			__( 'Resi UE', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-returns',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'woocommerce',
			__( 'Guida Resi UE', 'woo-legal-returns' ),
			__( 'Guida Resi', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-guide',
			[ $this, 'render_guide' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'wlr-returns' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'wlr-admin',
			WLR_PLUGIN_URL . 'assets/css/wlr-admin.css',
			[ 'woocommerce_admin_styles' ],
			WLR_VERSION
		);
		wp_enqueue_script(
			'wlr-admin',
			WLR_PLUGIN_URL . 'assets/js/wlr-admin.js',
			[ 'jquery' ],
			WLR_VERSION,
			true
		);
		wp_localize_script(
			'wlr-admin',
			'wlrAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wlr_update_status' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Rendering pagina
	// -------------------------------------------------------------------------

	public function render_page(): void {
		$action = sanitize_key( $_GET['action'] ?? 'list' );

		switch ( $action ) {
			case 'view':
				$this->render_detail();
				break;
			default:
				$this->render_list();
		}
	}

	public function render_guide(): void {
		include WLR_PLUGIN_DIR . 'templates/admin/guide.php';
	}

	private function render_list(): void {
		$status_filter = sanitize_key( $_GET['status'] ?? '' );
		$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$search        = sanitize_text_field( $_GET['s'] ?? '' );

		$query_args = [
			'posts_per_page' => 20,
			'paged'          => $paged,
		];

		if ( $status_filter && array_key_exists( $status_filter, WLR_Post_Type::STATUSES ) ) {
			$query_args['post_status'] = $status_filter;
		}

		if ( $search ) {
			$query_args['s'] = $search;
		}

		$result = WLR_Post_Type::get_returns_for_admin( $query_args );
		$total  = $result['total'];
		$posts  = $result['posts'];

		include WLR_PLUGIN_DIR . 'templates/admin/list.php';
	}

	private function render_detail(): void {
		$return_id = absint( $_GET['id'] ?? 0 );
		$post      = $return_id ? get_post( $return_id ) : null;

		if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Richiesta di reso non trovata.', 'woo-legal-returns' ) );
		}

		$order_id    = (int) get_post_meta( $return_id, '_wlr_order_id', true );
		$customer_id = (int) get_post_meta( $return_id, '_wlr_customer_id', true );
		$order       = wc_get_order( $order_id );
		$wp_customer = $customer_id ? get_userdata( $customer_id ) : null;
		// Oggetto cliente compatibile sia per registrati che ospiti.
		$customer = $wp_customer ?: ( $order ? (object) [
			'display_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'user_email'   => $order->get_billing_email(),
			'ID'           => 0,
		] : null );
		$items       = get_post_meta( $return_id, '_wlr_items', true ) ?: [];
		$reason      = get_post_meta( $return_id, '_wlr_reason', true );
		$history     = get_post_meta( $return_id, '_wlr_history', true ) ?: [];
		$created_at  = get_post_meta( $return_id, '_wlr_created_at', true );
		$reasons     = WLR_Customer_Account::instance()->get_return_reasons();

		include WLR_PLUGIN_DIR . 'templates/admin/detail.php';
	}

	// -------------------------------------------------------------------------
	// Azioni
	// -------------------------------------------------------------------------

	/**
	 * POST: elimina (trash) una richiesta di reso.
	 */
	public function handle_delete_return(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		$return_id = absint( $_POST['return_id'] ?? 0 );
		check_admin_referer( 'wlr_delete_return_' . $return_id );

		$post = $return_id ? get_post( $return_id ) : null;
		if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Richiesta non trovata.', 'woo-legal-returns' ) );
		}

		wp_trash_post( $return_id );

		wp_safe_redirect( admin_url( 'admin.php?page=wlr-returns&deleted=1' ) );
		exit;
	}

	/**
	 * AJAX: aggiorna stato (da detail page).
	 */
	public function handle_update_status(): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		try {
			check_ajax_referer( 'wlr_update_status', 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'woo-legal-returns' ) ] );
				return;
			}

			$return_id  = absint( $_POST['return_id'] ?? 0 );
			$new_status = sanitize_key( $_POST['status'] ?? '' );
			$note       = sanitize_textarea_field( $_POST['note'] ?? '' );

			if ( ! $return_id || ! $new_status ) {
				wp_send_json_error( [ 'message' => __( 'Dati mancanti.', 'woo-legal-returns' ) ] );
				return;
			}

			$old_status = get_post_field( 'post_status', $return_id );

			$ok = WLR_Post_Type::update_status( $return_id, $new_status, $note );
			if ( ! $ok ) {
				wp_send_json_error( [ 'message' => __( 'Aggiornamento fallito.', 'woo-legal-returns' ) ] );
				return;
			}

			// Emetti evento per le email (try/catch: errori email non rompono la risposta AJAX).
			try {
				do_action( 'wlr_return_status_changed', $return_id, $new_status, $old_status );
			} catch ( \Throwable $e ) {
				error_log( '[WLR] Errore invio email su wlr_return_status_changed: ' . $e->getMessage() );
			}

			wp_send_json_success(
				[
					'message'      => __( 'Stato aggiornato.', 'woo-legal-returns' ),
					'new_status'   => $new_status,
					'status_label' => WLR_Post_Type::get_status_label( $new_status ),
				]
			);

		} catch ( \Throwable $e ) {
			error_log( '[WLR] handle_update_status error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( [ 'message' => __( 'Errore interno. Controlla il debug.log del server.', 'woo-legal-returns' ) ] );
		}
	}

	/**
	 * POST form: aggiorna stato (fallback no-JS).
	 */
	public function handle_update_status_post(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}
		check_admin_referer( 'wlr_update_status_post' );

		$return_id  = absint( $_POST['return_id'] ?? 0 );
		$new_status = sanitize_key( $_POST['status'] ?? '' );
		$note       = sanitize_textarea_field( $_POST['note'] ?? '' );
		$old_status = get_post_field( 'post_status', $return_id );

		WLR_Post_Type::update_status( $return_id, $new_status, $note );
		do_action( 'wlr_return_status_changed', $return_id, $new_status, $old_status );

		wp_safe_redirect(
			admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return_id . '&updated=1' )
		);
		exit;
	}
}
