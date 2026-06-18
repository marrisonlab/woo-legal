<?php
/**
 * Gestione notifiche email per le richieste di reso.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Emails {

	private static ?WLR_Emails $instance = null;

	/** Traccia l'email corrente in cui il link è già stato stampato (email_id::order_id). */
	private string $link_printed_key = '';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'wlr_return_created',        [ $this, 'on_return_created' ], 10, 3 );
		add_action( 'wlr_return_status_changed', [ $this, 'on_status_changed' ], 10, 3 );

		// Link di recesso nelle email ordine WooCommerce.
		// Hook 1: dopo la tabella ordine (posizione ideale, dipende dalla template).
		add_action( 'woocommerce_email_after_order_table', [ $this, 'add_withdrawal_link_to_order_email' ], 10, 4 );
		// Hook 2: nel footer (fallback robusto – chiesto da WC_Email::get_footer(), non sovrascrivibile).
		add_action( 'woocommerce_email_footer', [ $this, 'add_withdrawal_link_to_email_footer' ] );
	}

	/**
	 * Email inviate quando viene creata una nuova richiesta.
	 *
	 * @param int $return_id
	 * @param int $order_id
	 * @param int $customer_id
	 */
	public function on_return_created( int $return_id, int $order_id, int $customer_id ): void {
		$this->send_customer_received( $return_id );
		$this->send_admin_new_request( $return_id );
	}

	/**
	 * Email inviate quando lo stato cambia.
	 *
	 * @param int    $return_id
	 * @param string $new_status
	 * @param string $old_status
	 */
	public function on_status_changed( int $return_id, string $new_status, string $old_status ): void {
		$this->send_customer_status_update( $return_id, $new_status );
		try {
			$this->maybe_update_wc_order_status( $return_id, $new_status );
		} catch ( \Throwable $e ) {
			error_log( '[WLR] Errore update ordine WC: ' . $e->getMessage() );
		}
	}

	/**
	 * Aggiorna automaticamente lo stato dell'ordine WooCommerce.
	 * - reso approvato → ordine in sospeso
	 * - reso rimborsato → ordine rimborsato
	 *
	 * @param int    $return_id
	 * @param string $new_status
	 */
	private function maybe_update_wc_order_status( int $return_id, string $new_status ): void {
		$wc_status_map = [
			'wlr-approved' => 'on-hold',
			'wlr-refunded' => 'refunded',
		];

		if ( ! isset( $wc_status_map[ $new_status ] ) ) {
			return;
		}

		$order_id = (int) get_post_meta( $return_id, '_wlr_order_id', true );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order->update_status(
			$wc_status_map[ $new_status ],
			__( 'Aggiornato automaticamente da richiesta di reso #', 'woo-legal-returns' ) . $return_id
		);
	}

	/**
	 * Hook 1: stampa il link dopo la tabella ordine (posizione ideale).
	 * Dipende dalla template WC – potrebbe non girare se sovrascritta dal tema.
	 */
	public function add_withdrawal_link_to_order_email( $order, $sent_to_admin, $plain_text, $email ): void {
		$email_id = $email->id ?? '';

		if ( $sent_to_admin || $plain_text ) {
			return;
		}

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$allowed = [ 'customer_processing_order', 'customer_completed_order' ];
		if ( ! in_array( $email_id, $allowed, true ) ) {
			return;
		}

		if ( ! $this->should_show_withdrawal_link( $order ) ) {
			return;
		}

		$this->output_withdrawal_link( $order );
		$this->link_printed_key = $email_id . '::' . $order->get_id();
	}

	/**
	 * Hook 2: fallback nel footer dell'email.
	 * Chiesto da WC_Email::get_footer() → sempre disponibile indipendentemente dal tema.
	 */
	public function add_withdrawal_link_to_email_footer( $email ): void {
		// Recupera l'ordine dal contesto dell'email.
		if ( ! is_object( $email ) || ! isset( $email->id ) ) {
			$this->link_printed_key = '';
			return;
		}

		$allowed = [ 'customer_processing_order', 'customer_completed_order' ];
		if ( ! in_array( $email->id, $allowed, true ) ) {
			return;
		}

		$order = $email->object ?? null;
		if ( ! $order instanceof \WC_Order ) {
			$this->link_printed_key = '';
			return;
		}

		$current_key = $email->id . '::' . $order->get_id();

		// Se il link è già stato stampato da Hook 1, salta (e resetta per la prossima email).
		if ( $this->link_printed_key === $current_key ) {
			$this->link_printed_key = '';
			return;
		}

		$this->link_printed_key = '';

		if ( ! $this->should_show_withdrawal_link( $order ) ) {
			return;
		}

		$this->output_withdrawal_link( $order );
	}

	/**
	 * Controlla se il link di recesso deve essere mostrato per questo ordine.
	 */
	private function should_show_withdrawal_link( \WC_Order $order ): bool {
		if ( ! WLR_Post_Type::is_within_return_window( $order ) ) {
			return false;
		}
		if ( WLR_Post_Type::get_return_by_order( $order->get_id() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Stampa il blocco HTML con il link di recesso.
	 */
	private function output_withdrawal_link( \WC_Order $order ): void {
		$customer_id = (int) $order->get_customer_id();
		$is_guest    = ( 0 === $customer_id );

		if ( $is_guest ) {
			$return_url = add_query_arg(
				[ 'ordine' => $order->get_id(), 'key' => $order->get_order_key() ],
				get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . WLR_Customer_Account::ENDPOINT . '/'
			);
		} else {
			$return_url = wc_get_account_endpoint_url( WLR_Customer_Account::ENDPOINT );
		}

		printf(
			'<p style="margin-top:20px;padding:12px 15px;background:#f8f8f8;border-left:4px solid #96588a;font-size:13px;">' .
			'<strong>%s</strong> %s <a href="%s" style="color:#96588a;">%s</a></p>',
			esc_html__( 'Diritto di recesso:', 'woo-legal-returns' ),
			esc_html__( 'Hai 14 giorni dalla ricezione per recedere dal contratto.', 'woo-legal-returns' ),
			esc_url( $return_url ),
			esc_html__( 'Recedere dal contratto qui', 'woo-legal-returns' )
		);
	}

	// -------------------------------------------------------------------------
	// Email al cliente: conferma ricezione
	// -------------------------------------------------------------------------

	private function send_customer_received( int $return_id ): void {
		$data = $this->get_email_data( $return_id );
		if ( ! $data ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: return ID */
			__( '[%s] Richiesta di reso #%d ricevuta', 'woo-legal-returns' ),
			get_bloginfo( 'name' ),
			$return_id
		);

		$message = $this->get_template_content(
			'emails/customer-return-received.php',
			$data
		);

		$this->send( $data['customer_email'], $subject, $message );
	}

	// -------------------------------------------------------------------------
	// Email all'admin: nuova richiesta
	// -------------------------------------------------------------------------

	private function send_admin_new_request( int $return_id ): void {
		$data = $this->get_email_data( $return_id );
		if ( ! $data ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: return ID */
			__( '[%s] Nuova richiesta di reso #%d', 'woo-legal-returns' ),
			get_bloginfo( 'name' ),
			$return_id
		);

		$message = $this->get_template_content(
			'emails/admin-new-request.php',
			$data
		);

		$this->send( get_option( 'admin_email' ), $subject, $message );
	}

	// -------------------------------------------------------------------------
	// Email al cliente: aggiornamento stato
	// -------------------------------------------------------------------------

	private function send_customer_status_update( int $return_id, string $new_status ): void {
		$data = $this->get_email_data( $return_id );
		if ( ! $data ) {
			return;
		}

		$status_labels = [
			'wlr-approved'  => __( 'approvata', 'woo-legal-returns' ),
			'wlr-rejected'  => __( 'rifiutata', 'woo-legal-returns' ),
			'wlr-refunded'  => __( 'rimborsata', 'woo-legal-returns' ),
			'wlr-cancelled' => __( 'annullata', 'woo-legal-returns' ),
		];

		if ( ! isset( $status_labels[ $new_status ] ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: blog name, 2: return id, 3: new status */
			__( '[%1$s] Aggiornamento richiesta reso #%2$d: %3$s', 'woo-legal-returns' ),
			get_bloginfo( 'name' ),
			$return_id,
			$status_labels[ $new_status ]
		);

		$data['new_status']       = $new_status;
		$data['new_status_label'] = WLR_Post_Type::get_status_label( $new_status );
		$history                  = get_post_meta( $return_id, '_wlr_history', true ) ?: [];
		$data['admin_note']       = ! empty( $history ) ? end( $history )['note'] : '';

		$message = $this->get_template_content(
			'emails/customer-status-update.php',
			$data
		);

		$this->send( $data['customer_email'], $subject, $message );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Raccoglie i dati comuni per le email.
	 *
	 * @param int $return_id
	 * @return array|null
	 */
	private function get_email_data( int $return_id ): ?array {
		$post = get_post( $return_id );
		if ( ! $post || WLR_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$order_id    = (int) get_post_meta( $return_id, '_wlr_order_id', true );
		$customer_id = (int) get_post_meta( $return_id, '_wlr_customer_id', true );
		$order       = wc_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		// Supporto ospiti: $customer_id può essere 0.
		$is_guest = ( 0 === $customer_id );
		$customer = $is_guest ? null : get_userdata( $customer_id );

		// Oggetto "cliente" sintetico per i template (funziona sia per registrati che ospiti).
		$customer_obj = (object) [
			'display_name' => $is_guest
				? trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() )
				: ( $customer ? $customer->display_name : $order->get_billing_first_name() ),
			'user_email'   => $order->get_billing_email(),
		];

		// URL per il cliente: con order_key per ospiti, My Account per registrati.
		if ( $is_guest ) {
			$account_url = add_query_arg(
				[ 'ordine' => $order_id, 'key' => $order->get_order_key() ],
				get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . WLR_Customer_Account::ENDPOINT . '/'
			);
		} else {
			$account_url = wc_get_account_endpoint_url( WLR_Customer_Account::ENDPOINT );
		}

		return [
			'return_id'      => $return_id,
			'return_post'    => $post,
			'order'          => $order,
			'order_id'       => $order_id,
			'customer'       => $customer_obj,
			'is_guest'       => $is_guest,
			'customer_email' => $order->get_billing_email(),
			'reason'         => get_post_meta( $return_id, '_wlr_reason', true ),
			'reason_label'   => ( static function( string $key ): string {
				$r = WLR_Customer_Account::instance()->get_return_reasons();
				return $r[ $key ] ?? $key;
			} )( get_post_meta( $return_id, '_wlr_reason', true ) ),
			'items'          => get_post_meta( $return_id, '_wlr_items', true ) ?: [],
			'notes'          => $post->post_content,
			'status'         => $post->post_status,
			'status_label'   => WLR_Post_Type::get_status_label( $post->post_status ),
			'created_at'     => get_post_meta( $return_id, '_wlr_created_at', true ),
			'admin_url'      => admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $return_id ),
			'account_url'    => $account_url,
			'blog_name'      => get_bloginfo( 'name' ),
		];
	}

	/**
	 * Carica un template email e restituisce l'HTML.
	 *
	 * @param string $template_name
	 * @param array  $data
	 * @return string
	 */
	private function get_template_content( string $template_name, array $data ): string {
		// Il plugin usa i template di WooCommerce (header/footer email).
		ob_start();

		wc_get_template(
			$template_name,
			$data,
			'woo-legal-returns/',
			WLR_PLUGIN_DIR . 'templates/'
		);

		return ob_get_clean();
	}

	/**
	 * Invia un'email HTML usando wp_mail con intestazioni WooCommerce.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 */
	private function send( string $to, string $subject, string $message ): void {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		wp_mail( $to, $subject, $message, $headers );
	}
}
