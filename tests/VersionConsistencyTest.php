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

	/**
	 * The block metadata and npm package must carry the plugin version too.
	 *
	 * A test asserts the block.json version, and the npm package is what builds
	 * the WordPress 7 admin bundle, so both belong on the release bump list.
	 *
	 * @return void
	 */
	public function test_build_metadata_versions_match(): void {
		$root   = dirname( __DIR__ );
		$plugin = file_get_contents( $root . '/functionalities.php' );

		$this->assertSame( 1, preg_match( '/^ \* Version:\s*([0-9.]+)/m', $plugin, $header ) );

		foreach ( array( '/assets/blocks/svg-icon/block.json', '/package.json' ) as $file ) {
			$decoded = json_decode( (string) file_get_contents( $root . $file ), true );
			$this->assertIsArray( $decoded, $file );
			$this->assertSame( $header[1], $decoded['version'] ?? '', $file . ' is out of step with the plugin header' );
		}
	}

	/**
	 * Every hook shown in the admin docs must exist in the code.
	 *
	 * The documented list drifted badly enough by 1.5.0 that 19 of the hooks it
	 * advertised were never fired anywhere.
	 *
	 * @return void
	 */
	public function test_documented_hooks_exist(): void {
		$root = dirname( __DIR__ );
		$code = '';

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$code .= file_get_contents( $file->getPathname() );
			}
		}

		$docs = file_get_contents( $root . '/includes/admin/class-module-docs.php' );
		$this->assertSame( 1, preg_match_all( "/'name'\s*=> '(functionalities_[a-z0-9_{}$]+)'/", $docs, $matches ) > 0 ? 1 : 0 );

		$missing = array();
		foreach ( array_unique( $matches[1] ) as $hook ) {
			// Dynamic hook names are documented with their variable segment.
			$needle = strstr( $hook, '{', true );
            $needle = false === $needle ? $hook : $needle;

			if ( false === strpos( $code, "'" . $needle ) ) {
				$missing[] = $hook;
			}
		}

		$this->assertSame( array(), $missing, 'Documented hooks that are never fired: ' . implode( ', ', $missing ) );
	}
}
