<?php
/**
 * Plugin Name:       Woo Legal Returns – EU Directive
 * Plugin URI:        https://github.com/marrisonlab/woo-legal
 * Description:       Adegua WooCommerce alla Direttiva UE sui Diritti dei Consumatori: modulo di recesso standardizzato, gestione richieste di reso nell'area cliente, notifiche email e dashboard admin.
 * Version:           1.0.0
 * Author:            Angelo
 * Text Domain:       woo-legal-returns
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'WLR_VERSION',          '1.0.0' );
define( 'WLR_PLUGIN_FILE',      __FILE__ );
define( 'WLR_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );
define( 'WLR_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'WLR_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'WLR_RETURN_DAYS',      14 ); // Diritto di recesso: 14 giorni

/**
 * Verifica dipendenze prima di caricare il plugin.
 */
function wlr_check_requirements(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Mostra avviso admin se WooCommerce non è attivo.
 */
function wlr_admin_notice_missing_woo(): void {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Woo Legal Returns richiede WooCommerce attivo.', 'woo-legal-returns' ) .
		'</p></div>';
}

/**
 * Inizializza il plugin.
 */
function wlr_init(): void {
	if ( ! wlr_check_requirements() ) {
		add_action( 'admin_notices', 'wlr_admin_notice_missing_woo' );
		return;
	}

	load_plugin_textdomain( 'woo-legal-returns', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require_once WLR_PLUGIN_DIR . 'includes/class-wlr-post-type.php';
	require_once WLR_PLUGIN_DIR . 'includes/class-wlr-customer-account.php';
	require_once WLR_PLUGIN_DIR . 'includes/class-wlr-emails.php';
	require_once WLR_PLUGIN_DIR . 'includes/class-wlr-admin.php';

	WLR_Post_Type::instance();
	WLR_Customer_Account::instance();
	WLR_Emails::instance();
	WLR_Admin::instance();
}
add_action( 'plugins_loaded', 'wlr_init' );

/**
 * Aggiornamenti automatici da GitHub (non richiede WooCommerce).
 * I filtri vengono registrati subito, prima di qualsiasi hook,
 * per intercettare correttamente i controlli aggiornamenti via cron.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wlr-github-updater.php';
( new WLR_GitHub_Updater() )->hooks();

/**
 * Attivazione: crea tabella personalizzata e flush rewrite rules.
 */
function wlr_activate(): void {
	require_once WLR_PLUGIN_DIR . 'includes/class-wlr-post-type.php';
	WLR_Post_Type::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wlr_activate' );

/**
 * Disattivazione.
 */
function wlr_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wlr_deactivate' );

/**
 * Dichiarazione compatibilità HPOS (WooCommerce High-Performance Order Storage).
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
