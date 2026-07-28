<?php
/**
 * Withdrawal request CPT and persistence logic.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Post_Type {

	public const POST_TYPE = 'wlr_return';

	public const STATUSES = array(
		'wlr-requested' => 'Richiesto',
		'wlr-approved'  => 'Approvato',
		'wlr-rejected'  => 'Rifiutato',
		'wlr-refunded'  => 'Rimborsato',
		'wlr-cancelled' => 'Annullato',
	);

	private static ?WLR_Post_Type $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_statuses' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Richieste di Reso', 'woo-legal-returns' ),
				'labels'          => array(
					'name'               => __( 'Richieste di Reso', 'woo-legal-returns' ),
					'singular_name'      => __( 'Richiesta di Reso', 'woo-legal-returns' ),
					'add_new'            => __( 'Nuova Richiesta', 'woo-legal-returns' ),
					'add_new_item'       => __( 'Aggiungi Richiesta di Reso', 'woo-legal-returns' ),
					'edit_item'          => __( 'Modifica Richiesta di Reso', 'woo-legal-returns' ),
					'view_item'          => __( 'Visualizza Richiesta', 'woo-legal-returns' ),
					'search_items'       => __( 'Cerca Richieste', 'woo-legal-returns' ),
					'not_found'          => __( 'Nessuna richiesta trovata.', 'woo-legal-returns' ),
					'not_found_in_trash' => __( 'Nessuna richiesta nel cestino.', 'woo-legal-returns' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	public static function register_statuses(): void {
		foreach ( self::STATUSES as $status => $label ) {
			register_post_status(
				$status,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count */
					'label_count'               => _n_noop( 'Richiesta di reso <span class="count">(%s)</span>', 'Richieste di reso <span class="count">(%s)</span>', 'woo-legal-returns' ),
				)
			);
		}
	}

	/**
	 * Create a withdrawal request after full server-side validation.
	 *
	 * @param array $data Raw request data.
	 * @return int|\WP_Error
	 */
	public static function create_return( array $data ): int|\WP_Error {
		$validation = self::validate_return_request( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		/** @var WC_Order $order */
		$order       = $validation['order'];
		$order_id    = $order->get_id();
		$customer_id = (int) $validation['customer_id'];
		$deadline    = $validation['deadline'];

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				/* translators: %s: WooCommerce order number. */
				'post_title'   => sprintf( __( 'Reso ordine #%s', 'woo-legal-returns' ), $order->get_order_number() ),
				'post_status'  => 'wlr-requested',
				'post_author'  => $customer_id,
				'post_content' => $validation['notes'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$submitted_at_utc = current_time( 'mysql', true );

		update_post_meta( $post_id, '_wlr_order_id', $order_id );
		update_post_meta( $post_id, '_wlr_customer_id', $customer_id );
		update_post_meta( $post_id, '_wlr_customer_email', $validation['customer_email'] );
		update_post_meta( $post_id, '_wlr_reason', $validation['reason'] );
		update_post_meta( $post_id, '_wlr_items', $validation['items'] );
		update_post_meta( $post_id, '_wlr_created_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_wlr_submitted_at_utc', $submitted_at_utc );
		update_post_meta( $post_id, '_wlr_ip', WC_Geolocation::get_ip_address() );
		update_post_meta( $post_id, '_wlr_user_agent', self::get_user_agent() );
		update_post_meta( $post_id, '_wlr_deadline_basis', $deadline['basis'] );
		update_post_meta( $post_id, '_wlr_deadline_at', $deadline['date'] );
		update_post_meta( $post_id, '_wlr_deadline_may_be_expired', $deadline['may_be_expired'] ? '1' : '0' );
		update_post_meta( $post_id, '_wlr_excluded_items', WLR_Product_Settings::get_excluded_items_in_order( $order_id ) );
		update_post_meta( $post_id, '_wlr_receipt_sent', '0' );

		if ( 0 === $customer_id ) {
			update_post_meta( $post_id, '_wlr_guest_email', $validation['customer_email'] );
		}

		$receipt_hash = self::compute_receipt_hash( $post_id );
		update_post_meta( $post_id, '_wlr_receipt_hash', $receipt_hash );

		$order->add_order_note(
			sprintf(
				/* translators: 1: request ID, 2: receipt hash. */
				__( 'Richiesta di recesso #%1$d registrata. Hash ricevuta: %2$s', 'woo-legal-returns' ),
				$post_id,
				$receipt_hash
			),
			false
		);

		return $post_id;
	}

	/**
	 * Validate and normalize a request without creating it.
	 *
	 * @param array $data Raw request data.
	 * @return array|\WP_Error
	 */
	public static function validate_return_request( array $data ): array|\WP_Error {
		$order_id    = absint( $data['order_id'] ?? 0 );
		$customer_id = absint( $data['customer_id'] ?? 0 );
		$reason      = sanitize_key( $data['reason'] ?? '' );
		$notes       = sanitize_textarea_field( $data['notes'] ?? '' );

		if ( ! $order_id || ! $reason ) {
			return new WP_Error( 'wlr_invalid_data', __( 'Compila tutti i campi obbligatori.', 'woo-legal-returns' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'wlr_invalid_order', __( 'Ordine non trovato.', 'woo-legal-returns' ) );
		}

		$auth = self::validate_order_access( $order, $customer_id, $data );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		if ( ! in_array( $order->get_status(), self::get_eligible_order_statuses(), true ) ) {
			return new WP_Error( 'wlr_status_not_eligible', __( 'Questo ordine non e in uno stato idoneo per aprire una richiesta di recesso.', 'woo-legal-returns' ) );
		}

		$deadline = self::get_order_deadline_info( $order );
		if ( self::deadline_is_strict() && $deadline['may_be_expired'] ) {
			return new WP_Error(
				'wlr_expired',
				sprintf(
					/* translators: %d: number of days. */
					__( 'Il periodo indicativo di recesso di %d giorni risulta scaduto.', 'woo-legal-returns' ),
					WLR_RETURN_DAYS
				)
			);
		}

		if ( self::get_blocking_return_by_order( $order_id ) ) {
			return new WP_Error( 'wlr_duplicate', __( 'Esiste gia una richiesta di reso aperta per questo ordine.', 'woo-legal-returns' ) );
		}

		$items = self::validate_items_for_order( $order, is_array( $data['items'] ?? null ) ? $data['items'] : array() );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		return array(
			'order'          => $order,
			'customer_id'    => $customer_id,
			'customer_email' => $auth['email'],
			'reason'         => $reason,
			'notes'          => $notes,
			'items'          => $items,
			'deadline'       => $deadline,
		);
	}

	public static function update_status( int $return_id, string $status, string $note = '' ): bool {
		if ( ! array_key_exists( $status, self::STATUSES ) ) {
			return false;
		}

		$post = get_post( $return_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( 'wlr-rejected' === $status && '' === trim( $note ) && 'wlr-rejected' !== $post->post_status ) {
			return false;
		}

		$updated = wp_update_post(
			array(
				'ID'          => $return_id,
				'post_status' => $status,
			)
		);

		if ( ! $updated || is_wp_error( $updated ) ) {
			return false;
		}

		update_post_meta( $return_id, '_wlr_status_changed_at', current_time( 'mysql', true ) );

		$history   = get_post_meta( $return_id, '_wlr_history', true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = array(
			'status'  => $status,
			'note'    => sanitize_textarea_field( $note ),
			'user_id' => get_current_user_id(),
			'date'    => current_time( 'mysql' ),
		);
		update_post_meta( $return_id, '_wlr_history', $history );

		return true;
	}

	public static function get_return_by_order( int $order_id ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'meta_key'       => '_wlr_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $order_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return empty( $posts ) ? null : get_post( $posts[0] );
	}

	public static function get_blocking_return_by_order( int $order_id ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'wlr-requested', 'wlr-approved', 'wlr-refunded' ),
				'meta_key'       => '_wlr_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $order_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return empty( $posts ) ? null : get_post( $posts[0] );
	}

	public static function get_returns_for_customer( int $customer_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'author'         => $customer_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	public static function get_returns_for_email( string $email ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wlr_customer_email',
						'value' => sanitize_email( $email ),
					),
				),
			)
		);
	}

	public static function get_returns_for_admin( array $args = array() ): array {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array_keys( self::STATUSES ),
			'posts_per_page' => 20,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( wp_parse_args( $args, $defaults ) );

		return array(
			'posts' => $query->posts,
			'total' => $query->found_posts,
		);
	}

	public static function is_within_return_window( WC_Order $order ): bool {
		if ( ! self::deadline_is_strict() ) {
			return true;
		}

		$deadline = self::get_order_deadline_info( $order );

		return ! $deadline['may_be_expired'];
	}

	public static function get_order_deadline_info( WC_Order $order ): array {
		$basis = (string) get_option( 'wlr_deadline_basis', 'completed_or_paid' );

		if ( 'created' === $basis ) {
			$date        = $order->get_date_created();
			$basis_label = 'created';
		} else {
			$date        = $order->get_date_completed() ?? $order->get_date_paid() ?? $order->get_date_created();
			$basis_label = 'completed_or_paid';
		}

		if ( ! $date ) {
			return array(
				'basis'          => $basis_label,
				'date'           => '',
				'timestamp'      => 0,
				'may_be_expired' => false,
			);
		}

		$grace_days = max( 0, absint( get_option( 'wlr_deadline_grace_days', 0 ) ) );
		$timestamp  = $date->getTimestamp() + ( WLR_RETURN_DAYS * DAY_IN_SECONDS ) + ( $grace_days * DAY_IN_SECONDS );

		return array(
			'basis'          => $basis_label,
			'date'           => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'timestamp'      => $timestamp,
			'may_be_expired' => time() > $timestamp,
		);
	}

	public static function deadline_is_strict(): bool {
		return 'strict' === get_option( 'wlr_deadline_mode', 'advisory' );
	}

	public static function get_eligible_order_statuses(): array {
		$statuses = get_option( 'wlr_eligible_order_statuses', array( 'processing', 'completed' ) );
		$statuses = is_array( $statuses ) ? array_map( 'sanitize_key', $statuses ) : array( 'processing', 'completed' );

		return array_values( array_filter( $statuses ) );
	}

	public static function compute_receipt_hash( int $return_id ): string {
		$post = get_post( $return_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$payload = array(
			'id'           => $return_id,
			'order_id'     => get_post_meta( $return_id, '_wlr_order_id', true ),
			'email'        => get_post_meta( $return_id, '_wlr_customer_email', true ),
			'reason'       => get_post_meta( $return_id, '_wlr_reason', true ),
			'items'        => get_post_meta( $return_id, '_wlr_items', true ),
			'notes'        => $post->post_content,
			'submitted_at' => get_post_meta( $return_id, '_wlr_submitted_at_utc', true ),
		);

		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	public static function get_status_label( string $status ): string {
		return self::STATUSES[ $status ] ?? $status;
	}

	public static function get_status_class( string $status ): string {
		$map = array(
			'wlr-requested' => 'wlr-badge--pending',
			'wlr-approved'  => 'wlr-badge--success',
			'wlr-rejected'  => 'wlr-badge--error',
			'wlr-refunded'  => 'wlr-badge--info',
			'wlr-cancelled' => 'wlr-badge--muted',
		);

		return $map[ $status ] ?? '';
	}

	private static function validate_order_access( WC_Order $order, int $customer_id, array $data ): array|WP_Error {
		if ( 0 === $customer_id ) {
			$order_key   = sanitize_text_field( $data['order_key'] ?? '' );
			$guest_email = sanitize_email( $data['guest_email'] ?? '' );

			if ( ! $guest_email || strtolower( $order->get_billing_email() ) !== strtolower( $guest_email ) ) {
				return new WP_Error( 'wlr_invalid_email', __( 'Email non corrispondente all\'ordine.', 'woo-legal-returns' ) );
			}

			if ( $order_key && ! hash_equals( $order->get_order_key(), $order_key ) ) {
				return new WP_Error( 'wlr_invalid_key', __( 'Chiave ordine non valida.', 'woo-legal-returns' ) );
			}

			if ( ! $order_key && ! (bool) apply_filters( 'wlr_allow_guest_email_order_lookup', true, $order ) ) {
				return new WP_Error( 'wlr_invalid_key', __( 'Chiave ordine non valida.', 'woo-legal-returns' ) );
			}

			return array( 'email' => $guest_email );
		}

		if ( (int) $order->get_customer_id() !== $customer_id ) {
			return new WP_Error( 'wlr_not_owner', __( 'Non sei il proprietario di questo ordine.', 'woo-legal-returns' ) );
		}

		$user  = get_userdata( $customer_id );
		$email = sanitize_email( $order->get_billing_email() );
		if ( ! $email && $user ) {
			$email = sanitize_email( $user->user_email );
		}

		return array( 'email' => $email );
	}

	private static function validate_items_for_order( WC_Order $order, array $items ): array|WP_Error {
		if ( empty( $items ) ) {
			return new WP_Error( 'wlr_no_items', __( 'Seleziona almeno un prodotto recedibile.', 'woo-legal-returns' ) );
		}

		$clean = array();

		foreach ( $items as $item ) {
			$item_id = absint( $item['item_id'] ?? 0 );
			$qty     = absint( $item['qty'] ?? 0 );

			if ( ! $item_id || $qty < 1 ) {
				return new WP_Error( 'wlr_invalid_items', __( 'La selezione dei prodotti non e valida.', 'woo-legal-returns' ) );
			}

			$order_item = $order->get_item( $item_id );
			if ( ! $order_item instanceof WC_Order_Item_Product ) {
				return new WP_Error( 'wlr_invalid_item', __( 'Uno dei prodotti selezionati non appartiene all\'ordine.', 'woo-legal-returns' ) );
			}

			$product = $order_item->get_product();
			if ( ! $product || $product->is_virtual() || $product->is_downloadable() ) {
				return new WP_Error( 'wlr_non_returnable_item', __( 'Uno dei prodotti selezionati non e recedibile tramite questo modulo.', 'woo-legal-returns' ) );
			}

			if ( $qty > (int) $order_item->get_quantity() ) {
				return new WP_Error( 'wlr_invalid_qty', __( 'La quantita richiesta supera quella ordinata.', 'woo-legal-returns' ) );
			}

			$product_id   = $product->get_id();
			$variation_id = method_exists( $order_item, 'get_variation_id' ) ? (int) $order_item->get_variation_id() : 0;
			$check_id     = $variation_id ? $variation_id : $product_id;

			if ( WLR_Product_Settings::is_no_return( $check_id ) ) {
				return new WP_Error(
					'wlr_excluded_item',
					sprintf(
						/* translators: %s: product name. */
						__( 'Il prodotto "%s" e escluso dal diritto di recesso.', 'woo-legal-returns' ),
						$order_item->get_name()
					)
				);
			}

			$clean[] = array(
				'item_id'    => $item_id,
				'product_id' => $product_id,
				'qty'        => $qty,
				'name'       => $order_item->get_name(),
				'sku'        => $product->get_sku(),
			);
		}

		return $clean;
	}

	private static function get_user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}
}
