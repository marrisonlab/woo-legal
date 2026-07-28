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
		add_filter( 'the_content', array( $this, 'remove_legacy_model_form_section' ), 8 );
	}

	public function shortcode( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts( array(), $atts, 'wlr_model_withdrawal_form' );
		unset( $atts );

		return '';
	}

	public function remove_legacy_model_form_section( string $content ): string {
		if (
			false === strpos( $content, 'wlr_model_withdrawal_form' )
			&& false === stripos( $content, 'Modulo tipo di recesso' )
			&& false === stripos( $content, 'Fac-simile del modulo tipo di recesso' )
		) {
			return $content;
		}

		$heading_text = '(?:Modulo tipo di recesso|Fac-simile del modulo tipo di recesso)';
		$shortcode    = '\[wlr_model_withdrawal_form[^\]]*\]';
		$patterns     = array(
			'/<!-- wp:heading[^>]*-->\s*<h[1-6][^>]*>' . $heading_text . '<\/h[1-6]>\s*<!-- \/wp:heading -->\s*(?:<!-- wp:paragraph -->.*?<!-- \/wp:paragraph -->\s*)?<!-- wp:shortcode -->\s*' . $shortcode . '\s*<!-- \/wp:shortcode -->/is',
			'/<h[1-6][^>]*>' . $heading_text . '<\/h[1-6]>\s*(?:<p>.*?<\/p>\s*)?' . $shortcode . '/is',
			'/<!-- wp:shortcode -->\s*' . $shortcode . '\s*<!-- \/wp:shortcode -->/i',
			'/' . $shortcode . '/i',
		);

		$clean = preg_replace( $patterns, '', $content );

		return is_string( $clean ) ? $clean : $content;
	}
}
