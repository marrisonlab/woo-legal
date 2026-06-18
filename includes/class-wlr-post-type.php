<?php
/**
 * Custom Post Type per le richieste di reso e logica di gestione dati.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Post_Type {

	/** Slug CPT */
	const POST_TYPE = 'wlr_return';

	/** Stati disponibili */
	const STATUSES = [
		'wlr-requested'  => 'Richiesto',
		'wlr-approved'   => 'Approvato',
		'wlr-rejected'   => 'Rifiutato',
		'wlr-refunded'   => 'Rimborsato',
		'wlr-cancelled'  => 'Annullato',
	];

	private static ?WLR_Post_Type $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_statuses' ] );
	}

	/**
	 * Registra il Custom Post Type.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'               => __( 'Richieste di Reso', 'woo-legal-returns' ),
				'labels'              => [
					'name'               => __( 'Richieste di Reso', 'woo-legal-returns' ),
					'singular_name'      => __( 'Richiesta di Reso', 'woo-legal-returns' ),
					'add_new'            => __( 'Nuova Richiesta', 'woo-legal-returns' ),
					'add_new_item'       => __( 'Aggiungi Richiesta di Reso', 'woo-legal-returns' ),
					'edit_item'          => __( 'Modifica Richiesta di Reso', 'woo-legal-returns' ),
					'view_item'          => __( 'Visualizza Richiesta', 'woo-legal-returns' ),
					'search_items'       => __( 'Cerca Richieste', 'woo-legal-returns' ),
					'not_found'          => __( 'Nessuna richiesta trovata.', 'woo-legal-returns' ),
					'not_found_in_trash' => __( 'Nessuna richiesta nel cestino.', 'woo-legal-returns' ),
				],
				'public'              => false,
				'show_ui'             => false, // Gestiamo tutto con la nostra dashboard
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'editor', 'custom-fields' ],
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			]
		);
	}

	/**
	 * Registra gli stati personalizzati del post.
	 */
	public static function register_statuses(): void {
		foreach ( self::STATUSES as $status => $label ) {
			register_post_status(
				$status,
				[
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count */
					'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'woo-legal-returns' ),
				]
			);
		}
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Crea una nuova richiesta di reso.
	 *
	 * @param array $data {
	 *   @type int    $order_id      ID ordine WooCommerce.
	 *   @type int    $customer_id   ID utente WordPress.
	 *   @type array  $items         Prodotti da rendere [{item_id, qty, reason}].
	 *   @type string $reason        Motivo principale del reso.
	 *   @type string $notes         Note aggiuntive del cliente.
	 * }
	 * @return int|\WP_Error ID del post creato o errore.
	 */
	public static function create_return( array $data ): int|\WP_Error {
		$order_id    = absint( $data['order_id'] ?? 0 );
		$customer_id = absint( $data['customer_id'] ?? 0 );

		if ( ! $order_id ) {
			return new \WP_Error( 'wlr_invalid_data', __( 'Dati non validi.', 'woo-legal-returns' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'wlr_invalid_order', __( 'Ordine non trovato.', 'woo-legal-returns' ) );
		}

		if ( 0 === $customer_id ) {
			// Ospite: verifica tramite order_key + billing_email.
			$order_key   = sanitize_text_field( $data['order_key'] ?? '' );
			$guest_email = sanitize_email( $data['guest_email'] ?? '' );

			if ( ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
				return new \WP_Error( 'wlr_invalid_key', __( 'Chiave ordine non valida.', 'woo-legal-returns' ) );
			}
			if ( $guest_email && strtolower( $order->get_billing_email() ) !== strtolower( $guest_email ) ) {
				return new \WP_Error( 'wlr_invalid_email', __( 'Email non corrispondente all\'ordine.', 'woo-legal-returns' ) );
			}
		} else {
			// Cliente registrato: verifica ownership tramite customer_id.
			if ( (int) $order->get_customer_id() !== $customer_id ) {
				return new \WP_Error( 'wlr_not_owner', __( 'Non sei il proprietario di questo ordine.', 'woo-legal-returns' ) );
			}
		}

		// Verifica finestra dei 14 giorni (dal completamento ordine).
		if ( ! self::is_within_return_window( $order ) ) {
			return new \WP_Error( 'wlr_expired', sprintf(
				/* translators: %d: number of days */
				__( 'Il periodo di recesso di %d giorni è scaduto.', 'woo-legal-returns' ),
				WLR_RETURN_DAYS
			) );
		}

		// Verifica che non esista già una richiesta attiva per lo stesso ordine.
		if ( self::get_return_by_order( $order_id ) ) {
			return new \WP_Error( 'wlr_duplicate', __( 'Esiste già una richiesta di reso per questo ordine.', 'woo-legal-returns' ) );
		}

		/* translators: %s: order number */
		$title = sprintf( __( 'Reso ordine #%s', 'woo-legal-returns' ), $order->get_order_number() );

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'wlr-requested',
				'post_author' => $customer_id,
				'post_content' => sanitize_textarea_field( $data['notes'] ?? '' ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Meta dati.
		update_post_meta( $post_id, '_wlr_order_id',    $order_id );
		update_post_meta( $post_id, '_wlr_customer_id', $customer_id );
		update_post_meta( $post_id, '_wlr_reason',      sanitize_text_field( $data['reason'] ?? '' ) );
		update_post_meta( $post_id, '_wlr_items',       self::sanitize_items( $data['items'] ?? [] ) );
		update_post_meta( $post_id, '_wlr_created_at',  current_time( 'mysql' ) );
		update_post_meta( $post_id, '_wlr_ip',          WC_Geolocation::get_ip_address() );

		// Per gli ospiti salva l'email di fatturazione come identificativo.
		if ( 0 === $customer_id ) {
			update_post_meta( $post_id, '_wlr_guest_email', sanitize_email( $data['guest_email'] ?? $order->get_billing_email() ) );
		}

		return $post_id;
	}

	/**
	 * Aggiorna lo stato di una richiesta di reso.
	 *
	 * @param int    $return_id ID del post reso.
	 * @param string $status    Nuovo stato.
	 * @param string $note      Nota opzionale dell'admin.
	 * @return bool
	 */
	public static function update_status( int $return_id, string $status, string $note = '' ): bool {
		if ( ! array_key_exists( $status, self::STATUSES ) ) {
			return false;
		}

		$updated = wp_update_post(
			[
				'ID'          => $return_id,
				'post_status' => $status,
			]
		);

		if ( $note ) {
			$history   = get_post_meta( $return_id, '_wlr_history', true ) ?: [];
			$history[] = [
				'status'  => $status,
				'note'    => sanitize_textarea_field( $note ),
				'user_id' => get_current_user_id(),
				'date'    => current_time( 'mysql' ),
			];
			update_post_meta( $return_id, '_wlr_history', $history );
		}

		return (bool) $updated;
	}

	/**
	 * Recupera la richiesta di reso per un dato ordine (se esiste e non è cancellata).
	 *
	 * @param int $order_id
	 * @return \WP_Post|null
	 */
	public static function get_return_by_order( int $order_id ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'meta_key'       => '_wlr_order_id',
				'meta_value'     => $order_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return get_post( $posts[0] );
	}

	/**
	 * Restituisce tutte le richieste di reso di un cliente.
	 *
	 * @param int $customer_id
	 * @return \WP_Post[]
	 */
	public static function get_returns_for_customer( int $customer_id ): array {
		return get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => array_keys( self::STATUSES ),
				'author'         => $customer_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
	}

	/**
	 * Restituisce le richieste di reso per la dashboard admin.
	 *
	 * @param array $args Argomenti WP_Query aggiuntivi.
	 * @return array{ posts: \WP_Post[], total: int }
	 */
	public static function get_returns_for_admin( array $args = [] ): array {
		$defaults = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => array_keys( self::STATUSES ),
			'posts_per_page' => 20,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query_args = wp_parse_args( $args, $defaults );
		$query      = new \WP_Query( $query_args );

		return [
			'posts' => $query->posts,
			'total' => $query->found_posts,
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verifica se l'ordine è ancora nella finestra di recesso.
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public static function is_within_return_window( \WC_Order $order ): bool {
		$completed_date = $order->get_date_completed() ?? $order->get_date_paid() ?? $order->get_date_created();
		if ( ! $completed_date ) {
			return false;
		}

		$days_elapsed = ( time() - $completed_date->getTimestamp() ) / DAY_IN_SECONDS;

		return $days_elapsed <= WLR_RETURN_DAYS;
	}

	/**
	 * Sanitizza l'array degli item del reso.
	 *
	 * @param array $items
	 * @return array
	 */
	private static function sanitize_items( array $items ): array {
		$clean = [];
		foreach ( $items as $item ) {
			$clean[] = [
				'item_id' => absint( $item['item_id'] ?? 0 ),
				'qty'     => absint( $item['qty'] ?? 1 ),
				'reason'  => sanitize_text_field( $item['reason'] ?? '' ),
			];
		}
		return $clean;
	}

	/**
	 * Etichetta leggibile dello stato.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function get_status_label( string $status ): string {
		return self::STATUSES[ $status ] ?? $status;
	}

	/**
	 * Badge CSS class per lo stato.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function get_status_class( string $status ): string {
		$map = [
			'wlr-requested' => 'wlr-badge--pending',
			'wlr-approved'  => 'wlr-badge--success',
			'wlr-rejected'  => 'wlr-badge--error',
			'wlr-refunded'  => 'wlr-badge--info',
			'wlr-cancelled' => 'wlr-badge--muted',
		];
		return $map[ $status ] ?? '';
	}
}
