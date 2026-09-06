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
		// Precaching is per URL with a catch: cache.addAll() is all-or-nothing, so a
		// single stale precache entry used to stop the worker installing at all.
		$this->assertStringContainsString( 'PRECACHE_URLS.map(', $source );
		$this->assertStringNotContainsString( 'addAll(PRECACHE_URLS)', $source );
	}

	/**
	 * Administration screens and private responses must never be cached.
	 *
	 * @return void
	 */
	public function test_service_worker_excludes_admin_and_private_responses(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/features/class-pwa.php' );

		$this->assertIsString( $source );
		$this->assertStringContainsString( "'/wp-admin', '/wp-login.php', '/wp-json'", $source );
		$this->assertStringContainsString( 'if(isExcluded(url))return;', $source );
		$this->assertStringContainsString( 'no-store|private', $source );
		$this->assertStringContainsString( 'MAX_ENTRIES', $source );
	}
}
