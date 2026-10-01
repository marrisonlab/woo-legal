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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wlr_update_status', array( $this, 'handle_update_status' ) );
		add_action( 'admin_post_wlr_update_status', array( $this, 'handle_update_status_post' ) );
		add_action( 'admin_post_wlr_delete_return', array( $this, 'handle_delete_return' ) );
		add_action( 'admin_post_wlr_restore_return', array( $this, 'handle_restore_return' ) );
		add_action( 'admin_post_wlr_resend_receipt', array( $this, 'handle_resend_receipt' ) );
		add_action( 'admin_post_wlr_export_returns', array( $this, 'handle_export_returns' ) );
		add_action( 'admin_post_wlr_bulk_update_returns', array( $this, 'handle_bulk_update_returns' ) );
		add_action( 'admin_post_wlr_save_settings', array( $this, 'handle_save_settings' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_orders_withdrawal_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_orders_withdrawal_column' ), 20, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_orders_withdrawal_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_orders_withdrawal_column' ), 20, 2 );
		add_filter( 'the_posts', array( $this, 'prime_legacy_order_returns' ), 20, 2 );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'prime_hpos_order_returns' ), PHP_INT_MAX );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Richieste di Reso', 'woo-legal-returns' ),
			__( 'Resi UE', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-returns',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'Guida Resi UE', 'woo-legal-returns' ),
			__( 'Guida Resi', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-guide',
			array( $this, 'render_guide' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'Export Resi UE', 'woo-legal-returns' ),
			__( 'Export Resi', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-export',
			array( $this, 'render_export_page' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'Impostazioni Resi UE', 'woo-legal-returns' ),
			__( 'Impostazioni Resi', 'woo-legal-returns' ),
			'manage_woocommerce',
			'wlr-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		$screen    = get_current_screen();
		$screen_id = $screen ? (string) $screen->id : '';
		$is_wlr    = false !== strpos( $hook, 'wlr-returns' ) || false !== strpos( $hook, 'wlr-settings' );
		$is_orders = in_array( $screen_id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );

		if ( ! $is_wlr && ! $is_orders ) {
			return;
		}

		wp_enqueue_style(
			'wlr-admin',
			WLR_PLUGIN_URL . 'assets/css/wlr-admin.css',
			array( 'woocommerce_admin_styles' ),
			WLR_VERSION
		);

		if ( false === strpos( $hook, 'wlr-returns' ) ) {
			return;
		}

		wp_enqueue_script(
			'wlr-admin',
			WLR_PLUGIN_URL . 'assets/js/wlr-admin.js',
			array( 'jquery' ),
			WLR_VERSION,
			true
		);
		wp_localize_script(
			'wlr-admin',
			'wlrAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wlr_update_status' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Rendering pagina
	// -------------------------------------------------------------------------

	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

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

	public function render_export_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Esporta richieste di recesso', 'woo-legal-returns' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wlr_export_returns">
				<?php wp_nonce_field( 'wlr_export_returns' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wlr_export_status"><?php esc_html_e( 'Stato', 'woo-legal-returns' ); ?></label></th>
						<td>
							<select name="status" id="wlr_export_status">
								<option value=""><?php esc_html_e( 'Tutti', 'woo-legal-returns' ); ?></option>
								<?php foreach ( WLR_Post_Type::STATUSES as $status => $label ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_export_from"><?php esc_html_e( 'Dal', 'woo-legal-returns' ); ?></label></th>
						<td><input type="date" name="date_from" id="wlr_export_from"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_export_to"><?php esc_html_e( 'Al', 'woo-legal-returns' ); ?></label></th>
						<td><input type="date" name="date_to" id="wlr_export_to"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dati tecnici', 'woo-legal-returns' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_pii" value="1">
								<?php esc_html_e( 'Includi IP e user agent', 'woo-legal-returns' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Scarica CSV', 'woo-legal-returns' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		$pages             = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
			)
		);
		$withdrawal_page   = absint( get_option( 'wlr_withdrawal_page_id', 0 ) );
		$notify_emails     = (string) get_option( 'wlr_notify_emails', get_option( 'admin_email' ) );
		$deadline_mode     = (string) get_option( 'wlr_deadline_mode', 'advisory' );
		$deadline_basis    = (string) get_option( 'wlr_deadline_basis', 'completed_or_paid' );
		$grace_days        = absint( get_option( 'wlr_deadline_grace_days', 0 ) );
		$eligible_statuses = WLR_Post_Type::get_eligible_order_statuses();
		$order_statuses    = wc_get_order_statuses();
		$setup_options     = (array) get_option( 'wlr_setup_options', array() );
		$checkout_enabled  = ! empty( $setup_options['checkout_notice_enabled'] );
		$checkout_text     = (string) ( $setup_options['checkout_notice_text'] ?? '' );
		$digital_text      = (string) get_option( 'wlr_digital_consent_text', WLR_Checkout_Consent::get_consent_text( 'digital' ) );
		$service_text      = (string) get_option( 'wlr_service_consent_text', WLR_Checkout_Consent::get_consent_text( 'service' ) );
		?>
		<div class="wrap wlr-admin-wrap">
			<h1><?php esc_html_e( 'Impostazioni Resi UE', 'woo-legal-returns' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Impostazioni salvate.', 'woo-legal-returns' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wlr_save_settings">
				<?php wp_nonce_field( 'wlr_save_settings' ); ?>

				<h2><?php esc_html_e( 'Modulo pubblico e notifiche', 'woo-legal-returns' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wlr_withdrawal_page_id"><?php esc_html_e( 'Pagina pubblica recesso', 'woo-legal-returns' ); ?></label></th>
						<td>
							<select name="wlr_withdrawal_page_id" id="wlr_withdrawal_page_id">
								<option value="0"><?php esc_html_e( 'Crea o usa pagina automatica', 'woo-legal-returns' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $withdrawal_page, $page->ID ); ?>>
										<?php echo esc_html( $page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'La pagina deve contenere lo shortcode [wlr_return_form]. Il plugin la crea automaticamente se non selezioni nulla.', 'woo-legal-returns' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_notify_emails"><?php esc_html_e( 'Email notifiche admin', 'woo-legal-returns' ); ?></label></th>
						<td>
							<input type="text" name="wlr_notify_emails" id="wlr_notify_emails" class="regular-text"
								value="<?php echo esc_attr( $notify_emails ); ?>">
							<p class="description"><?php esc_html_e( 'Separale con virgole. Ricevono le nuove richieste di recesso.', 'woo-legal-returns' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Regole di ammissibilita', 'woo-legal-returns' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Stati ordine idonei', 'woo-legal-returns' ); ?></th>
						<td>
							<?php foreach ( $order_statuses as $status_key => $label ) : ?>
								<?php $status_value = str_replace( 'wc-', '', $status_key ); ?>
								<label class="wlr-inline-check">
									<input type="checkbox" name="wlr_eligible_order_statuses[]"
										value="<?php echo esc_attr( $status_value ); ?>"
										<?php checked( in_array( $status_value, $eligible_statuses, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label><br>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_deadline_mode"><?php esc_html_e( 'Termine 14 giorni', 'woo-legal-returns' ); ?></label></th>
						<td>
							<select name="wlr_deadline_mode" id="wlr_deadline_mode">
								<option value="advisory" <?php selected( $deadline_mode, 'advisory' ); ?>><?php esc_html_e( 'Avviso e verifica manuale', 'woo-legal-returns' ); ?></option>
								<option value="strict" <?php selected( $deadline_mode, 'strict' ); ?>><?php esc_html_e( 'Blocca oltre termine', 'woo-legal-returns' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_deadline_basis"><?php esc_html_e( 'Decorrenza termine', 'woo-legal-returns' ); ?></label></th>
						<td>
							<select name="wlr_deadline_basis" id="wlr_deadline_basis">
								<option value="completed_or_paid" <?php selected( $deadline_basis, 'completed_or_paid' ); ?>><?php esc_html_e( 'Completamento/pagamento ordine', 'woo-legal-returns' ); ?></option>
								<option value="created" <?php selected( $deadline_basis, 'created' ); ?>><?php esc_html_e( 'Data creazione ordine', 'woo-legal-returns' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Queste basi producono una stima. Il blocco strict richiede una data attendibile, l’informativa completa e il calendario verificato, registrati nell’ordine.', 'woo-legal-returns' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_deadline_grace_days"><?php esc_html_e( 'Giorni di tolleranza', 'woo-legal-returns' ); ?></label></th>
						<td><input type="number" min="0" max="365" name="wlr_deadline_grace_days" id="wlr_deadline_grace_days" value="<?php echo esc_attr( $grace_days ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Checkout e consensi', 'woo-legal-returns' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Avviso checkout', 'woo-legal-returns' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wlr_checkout_notice_enabled" value="1" <?php checked( $checkout_enabled ); ?>>
								<?php esc_html_e( 'Mostra avviso informativo al checkout', 'woo-legal-returns' ); ?>
							</label>
							<p>
								<textarea name="wlr_checkout_notice_text" rows="3" class="large-text"><?php echo esc_textarea( $checkout_text ); ?></textarea>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_digital_consent_text"><?php esc_html_e( 'Consenso contenuti digitali', 'woo-legal-returns' ); ?></label></th>
						<td><textarea name="wlr_digital_consent_text" id="wlr_digital_consent_text" rows="3" class="large-text"><?php echo esc_textarea( $digital_text ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wlr_service_consent_text"><?php esc_html_e( 'Consenso servizi', 'woo-legal-returns' ); ?></label></th>
						<td><textarea name="wlr_service_consent_text" id="wlr_service_consent_text" rows="3" class="large-text"><?php echo esc_textarea( $service_text ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( __( 'Salva impostazioni', 'woo-legal-returns' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function render_list(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged         = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'posts_per_page' => 20,
			'paged'          => $paged,
		);

		if ( $status_filter && ( array_key_exists( $status_filter, WLR_Post_Type::STATUSES ) || 'trash' === $status_filter ) ) {
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only detail view.
		$return_id = absint( wp_unslash( $_GET['id'] ?? 0 ) );
		$post      = $return_id ? get_post( $return_id ) : null;

		if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Richiesta di reso non trovata.', 'woo-legal-returns' ) );
		}

		$order_id    = (int) get_post_meta( $return_id, '_wlr_order_id', true );
		$customer_id = (int) get_post_meta( $return_id, '_wlr_customer_id', true );
		$order       = wc_get_order( $order_id );
		$wp_customer = $customer_id ? get_userdata( $customer_id ) : null;
		// Oggetto cliente compatibile sia per registrati che ospiti.
		if ( $wp_customer ) {
			$customer = $wp_customer;
		} elseif ( $order ) {
			$customer = (object) array(
				'display_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'user_email'   => $order->get_billing_email(),
				'ID'           => 0,
			);
		} else {
			$customer = null;
		}
		$saved_name  = (string) get_post_meta( $return_id, '_wlr_customer_name', true );
		$saved_email = (string) get_post_meta( $return_id, '_wlr_customer_email', true );
		if ( $saved_email ) {
			$customer = (object) array(
				'display_name' => $saved_name ? $saved_name : ( $customer->display_name ?? __( 'Cliente', 'woo-legal-returns' ) ),
				'user_email'   => $saved_email,
				'ID'           => $customer_id,
			);
		}

		$items_raw               = get_post_meta( $return_id, '_wlr_items', true );
		$items                   = is_array( $items_raw ) ? $items_raw : array();
		$reason                  = get_post_meta( $return_id, '_wlr_reason', true );
		$history_raw             = get_post_meta( $return_id, '_wlr_history', true );
		$history                 = is_array( $history_raw ) ? $history_raw : array();
		$created_at              = get_post_meta( $return_id, '_wlr_created_at', true );
		$receipt_hash            = get_post_meta( $return_id, '_wlr_receipt_hash', true );
		$submitted_at_utc        = get_post_meta( $return_id, '_wlr_submitted_at_utc', true );
		$ip                      = get_post_meta( $return_id, '_wlr_ip', true );
		$user_agent              = get_post_meta( $return_id, '_wlr_user_agent', true );
		$deadline_at             = get_post_meta( $return_id, '_wlr_deadline_at', true );
		$deadline_may_be_expired = '1' === get_post_meta( $return_id, '_wlr_deadline_may_be_expired', true );
		$checkout_consents       = $order instanceof \WC_Order && class_exists( 'WLR_Checkout_Consent' )
			? WLR_Checkout_Consent::get_order_consents( $order )
			: array();
		$reasons                 = WLR_Customer_Account::instance()->get_return_reasons();

		include WLR_PLUGIN_DIR . 'templates/admin/detail.php';
	}

	// -------------------------------------------------------------------------
	// Azioni
	// -------------------------------------------------------------------------

	public function add_orders_withdrawal_column( array $columns ): array {
		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $label ) {
			if ( 'order_status' === $key ) {
				$new_columns['wlr_withdrawal'] = __( 'Recesso', 'woo-legal-returns' );
				$inserted                      = true;
			}

			$new_columns[ $key ] = $label;
		}

		if ( ! $inserted ) {
			$new_columns['wlr_withdrawal'] = __( 'Recesso', 'woo-legal-returns' );
		}

		return $new_columns;
	}

	public function prime_legacy_order_returns( array $posts, $query ): array {
		if ( is_admin() && $query->is_main_query() && 'shop_order' === $query->get( 'post_type' ) ) {
			WLR_Post_Type::prime_order_returns( wp_list_pluck( $posts, 'ID' ) );
		}
		return $posts;
	}

	public function prime_hpos_order_returns( array $args ): array {
		if ( 'shop_order' !== ( $args['type'] ?? 'shop_order' ) ) {
			return $args;
		}
		$lookup             = $args;
		$lookup['paginate'] = false;
		$lookup['return']   = 'ids';
		WLR_Post_Type::prime_order_returns( wc_get_orders( $lookup ) );
		return $args;
	}

	public function render_orders_withdrawal_column( string $column, mixed $order_or_id = null ): void {
		if ( 'wlr_withdrawal' !== $column ) {
			return;
		}

		$order_id = $order_or_id instanceof WC_Order ? $order_or_id->get_id() : absint( $order_or_id );
		if ( ! $order_id ) {
			echo '&mdash;';
			return;
		}

		$return = WLR_Post_Type::get_return_by_order( $order_id );
		if ( ! $return ) {
			echo '&mdash;';
			return;
		}

		$url = admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return->ID );
		echo '<a href="' . esc_url( $url ) . '"><span class="wlr-badge ' . esc_attr( WLR_Post_Type::get_status_class( $return->post_status ) ) . '">' . esc_html( WLR_Post_Type::get_status_label( $return->post_status ) ) . '</span></a>';
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		check_admin_referer( 'wlr_save_settings' );

		$page_id = absint( wp_unslash( $_POST['wlr_withdrawal_page_id'] ?? 0 ) );
		if ( ! $page_id ) {
			$page_id = WLR_Customer_Account::ensure_withdrawal_page();
		}
		if ( $page_id ) {
			update_option( 'wlr_withdrawal_page_id', $page_id );
		}

		$emails_raw  = sanitize_text_field( wp_unslash( $_POST['wlr_notify_emails'] ?? '' ) );
		$email_parts = preg_split( '/[\s,;]+/', $emails_raw );
		$emails      = array_filter(
			array_map(
				'sanitize_email',
				false === $email_parts ? array() : $email_parts
			)
		);
		update_option( 'wlr_notify_emails', implode( ', ', array_unique( $emails ) ) );

		$statuses = array_map( 'sanitize_key', (array) wp_unslash( $_POST['wlr_eligible_order_statuses'] ?? array() ) );
		$statuses = array_values( array_filter( $statuses ) );
		if ( empty( $statuses ) ) {
			$statuses = array( 'processing', 'completed' );
		}
		update_option( 'wlr_eligible_order_statuses', $statuses );

		$deadline_mode = sanitize_key( wp_unslash( $_POST['wlr_deadline_mode'] ?? 'advisory' ) );
		update_option( 'wlr_deadline_mode', in_array( $deadline_mode, array( 'advisory', 'strict' ), true ) ? $deadline_mode : 'advisory' );

		$deadline_basis = sanitize_key( wp_unslash( $_POST['wlr_deadline_basis'] ?? 'completed_or_paid' ) );
		update_option( 'wlr_deadline_basis', in_array( $deadline_basis, array( 'completed_or_paid', 'created' ), true ) ? $deadline_basis : 'completed_or_paid' );
		update_option( 'wlr_deadline_grace_days', min( 365, absint( wp_unslash( $_POST['wlr_deadline_grace_days'] ?? 0 ) ) ) );

		update_option( 'wlr_digital_consent_text', wp_kses_post( wp_unslash( $_POST['wlr_digital_consent_text'] ?? '' ) ) );
		update_option( 'wlr_service_consent_text', wp_kses_post( wp_unslash( $_POST['wlr_service_consent_text'] ?? '' ) ) );

		$setup_options                            = (array) get_option( 'wlr_setup_options', array() );
		$setup_options['checkout_notice_enabled'] = ! empty( $_POST['wlr_checkout_notice_enabled'] );
		$setup_options['checkout_notice_text']    = wp_kses_post( wp_unslash( $_POST['wlr_checkout_notice_text'] ?? '' ) );
		update_option( 'wlr_setup_options', $setup_options );

		wp_safe_redirect( admin_url( 'admin.php?page=wlr-settings&updated=1' ) );
		exit;
	}

	public function handle_bulk_update_returns(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		check_admin_referer( 'wlr_bulk_update_returns' );

		$return_ids = array_map( 'absint', (array) wp_unslash( $_POST['return_ids'] ?? array() ) );
		$new_status = sanitize_key( wp_unslash( $_POST['bulk_status'] ?? '' ) );
		$note       = sanitize_textarea_field( wp_unslash( $_POST['bulk_note'] ?? '' ) );

		if ( ! array_key_exists( $new_status, WLR_Post_Type::STATUSES ) || empty( $return_ids ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wlr-returns&bulk_updated=0' ) );
			exit;
		}

		$updated = 0;
		foreach ( $return_ids as $return_id ) {
			$post = get_post( $return_id );
			if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$old_status = $post->post_status;
			if ( $new_status === $old_status && '' === trim( $note ) ) {
				continue;
			}
			if ( ! WLR_Post_Type::update_status( $return_id, $new_status, $note ) ) {
				continue;
			}

			++$updated;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wlr-returns&bulk_updated=' . $updated ) );
		exit;
	}

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

		$lock = WLR_Lock::acquire( 'order:' . absint( get_post_meta( $return_id, '_wlr_order_id', true ) ) );
		if ( is_wp_error( $lock ) ) {
			wp_die( esc_html( $lock->get_error_message() ) );
		}
		try {
			if ( ! wp_trash_post( $return_id ) ) {
				wp_die( esc_html__( 'Spostamento nel cestino fallito.', 'woo-legal-returns' ) );
			}
			wp_clear_scheduled_hook( 'wlr_retry_receipt', array( $return_id ) );
		} finally {
			WLR_Lock::release( $lock );
		}

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
				wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'woo-legal-returns' ) ) );
				return;
			}

			$return_id  = absint( wp_unslash( $_POST['return_id'] ?? 0 ) );
			$new_status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
			$note       = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

			if ( ! $return_id || ! $new_status ) {
				wp_send_json_error( array( 'message' => __( 'Dati mancanti.', 'woo-legal-returns' ) ) );
				return;
			}

			$ok = WLR_Post_Type::update_status( $return_id, $new_status, $note );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'Aggiornamento fallito.', 'woo-legal-returns' ) ) );
				return;
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Stato aggiornato.', 'woo-legal-returns' ),
					'new_status'   => $new_status,
					'status_label' => WLR_Post_Type::get_status_label( $new_status ),
				)
			);

		} catch ( \Throwable $e ) {
			$this->log_error( 'handle_update_status error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array( 'message' => __( 'Errore interno. Controlla il debug.log del server.', 'woo-legal-returns' ) ) );
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

		$return_id  = absint( wp_unslash( $_POST['return_id'] ?? 0 ) );
		$new_status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$note       = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

		if ( ! WLR_Post_Type::update_status( $return_id, $new_status, $note ) ) {
			wp_die( esc_html__( 'Aggiornamento fallito. Se rifiuti una richiesta devi indicare una nota per il cliente.', 'woo-legal-returns' ) );
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return_id . '&updated=1' )
		);
		exit;
	}

	public function handle_export_returns(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		check_admin_referer( 'wlr_export_returns' );

		$args = array(
			'post_type'      => WLR_Post_Type::POST_TYPE,
			'post_status'    => array_keys( WLR_Post_Type::STATUSES ),
			'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded streaming export batch.
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		if ( $status && array_key_exists( $status, WLR_Post_Type::STATUSES ) ) {
			$args['post_status'] = $status;
		}

		$date_query = array();
		$date_from  = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
		$date_to    = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_query['after'] = $date_from . ' 00:00:00';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_query['before'] = $date_to . ' 23:59:59';
		}
		if ( $date_query ) {
			$date_query['inclusive'] = true;
			$args['date_query']      = array( $date_query );
		}

		$include_pii = ! empty( $_POST['include_pii'] );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wlr-returns-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$columns = array(
			'ID',
			'Data invio UTC',
			'Ordine',
			'Email',
			'Stato',
			'Motivo',
			'Prodotti',
			'Hash ricevuta',
			'Ricevuta inviata',
			'Deadline indicativa',
			'Deadline superata',
		);

		if ( $include_pii ) {
			$columns[] = 'IP';
			$columns[] = 'User agent';
		}

		fputcsv( $output, array_map( array( $this, 'csv_escape' ), $columns ), ',', '"', '' );

		$args['paged'] = 1;
		do {
			$posts = get_posts( $args );
			foreach ( $posts as $post ) {
				$items      = get_post_meta( $post->ID, '_wlr_items', true );
				$items      = is_array( $items ) ? $items : array();
				$item_names = array_map(
					static fn( $item ) => ( $item['name'] ?? '#' . ( $item['item_id'] ?? '' ) ) . ' x' . (int) ( $item['qty'] ?? 1 ),
					$items
				);

				$row = array(
					$post->ID,
					get_post_meta( $post->ID, '_wlr_submitted_at_utc', true ),
					get_post_meta( $post->ID, '_wlr_order_id', true ),
					get_post_meta( $post->ID, '_wlr_customer_email', true ),
					WLR_Post_Type::get_status_label( $post->post_status ),
					get_post_meta( $post->ID, '_wlr_reason', true ),
					implode( '; ', $item_names ),
					get_post_meta( $post->ID, '_wlr_receipt_hash', true ),
					'1' === get_post_meta( $post->ID, '_wlr_receipt_sent', true ) ? 'yes' : 'no',
					get_post_meta( $post->ID, '_wlr_deadline_at', true ),
					'1' === get_post_meta( $post->ID, '_wlr_deadline_may_be_expired', true ) ? 'yes' : 'no',
				);

				if ( $include_pii ) {
					$row[] = get_post_meta( $post->ID, '_wlr_ip', true );
					$row[] = get_post_meta( $post->ID, '_wlr_user_agent', true );
				}

				fputcsv( $output, array_map( array( $this, 'csv_escape' ), $row ), ',', '"', '' );
			}
			++$args['paged'];
			$batch_count = count( $posts );
		} while ( 200 === $batch_count );

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function csv_escape( mixed $value ): string {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	public function handle_resend_receipt(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}
		$return_id = absint( $_POST['return_id'] ?? 0 );
		check_admin_referer( 'wlr_resend_receipt_' . $return_id );
		WLR_Emails::instance()->send_customer_received( $return_id, true );
		wp_safe_redirect( admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return_id ) );
		exit;
	}

	public function handle_restore_return(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}
		$return_id = absint( $_POST['return_id'] ?? 0 );
		check_admin_referer( 'wlr_restore_return_' . $return_id );
		$post   = get_post( $return_id );
		$status = (string) get_post_meta( $return_id, '_wp_trash_meta_status', true );
		if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type || 'trash' !== $post->post_status
			|| ! WLR_Post_Type::update_status( $return_id, array_key_exists( $status, WLR_Post_Type::STATUSES ) ? $status : 'wlr-requested' ) ) {
			wp_die( esc_html__( 'Ripristino fallito: verifica ordine, esclusioni e quantità già impegnate da altre richieste.', 'woo-legal-returns' ) );
		}
		delete_post_meta( $return_id, '_wp_trash_meta_status' );
		delete_post_meta( $return_id, '_wp_trash_meta_time' );
		wp_safe_redirect( admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return_id ) );
		exit;
	}

	private function log_error( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'woo-legal-returns' ) );
		}
	}
}
