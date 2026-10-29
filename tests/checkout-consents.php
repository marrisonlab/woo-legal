<?php
/** Local checks for irrelevant checkout fields and preserved consent receipts. */
namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
	class CartSchema { public const IDENTIFIER = 'cart'; }
}
namespace Automattic\WooCommerce\Blocks\Domain\Services {
	class CheckoutFields {
		public function get_field_from_object( $key, $order, $group ) { return '1' === $order->get_meta( '_wc_other/' . $key ); }
	}
}
namespace Automattic\WooCommerce\Blocks {
	class Package {
		public static function container() { return new class {
			public function get( $class ) { return new $class(); }
		}; }
	}
}
namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \RuntimeException {
		public function __construct( $code, $message, $status ) { parent::__construct( $message ); }
	}
}
namespace {
define( 'ABSPATH', __DIR__ );
define( 'WC_VERSION', '9.9.5' );
define( 'ARRAY_A', 'ARRAY_A' );

class WC_Order_Item_Product {
	public function __construct( public string $status = 'standard' ) {}
}
class WC_Order {
	private static int $next_id = 0;
	private int $id;
	public array $meta = array();
	public array $items;
	public function __construct( array $statuses = array( 'standard' ) ) { $this->id = ++self::$next_id; $GLOBALS['orders'][ $this->id ] = $this; $this->items = array_map( fn( $status ) => new WC_Order_Item_Product( $status ), $statuses ); }
	public function get_id() { return $this->id; }
	public function get_items() { return $this->items; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function save() {}
}
class WC_Geolocation { public static function get_ip_address() { return '127.0.0.1'; } }
class WLR_Product_Settings {
	public static function get_item_status( $item ) { return $item->status; }
	public static function product_requires_checkout_consent( $id, $type ) { return ( 'digital' === $type ? 2 : 3 ) === $id; }
}
class WP_Error {
	public array $errors = array();
	public function add( $code, $message ) { $this->errors[ $code ] = $message; }
}
function __( $text, ...$args ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, ...$args ) { return esc_html( $text ); }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_kses_post( $text ) { return $text; }
function wp_unslash( $text ) { return $text; }
function sanitize_text_field( $text ) { return strip_tags( $text ); }
function get_option( $key, $default ) { return $default; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ] = array( $callback, $priority, $args ); }
function add_filter( ...$args ) { add_action( ...$args ); }
function absint( $number ) { return abs( (int) $number ); }
function WC() { return $GLOBALS['wc']; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function woocommerce_form_field( $name, $options ) { echo $name; }
function woocommerce_register_additional_checkout_field( $options ) { $GLOBALS['registered'][ $options['id'] ] = $options; }
function woocommerce_store_api_register_endpoint_data( $options ) { $GLOBALS['extension'] = $options; }
function check( $condition, $message ) { ++$GLOBALS['checks']; if ( ! $condition ) { throw new \RuntimeException( $message ); } }
function output( $callback ) { ob_start(); $callback(); return ob_get_clean(); }
function set_cart( array $ids ) { $GLOBALS['wc'] = (object) array( 'cart' => new class( $ids ) {
	public function __construct( private array $ids ) {}
	public function get_cart() { return array_map( fn( $id ) => array( 'product_id' => $id ), $this->ids ); }
} ); }

require __DIR__ . '/../woo-legal/includes/class-wlr-checkout-consent.php';
$checks = 0;
$plugin = WLR_Checkout_Consent::instance();
$plugin->register_block_fields();
foreach ( $GLOBALS['registered'] as $field ) {
	check( false === $field['show_in_order_confirmation'], 'Core must not turn absent checkboxes into customer-facing No values' );
}
check( isset( $GLOBALS['hooks']['woocommerce_order_details_after_customer_details'] ), 'Customer order receipt must be hooked' );
check( isset( $GLOBALS['hooks']['woocommerce_thankyou'] ), 'Blocks confirmation must also render genuine receipts' );
check( $GLOBALS['hooks']['woocommerce_admin_shipping_fields'][1] > 10, 'Admin filter must run after core field injection' );
$fields = array(
	'first_name' => array( 'label' => 'Nome' ),
	'woo-legal/digital' => array( 'id' => '_wc_other/woo-legal/digital' ),
	'woo-legal/service' => array( 'id' => '_wc_other/woo-legal/service' ),
	'other-plugin/checkbox' => array( 'id' => '_wc_other/other-plugin/checkbox' ),
);
$physical = new WC_Order();
set_cart( array( 1 ) );
check( '' === output( fn() => $plugin->render_fields() ), 'Physical checkout must have no consent heading or checkboxes' );
$errors = new WP_Error();
$plugin->validate_fields( array(), $errors );
check( ! $errors->errors, 'Physical checkout must not require digital consent' );
$plugin->save_order_meta( $physical, array() );
check( ! $physical->meta, 'Physical classic checkout must not record irrelevant consents' );
// Older Blocks checkboxes can be absent or persist a false value: hide both.
foreach ( array( '', '0' ) as $value ) {
	$physical->meta = array( '_wc_other/woo-legal/digital' => $value, '_wc_other/woo-legal/service' => $value );
	$original = $physical->meta;
	foreach ( array( 'view', 'edit' ) as $context ) {
		$visible = $plugin->filter_admin_consent_fields( $fields, $physical, $context );
		check( array_keys( $visible ) === array( 'first_name', 'other-plugin/checkbox' ), 'Physical admin order must hide only irrelevant plugin fields in ' . $context );
	}
	check( '' === output( fn() => $plugin->render_order_consents( $physical ) ), 'Physical customer order must show no consent section' );
	check( '' === output( fn() => $plugin->render_email_consents( $physical, false, false, null ) ), 'Physical email must show no consent section' );
	check( $original === $physical->meta, 'Viewing old orders must not rewrite evidence' );
}
set_cart( array( 1, 2 ) );
check( str_contains( output( fn() => $plugin->render_fields() ), 'wlr_consent_digital' ), 'Mixed checkout must retain digital checkbox' );
check( ! str_contains( output( fn() => $plugin->render_fields() ), 'wlr_consent_service' ), 'Mixed digital cart must not show service consent' );
$errors = new WP_Error();
$plugin->validate_fields( array(), $errors );
check( isset( $errors->errors['wlr_consent_digital_required'] ), 'Digital consent validation must remain enforced' );
$digital = new WC_Order( array( 'standard', 'digital_content' ) );
$digital->meta['_wc_other/woo-legal/digital'] = '1';
$plugin->save_block_consents( $digital );
set_cart( array( 1 ) );
$visible = $plugin->filter_admin_consent_fields( $fields, $digital );
check( isset( $visible['woo-legal/digital'] ) && ! isset( $visible['woo-legal/service'] ), 'Digital history must be independent of current physical cart' );
$html = output( fn() => $plugin->render_order_consents( $digital ) );
check( str_contains( $html, 'Consenso espresso:' ) && ! str_contains( $html, 'che il servizio inizi' ), 'Digital receipt must show only recorded digital text' );
check( substr_count( $html, 'esecuzione immediata' ) === 1, 'Digital receipt must appear once' );
check( '' === output( fn() => $plugin->render_thankyou_consents( $digital->get_id() ) ), 'Classic details plus thankyou hooks must not duplicate receipts' );
$blocks_renderer = new WLR_Checkout_Consent();
check( str_contains( output( fn() => $blocks_renderer->render_thankyou_consents( $digital->get_id() ) ), 'Consenso espresso:' ), 'Blocks thankyou hook must render saved digital receipt without classic details' );
check( '' === output( fn() => $blocks_renderer->render_thankyou_consents( $physical->get_id() ) ), 'Blocks physical confirmation must have no consent text' );
check( str_contains( output( fn() => $plugin->render_email_consents( $digital, false, true, null ) ), 'Consenso espresso:' ), 'Digital receipt must remain in plain email' );
$digital->items = array();
check( isset( $plugin->filter_admin_consent_fields( $fields, $digital )['woo-legal/digital'] ), 'Historical evidence survives deleted products' );

set_cart( array( 1, 3 ) );
$service = new WC_Order( array( 'standard', 'early_service' ) );
$plugin->save_block_consents( $service );
check( 'no' === $service->get_meta( '_wlr_consent_service_accepted' ), 'Optional service refusal must be recorded when relevant' );
$visible = $plugin->filter_admin_consent_fields( $fields, $service );
check( isset( $visible['woo-legal/service'] ) && ! isset( $visible['woo-legal/digital'] ), 'Service order must not show digital consent' );
check( str_contains( output( fn() => $plugin->render_order_consents( $service ) ), 'Consenso non prestato:' ), 'Relevant unaccepted service consent must remain visible' );
set_cart( array( 2 ) );
try {
	$plugin->save_block_consents( new WC_Order( array( 'digital_content' ) ) );
	check( false, 'Blocks must reject missing digital consent' );
} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception ) {
	check( true, 'Blocks rejects missing digital consent' );
}
check( $fields === $plugin->filter_admin_consent_fields( $fields ), 'No order context must leave unrelated admin registration alone' );
check( '' === output( fn() => $plugin->render_email_consents( $digital, true, false, null ) ), 'Customer receipt must not leak into admin email' );
echo "OK: {$checks} checkout consent checks\n";
}
