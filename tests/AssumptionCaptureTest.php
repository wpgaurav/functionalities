<?php
/**
 * Assumption scans inspect anonymous rendered HTML and preserve failure state.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-assumption-detection.php';

final class AssumptionCaptureTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_options'] = array(
			'functionalities_assumption_detection' => array(
				'enabled' => true,
				'detect_schema_collision' => false,
				'detect_analytics_dupe' => true,
				'detect_font_redundancy' => false,
				'detect_inline_css_growth' => false,
				'detect_jquery_conflicts' => false,
				'detect_meta_duplication' => true,
				'detect_rest_exposure' => false,
				'detect_lazy_load_conflict' => true,
				'detect_mixed_content' => false,
				'detect_missing_security_headers' => false,
				'detect_debug_exposure' => false,
				'detect_cron_issues' => false,
			),
		);
		$GLOBALS['functionalities_test_transients'] = array();
		$GLOBALS['functionalities_test_action_calls'] = array();
		$GLOBALS['functionalities_http_calls'] = 0;
		$GLOBALS['functionalities_http_fail'] = false;
		$GLOBALS['functionalities_test_is_admin'] = true;
		$property = new ReflectionProperty( \Functionalities\Features\Assumption_Detection::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
		\Functionalities\Features\Assumption_Detection::clear_cache();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['functionalities_http_handler'], $GLOBALS['functionalities_action_handler'] );
		$GLOBALS['functionalities_test_is_admin'] = false;
		parent::tearDown();
	}

	private function respond_with_html( string $html ): void {
		$GLOBALS['functionalities_http_handler'] = static function () use ( $html ) {
			return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'text/html; charset=UTF-8' ), 'body' => $html );
		};
	}

	private function ga4_tag(): string {
		return '<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC123"></script><script>gtag("config","G-ABC123");</script>';
	}

	private function captured_html( string $html ): void {
		$property = new ReflectionProperty( \Functionalities\Features\Assumption_Detection::class, 'frontend_output_cache' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, array( 'head' => $html, 'footer' => '', 'full' => $html, 'error' => null ) );
	}

	public function test_admin_scan_fetches_anonymous_full_document_once_without_running_frontend_hooks(): void {
		$requests = array();
		$html = '<html><head><meta name="viewport" content="a"><meta name="viewport" content="b"></head><body><img loading="lazy" src="/photo.jpg"><script src="/lazysizes.min.js"></script>' . $this->ga4_tag() . '</body></html>';
		$GLOBALS['functionalities_http_handler'] = static function ( $url, $args ) use ( &$requests, $html ) {
			$requests[] = array( 'url' => $url, 'args' => $args );
			return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'Content-Type' => 'text/html' ), 'body' => $html );
		};
		$warnings = \Functionalities\Features\Assumption_Detection::force_run_detection();
		$this->assertSame( array( 'meta_duplication', 'lazy_load_conflict' ), array_column( $warnings, 'type' ) );
		$this->assertCount( 1, $requests );
		$this->assertSame( home_url( '/' ), $requests[0]['url'] );
		$this->assertSame( array(), $requests[0]['args']['cookies'] );
		$this->assertSame( '', $requests[0]['args']['headers']['Cookie'] );
		$this->assertArrayNotHasKey( 'Authorization', $requests[0]['args']['headers'] );
		$this->assertSame( 2 * 1024 * 1024, $requests[0]['args']['limit_response_size'] );
		$this->assertSame( 0, $GLOBALS['functionalities_test_action_calls']['wp_head'] ?? 0 );
		$this->assertSame( 0, $GLOBALS['functionalities_test_action_calls']['wp_footer'] ?? 0 );
		$this->assertSame( 'complete', \Functionalities\Features\Assumption_Detection::get_scan_status()['state'] );
	}

	public function test_single_ga4_loader_and_config_are_one_installation(): void {
		$this->captured_html( '<html><head>' . $this->ga4_tag() . '</head><body></body></html>' );
		$this->assertSame( array(), \Functionalities\Features\Assumption_Detection::detect_analytics_duplication() );
	}

	public function test_two_ga4_installations_still_produce_a_duplicate_warning(): void {
		$this->captured_html( '<html><head>' . $this->ga4_tag() . '</head><body>' . $this->ga4_tag() . '</body></html>' );
		$warnings = \Functionalities\Features\Assumption_Detection::detect_analytics_duplication();
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'analytics_duplication', $warnings[0]['type'] );
		$this->assertSame( 2, $warnings[0]['details']['count'] );
	}

	public function test_tracking_examples_in_page_text_do_not_count_as_installations(): void {
		$this->captured_html( '<html><head>' . $this->ga4_tag() . '</head><body><pre>gtag("config","G-ABC123");</pre></body></html>' );
		$this->assertSame( array(), \Functionalities\Features\Assumption_Detection::detect_analytics_duplication() );
	}

	public function test_failed_fetch_retains_previous_findings_and_success_timestamp_then_recovers(): void {
		$previous = array( array( 'type' => 'meta_duplication', 'message' => 'Previous finding' ) );
		$last_success = time() - 60;
		update_option( \Functionalities\Features\Assumption_Detection::OPTION_KEY, $previous );
		update_option( 'functionalities_assumptions_last_run', $last_success );
		$GLOBALS['functionalities_http_handler'] = static function () {
			return new WP_Error( 'http_request_failed', 'Network unavailable.' );
		};
		$this->assertSame( $previous, \Functionalities\Features\Assumption_Detection::force_run_detection() );
		$this->assertSame( $last_success, get_option( 'functionalities_assumptions_last_run' ) );
		$status = \Functionalities\Features\Assumption_Detection::get_scan_status();
		$this->assertSame( 'error', $status['state'] );
		$this->assertSame( 'request_failed', $status['error']['code'] );
		$this->respond_with_html( '<html><head></head><body>Public homepage</body></html>' );
		$this->assertSame( array(), \Functionalities\Features\Assumption_Detection::force_run_detection() );
		$this->assertSame( 'complete', \Functionalities\Features\Assumption_Detection::get_scan_status()['state'] );
		$this->assertGreaterThanOrEqual( $last_success, get_option( 'functionalities_assumptions_last_run' ) );
	}

	public function test_http_exception_does_not_discard_the_callers_output_buffer(): void {
		$GLOBALS['functionalities_http_handler'] = static function () {
			throw new TypeError( 'Simulated transport callback failure.' );
		};
		$entry_depth = ob_get_level();
		ob_start();
		echo 'Caller-owned output';
		try {
			\Functionalities\Features\Assumption_Detection::force_run_detection();
			$this->assertSame( $entry_depth + 1, ob_get_level() );
			$this->assertSame( 'Caller-owned output', ob_get_contents() );
			$this->assertSame( 1, $GLOBALS['functionalities_http_calls'] );
			$this->assertSame( 'request_failed', \Functionalities\Features\Assumption_Detection::get_scan_status()['error']['code'] );
		} finally {
			while ( ob_get_level() > $entry_depth ) {
				ob_end_clean();
			}
		}
	}

	public function test_frontend_hook_exceptions_cannot_destroy_admin_output_buffers(): void {
		$this->respond_with_html( '<html><head></head><body>Public homepage</body></html>' );
		$GLOBALS['functionalities_action_handler'] = static function ( $hook ) {
			if ( 'wp_head' === $hook ) {
				throw new RuntimeException( 'A frontend callback requires a frontend request.' );
			}
		};
		$entry_depth = ob_get_level();
		ob_start();
		echo 'Admin output owned by another plugin';
		try {
			\Functionalities\Features\Assumption_Detection::force_run_detection();
			$this->assertSame( $entry_depth + 1, ob_get_level() );
			$this->assertSame( 'Admin output owned by another plugin', ob_get_contents() );
			$this->assertSame( 0, $GLOBALS['functionalities_test_action_calls']['wp_head'] ?? 0 );
			$this->assertSame( 'complete', \Functionalities\Features\Assumption_Detection::get_scan_status()['state'] );
		} finally {
			while ( ob_get_level() > $entry_depth ) {
				ob_end_clean();
			}
		}
	}

	/**
	 * @dataProvider invalid_responses
	 */
	public function test_invalid_public_responses_are_failed_scans( int $code, string $type, string $body, string $error ): void {
		$GLOBALS['functionalities_http_handler'] = static function () use ( $code, $type, $body ) {
			return array( 'response' => array( 'code' => $code ), 'headers' => array( 'content-type' => $type ), 'body' => $body );
		};
		\Functionalities\Features\Assumption_Detection::force_run_detection();
		$this->assertSame( 0, get_option( 'functionalities_assumptions_last_run', 0 ) );
		$status = \Functionalities\Features\Assumption_Detection::get_scan_status();
		$this->assertSame( 'error', $status['state'] );
		$this->assertSame( $error, $status['error']['code'] );
	}

	public static function invalid_responses(): array {
		return array(
			'HTTP authentication challenge' => array( 401, 'text/html', '<html><body>Login required</body></html>', 'http_status' ),
			'JSON response' => array( 200, 'application/json', '{"html":"<html></html>"}', 'invalid_content' ),
			'empty response' => array( 200, 'text/html', '', 'invalid_content' ),
			'truncated response' => array( 200, 'text/html', '<html>' . str_repeat( 'x', 2 * 1024 * 1024 ), 'response_too_large' ),
		);
	}
}
