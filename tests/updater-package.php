<?php
/** Package selection checks; no WordPress installation or filesystem mutation. */
define( 'ABSPATH', __DIR__ );
define( 'WLR_PLUGIN_FILE', __DIR__ . '/../woo-legal/woo-legal-returns.php' );
define( 'WLR_VERSION', '1.5.6' );

class WP_Error {
	public function __construct( public string $code, public string $message ) {}
}
class Test_Filesystem {
	public array $files = array();
	public array $moves = array();
	public bool $allow_move = true;
	public function exists( $path ) { return in_array( $path, $this->files, true ); }
	public function move( $from, $to ) { $this->moves[] = array( $from, $to ); return $this->allow_move; }
}
function __( $text, ...$args ) { return $text; }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function plugin_basename( $path ) { return 'woo-legal/woo-legal-returns.php'; }
function check( $condition, $message ) { ++$GLOBALS['checks']; if ( ! $condition ) { throw new RuntimeException( $message ); } }

require __DIR__ . '/../woo-legal/includes/class-wlr-github-updater.php';
$checks = 0;
$updater = new WLR_GitHub_Updater();
$wp_filesystem = new Test_Filesystem();
$context = array( 'plugin' => 'woo-legal/woo-legal-returns.php' );
$select = fn( $source, $hook = null ) => $updater->fix_folder_name( $source, '/upgrade/', null, $hook ?? $context );
$wp_filesystem->files = array( '/upgrade/repo-hash/woo-legal/woo-legal-returns.php' );
check( '/upgrade/repo-hash/woo-legal/' === $select( '/upgrade/repo-hash/' ), 'Repository archive must select only the nested installable directory' );
check( ! $wp_filesystem->moves, 'Nested plugin must not move the repository or its development files' );
$wp_filesystem->files = array( '/upgrade/woo-legal/woo-legal/woo-legal-returns.php' );
check( '/upgrade/woo-legal/woo-legal/' === $select( '/upgrade/woo-legal/' ), 'Nested repository must work even if outer folder already has the plugin slug' );
$wp_filesystem->files = array( '/upgrade/woo-legal/woo-legal-returns.php' );
check( '/upgrade/woo-legal/' === $select( '/upgrade/woo-legal/' ), 'Installable ZIP must retain the existing plugin folder' );
$wp_filesystem->files = array( '/upgrade/old-release/woo-legal-returns.php' );
check( '/upgrade/woo-legal/' === $select( '/upgrade/old-release/' ), 'Old flat repository archives must still be normalized' );
check( array( '/upgrade/old-release/', '/upgrade/woo-legal/' ) === $wp_filesystem->moves[0], 'Old package normalization must use the filesystem API' );
$wp_filesystem->files = array();
check( 'wlr_invalid_package' === $select( '/upgrade/empty/' )->code, 'Missing plugin file must be rejected' );
check( '/upgrade/other/' === $select( '/upgrade/other/', array( 'plugin' => 'other/other.php' ) ), 'Other plugin updates must be untouched' );
$wp_filesystem = null;
check( 'wlr_filesystem_unavailable' === $select( '/upgrade/repo-hash/' )->code, 'Unavailable filesystem must fail with a diagnostic' );
echo "OK: {$checks} updater package checks\n";
