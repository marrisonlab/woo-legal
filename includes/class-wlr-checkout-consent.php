<?php
/**
 * Checkout withdrawal consents.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Checkout_Consent {

	private static ?WLR_Checkout_Consent $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_fields' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_fields' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 20, 2 );
		add_action( 'woocommerce_init', array( $this, 'register_block_fields' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_block_consents' ), 5 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_consents' ), 20, 4 );
		add_action( 'admin_notices', array( $this, 'compatibility_notice' ) );
	}

	public function render_fields(): void {
		$requires_digital = self::cart_requires_consent( 'digital' );
		$requires_service = self::cart_requires_consent( 'service' );

		if ( ! $requires_digital && ! $requires_service ) {
			return;
		}

		echo '<div class="wlr-checkout-consents">';
		echo '<h3>' . esc_html__( 'Consensi sul diritto di recesso', 'woo-legal-returns' ) . '</h3>';

		if ( $requires_digital ) {
			woocommerce_form_field(
				'wlr_consent_digital',
				array(
					'type'     => 'checkbox',
					'required' => true,
					'label'    => wp_kses_post( self::get_consent_text( 'digital' ) ),
				)
			);
		}

		if ( $requires_service ) {
			woocommerce_form_field(
				'wlr_consent_service',
				array(
					'type'     => 'checkbox',
					'required' => false,
					'label'    => wp_kses_post( self::get_consent_text( 'service' ) ),
				)
			);
		}

		echo '</div>';
	}

	public function validate_fields( array $data, WP_Error $errors ): void {
		unset( $data );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout nonce before this hook.
		if ( self::cart_requires_consent( 'digital' ) && empty( $_POST['wlr_consent_digital'] ) ) {
			$errors->add(
				'wlr_consent_digital_required',
				__( 'Per acquistare contenuti digitali con esecuzione immediata devi prestare il consenso espresso richiesto per l\'eccezione al diritto di recesso.', 'woo-legal-returns' )
			);
		}
	}

	public function save_order_meta( WC_Order $order, array $data ): void {
		unset( $data );

		$timestamp = gmdate( 'c' );
		$ip        = WC_Geolocation::get_ip_address();
		$ua        = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		foreach ( array( 'digital', 'service' ) as $type ) {
			if ( ! self::cart_requires_consent( $type ) ) {
				continue;
			}

			$field = 'wlr_consent_' . $type;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout nonce before order creation.
			$accepted = ! empty( $_POST[ $field ] ) ? 'yes' : 'no';

			$order->update_meta_data( '_wlr_consent_' . $type . '_accepted', $accepted );
			$order->update_meta_data( '_wlr_consent_' . $type . '_text', wp_strip_all_tags( self::get_consent_text( $type ) ) );
			$order->update_meta_data( '_wlr_consent_' . $type . '_timestamp', $timestamp );
			$order->update_meta_data( '_wlr_consent_' . $type . '_ip', $ip );
			$order->update_meta_data( '_wlr_consent_' . $type . '_ua', $ua );
			$order->update_meta_data( '_wlr_consent_' . $type . '_loss_acknowledged', $accepted );
		}
	}

	public static function cart_requires_consent( string $type ): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( WLR_Product_Settings::product_requires_checkout_consent( $variation_id ? $variation_id : $product_id, $type ) ) {
				return true;
			}
		}

		return false;
	}

	public static function get_consent_text( string $type ): string {
		if ( 'service' === $type ) {
			$option_text = (string) get_option( 'wlr_service_consent_text', '' );
			if ( '' !== trim( $option_text ) ) {
				return (string) apply_filters( 'wlr_service_consent_text', $option_text . ' ' . __( 'Riconosco che perderò il diritto di recesso quando il servizio sarà interamente eseguito.', 'woo-legal-returns' ) );
			}

			return (string) apply_filters(
				'wlr_service_consent_text',
				__( 'Chiedo espressamente che il servizio inizi durante il periodo di recesso. Riconosco che, in caso di recesso dopo l’inizio, potrà essere dovuto un importo proporzionale al servizio prestato e che perderò il diritto di recesso quando il servizio sarà interamente eseguito.', 'woo-legal-returns' )
			);
		}

		$option_text = (string) get_option( 'wlr_digital_consent_text', '' );
		if ( '' !== trim( $option_text ) ) {
			return (string) apply_filters( 'wlr_digital_consent_text', $option_text . ' ' . __( 'Riconosco che, iniziata l’esecuzione del contenuto digitale, perderò il diritto di recesso nei casi previsti dalla normativa.', 'woo-legal-returns' ) );
		}

		return (string) apply_filters(
			'wlr_digital_consent_text',
			__( 'Chiedo espressamente l\'esecuzione immediata del contenuto digitale e riconosco che, una volta iniziata l\'esecuzione, perdero il diritto di recesso nei casi previsti dalla normativa applicabile.', 'woo-legal-returns' )
		);
	}

	public function register_block_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || version_compare( WC_VERSION, '9.9', '<' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => 'woo-legal',
				'data_callback'   => static fn() => array(
					'digital' => self::cart_requires_consent( 'digital' ),
					'service' => self::cart_requires_consent( 'service' ),
				),
				'schema_callback' => static fn() => array(
					'digital' => array(
						'type'     => 'boolean',
						'readonly' => true,
					),
					'service' => array(
						'type'     => 'boolean',
						'readonly' => true,
					),
				),
				'schema_type'     => ARRAY_A,
			)
		);
		foreach ( array( 'digital', 'service' ) as $type ) {
			$schema = static fn( $value ) => array(
				'properties' => array(
					'cart' => array(
						'properties' => array(
							'extensions' => array(
								'properties' => array(
									'woo-legal' => array(
										'required'   => array( $type ),
										'properties' => array( $type => array( 'const' => $value ) ),
									),
								),
								'required'   => array( 'woo-legal' ),
							),
						),
						'required'   => array( 'extensions' ),
					),
				),
				'required'   => array( 'cart' ),
			);
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'woo-legal/' . $type,
					'label'    => wp_strip_all_tags( self::get_consent_text( $type ) ),
					'location' => 'order',
					'type'     => 'checkbox',
					'required' => 'digital' === $type ? $schema( true ) : false,
					'hidden'   => $schema( false ),
				)
			);
		}
	}

	public function save_block_consents( WC_Order $order ): void {
		$fields = class_exists( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class )
			? \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class ) : null;
		foreach ( array( 'digital', 'service' ) as $type ) {
			if ( ! self::cart_requires_consent( $type ) ) {
				// A changed cart must not retain a consent from a previous checkout attempt.
				foreach ( array( 'accepted', 'text', 'timestamp', 'ip', 'ua', 'loss_acknowledged' ) as $suffix ) {
					$order->delete_meta_data( '_wlr_consent_' . $type . '_' . $suffix );
				}
				continue;
			}
			$accepted = $fields && (bool) $fields->get_field_from_object( 'woo-legal/' . $type, $order, 'other' );
			if ( 'digital' === $type && ! $accepted ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'wlr_consent_required', esc_html__( 'Consenso digitale mancante. Su WooCommerce precedente alla versione 9.9 usa il checkout classico o aggiorna WooCommerce.', 'woo-legal-returns' ), 400 );
			}
			$order->update_meta_data( '_wlr_consent_' . $type . '_accepted', $accepted ? 'yes' : 'no' );
			$order->update_meta_data( '_wlr_consent_' . $type . '_text', wp_strip_all_tags( self::get_consent_text( $type ) ) );
			$order->update_meta_data( '_wlr_consent_' . $type . '_timestamp', gmdate( 'c' ) );
			$order->update_meta_data( '_wlr_consent_' . $type . '_ip', WC_Geolocation::get_ip_address() );
			$order->update_meta_data( '_wlr_consent_' . $type . '_ua', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
			$order->update_meta_data( '_wlr_consent_' . $type . '_loss_acknowledged', $accepted ? 'yes' : 'no' );
		}
		$order->save();
	}

	public function render_email_consents( $order, $sent_to_admin, $plain_text, $email ): void {
		unset( $email );
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		foreach ( self::get_order_consents( $order ) as $consent ) {
			$text = ( 'yes' === $consent['accepted'] ? esc_html__( 'Consenso espresso: ', 'woo-legal-returns' ) : esc_html__( 'Consenso non prestato: ', 'woo-legal-returns' ) ) . $consent['text'] . ' — ' . $consent['timestamp'];
			echo $plain_text ? "\n" . esc_html( $text ) . "\n" : '<p>' . esc_html( $text ) . '</p>';
		}
	}

	public function compatibility_notice(): void {
		if ( current_user_can( 'manage_woocommerce' ) && version_compare( WC_VERSION, '9.9', '<' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Woo Legal Returns: i consensi nel checkout a blocchi richiedono WooCommerce 9.9 o successivo. Per gli ordini digitali con esecuzione immediata usa il checkout classico oppure aggiorna WooCommerce.', 'woo-legal-returns' ) . '</p></div>';
		}
	}

	public static function get_order_consents( WC_Order $order ): array {
		$consents = array();

		foreach ( array( 'digital', 'service' ) as $type ) {
			$text = (string) $order->get_meta( '_wlr_consent_' . $type . '_text' );
			if ( '' === $text ) {
				continue;
			}

			$consents[ $type ] = array(
				'accepted'          => (string) $order->get_meta( '_wlr_consent_' . $type . '_accepted' ),
				'text'              => $text,
				'timestamp'         => (string) $order->get_meta( '_wlr_consent_' . $type . '_timestamp' ),
				'ip'                => (string) $order->get_meta( '_wlr_consent_' . $type . '_ip' ),
				'ua'                => (string) $order->get_meta( '_wlr_consent_' . $type . '_ua' ),
				'loss_acknowledged' => (string) $order->get_meta( '_wlr_consent_' . $type . '_loss_acknowledged' ),
			);
		}

		return $consents;
	}
}
