<?php
/**
 * WordPress privacy tools integration.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Privacy {

	private static ?WLR_Privacy $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'register_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'export_order_consents' ), 10, 2 );
		add_action( 'woocommerce_privacy_before_remove_order_personal_data', array( $this, 'erase_order_consents' ) );
	}

	public function register_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'Quando invii una richiesta di recesso o reso tramite il modulo online, conserviamo i dati necessari a gestire e provare la ricezione della dichiarazione.', 'woo-legal-returns' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Nome, email, riferimento ordine e prodotti oggetto della richiesta.', 'woo-legal-returns' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Data e ora di invio, indirizzo IP, user agent e hash della ricevuta.', 'woo-legal-returns' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Stato della richiesta e comunicazioni amministrative collegate.', 'woo-legal-returns' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . esc_html__( 'I dati sono conservati localmente sul sito per gestire obblighi contrattuali, assistenza e prova dell’esercizio dei diritti del consumatore.', 'woo-legal-returns' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Woo Legal Returns', 'woo-legal-returns' ), wp_kses_post( $content ) );
	}

	public function register_exporter( array $exporters ): array {
		$exporters['wlr-returns'] = array(
			'exporter_friendly_name' => __( 'Woo Legal Returns', 'woo-legal-returns' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$items = array();
		$page  = max( 1, $page );
		$query = new WP_Query(
			array(
				'post_type'      => WLR_Post_Type::POST_TYPE,
				'post_status'    => array_merge( array_keys( WLR_Post_Type::STATUSES ), array( 'trash' ) ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => 50,
				'paged'          => $page,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wlr_customer_email',
						'value' => sanitize_email( $email_address ),
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$data = array(
				array(
					'name'  => __( 'Richiesta', 'woo-legal-returns' ),
					'value' => '#' . $post_id,
				),
				array(
					'name'  => __( 'Ordine', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_order_id', true ),
				),
				array(
					'name'  => __( 'Email', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_customer_email', true ),
				),
				array(
					'name'  => __( 'Motivo', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_reason', true ),
				),
				array(
					'name'  => __( 'Note', 'woo-legal-returns' ),
					'value' => $post->post_content,
				),
				array(
					'name'  => __( 'Stato', 'woo-legal-returns' ),
					'value' => WLR_Post_Type::get_status_label( $post->post_status ),
				),
				array(
					'name'  => __( 'Data invio UTC', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_submitted_at_utc', true ),
				),
				array(
					'name'  => __( 'IP', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_ip', true ),
				),
				array(
					'name'  => __( 'User agent', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_user_agent', true ),
				),
				array(
					'name'  => __( 'Hash ricevuta', 'woo-legal-returns' ),
					'value' => get_post_meta( $post_id, '_wlr_receipt_hash', true ),
				),
			);
			foreach ( array(
				'_wlr_customer_name' => 'Nome',
				'_wlr_items'         => 'Articoli',
				'_wlr_history'       => 'Cronologia',
				'_wlr_declaration'   => 'Dichiarazione confermata',
			) as $key => $label ) {
				$value  = get_post_meta( $post_id, $key, true );
				$data[] = array(
					'name'  => $label,
					'value' => is_array( $value ) ? wp_json_encode( $value ) : (string) $value,
				);
			}

			$items[] = array(
				'group_id'    => 'wlr-returns',
				'group_label' => __( 'Richieste di recesso e reso', 'woo-legal-returns' ),
				'item_id'     => 'wlr-return-' . $post_id,
				'data'        => array_values(
					array_filter(
						$data,
						static fn( $row ) => '' !== (string) $row['value']
					)
				),
			);
		}

		return array(
			'data' => $items,
			'done' => $page >= max( 1, (int) $query->max_num_pages ),
		);
	}

	public function register_eraser( array $erasers ): array {
		$erasers['wlr-returns'] = array(
			'eraser_friendly_name' => __( 'Woo Legal Returns', 'woo-legal-returns' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$page = max( 1, $page );
		$key  = 'wlr_privacy_erase_' . wp_hash( strtolower( sanitize_email( $email_address ) ) );
		if ( 1 === $page ) {
			$ids = get_posts(
				array(
					'post_type'      => WLR_Post_Type::POST_TYPE,
					'post_status'    => array_merge( array_keys( WLR_Post_Type::STATUSES ), array( 'trash' ) ),
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_wlr_customer_email',
							'value' => sanitize_email( $email_address ),
						),
					),
				)
			);
			set_transient( $key, $ids, DAY_IN_SECONDS );
		} else {
			$ids = get_transient( $key );
		}
		if ( ! is_array( $ids ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array( __( 'Sessione di cancellazione scaduta: avvia nuovamente la richiesta privacy.', 'woo-legal-returns' ) ),
				'done'           => true,
			);
		}
		$batch = array_slice( $ids, ( $page - 1 ) * 50, 50 );

		$removed  = false;
		$retained = false;
		$messages = array();

		foreach ( $batch as $post_id ) {
			if ( ! get_post( $post_id ) ) {
				continue;
			}
			if ( wp_delete_post( $post_id, true ) ) {
				wp_clear_scheduled_hook( 'wlr_retry_receipt', array( (int) $post_id ) );
				$removed = true;
			} else {
				$retained   = true;
				$messages[] = sprintf(
					/* translators: %d: request ID. */
					__( 'La richiesta #%d non puo essere eliminata.', 'woo-legal-returns' ),
					$post_id
				);
			}
		}

		$done = $page * 50 >= count( $ids );
		if ( $done ) {
			delete_transient( $key );
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	public function export_order_consents( array $data, WC_Order $order ): array {
		foreach ( WLR_Checkout_Consent::get_order_consents( $order ) as $type => $consent ) {
			$data[] = array(
				'name'  => 'Woo Legal Returns — ' . $type,
				'value' => wp_json_encode( $consent ),
			);
		}
		return $data;
	}

	public function erase_order_consents( WC_Order $order ): void {
		foreach ( array( 'digital', 'service' ) as $type ) {
			foreach ( array( 'accepted', 'text', 'timestamp', 'ip', 'ua', 'loss_acknowledged' ) as $suffix ) {
				$order->delete_meta_data( '_wlr_consent_' . $type . '_' . $suffix );
			}
			$order->delete_meta_data( '_wc_other/woo-legal/' . $type );
		}
		$order->save();
	}
}
