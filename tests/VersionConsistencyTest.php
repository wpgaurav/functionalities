<?php
/**
 * Version consistency tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class VersionConsistencyTest extends TestCase {
	/**
	 * Plugin header, constant, readme, and README versions must agree.
	 *
	 * @return void
	 */
	public function test_version_declarations_match() : void {
		$root        = dirname( __DIR__ );
		$plugin      = file_get_contents( $root . '/functionalities.php' );
		$readme      = file_get_contents( $root . '/readme.txt' );
		$github_docs = file_get_contents( $root . '/README.md' );

		$this->assertIsString( $plugin );
		$this->assertIsString( $readme );
		$this->assertIsString( $github_docs );
		$this->assertSame( 1, preg_match( '/^ \* Version:\s*([0-9.]+)/m', $plugin, $header ) );
		$this->assertSame( 1, preg_match( "/define\(\s*'FUNCTIONALITIES_VERSION',\s*'([0-9.]+)'\s*\)/", $plugin, $constant ) );
		$this->assertSame( 1, preg_match( '/^Stable tag:\s*([0-9.]+)/m', $readme, $stable ) );
		$this->assertSame( 1, preg_match( '/^\*\*Version:\*\*\s*([0-9.]+)/m', $github_docs, $docs ) );

		$this->assertSame( $header[1], $constant[1] );
		$this->assertSame( $header[1], $stable[1] );
		$this->assertSame( $header[1], $docs[1] );
	}
}
