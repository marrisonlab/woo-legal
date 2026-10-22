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

	/** Contact markup shared by the policy and its alternative withdrawal form. */
	public static function email_link( string $email ): string {
		$email = sanitize_email( $email );
		return '' !== $email ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '';
	}

	public static function phone_link( string $phone ): string {
		$phone  = sanitize_text_field( $phone );
		$digits = preg_replace( '/[^0-9]/', '', $phone );
		if ( '' === $digits ) {
			return esc_html( $phone );
		}
		$number = ( str_starts_with( $phone, '+' ) ? '+' : '' ) . $digits;
		return '<a href="' . esc_url( 'tel:' . $number ) . '">' . esc_html( $phone ) . '</a>';
	}

	public function shortcode( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts( array(), $atts, 'wlr_model_withdrawal_form' );
		unset( $atts );

		$options = (array) get_option( 'wlr_setup_options', array() );
		$trader  = implode(
			', ',
			array_filter(
				array(
					esc_html( (string) ( $options['trader_name'] ?? get_bloginfo( 'name' ) ) ),
					esc_html( (string) ( $options['trader_address'] ?? get_option( 'woocommerce_store_address', '' ) ) ),
					self::email_link( (string) ( $options['trader_email'] ?? get_option( 'woocommerce_email_from_address', get_option( 'admin_email', '' ) ) ) ),
					self::phone_link( (string) ( $options['trader_phone'] ?? get_option( 'woocommerce_store_phone', '' ) ) ),
				)
			)
		);
		return '<div class="wlr-model-withdrawal-form" style="margin:1.5em 0;padding:clamp(1rem,3vw,1.5rem);border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;color:#1f2937;overflow-wrap:anywhere;"><p style="margin-top:0;"><strong>' . esc_html__( 'Modulo tipo di recesso — alternativa alla procedura online', 'woo-legal-returns' ) . '</strong></p><p>' .
			esc_html__( 'In alternativa alla procedura online, puoi compilare questo modulo e inviarlo ai recapiti del venditore. Se hai già inviato la richiesta dall’area Resi e Recesso, non è necessario compilare anche questo modulo.', 'woo-legal-returns' ) . '</p><p>' .
			esc_html__( 'Compilare e restituire questo modulo solo se si desidera recedere dal contratto.', 'woo-legal-returns' ) . '</p><p>' .
			esc_html__( 'Destinatario:', 'woo-legal-returns' ) . ' ' . $trader . '</p><p>' .
			esc_html__( 'Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto di vendita dei seguenti beni (*) / fornitura del seguente servizio (*):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Ordinato il (*) / ricevuto il (*):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Nome del/dei consumatore/i:', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Indirizzo del/dei consumatore/i:', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Firma del/dei consumatore/i (solo per modulo cartaceo):', 'woo-legal-returns' ) . ' ____________________</p><p>' .
			esc_html__( 'Data:', 'woo-legal-returns' ) . ' ____________________</p><p style="margin-bottom:0;">' .
			esc_html__( '(*) Cancellare la dicitura inutile.', 'woo-legal-returns' ) . '</p></div>';
	}

	public function remove_legacy_model_form_section( string $content ): string {
		return $content;
	}
}
