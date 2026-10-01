<?php
/**
 * Isolated scheduled-scan notification worker.
 *
 * @package FunctionalitiesTests
 */

namespace Functionalities\Core {
	class Module_Registry {
		public static function boot_module( $slug ) { return true; }
		public static function is_enabled( $slug ) { return true; }
	}
}

namespace Functionalities\Features {
	class Assumption_Detection {
		const OPTION_KEY = 'functionalities_assumptions_detected';
		public static function force_run_detection() { return array( array( 'type' => 'new_finding' ) ); }
		public static function get_scan_status() { return 'scan-failure' === $GLOBALS['scenario'] ? array( 'state' => 'error', 'error' => array( 'message' => 'Request failed.' ) ) : array( 'state' => 'complete' ); }
	}
}

namespace Functionalities\Storage {
	class Data_Directory {
		public static function probe_url() { return in_array( $GLOBALS['scenario'], array( 'data-error', 'data-unmapped' ), true ) ? '' : 'https://example.test/privacy-probe.json.php'; }
		public static function get_errors() { return 'data-error' === $GLOBALS['scenario'] ? array( 'migration_failed' ) : array(); }
	}
}

namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	$GLOBALS['scenario'] = $argv[1];
	$GLOBALS['attempts'] = 0;
	$GLOBALS['http_calls'] = 0;
	$GLOBALS['options'] = array(
		'functionalities_assumption_detection' => array( 'enabled' => true, 'email_notifications' => true ),
		'functionalities_assumption_scan_summary' => array( 'digest' => 'old', 'notified_digest' => 'old', 'notified_at' => 'cooldown' === $argv[1] ? time() : 0 ),
	);
	function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function __( $text ) { return $text; }
	function esc_html__( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
	function wp_mail() { ++$GLOBALS['attempts']; return 'failure' !== $GLOBALS['scenario'] || $GLOBALS['attempts'] > 1; }
	function wp_remote_get( $url, $args = array() ) { ++$GLOBALS['http_calls']; return array( 'response' => array( 'code' => 'data-guarded' === $GLOBALS['scenario'] ? 404 : ( 'data-source' === $GLOBALS['scenario'] ? 200 : 503 ) ), 'body' => 'data-source' === $GLOBALS['scenario'] ? '<?php exit; ?>{"functionalities_private_canary":true}' : '' ); }
	function is_wp_error( $value ) { return false; }
	function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
	function wp_remote_retrieve_body( $response ) { return $response['body']; }
	require dirname( __DIR__, 2 ) . '/includes/admin/class-site-health-controller.php';
	if ( 0 === strpos( $GLOBALS['scenario'], 'data-' ) ) {
		echo json_encode( array( 'health' => \Functionalities\Admin\Site_Health_Controller::get_data_exposure_result(), 'http_calls' => $GLOBALS['http_calls'] ) );
		exit;
	}
	if ( 'scan-failure' === $GLOBALS['scenario'] ) {
		\Functionalities\Admin\Site_Health_Controller::run_background_scan();
		echo json_encode( array( 'health' => \Functionalities\Admin\Site_Health_Controller::get_site_health_result(), 'state' => $GLOBALS['options']['functionalities_assumption_scan_summary'], 'attempts' => $GLOBALS['attempts'] ) );
		exit;
	}
	\Functionalities\Admin\Site_Health_Controller::run_background_scan();
	$first_attempts = $GLOBALS['attempts'];
	$GLOBALS['options']['functionalities_assumption_scan_summary']['notified_at'] = time() - ( 2 * DAY_IN_SECONDS );
	\Functionalities\Admin\Site_Health_Controller::run_background_scan();
	echo json_encode( array( 'first_attempts' => $first_attempts, 'attempts' => $GLOBALS['attempts'], 'state' => $GLOBALS['options']['functionalities_assumption_scan_summary'] ) );
}
