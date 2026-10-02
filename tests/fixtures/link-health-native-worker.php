<?php
function release_invoke( $target, $method, ...$args ) {
	$reflection = new ReflectionMethod( $target, $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invoke( is_object( $target ) ? $target : null, ...$args );
}

/** Native forms use the same generation checks as AJAX. */
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/UtilityModulesTest.php';
require dirname( __DIR__, 2 ) . '/includes/admin/trait-admin-utilities-ui.php';
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function check_admin_referer( $action ) { return true; }
function admin_url( $path ) { return $path; }
function wp_safe_redirect( $url ) { throw new RuntimeException( 'redirect' ); }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( 'denied' ); }
function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="test">'; }
class NativeReleaseHarness { use Functionalities\Admin\Admin_Utilities_UI; }
use Functionalities\Features\Link_Health as Links;
$test = new UtilityModulesTest( 'test_native' );
release_invoke( $test, 'setUp' );
try {
	$old = Links::start_scan(); Links::stop_scan( $old['run'] ); $new = Links::start_scan();
	$_POST = array( 'operation' => 'stop', 'run' => 'current' === $argv[1] ? $new['run'] : $old['run'] );
	if ( 'missing' === $argv[1] ) { unset( $_POST['run'] ); }
	try { NativeReleaseHarness::handle_link_health_action(); } catch ( RuntimeException $error ) { $outcome = $error->getMessage(); }
	ob_start();
	release_invoke( NativeReleaseHarness::class, 'link_health_button', 'stop', 'Stop' );
	$html = ob_get_clean();
	echo json_encode( array( 'outcome' => $outcome, 'status' => Links::state()['status'], 'form_has_run' => false !== strpos( $html, 'name="run" value="' . $new['run'] . '"' ) ) );
} finally { release_invoke( $test, 'tearDown' ); }
