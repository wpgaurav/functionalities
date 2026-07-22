<?php
/**
 * PWA offline response regression test.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class PwaOfflineStatusTest extends TestCase {
	/**
	 * The offline shell must be a successful response for cache.addAll().
	 *
	 * @return void
	 */
	public function test_offline_shell_is_cacheable_during_service_worker_install(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/features/class-pwa.php' );

		$this->assertIsString( $source );
		$this->assertMatchesRegularExpression(
			'/private static function output_offline_page\(\).*?status_header\( 200 \)/s',
			$source
		);
		$this->assertStringContainsString( 'c.addAll(PRECACHE_URLS)', $source );
	}
}
