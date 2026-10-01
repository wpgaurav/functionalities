<?php
/**
 * Scheduled notification delivery regression tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class SiteHealthNotificationsTest extends TestCase {
	public function test_changed_findings_are_delivered_after_cooldown(): void {
		$result = $this->run_worker( 'cooldown' );
		$this->assertSame( 0, $result['first_attempts'] );
		$this->assertSame( 1, $result['attempts'] );
	}

	public function test_failed_delivery_is_retried_for_unchanged_findings(): void {
		$result = $this->run_worker( 'failure' );
		$this->assertSame( 1, $result['first_attempts'] );
		$this->assertSame( 2, $result['attempts'] );
		$this->assertSame( $result['state']['digest'], $result['state']['notified_digest'] );
	}

	public function test_failed_scan_is_reported_without_advancing_delivery_state(): void {
		$result = $this->run_worker( 'scan-failure' );
		$this->assertSame( 'critical', $result['health']['status'] );
		$this->assertSame( 'old', $result['state']['digest'] );
		$this->assertSame( 0, $result['attempts'] );
	}

	/** @dataProvider privacy_scenarios */
	public function test_privacy_probe_distinguishes_exposure_from_failed_checks( string $scenario, string $expected ): void {
		$result = $this->run_worker( $scenario );
		$this->assertSame( $expected, $result['health']['status'] );
		if ( 'data-error' === $scenario || 'data-unmapped' === $scenario ) {
			$this->assertSame( 0, $result['http_calls'] );
		}
	}

	public static function privacy_scenarios(): array {
		return array( array( 'data-error', 'critical' ), array( 'data-unmapped', 'recommended' ), array( 'data-source', 'critical' ), array( 'data-unexpected', 'recommended' ), array( 'data-guarded', 'good' ) );
	}

	private function run_worker( string $scenario ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/site-health-worker.php' ) . ' ' . escapeshellarg( $scenario );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result );
		return $result;
	}
}
