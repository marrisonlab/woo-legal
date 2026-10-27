<?php
/** Local policy/wizard regression checks with WordPress doubles; no live database. */
define( 'ABSPATH', __DIR__ );
define( 'WLR_RETURN_DAYS', 14 );

class Test_Redirect extends RuntimeException {}
class Test_Die extends RuntimeException {}
class WP_Error {}

$options = array();
$posts = array( 10 => (object) array( 'ID' => 10, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'La mia informativa', 'post_content' => 'Layout Elementor originale' ) );
$meta = array();
$shortcodes = array();
$actions = array();
$writes = array();
$allowed = true;
$valid_nonce = true;
$checks = 0;

function check( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function __( $text, ...$args ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_textarea( $text ) { return esc_html( $text ); }
function esc_html__( $text, ...$args ) { return esc_html( $text ); }
function esc_html_e( $text, ...$args ) { echo esc_html( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_textarea_field( $text ) { return sanitize_text_field( $text ); }
function sanitize_email( $text ) { return filter_var( $text, FILTER_VALIDATE_EMAIL ) ? $text : ''; }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $text ) ); }
function absint( $number ) { return abs( (int) $number ); }
function wp_unslash( $text ) { return $text; }
function get_option( $key, $fallback = false ) { return $GLOBALS['options'][ $key ] ?? $fallback; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; }
function get_bloginfo( $field ) { return 'Test shop'; }
function date_i18n( $format ) { return '22/10/2026'; }
function is_admin() { return false; }
function add_shortcode( $name, $callback ) { $GLOBALS['shortcodes'][ $name ] = $callback; }
function add_action( $name, ...$args ) { $GLOBALS['actions'][] = $name; }
function add_filter( ...$args ) {}
function current_user_can( ...$args ) { return $GLOBALS['allowed']; }
function wp_die( $message, ...$args ) { throw new Test_Die( $message ); }
function check_admin_referer( ...$args ) { if ( ! $GLOBALS['valid_nonce'] ) { wp_die( 'Invalid nonce' ); } }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_safe_redirect( $url ) { throw new Test_Redirect( $url ); }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_status( $id ) { return get_post( $id )->post_status ?? false; }
function get_pages( $args ) { return array_values( $GLOBALS['posts'] ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function wp_insert_post( $data, $wp_error = false ) {
	$GLOBALS['writes'][] = 'insert';
	$GLOBALS['posts'][11] = (object) array_merge( $data, array( 'ID' => 11 ) );
	return 11;
}
function wp_update_post( $data, $wp_error = false ) {
	$GLOBALS['writes'][] = 'update';
	foreach ( $data as $key => $value ) { $GLOBALS['posts'][ $data['ID'] ]->$key = $value; }
	return $data['ID'];
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_nonce_field( ...$args ) {}
function checked( $value, $expected = true ) { if ( $value == $expected ) { echo 'checked="checked"'; } }
function selected( $value, $expected ) { if ( $value == $expected ) { echo 'selected="selected"'; } }
function is_user_logged_in() { return false; }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://example.test/account/' . $endpoint; }
function shortcode_atts( $defaults, $atts, ...$args ) { return array_merge( $defaults, array_intersect_key( $atts, $defaults ) ); }
// Minimal doubles for block/shortcode expansion; actual WordPress parsing is a staging check.
function do_blocks( $content ) { ++$GLOBALS['block_renders']; return preg_replace( '/<!--.*?-->/s', '', $content ); }
function do_shortcode( $content ) {
	return preg_replace_callback( '/\[(wlr_\w+)([^\]]*)\]/', function ( $match ) {
		if ( ! isset( $GLOBALS['shortcodes'][ $match[1] ] ) ) { return $match[0]; }
		preg_match_all( '/(\w+)="([^"]*)"/', $match[2], $attributes, PREG_SET_ORDER );
		$atts = array();
		foreach ( $attributes as $attribute ) { $atts[ $attribute[1] ] = $attribute[2]; }
		return call_user_func( $GLOBALS['shortcodes'][ $match[1] ], $atts );
	}, $content );
}

require __DIR__ . '/../includes/class-wlr-setup-wizard.php';
require __DIR__ . '/../includes/class-wlr-annex-form.php';
require __DIR__ . '/../includes/class-wlr-customer-account.php';

$wizard = WLR_Setup_Wizard::instance();
check( isset( $shortcodes['wlr_precontractual_policy'] ), 'Policy shortcode must register on frontend' );
check( empty( $actions ), 'Frontend must not register wizard admin hooks' );
WLR_Annex_Form::instance();
WLR_Customer_Account::instance();

function save_step( $method, $expected = 'redirect' ) {
	try {
		( new ReflectionMethod( WLR_Setup_Wizard::class, $method ) )->invoke( $GLOBALS['wizard'] );
		throw new RuntimeException( 'Expected redirect or rejection' );
	} catch ( Test_Redirect $e ) {
		check( 'redirect' === $expected, 'Invalid submission must not redirect' );
	} catch ( Test_Die $e ) {
		check( 'die' === $expected, 'Valid submission must not be rejected: ' . $e->getMessage() );
	}
}
function render_step( $method ) {
	ob_start();
	( new ReflectionMethod( WLR_Setup_Wizard::class, $method ) )->invoke( $GLOBALS['wizard'] );
	return ob_get_clean();
}

$_POST = array( 'page_mode' => 'shortcode', 'trader_name' => 'Venditore & soci', 'trader_address' => "Via Roma 1\nRoma", 'trader_email' => 'shop@example.test', 'trader_phone' => '+39 (081) 123-456' );
save_step( 'save_precontractual' );
check( empty( $writes ) && count( $posts ) === 1, 'Shortcode mode must not create/update pages' );
check( $wizard->get_option( 'precontractual_mode' ) === 'shortcode' && $wizard->get_option( 'precontractual_page_id' ) === 0, 'Mode must persist without an associated page' );
$html = render_step( 'render_step_precontractual' );
check( str_contains( $html, 'value="shortcode" checked="checked"' ), 'Reopened wizard must retain shortcode selection' );
check( ! str_contains( $html, 'name="refresh_existing_page" value="1" checked' ), 'Shortcode page must not default to replacement' );
check( str_contains( $html, '[wlr_precontractual_policy]' ), 'Wizard must expose the shortcode to copy' );
save_step( 'save_menu' );
$html = render_step( 'render_step_checkout_notice' );
check( ! str_contains( $html, 'href=&quot;#&quot;' ), 'No placeholder checkout link without an associated page' );

$_POST['shortcode_page_id'] = 10;
$_POST['refresh_existing_page'] = 1;
save_step( 'save_precontractual' );
check( $wizard->get_option( 'precontractual_page_id' ) === 10, 'Custom page must be associated for links' );
check( empty( $writes ) && $posts[10]->post_content === 'Layout Elementor originale', 'Even a stale refresh checkbox must not overwrite shortcode page' );
$block_renders = 0;
$html = do_shortcode( '[wlr_precontractual_policy]' );
check( $block_renders === 0, 'Policy shortcode must not invoke the block renderer or its presentation filters' );
check( ! str_contains( $html, '<!--' ) && ! str_contains( $html, 'wp-block-' ), 'Policy shortcode must not include editor markers or block presentation classes' );
preg_match_all( '/<\/?([a-z][a-z0-9]*)\b/i', $html, $policy_tags );
check( ! array_diff( array_unique( $policy_tags[1] ), array( 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'div' ) ), 'Policy markup must be limited to basic formatting and the model box' );
check( substr_count( $html, '<div' ) === 1, 'The model box must be the only additional container' );
preg_match_all( '/style="([^"]*)"/', $html, $policy_styles );
check( $policy_styles[1] === array( 'border:1px solid #cbd5e1;background:#f8fafc;padding:1em;' ), 'Only the model box may set border, background and internal spacing; typography and layout remain inherited' );
check( str_contains( $html, 'Venditore &amp; soci' ) && str_contains( $html, 'Via Roma 1' ) && str_contains( $html, 'shop@example.test' ) && str_contains( $html, '+39 (081) 123-456' ), 'Full policy must show saved trader data' );
check( substr_count( $html, 'href="mailto:shop@example.test"' ) === 3, 'Email must be linked in seller details, withdrawal instructions and model recipient' );
check( substr_count( $html, 'href="tel:+39081123456"' ) === 2, 'Phone must be linked with normalized destination in seller details and model recipient' );
check( str_contains( $html, 'border:1px solid #cbd5e1' ) && str_contains( $html, 'background:#f8fafc' ), 'Model must have a visible box even without frontend assets' );
check( str_contains( $html, 'non è necessario compilare anche questo modulo' ), 'Model must explain that online requests need not be duplicated' );
check( WLR_Annex_Form::email_link( '" onclick="alert(1)' ) === '', 'Invalid email must not create a link' );
check( ! str_contains( WLR_Annex_Form::phone_link( 'Assistenza' ), 'href=' ), 'Missing phone digits must not create an invalid tel link' );
check( str_contains( $html, 'Modulo tipo di recesso' ) && str_contains( $html, 'account/resi' ), 'Nested form and withdrawal link must render' );
check( ! preg_match( '/<h[1-6]\b|wp:heading|\[wlr_/', $html ), 'Full rendered policy must have no headings or unresolved shortcodes' );
check( ! str_contains( $html, 'class="button' ), 'Policy link must inherit text styling' );
check( str_contains( $html, '<strong>' ) && str_contains( $html, '<ul>' ) && str_contains( $html, '<em>' ), 'Basic policy formatting must remain' );
check( empty( $writes ), 'Rendering policy must not create pages' );
$_POST['trader_name'] = 'Nome aggiornato';
save_step( 'save_precontractual' );
check( str_contains( do_shortcode( '[wlr_precontractual_policy]' ), 'Nome aggiornato' ), 'Shortcode must use current saved trader data' );

$before = $options;
foreach ( array( 999, 10 ) as $invalid_id ) {
	$_POST['shortcode_page_id'] = $invalid_id;
	$allowed = 999 === $invalid_id;
	save_step( 'save_precontractual', 'die' );
	check( $options === $before && empty( $writes ), 'Invalid/uneditable page must not mutate settings or posts' );
}
$allowed = true;
$posts[10]->post_status = 'draft';
$_POST['shortcode_page_id'] = 10;
save_step( 'save_precontractual', 'die' );
$posts[10]->post_status = 'publish';
$_POST['page_mode'] = 'invalid';
save_step( 'save_precontractual', 'die' );
check( $options === $before, 'Invalid mode must preserve settings' );

$_POST['page_mode'] = 'existing';
$_POST['existing_page_id'] = 10;
unset( $_POST['refresh_existing_page'] );
save_step( 'save_precontractual' );
check( empty( $writes ) && $posts[10]->post_content === 'Layout Elementor originale', 'Legacy association must preserve existing content' );
$_POST['refresh_existing_page'] = 1;
save_step( 'save_precontractual' );
check( $writes === array( 'update' ) && ! preg_match( '/<h[1-6]\b|wp:heading/', $posts[10]->post_content ), 'Explicit refresh must generate policy without headings' );
$_POST['page_mode'] = 'new';
save_step( 'save_precontractual' );
check( $writes === array( 'update', 'insert' ) && $wizard->get_option( 'precontractual_page_id' ) === 11, 'New page mode must still create and associate a page' );
check( get_post_meta( 11, '_wlr_generated_precontractual_page', true ) === '1', 'Generated page must retain its marker' );
check( ! preg_match( '/<h[1-6]\b/', do_shortcode( do_blocks( $posts[11]->post_content ) ) ), 'Generated page including model form must contain no headings' );

$_POST['wlr_wizard_step'] = 'precontractual';
$allowed = false;
try { $wizard->handle_post(); throw new RuntimeException( 'Missing permission rejected too late' ); } catch ( Test_Die $e ) { check( str_contains( $e->getMessage(), 'Permessi' ), 'Wizard must reject missing manage_options' ); }
$allowed = true;
$valid_nonce = false;
try { $wizard->handle_post(); throw new RuntimeException( 'Invalid nonce accepted' ); } catch ( Test_Die $e ) { check( $e->getMessage() === 'Invalid nonce', 'Wizard must verify nonce before saving' ); }
echo "Passed {$checks} local policy/wizard checks.\n";
