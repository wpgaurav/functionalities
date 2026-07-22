<?php
/**
 * Isolated WordPress bootstrap shim for lazy-loading assertions.
 *
 * @package FunctionalitiesTests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$enabled = isset( $argv[1] ) ? $argv[1] : 'none';
$mode    = isset( $argv[2] ) ? $argv[2] : 'frontend';
$hooks   = array();

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url() {
	return 'https://example.test/wp-content/plugins/functionalities/';
}

function add_action( $hook, $callback ) {
	global $hooks;
	$hooks[ $hook ][] = $callback;
}

function add_filter( $hook, $callback ) {
	add_action( $hook, $callback );
}

function do_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_basename( $file ) { return basename( $file ); }
function is_admin() {
	global $mode;
	return 'admin' === $mode;
}
function apply_filters( $hook, $value ) { return $value; }
function __( $text ) { return $text; }
function wp_get_scheduled_event() { return false; }
function wp_clear_scheduled_hook() {}
function wp_schedule_event() {}
function did_action( $hook ) {
	return 'init' === $hook ? 1 : 0;
}
function get_bloginfo() { return 'Test Site'; }
function wp_get_attachment_image_url() { return false; }
function add_rewrite_rule() {}

function get_option( $option, $default = false ) {
	global $enabled;
	$target = 'functionalities_' . str_replace( '-', '_', $enabled );
	return $option === $target ? array( 'enabled' => true ) : $default;
}

require dirname( __DIR__, 2 ) . '/functionalities.php';

foreach ( $hooks['plugins_loaded'] as $callback ) {
	call_user_func( $callback );
}
foreach ( $hooks['init'] as $callback ) {
	call_user_func( $callback );
}

$features = array();
foreach ( get_included_files() as $file ) {
	if ( false !== strpos( $file, '/includes/features/' ) ) {
		$features[] = basename( $file );
	}
}
sort( $features );

echo json_encode( array( 'features' => $features, 'hooks' => array_keys( $hooks ) ) );
