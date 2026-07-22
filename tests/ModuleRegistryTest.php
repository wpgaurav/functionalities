<?php
/**
 * Lazy module registry tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase {
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
