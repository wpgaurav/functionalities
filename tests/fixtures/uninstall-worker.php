<?php
/**
 * Isolated uninstall worker with an inert filesystem deletion spy.
 *
 * @package FunctionalitiesTests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'WP_UNINSTALL_PLUGIN', true );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/functionalities-uninstall-' . bin2hex( random_bytes( 8 ) ) );
$scenario = $argv[1];
$base = WP_CONTENT_DIR . ( 'custom' === $scenario ? '/custom-data' : '/functionalities' );
mkdir( $base . '/Aaaaaaaa', 0755, true );
mkdir( $base . '/Bbbbbbbb', 0755, true );
$GLOBALS['current_blog'] = 1;
$GLOBALS['blog_stack'] = array();
$GLOBALS['options'] = array(
	1 => array( 'functionalities_data_key' => 'Aaaaaaaa', 'functionalities_delete_data_on_uninstall' => 'custom' !== $scenario ),
	2 => array( 'functionalities_data_key' => 'Bbbbbbbb', 'functionalities_delete_data_on_uninstall' => 'custom' === $scenario ),
);
foreach ( array( 1, 2 ) as $blog ) {
	foreach ( array( 'content_tools', 'link_health', 'site_activity' ) as $module ) {
		$GLOBALS['options'][ $blog ][ 'functionalities_' . $module ] = array( 'enabled' => true );
	}
}
$GLOBALS['scheduled_clears'] = array();
$GLOBALS['deleted_paths'] = array();
function is_multisite() { return true; }
function get_current_blog_id() { return $GLOBALS['current_blog']; }
function get_sites( $args ) { return array_slice( array( 1, 2 ), $args['offset'] ?? 0, $args['number'] ?? 100 ); }
function switch_to_blog( $id ) { $GLOBALS['blog_stack'][] = $GLOBALS['current_blog']; $GLOBALS['current_blog'] = (int) $id; }
function restore_current_blog() { $GLOBALS['current_blog'] = array_pop( $GLOBALS['blog_stack'] ); }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $GLOBALS['current_blog'] ][ $name ] ?? $default; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $GLOBALS['current_blog'] ][ $name ] ); }
function delete_transient() {}
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['scheduled_clears'][] = $hook; }
function apply_filters( $hook, $value ) { global $base; return 'functionalities_data_base_dir' === $hook ? $base : $value; }
function wp_delete_file( $file ) { $GLOBALS['deleted_paths'][] = $file; }
class UninstallDatabaseSpy {
	public $postmeta = 'wp_postmeta';
	public $options = 'wp_options';
	public function delete() {}
	public function query() {}
	public function prepare( $sql, ...$args ) { return $sql; }
	public function esc_like( $value ) { return $value; }
}
class UninstallFilesystemSpy {
	public function delete( $path, $recursive = false ) { $GLOBALS['deleted_paths'][] = $path; return true; }
	public function rmdir() { return true; }
}
$GLOBALS['wpdb'] = new UninstallDatabaseSpy();
function WP_Filesystem() { $GLOBALS['wp_filesystem'] = new UninstallFilesystemSpy(); return true; }
require dirname( __DIR__, 2 ) . '/uninstall.php';
echo json_encode( array( 'scheduled_clears' => $GLOBALS['scheduled_clears'], 'base' => $base, 'deleted_paths' => $GLOBALS['deleted_paths'], 'options' => $GLOBALS['options'], 'current_blog' => $GLOBALS['current_blog'] ) );
rmdir( $base . '/Aaaaaaaa' );
rmdir( $base . '/Bbbbbbbb' );
rmdir( $base );
rmdir( WP_CONTENT_DIR );
