<?php
/**
 * Lazy module registry tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase {
	public function test_completed_upgrades_do_not_initialize_private_storage_for_disabled_modules(): void {
		$result = $this->run_worker( 'none', 'current-version' );
		$this->assertSame( array(), $result['features'] );
		$this->assertSame( array(), $result['storage'] );
	}
	/**
	 * Disabled frontend requests must not include feature classes.
	 *
	 * @return void
	 */
	public function test_disabled_frontend_loads_no_feature_files(): void {
		$result = $this->run_worker( 'none' );

		$this->assertSame( array(), $result['features'] );
	}

	/**
	 * Enabling one module loads only that feature class.
	 *
	 * @return void
	 */
	public function test_single_enabled_module_loads_only_that_feature(): void {
		$result = $this->run_worker( 'misc' );

		$this->assertSame( array( 'class-misc.php' ), $result['features'] );
	}

	/**
	 * PWA boot must defer rewrite registration until WordPress init.
	 *
	 * @return void
	 */
	public function test_pwa_boots_safely_before_wordpress_init(): void {
		$result = $this->run_worker( 'pwa' );

		$this->assertSame( array( 'class-pwa.php' ), $result['features'] );
		$this->assertContains( 'init', $result['hooks'] );
		$this->assertContains( 'added_option', $result['hooks'] );
		$this->assertContains( 'updated_option', $result['hooks'] );
	}

	/**
	 * The small admin bootstrap must register its router and controllers.
	 *
	 * @return void
	 */
	public function test_admin_bootstrap_registers_router_hooks(): void {
		$result = $this->run_worker( 'none', 'admin' );

		$this->assertContains( 'admin_menu', $result['hooks'] );
		$this->assertContains( 'functionalities_admin_dashboard_tools', $result['hooks'] );
		$this->assertContains( 'site_status_tests', $result['hooks'] );
	}

	public function test_detector_filter_contract_is_not_used_as_a_master_gate(): void {
		$result = $this->run_worker( 'none', 'filter-contract' );

		$this->assertSame( array(), $result['features'] );
		$this->assertTrue( $result['detector_contract'] );
	}

	public function test_master_filter_can_enable_a_stored_disabled_module(): void {
		$result = $this->run_worker( 'misc', 'master-enable' );

		$this->assertContains( 'use_widgets_block_editor', $result['hooks'] );
	}

	/**
	 * Execute the isolated bootstrap worker.
	 *
	 * @param string $module Module to enable.
	 * @param string $mode   Request mode.
	 * @return array
	 */
	private function run_worker( string $module, string $mode = 'frontend' ): array {
		$worker  = __DIR__ . '/fixtures/module-registry-worker.php';
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $worker ) . ' ' . escapeshellarg( $module ) . ' ' . escapeshellarg( $mode );
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status );

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$decoded = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}
}
