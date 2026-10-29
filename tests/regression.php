<?php
/** Local regression checks with WordPress/WooCommerce doubles; no live database. */
define( 'ABSPATH', __DIR__ );
define( 'WLR_RETURN_DAYS', 14 );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
	public function __construct( private string $code, private string $message ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
class WP_Post {
	public function __construct( public int $ID, public string $post_status = 'wlr-requested', public string $post_type = 'wlr_return', public string $post_content = '' ) {}
}
class WC_Order_Item_Product {
	public string $type = 'goods';
	public function get_product() { return null; }
	public function get_product_id() { return 1; }
	public function get_name() { return 'Test product'; }
	public function get_quantity() { return 2; }
	public function get_meta( $key ) { return ''; }
}
class WC_Order {
	public array $meta = array();
	public array $items;
	public ?DateTimeImmutable $created;
	public ?DateTimeImmutable $paid = null;
	public ?DateTimeImmutable $completed = null;
	public function __construct() {
		$this->items = array( 11 => new WC_Order_Item_Product() );
		$this->created = new DateTimeImmutable( 'today', wp_timezone() );
	}
	public function get_id() { return 1; }
	public function get_order_number() { return '1'; }
	public function get_status() { return 'completed'; }
	public function get_customer_id() { return 7; }
	public function get_billing_email() { return 'customer@example.test'; }
	public function get_order_key() { return 'test-key'; }
	public function get_items() { return $this->items; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function get_date_created() { return $this->created; }
	public function get_date_paid() { return $this->paid; }
	public function get_date_completed() { return $this->completed; }
	public function get_qty_refunded_for_item( $id ) { return 0; }
	public function add_order_note( ...$args ) {}
}
class WLR_Product_Settings {
	public static function get_item_contract_type( $item ) { return $item->type; }
	public static function is_item_excluded( ...$args ) { return false; }
	public static function get_excluded_items_in_order( $id ) { return array(); }
}
class WLR_Customer_Account {
	public const ENDPOINT = 'returns';
	public static function instance(): self { return new self(); }
	public function get_return_reasons(): array { return array( 'other' => 'Altro motivo' ); }
	public static function get_order_return_url( $order ) { return 'https://example.test/returns'; }
}

function __( $text, ...$args ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, ...$args ) { return esc_html( $text ); }
function esc_url( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_email( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['callbacks'][ $hook ][ $priority ][] = $callback; }
function add_filter( ...$args ) {}
function remove_filter( ...$args ) {}
function do_action( $hook, ...$args ) {
	$GLOBALS['actions'][] = array( $hook, $args );
	$callbacks = $GLOBALS['callbacks'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $callback ) { $callback( ...$args ); } }
}
function wc_get_order( $id ) { return 1 === $id ? $GLOBALS['order'] : null; }
function get_userdata( $id ) { return (object) array( 'user_email' => 'customer@example.test' ); }
function get_current_user_id() { return 7; }
function get_post_meta( $id, $key, ...$args ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null; }
function get_posts( $args ) {
	if ( ( $args['fields'] ?? '' ) === 'ids' ) {
		$ids = array_keys( array_filter( $GLOBALS['posts'], fn( $post ) => $post->post_type === $args['post_type'] && $post->post_status === $args['post_status'] && $post->ID > $args['wlr_late_after_id'] ) );
		sort( $ids );
		return array_slice( $ids, 0, $args['posts_per_page'] );
	}
	return array_values( array_filter( $GLOBALS['posts'], function ( $post ) use ( $args ) {
		return in_array( $post->post_status, $args['post_status'], true ) && in_array( (int) get_post_meta( $post->ID, '_wlr_order_id' ), $args['meta_value'], true );
	} ) );
}
function wp_update_post( $data, $wp_error = false ) {
	if ( $GLOBALS['write_mode'] === 'fail' ) { return new WP_Error( 'db_error', 'Raw database details' ); }
	if ( $GLOBALS['write_mode'] !== 'ignore' ) { $GLOBALS['posts'][ $data['ID'] ]->post_status = $data['post_status']; }
	return $data['ID'];
}
function wp_insert_post( $data, $wp_error = false ) {
	$GLOBALS['inserts']++;
	$GLOBALS['posts'][100] = new WP_Post( 100, $data['post_status'], $data['post_type'], $data['post_content'] );
	return 100;
}
function wp_trash_post( $id ) {
	if ( $GLOBALS['delete_mode'] === 'fail' ) { return false; }
	$post = get_post( $id );
	if ( $GLOBALS['delete_mode'] !== 'ignore' ) {
		update_post_meta( $id, '_wp_trash_meta_status', $post->post_status );
		$GLOBALS['posts'][$id]->post_status = 'trash';
	}
	return $post;
}
function wp_delete_post( $id, $force = false ) {
	if ( $GLOBALS['delete_mode'] === 'fail' ) { return false; }
	$post = get_post( $id );
	if ( $GLOBALS['delete_mode'] !== 'ignore' ) { unset( $GLOBALS['posts'][$id], $GLOBALS['meta'][$id] ); }
	return $post;
}
function wp_clear_scheduled_hook( $hook, $args = array() ) { $GLOBALS['events'] = array_values( array_filter( $GLOBALS['events'], fn( $event ) => $event !== array( $hook, $args ) ) ); }
function current_time( $type, ...$args ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['events'][] = array( $hook, $args ); }
function wp_schedule_event( $time, $recurrence, $hook, $args = array() ) { wp_schedule_single_event( $time, $hook, $args ); }
function wp_next_scheduled( $hook, $args = array() ) { return in_array( array( $hook, $args ), $GLOBALS['events'], true ); }
function wc_get_logger() { return new class { public function error( ...$args ) {} }; }
function wp_generate_uuid4() { return 'test-token'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://example.test/account/' . $endpoint; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function get_bloginfo( $field ) { return 'Test shop'; }
class WC_Geolocation { public static function get_ip_address() { return '127.0.0.1'; } }
function check_ajax_referer( ...$args ) {}
function current_user_can( $capability ) { return $GLOBALS['allowed']; }
function wp_send_json_error( $data ) { echo json_encode( array( 'success' => false, 'data' => $data ) ); exit; }
function wp_send_json_success( $data ) { echo json_encode( array( 'success' => true, 'data' => $data ) ); exit; }

$wpdb = new class {
	public string $prefix = 'test_';
	public string $posts = 'test_posts';
	public bool $busy = false;
	public int $released = 0;
	public function prepare( $query, ...$args ) { return vsprintf( $query, $args ); }
	public function get_var( $query ) {
		if ( str_contains( $query, 'RELEASE_LOCK' ) ) { ++$this->released; return '1'; }
		return $this->busy ? '0' : '1';
	}
};
$options = array();
$posts = array( 9 => new WP_Post( 9 ) );
$meta = array( 9 => array( '_wlr_order_id' => 1, '_wlr_items' => array( array( 'item_id' => 11, 'qty' => 1 ) ) ) );
$order = new WC_Order();
$actions = array();
$callbacks = array();
$events = array();
$write_mode = 'ok';
$delete_mode = 'ok';
$allowed = true;
$inserts = 0;
require __DIR__ . '/../woo-legal/includes/class-wlr-lock.php';
require __DIR__ . '/../woo-legal/includes/class-wlr-post-type.php';

// Invoke the actual AJAX handler in isolated processes because JSON responses exit.
if ( ( $argv[1] ?? '' ) === '--ajax' ) {
	$case = $argv[2];
	$_POST = array( 'return_id' => 9, 'status' => 'wlr-rejected', 'note' => '' );
	if ( $case === 'success' ) { $_POST['note'] = 'Termine scaduto'; }
	if ( $case === 'busy' ) { $wpdb->busy = true; }
	if ( $case === 'invalid' ) { $_POST['status'] = 'unknown'; }
	if ( $case === 'denied' ) { $allowed = false; }
	if ( $case === 'write' ) { $_POST['note'] = 'Termine scaduto'; $write_mode = 'fail'; }
	if ( $case === 'unsaved' ) { $_POST['note'] = 'Termine scaduto'; $write_mode = 'ignore'; }
	require __DIR__ . '/../woo-legal/includes/class-wlr-admin.php';
	( new WLR_Admin() )->handle_update_status();
	exit( 1 );
}

$checks = 0;
function check( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function error_is( $value, string $code ): bool { return is_wp_error( $value ) && $value->get_error_code() === $code; }
$payload = array( 'order_id' => 1, 'customer_id' => 7, 'customer_name' => 'Test Customer', 'items' => array( array( 'item_id' => 11, 'qty' => 1 ) ) );

check( WLR_Post_Type::deadline_is_strict(), 'New installation must block by default' );
$options['wlr_deadline_mode'] = 'advisory';
WLR_Post_Type::upgrade_deadline_policy();
check( WLR_Post_Type::deadline_is_strict(), 'Upgrade must activate blocking for existing sites' );
$options['wlr_deadline_mode'] = 'advisory';
WLR_Post_Type::upgrade_deadline_policy();
check( WLR_Post_Type::deadline_is_strict() && $options['wlr_deadline_mode'] === 'strict', 'Historical manual mode cannot disable the block' );
$options['wlr_deadline_mode'] = 'strict';

$order->created = new DateTimeImmutable( '2024-11-18', wp_timezone() );
$deadline = WLR_Post_Type::get_order_deadline_info( $order );
check( $deadline['date'] === '2024-12-02 22:59:59', 'Screenshot deadline uses site midnight and UTC storage' );
check( ! $deadline['reliable'], 'Historical order lacks reliability metadata' );
check( ! WLR_Post_Type::is_within_return_window( $order ), 'Expired unverified order must be hidden' );
check( error_is( WLR_Post_Type::validate_return_request( $payload ), 'wlr_expired' ), 'Server must reject expired historical order' );
check( error_is( WLR_Post_Type::create_return( $payload ), 'wlr_expired' ) && $inserts === 0, 'Final submission must not persist expired request' );
$guest = $payload;
$guest['customer_id'] = 0;
$guest['guest_email'] = 'customer@example.test';
$guest['order_key'] = 'test-key';
check( error_is( WLR_Post_Type::validate_return_request( $guest ), 'wlr_expired' ), 'Guest lookup must also enforce expiration' );

$order->created = new DateTimeImmutable( 'today', wp_timezone() );
check( WLR_Post_Type::is_within_return_window( $order ), 'Recent order remains eligible' );
check( is_array( WLR_Post_Type::validate_return_request( $payload ) ), 'Missing reason must remain valid' );
check( is_array( WLR_Post_Type::validate_return_request( $payload + array( 'reason' => 'other' ) ) ), 'Known reason accepted' );
check( error_is( WLR_Post_Type::validate_return_request( $payload + array( 'reason' => 'unknown' ) ), 'wlr_invalid_data' ), 'Unknown reason rejected' );
check( WLR_Post_Type::get_reason_label( '' ) === 'Non indicato (facoltativo)', 'Empty reason gets readable label' );
check( WLR_Post_Type::get_reason_label( 'other' ) === 'Altro motivo', 'Known reason label preserved' );

$order->created = new DateTimeImmutable( '2024-11-18', wp_timezone() );
$order->meta['_wlr_received_date'] = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
check( WLR_Post_Type::is_within_return_window( $order ), 'Actual recent receipt takes precedence over old creation date' );
$order->meta['_wlr_received_date'] = '2024-02-31';
check( error_is( WLR_Post_Type::validate_return_request( $payload ), 'wlr_expired' ), 'Invalid receipt cannot bypass fallback deadline' );
$order->meta = array();
$order->completed = new DateTimeImmutable( 'today', wp_timezone() );
check( WLR_Post_Type::is_within_return_window( $order ), 'Configured completion date takes precedence' );
$options['wlr_deadline_basis'] = 'created';
check( ! WLR_Post_Type::is_within_return_window( $order ), 'Configured creation basis must be enforced' );
$options['wlr_deadline_basis'] = 'completed_or_paid';
$order->completed = null;
$order->paid = new DateTimeImmutable( 'today', wp_timezone() );
check( WLR_Post_Type::is_within_return_window( $order ), 'Payment fallback is honored' );
$order->items[11]->type = 'service';
check( ! WLR_Post_Type::is_within_return_window( $order ), 'Services must use contract date rather than payment date' );
$order->items[11]->type = 'goods';
$order->paid = null;
$order->created = null;
check( ! WLR_Post_Type::is_within_return_window( $order ), 'Missing all dates must fail closed' );
check( error_is( WLR_Post_Type::validate_return_request( $payload ), 'wlr_missing_deadline' ), 'Missing date must explain failure' );

$order->created = new DateTimeImmutable( '2024-03-16', wp_timezone() );
$order->meta['_wlr_deadline_holidays'] = array( '2024-04-01' );
check( WLR_Post_Type::get_order_deadline_info( $order )['date'] === '2024-04-02 21:59:59', 'Weekend, holiday and DST extension must remain intact' );
$order->meta = array();
$order->created = new DateTimeImmutable( 'today -15 days', wp_timezone() );
$options['wlr_deadline_grace_days'] = 30;
check( WLR_Post_Type::is_within_return_window( $order ), 'Configured grace days must remain honored' );
$options['wlr_deadline_grace_days'] = 0;
$options['wlr_deadline_mode'] = 'advisory';
check( ! WLR_Post_Type::is_within_return_window( $order ), 'Stored manual policy must not allow expired requests' );
check( error_is( WLR_Post_Type::validate_return_request( $payload ), 'wlr_expired' ), 'Stored manual policy must not bypass server validation' );
$options['wlr_deadline_mode'] = 'strict';
$order->created = new DateTimeImmutable( 'today', wp_timezone() );
check( is_array( WLR_Post_Type::validate_return_request( $payload ) ), 'Preview before expiration is valid' );
$order->created = new DateTimeImmutable( '2024-11-18', wp_timezone() );
check( error_is( WLR_Post_Type::create_return( $payload ), 'wlr_expired' ) && $inserts === 0, 'Final confirmation rechecks deadline after preview' );

check( error_is( WLR_Post_Type::update_status_result( 9, 'wlr-rejected' ), 'wlr_rejection_note_required' ), 'Rejection must explain missing note' );
check( $posts[9]->post_status === 'wlr-requested' && empty( $actions ), 'Failed rejection must leave state and email hooks untouched' );
check( ! WLR_Post_Type::update_status( 9, 'wlr-rejected' ), 'Legacy boolean API must return false for errors' );
check( error_is( WLR_Post_Type::update_status_result( 9, 'wlr-rejected', '<b> </b>' ), 'wlr_rejection_note_required' ), 'HTML-only note must not satisfy requirement' );
check( WLR_Post_Type::update_status_result( 9, 'wlr-rejected', 'Termine scaduto' ) === true, 'Rejection with note succeeds' );
check( $posts[9]->post_status === 'wlr-rejected', 'Successful rejection must be persisted' );
check( $meta[9]['_wlr_history'][0]['note'] === 'Termine scaduto', 'Reason retained in customer-visible history' );
check( $actions[0][1] === array( 9, 'wlr-rejected', 'wlr-requested' ), 'Notification gets previous and new status' );
$action_count = count( $actions );
check( WLR_Post_Type::update_status_result( 9, 'wlr-rejected' ) === true && count( $actions ) === $action_count, 'Unchanged rejection is idempotent' );
check( error_is( WLR_Post_Type::update_status_result( 9, 'unknown' ), 'wlr_invalid_status' ), 'Invalid status gets diagnostic' );
check( error_is( WLR_Post_Type::update_status_result( 999, 'wlr-approved' ), 'wlr_invalid_return' ), 'Missing request gets diagnostic' );
$wpdb->busy = true;
check( error_is( WLR_Post_Type::update_status_result( 9, 'wlr-approved' ), 'wlr_busy' ), 'Lock failure keeps diagnostic' );
$wpdb->busy = false;
$write_mode = 'fail';
check( error_is( WLR_Post_Type::update_status_result( 9, 'wlr-cancelled' ), 'wlr_status_write_failed' ), 'Database failure gets safe diagnostic' );
$write_mode = 'ignore';
check( error_is( WLR_Post_Type::update_status_result( 9, 'wlr-cancelled' ), 'wlr_status_not_saved' ), 'Accepted write without actual status change must fail' );
check( count( $actions ) === $action_count && count( $meta[9]['_wlr_history'] ) === 1, 'Failed writes must not notify or append false history' );

foreach ( array( 'note' => 'wlr_rejection_note_required', 'busy' => 'wlr_busy', 'invalid' => 'wlr_invalid_status', 'write' => 'wlr_status_write_failed', 'unsaved' => 'wlr_status_not_saved', 'success' => '', 'denied' => '' ) as $case => $code ) {
	$process = proc_open( array( PHP_BINARY, __FILE__, '--ajax', $case ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	check( proc_close( $process ) === 0 && $error === '', 'AJAX process completes: ' . $case . ' ' . $error );
	$response = json_decode( $output, true );
	check( is_array( $response ) && $response['success'] === ( $case === 'success' ), 'AJAX outcome: ' . $case );
	if ( $code ) { check( $response['data']['code'] === $code, 'AJAX error classification: ' . $case ); }
}

$write_mode = 'ok';
$order->created = new DateTimeImmutable( 'today', wp_timezone() );
$new_return = WLR_Post_Type::create_return( $payload );
check( $new_return === 100 && $inserts === 1, 'Eligible request without reason must be persisted' );
check( $meta[100]['_wlr_reason'] === '' && $meta[100]['_wlr_declaration']['reason_label'] === 'Non indicato (facoltativo)', 'Receipt snapshot stores readable optional reason' );
require __DIR__ . '/../woo-legal/includes/class-wlr-emails.php';
$email_method = new ReflectionMethod( WLR_Emails::class, 'get_email_data' );
if ( PHP_VERSION_ID < 80100 ) { $email_method->setAccessible( true ); }
$emails = new WLR_Emails();
$link_method = new ReflectionMethod( WLR_Emails::class, 'output_withdrawal_link' );
if ( PHP_VERSION_ID < 80100 ) { $link_method->setAccessible( true ); }
foreach ( array( false, true ) as $plain_text ) {
	foreach ( array( array( 'goods' ), array( 'digital' ), array( 'service' ), array( 'goods', 'digital' ) ) as $types ) {
		$email_order = new WC_Order();
		$email_order->items = array();
		foreach ( $types as $type ) { $item = new WC_Order_Item_Product(); $item->type = $type; $email_order->items[] = $item; }
		ob_start();
		$link_method->invoke( $emails, $email_order, $plain_text );
		$link_html = ob_get_clean();
		check( str_contains( $link_html, 'https://example.test/returns' ), 'Simplified email must preserve withdrawal link' );
		if ( array( 'goods' ) === $types ) {
			check( str_contains( $link_html, 'ricezione dei beni' ) && ! str_contains( $link_html, 'digitali' ) && ! str_contains( $link_html, 'servizi' ), 'Physical email must omit irrelevant digital/service references' );
		} elseif ( count( $types ) === 1 ) {
			check( str_contains( $link_html, 'conclusione del contratto' ) && ! str_contains( $link_html, 'ricezione dei beni' ), 'Nonphysical email must omit irrelevant goods references' );
		} else {
			check( str_contains( $link_html, 'ricezione dei beni' ) && str_contains( $link_html, 'conclusione del contratto' ), 'Mixed email must preserve both timing rules' );
		}
	}
}
check( $email_method->invoke( $emails, 100 )['reason_label'] === 'Non indicato (facoltativo)', 'New emails render optional reason label' );
$meta[100]['_wlr_declaration']['reason_label'] = '';
$hash_before = WLR_Post_Type::compute_receipt_hash( 100 );
check( $email_method->invoke( $emails, 100 )['reason_label'] === 'Non indicato (facoltativo)', 'Historical empty snapshot label gets display fallback' );
check( $meta[100]['_wlr_declaration']['reason_label'] === '' && WLR_Post_Type::compute_receipt_hash( 100 ) === $hash_before, 'Display fallback must not rewrite historical declaration or hash' );
$meta[100]['_wlr_declaration']['reason_label'] = 'Etichetta storica';
check( $email_method->invoke( $emails, 100 )['reason_label'] === 'Etichetta storica', 'Historical non-empty label is preserved' );

// Automatic recovery uses submission time, never the date when the sweep runs.
$posts[200] = new WP_Post( 200 );
$meta[200] = array( '_wlr_order_id' => 1, '_wlr_submitted_at_utc' => '2026-10-07 13:04:46', '_wlr_deadline_at' => '2024-12-02 23:59:59' );
$actions = array();
check( WLR_Post_Type::reject_late_return( 200 ) === true && $posts[200]->post_status === 'wlr-rejected', 'Screenshot case must be automatically rejected' );
check( str_contains( $meta[200]['_wlr_history'][0]['note'], 'automaticamente' ), 'Automatic rejection retains its reason' );
check( $meta[200]['_wlr_history'][0]['user_id'] === 0, 'Automatic rejection must not be attributed to the customer' );
check( $actions[0][1] === array( 200, 'wlr-rejected', 'wlr-requested' ), 'Automatic rejection invokes existing customer notification' );
check( WLR_Post_Type::reject_late_return( 200 ) === false && count( $actions ) === 1, 'Automatic rejection must be idempotent' );
foreach ( array( 201 => '2024-12-01 13:00:00', 202 => '2024-12-02 23:59:59', 203 => '', 204 => '2024-02-31 13:00:00' ) as $id => $submitted ) {
	$posts[$id] = new WP_Post( $id );
	$meta[$id] = $meta[200];
	$meta[$id]['_wlr_submitted_at_utc'] = $submitted;
	check( WLR_Post_Type::reject_late_return( $id ) === false && $posts[$id]->post_status === 'wlr-requested', 'Timely, boundary or missing evidence must not be rejected: ' . $id );
}
foreach ( array( 'wlr-approved', 'wlr-refunded', 'wlr-cancelled', 'trash' ) as $status ) {
	$posts[205] = new WP_Post( 205, $status ); $meta[205] = $meta[200];
	check( WLR_Post_Type::reject_late_return( 205 ) === false && $posts[205]->post_status === $status, 'Only pending requests may be automatically rejected: ' . $status );
}
$posts[206] = new WP_Post( 206 ); $meta[206] = $meta[200];
$write_mode = 'fail';
check( error_is( WLR_Post_Type::reject_late_return( 206 ), 'wlr_status_write_failed' ) && $posts[206]->post_status === 'wlr-requested', 'Automatic rejection must report write failures' );
$write_mode = 'ok';
$wpdb->busy = true;
check( error_is( WLR_Post_Type::reject_late_return( 206 ), 'wlr_busy' ), 'Automatic rejection must respect order locks' );
$wpdb->busy = false;
WLR_Post_Type::instance();
check( wp_next_scheduled( 'wlr_reject_late_returns' ), 'Automatic recovery must be scheduled' );
check( isset( $callbacks['wlr_return_created'][5] ), 'New request guard must run before normal emails at priority 10' );
do_action( 'wlr_return_created', 206, 1, 7 );
check( $posts[206]->post_status === 'wlr-rejected', 'New request event catches a late record immediately' );

for ( $id = 300; $id < 405; $id++ ) { $posts[$id] = new WP_Post( $id ); $meta[$id] = $meta[200]; }
WLR_Post_Type::reject_late_returns_batch( 299 );
check( $posts[399]->post_status === 'wlr-rejected' && $posts[400]->post_status === 'wlr-requested', 'Recovery batch is bounded to 100 records' );
check( wp_next_scheduled( 'wlr_reject_late_returns', array( 399 ) ), 'Remaining records get a continuation event' );
WLR_Post_Type::reject_late_returns_batch( 399 );
check( $posts[404]->post_status === 'wlr-rejected' && $posts[201]->post_status === 'wlr-requested', 'Cursor continues without skipping records or rejecting timely requests' );
$query = new class { public function get( $key ) { return array( 'post_type' => 'wlr_return', 'wlr_late_after_id' => 399 )[$key] ?? ''; } };
check( WLR_Post_Type::filter_late_returns_cursor( 'WHERE 1=1', $query ) === 'WHERE 1=1 AND test_posts.ID > 399', 'Real WordPress query filter applies cursor' );
$other_query = new class { public function get( $key ) { return 'post_type' === $key ? 'post' : ''; } };
check( WLR_Post_Type::filter_late_returns_cursor( 'WHERE 1=1', $other_query ) === 'WHERE 1=1', 'Recovery filter must not affect unrelated queries' );

// Deletion must verify the write, preserve recoverable data, and release quantities.
$posts[100]->post_status = 'wlr-cancelled';
$posts[500] = new WP_Post( 500 );
$meta[500] = array( '_wlr_order_id' => 1, '_wlr_items' => array( array( 'item_id' => 11, 'qty' => 2 ) ), '_wlr_declaration' => array( 'name' => 'Test Customer' ) );
$actions = array();
check( WLR_Post_Type::get_returnable_items( $order ) === array(), 'Active request reserves all purchased quantities' );
check( error_is( WLR_Post_Type::delete_return( 500, true ), 'wlr_not_trashed' ), 'Permanent deletion requires trash first' );
$wpdb->busy = true;
check( error_is( WLR_Post_Type::delete_return( 500 ), 'wlr_busy' ) && $posts[500]->post_status === 'wlr-requested', 'Deletion must respect order lock' );
$wpdb->busy = false;
$delete_mode = 'fail';
check( error_is( WLR_Post_Type::delete_return( 500 ), 'wlr_delete_failed' ), 'Trash write failure must be reported' );
$delete_mode = 'ignore';
check( error_is( WLR_Post_Type::delete_return( 500 ), 'wlr_delete_failed' ), 'Accepted trash operation without saved status must fail' );
$delete_mode = 'ok';
wp_schedule_single_event( time(), 'wlr_retry_receipt', array( 500 ) );
check( WLR_Post_Type::delete_return( 500 ) === true && $posts[500]->post_status === 'trash', 'Request moves to trash' );
check( $meta[500]['_wp_trash_meta_status'] === 'wlr-requested' && $meta[500]['_wlr_declaration']['name'] === 'Test Customer', 'Trash preserves previous status and declaration for restoration' );
check( ! wp_next_scheduled( 'wlr_retry_receipt', array( 500 ) ), 'Deletion cancels pending receipt job' );
check( WLR_Post_Type::get_returnable_items( $order ) === array( 11 => 2 ), 'Trashing releases quantity reservation and clears cached returns' );
check( WLR_Post_Type::delete_return( 500 ) === true, 'Repeated trash operation is idempotent' );
$delete_mode = 'ignore';
check( error_is( WLR_Post_Type::delete_return( 500, true ), 'wlr_delete_failed' ), 'Permanent deletion without actual removal must fail' );
$delete_mode = 'ok';
check( WLR_Post_Type::delete_return( 500, true ) === true && ! isset( $posts[500], $meta[500] ), 'Permanent deletion removes request and metadata' );
check( error_is( WLR_Post_Type::delete_return( 999 ), 'wlr_invalid_return' ), 'Missing request cannot be deleted' );
$posts[501] = new WP_Post( 501, 'completed', 'shop_order' );
check( error_is( WLR_Post_Type::delete_return( 501, true ), 'wlr_invalid_return' ) && isset( $posts[501] ), 'Deletion must never delete the WooCommerce order' );
check( empty( $actions ), 'Deletion must not send status notification or alter order' );

define( 'EMPTY_TRASH_DAYS', 0 );
$posts[502] = new WP_Post( 502 );
check( error_is( WLR_Post_Type::delete_return( 502 ), 'wlr_trash_disabled' ) && isset( $posts[502] ), 'Disabled trash must require explicit permanent deletion' );
check( WLR_Post_Type::delete_return( 502, true ) === true && ! isset( $posts[502] ), 'Explicit permanent deletion works when WordPress trash is disabled' );
echo "Passed {$checks} local regression checks.\n";
