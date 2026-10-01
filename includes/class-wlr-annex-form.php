<?php
/**
 * Backward compatibility for the old model withdrawal form shortcode.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Annex_Form {

	private static ?WLR_Annex_Form $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_shortcode( 'wlr_model_withdrawal_form', array( $this, 'shortcode' ) );
	}

	public function shortcode( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts( array(), $atts, 'wlr_model_withdrawal_form' );
		unset( $atts );

		$options = get_option( 'wlr_setup_options', array() );
		$trader  = implode( ', ', array_filter( array( $options['trader_name'] ?? get_bloginfo( 'name' ), $options['trader_address'] ?? get_option( 'woocommerce_store_address', '' ), $options['trader_email'] ?? get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) ) ) );
		return '<div class="wlr-model-withdrawal-form"><h3>' . esc_html__( 'Modulo tipo di recesso', 'woo-legal-returns' ) . '</h3><p>' .
			esc_html__( 'Compilare e restituire questo modulo solo se si desidera recedere dal contratto.', 'woo-legal-returns' ) . '</p><p>' .
			esc_html__( 'Destinatario:', 'woo-legal-returns' ) . ' ' . esc_html( $trader ) . '</p><p>' .
			esc_html__( 'Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto di vendita dei seguenti beni (*) / fornitura del seguente servizio (*):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Ordinato il (*) / ricevuto il (*):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Nome del/dei consumatore/i:', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Indirizzo del/dei consumatore/i:', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Firma del/dei consumatore/i (solo per modulo cartaceo):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Data:', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( '(*) Cancellare la dicitura inutile.', 'woo-legal-returns' ) . '</p></div>';
	}

	public function remove_legacy_model_form_section( string $content ): string {
		return $content;
	}
}
