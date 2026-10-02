<?php
/**
 * Uninstall must respect per-site retention and data ownership.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class UninstallScopeTest extends TestCase {
	public function test_multisite_cleanup_preserves_a_site_that_did_not_opt_in(): void {
		$result = $this->run_worker( 'mixed' );
		$this->assertContains( $result['base'] . '/Aaaaaaaa', $result['deleted_paths'] );
		$this->assertNotContains( $result['base'], $result['deleted_paths'] );
		$this->assertNotContains( $result['base'] . '/Bbbbbbbb', $result['deleted_paths'] );
		$this->assertSame( 'Bbbbbbbb', $result['options'][2]['functionalities_data_key'] );
		$this->assertArrayNotHasKey( 'functionalities_data_key', $result['options'][1] );
		$this->assertSame( 1, $result['current_blog'] );
		foreach ( array( 'content_tools', 'link_health', 'site_activity' ) as $module ) {
			$this->assertArrayNotHasKey( 'functionalities_' . $module, $result['options'][1] );
			$this->assertTrue( $result['options'][2][ 'functionalities_' . $module ]['enabled'] );
		}
		foreach ( array( 'functionalities_link_health_batch', 'functionalities_link_health_weekly', 'functionalities_activity_prune' ) as $hook ) {
			$this->assertContains( $hook, $result['scheduled_clears'] );
		}
	}

	public function test_noncurrent_opted_in_site_is_cleaned_and_filtered_base_is_used(): void {
		$result = $this->run_worker( 'custom' );
		$this->assertSame( array( $result['base'] . '/Bbbbbbbb' ), $result['deleted_paths'] );
		$this->assertSame( 'Aaaaaaaa', $result['options'][1]['functionalities_data_key'] );
		$this->assertArrayNotHasKey( 'functionalities_data_key', $result['options'][2] );
	}

	private function run_worker( string $scenario ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/uninstall-worker.php' ) . ' ' . escapeshellarg( $scenario );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result );
		return $result;
	}
}
