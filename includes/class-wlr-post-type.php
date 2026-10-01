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
	private static array $order_returns     = array();

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
		$lock = WLR_Lock::acquire( 'order:' . absint( $data['order_id'] ?? 0 ) );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			// Revalidate inside the lock, using fresh data rather than the preview cache.
			self::$order_returns = array();
			$token               = sanitize_text_field( $data['request_token'] ?? '' );
			if ( $token ) {
				foreach ( self::get_order_returns( absint( $data['order_id'] ?? 0 ) ) as $existing ) {
					if ( hash_equals( (string) get_post_meta( $existing->ID, '_wlr_request_token', true ), hash( 'sha256', $token ) ) ) {
						return (int) $existing->ID;
					}
				}
			}
			$result = self::persist_return( $data );
		} finally {
			self::$order_returns = array();
			WLR_Lock::release( $lock );
		}
		if ( ! is_wp_error( $result ) ) {
			try {
				do_action( 'wlr_return_created', $result, absint( get_post_meta( $result, '_wlr_order_id', true ) ), absint( get_post_meta( $result, '_wlr_customer_id', true ) ) );
			} catch ( Throwable $error ) {
				wc_get_logger()->error( $error->getMessage(), array( 'source' => 'woo-legal-returns' ) );
			}
		}
		return $result;
	}

	private static function persist_return( array $data ): int|\WP_Error {
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
		update_post_meta( $post_id, '_wlr_customer_name', $validation['customer_name'] );
		update_post_meta( $post_id, '_wlr_order_number', $order->get_order_number() );
		update_post_meta( $post_id, '_wlr_request_token', hash( 'sha256', (string) ( $data['request_token'] ?? wp_generate_uuid4() ) ) );
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
		$declaration = array(
			'name'             => $validation['customer_name'],
			'email'            => $validation['customer_email'],
			'order_id'         => $order_id,
			'order_number'     => (string) $order->get_order_number(),
			'statement'        => self::get_declaration_text(),
			'items'            => $validation['items'],
			'reason'           => $validation['reason'],
			'reason_label'     => WLR_Customer_Account::instance()->get_return_reasons()[ $validation['reason'] ] ?? '',
			'notes'            => $validation['notes'],
			'submitted_at_utc' => $submitted_at_utc,
			'trader'           => array_intersect_key( (array) get_option( 'wlr_setup_options', array() ), array_flip( array( 'trader_name', 'trader_address', 'trader_email', 'trader_phone' ) ) ),
		);
		update_post_meta( $post_id, '_wlr_declaration', $declaration );

		if ( 0 === $customer_id ) {
			update_post_meta( $post_id, '_wlr_guest_email', $validation['customer_email'] );
		}

		$receipt_hash = self::compute_receipt_hash( $post_id );
		update_post_meta( $post_id, '_wlr_receipt_hash', $receipt_hash );

		try {
			$order->add_order_note(
				sprintf(
				/* translators: 1: request ID, 2: receipt hash. */
					__( 'Richiesta di recesso #%1$d registrata. Hash ricevuta: %2$s', 'woo-legal-returns' ),
					$post_id,
					$receipt_hash
				),
				false
			);
		} catch ( Throwable $error ) {
			wc_get_logger()->error( $error->getMessage(), array( 'source' => 'woo-legal-returns' ) );
		}
		// Recover receipt delivery even if the process ends before the immediate send.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wlr_retry_receipt', array( $post_id ) );

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

		if ( ! $order_id || ( $reason && ! array_key_exists( $reason, WLR_Customer_Account::instance()->get_return_reasons() ) ) ) {
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
		if ( self::deadline_is_strict() && ! empty( $deadline['reliable'] ) && $deadline['may_be_expired'] ) {
			return new WP_Error(
				'wlr_expired',
				sprintf(
					/* translators: %d: number of days. */
					__( 'Il periodo indicativo di recesso di %d giorni risulta scaduto.', 'woo-legal-returns' ),
					WLR_RETURN_DAYS
				)
			);
		}

		$items = self::validate_items_for_order( $order, is_array( $data['items'] ?? null ) ? $data['items'] : array() );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$name = sanitize_text_field( $data['customer_name'] ?? '' );
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'wlr_missing_name', __( 'Indica nome e cognome del richiedente.', 'woo-legal-returns' ) );
		}

		return array(
			'order'          => $order,
			'customer_id'    => $customer_id,
			'customer_email' => $auth['email'],
			'customer_name'  => $name,
			'reason'         => $reason,
			'notes'          => $notes,
			'items'          => $items,
			'deadline'       => $deadline,
		);
	}

	public static function update_status( int $return_id, string $status, string $note = '' ): bool {
		$order_id = absint( get_post_meta( $return_id, '_wlr_order_id', true ) );
		$lock     = WLR_Lock::acquire( 'order:' . $order_id );
		if ( is_wp_error( $lock ) ) {
			return false;
		}
		try {
			self::$order_returns = array();
			$before              = get_post( $return_id );
			$result              = self::persist_status( $return_id, $status, $note );
		} finally {
			self::$order_returns = array();
			WLR_Lock::release( $lock );
		}
		if ( $result && $before && 'trash' !== $before->post_status && ( $before->post_status !== $status || '' !== trim( $note ) ) ) {
			try {
				do_action( 'wlr_return_status_changed', $return_id, $status, $before->post_status );
			} catch ( Throwable $error ) {
				wc_get_logger()->error( $error->getMessage(), array( 'source' => 'woo-legal-returns' ) );
			}
		}
		return $result;
	}

	private static function persist_status( int $return_id, string $status, string $note ): bool {
		if ( ! array_key_exists( $status, self::STATUSES ) ) {
			return false;
		}

		$post = get_post( $return_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( $status === $post->post_status && '' === trim( $note ) ) {
			return true;
		}

		if ( 'wlr-rejected' === $status && '' === trim( $note ) && ! in_array( $post->post_status, array( 'wlr-rejected', 'trash' ), true ) ) {
			return false;
		}
		$reserving = array( 'wlr-requested', 'wlr-approved', 'wlr-refunded' );
		if ( in_array( $status, $reserving, true ) && ! in_array( $post->post_status, $reserving, true ) ) {
			$order = wc_get_order( absint( get_post_meta( $return_id, '_wlr_order_id', true ) ) );
			if ( ! $order || is_wp_error( self::validate_items_for_order( $order, (array) get_post_meta( $return_id, '_wlr_items', true ) ) ) ) {
				return false;
			}
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
		self::$order_returns = array();
		if ( 'trash' === $post->post_status && '1' !== get_post_meta( $return_id, '_wlr_receipt_sent', true ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wlr_retry_receipt', array( $return_id ) );
		}
		return true;
	}

	public static function get_return_by_order( int $order_id ): ?WP_Post {
		$posts = self::get_order_returns( $order_id );
		return $posts[0] ?? null;
	}

	public static function prime_order_returns( array $order_ids ): void {
		$missing = array_diff( array_map( 'absint', $order_ids ), array_keys( self::$order_returns ) );
		if ( ! $missing ) {
			return;
		}
		foreach ( $missing as $order_id ) {
			self::$order_returns[ $order_id ] = array();
		}
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'meta_key'       => '_wlr_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $missing, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare'   => 'IN',
				'posts_per_page' => -1,
				'orderby'        => array(
			'date' => 'DESC',
			'ID'   => 'DESC',
			),
			)
		);
		foreach ( $posts as $post ) {
			$order_id                           = (int) get_post_meta( $post->ID, '_wlr_order_id', true );
			self::$order_returns[ $order_id ][] = $post;
		}
	}

	public static function get_order_returns( int $order_id ): array {
		self::prime_order_returns( array( $order_id ) );
		return self::$order_returns[ $order_id ];
	}

	public static function get_blocking_return_by_order( int $order_id ): ?WP_Post {
		$order = wc_get_order( $order_id );
		return $order && ! self::get_returnable_items( $order ) ? self::get_return_by_order( $order_id ) : null;
	}

	public static function get_returns_for_customer( int $customer_id, int $page = 1 ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'author'         => $customer_id,
				'posts_per_page' => 20,
				'paged'          => max( 1, $page ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	public static function get_returns_for_email( string $email, int $page = 1 ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'posts_per_page' => 20,
				'paged'          => max( 1, $page ),
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

		$args                     = wp_parse_args( $args, $defaults );
		$args['wlr_admin_search'] = $args['s'] ?? '';
		add_filter( 'posts_search', array( self::class, 'filter_admin_search' ), 20, 2 );
		try {
			$query = new WP_Query( $args );
		} finally {
			remove_filter( 'posts_search', array( self::class, 'filter_admin_search' ), 20 );
		}

		return array(
			'posts' => $query->posts,
			'total' => $query->found_posts,
		);
	}

	public static function filter_admin_search( string $sql, $query ): string {
		global $wpdb;
		$search = (string) $query->get( 'wlr_admin_search' );
		if ( '' === $search ) {
			return $sql;
		}
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return $wpdb->prepare(
			" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} wlr_search_meta WHERE wlr_search_meta.post_id = {$wpdb->posts}.ID AND wlr_search_meta.meta_key IN ('_wlr_customer_email', '_wlr_customer_name', '_wlr_order_number', '_wlr_order_id') AND wlr_search_meta.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->users} wlr_search_user WHERE wlr_search_user.ID = {$wpdb->posts}.post_author AND (wlr_search_user.display_name LIKE %s OR wlr_search_user.user_email LIKE %s)))",
			$like,
			$like,
			$like,
			$like,
			$like
		);
	}

	public static function is_within_return_window( WC_Order $order ): bool {
		if ( ! self::deadline_is_strict() ) {
			return true;
		}

		$deadline = self::get_order_deadline_info( $order );

		return empty( $deadline['reliable'] ) || ! $deadline['may_be_expired'];
	}

	public static function get_order_deadline_info( WC_Order $order ): array {
		$basis = (string) get_option( 'wlr_deadline_basis', 'completed_or_paid' );

		$received  = (string) $order->get_meta( '_wlr_received_date' );
		$date      = null;
		$reliable  = false;
		$has_goods = false;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && 'goods' === WLR_Product_Settings::get_item_contract_type( $item ) ) {
				$has_goods = true;
				break;
			}
		}
		if ( $has_goods && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $received ) ) {
			$date        = DateTimeImmutable::createFromFormat( '!Y-m-d', $received, wp_timezone() );
			$reliable    = $date && $date->format( 'Y-m-d' ) === $received;
			$date        = $reliable ? $date : null;
			$basis_label = 'received';
		} elseif ( ! $has_goods ) {
			$date        = $order->get_date_created();
			$basis_label = 'contract';
			$reliable    = (bool) $date;
		} elseif ( 'created' === $basis ) {
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
				'reliable'       => false,
			);
		}

		$grace_days = max( 0, absint( get_option( 'wlr_deadline_grace_days', 0 ) ) );
		$end        = ( new DateTimeImmutable( '@' . $date->getTimestamp() ) )->setTimezone( wp_timezone() )->modify( '+' . ( WLR_RETURN_DAYS + $grace_days ) . ' days' )->setTime( 23, 59, 59 );
		// Calendar deadlines falling on a weekend or configured holiday extend to the next working day.
		$holidays = (array) apply_filters( 'wlr_deadline_holidays', (array) $order->get_meta( '_wlr_deadline_holidays' ), $order );
		for ( $day = 0; $day < 366; $day++ ) {
			if ( (int) $end->format( 'N' ) < 6 && ! in_array( $end->format( 'Y-m-d' ), $holidays, true ) ) {
				break;
			}
			$end = $end->modify( '+1 day' );
		}
		$timestamp = $end->getTimestamp();
		$reliable  = $reliable && 'yes' === $order->get_meta( '_wlr_withdrawal_information_complete' )
			&& (bool) apply_filters( 'wlr_deadline_calendar_verified', 'yes' === $order->get_meta( '_wlr_deadline_calendar_verified' ), $order );

		return array(
			'basis'          => $basis_label,
			'date'           => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'timestamp'      => $timestamp,
			'may_be_expired' => time() > $timestamp,
			'reliable'       => $reliable,
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

		$snapshot = get_post_meta( $return_id, '_wlr_declaration', true );
		$payload  = is_array( $snapshot ) ? $snapshot : array(
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

	public static function validate_order_access( WC_Order $order, int $customer_id, array $data ): array|WP_Error {
		if ( 0 === $customer_id || ( 0 === (int) $order->get_customer_id() && $customer_id !== (int) $order->get_customer_id() ) ) {
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

		$clean       = array();
		$available   = self::get_returnable_items( $order );
		$order_items = $order->get_items();
		$seen        = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error( 'wlr_invalid_items', __( 'La selezione degli articoli non è valida.', 'woo-legal-returns' ) );
			}
			$item_id = filter_var( $item['item_id'] ?? 0, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			$qty     = filter_var( $item['qty'] ?? 0, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

			if ( ! $item_id || $qty < 1 ) {
				return new WP_Error( 'wlr_invalid_items', __( 'La selezione dei prodotti non e valida.', 'woo-legal-returns' ) );
			}

			$order_item = $order_items[ $item_id ] ?? null;
			if ( ! $order_item instanceof WC_Order_Item_Product || isset( $seen[ $item_id ] ) ) {
				return new WP_Error( 'wlr_invalid_item', __( 'Uno dei prodotti selezionati non appartiene all\'ordine.', 'woo-legal-returns' ) );
			}

			$seen[ $item_id ] = true;
			$product          = $order_item->get_product();
			if ( ! isset( $available[ $item_id ] ) ) {
				return new WP_Error( 'wlr_non_returnable_item', __( 'Uno dei prodotti selezionati non e recedibile tramite questo modulo.', 'woo-legal-returns' ) );
			}

			if ( $qty > $available[ $item_id ] ) {
				return new WP_Error( 'wlr_invalid_qty', __( 'La quantita richiesta supera quella ordinata.', 'woo-legal-returns' ) );
			}

			$product_id = (int) $order_item->get_product_id();

			if ( WLR_Product_Settings::is_item_excluded( $order_item, $order ) ) {
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
				'sku'        => $order_item->get_meta( '_wlr_product_sku' ) ? (string) $order_item->get_meta( '_wlr_product_sku' ) : ( $product ? $product->get_sku() : '' ),
			);
		}

		return $clean;
	}

	public static function get_declaration_text(): string {
		return __( 'Con la presente notifico la mia decisione di recedere dal contratto relativo all’ordine e ai prodotti o servizi indicati in questa dichiarazione.', 'woo-legal-returns' );
	}

	public static function get_returnable_items( WC_Order $order ): array {
		$reserved = array();
		foreach ( self::get_order_returns( $order->get_id() ) as $post ) {
			if ( ! in_array( $post->post_status, array( 'wlr-requested', 'wlr-approved', 'wlr-refunded' ), true ) ) {
				continue;
			}
			foreach ( (array) get_post_meta( $post->ID, '_wlr_items', true ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id                         = absint( $row['item_id'] ?? 0 );
				$target                     = 'wlr-refunded' === $post->post_status ? 'refunded' : 'open';
				$reserved[ $id ][ $target ] = ( $reserved[ $id ][ $target ] ?? 0 ) + absint( $row['qty'] ?? 0 );
			}
		}
		$result = array();
		foreach ( $order->get_items() as $id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || WLR_Product_Settings::is_item_excluded( $item, $order ) ) {
				continue;
			}
			$wc_refunded = abs( (int) $order->get_qty_refunded_for_item( $id ) );
			$remaining   = (int) $item->get_quantity() - ( $reserved[ $id ]['open'] ?? 0 ) - max( $reserved[ $id ]['refunded'] ?? 0, $wc_refunded );
			if ( $remaining > 0 ) {
				$result[ $id ] = $remaining;
			}
		}
		return $result;
	}

	private static function get_user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}
}
