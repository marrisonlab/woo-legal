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
		}
	}

	public static function cart_requires_consent( string $type ): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( $variation_id && WLR_Product_Settings::product_requires_checkout_consent( $variation_id, $type ) ) {
				return true;
			}

			if ( $product_id && WLR_Product_Settings::product_requires_checkout_consent( $product_id, $type ) ) {
				return true;
			}
		}

		return false;
	}

	public static function get_consent_text( string $type ): string {
		if ( 'service' === $type ) {
			$option_text = (string) get_option( 'wlr_service_consent_text', '' );
			if ( '' !== trim( $option_text ) ) {
				return (string) apply_filters( 'wlr_service_consent_text', $option_text );
			}

			return (string) apply_filters(
				'wlr_service_consent_text',
				__( 'Chiedo espressamente che il servizio inizi durante il periodo di recesso e riconosco che, in caso di recesso dopo l\'inizio dell\'esecuzione, potra essere dovuto un importo proporzionale al servizio gia prestato.', 'woo-legal-returns' )
			);
		}

		$option_text = (string) get_option( 'wlr_digital_consent_text', '' );
		if ( '' !== trim( $option_text ) ) {
			return (string) apply_filters( 'wlr_digital_consent_text', $option_text );
		}

		return (string) apply_filters(
			'wlr_digital_consent_text',
			__( 'Chiedo espressamente l\'esecuzione immediata del contenuto digitale e riconosco che, una volta iniziata l\'esecuzione, perdero il diritto di recesso nei casi previsti dalla normativa applicabile.', 'woo-legal-returns' )
		);
	}

	public static function get_order_consents( WC_Order $order ): array {
		$consents = array();

		foreach ( array( 'digital', 'service' ) as $type ) {
			$text = (string) $order->get_meta( '_wlr_consent_' . $type . '_text' );
			if ( '' === $text ) {
				continue;
			}

			$consents[ $type ] = array(
				'accepted'  => (string) $order->get_meta( '_wlr_consent_' . $type . '_accepted' ),
				'text'      => $text,
				'timestamp' => (string) $order->get_meta( '_wlr_consent_' . $type . '_timestamp' ),
				'ip'        => (string) $order->get_meta( '_wlr_consent_' . $type . '_ip' ),
				'ua'        => (string) $order->get_meta( '_wlr_consent_' . $type . '_ua' ),
			);
		}

		return $consents;
	}
}
