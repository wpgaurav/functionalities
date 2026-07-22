<?php
/**
 * Atomic JSON storage tests.
 *
 * @package Functionalities\Tests
 */

use Functionalities\Storage\Atomic_JSON_Store;
use PHPUnit\Framework\TestCase;

final class AtomicJsonStoreTest extends TestCase {
	/**
	 * Temporary test directory.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * Create an isolated test directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/functionalities-test-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->directory, 0755, true );
		require_once dirname( __DIR__ ) . '/includes/storage/class-atomic-json-store.php';
	}

	/**
	 * Remove test files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$files = glob( $this->directory . '/*' );
		foreach ( $files ?: array() as $file ) {
			unlink( $file );
		}
		rmdir( $this->directory );
	}

	/**
	 * Data round-trips and locked updates retain prior values.
	 *
	 * @return void
	 */
	public function test_write_read_and_update_round_trip(): void {
		$path = $this->directory . '/data.json';

		$this->assertTrue( Atomic_JSON_Store::write( $path, array( 'count' => 1 ) )['success'] );
		$this->assertSame( 1, Atomic_JSON_Store::read( $path )['data']['count'] );

		$result = Atomic_JSON_Store::update(
			$path,
			static function ( array $data ): array {
				++$data['count'];
				return $data;
			}
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 2, $result['data']['count'] );
	}

	/**
	 * Corrupt JSON is reported and is never silently replaced with defaults.
	 *
	 * @return void
	 */
	public function test_invalid_json_is_reported(): void {
		$path = $this->directory . '/data.json';
		file_put_contents( $path, '{broken' );

		$result = Atomic_JSON_Store::read( $path, array( 'safe' => true ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'invalid_json', $result['error'] );
		$this->assertSame( array( 'safe' => true ), $result['data'] );
		$this->assertSame( '{broken', file_get_contents( $path ) );
	}

	/**
	 * An interrupted or rejected mutation leaves the last valid file intact.
	 *
	 * @return void
	 */
	public function test_aborted_update_preserves_last_valid_file(): void {
		$path = $this->directory . '/data.json';
		Atomic_JSON_Store::write( $path, array( 'version' => 'known-good' ) );

		$result = Atomic_JSON_Store::update(
			$path,
			static function () {
				return false;
			}
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'update_aborted', $result['error'] );
		$this->assertSame( 'known-good', Atomic_JSON_Store::read( $path )['data']['version'] );
	}

	/**
	 * Atomic replacement preserves an existing file's permissions.
	 *
	 * @return void
	 */
	public function test_replacement_preserves_permissions(): void {
		$path = $this->directory . '/permissions.json';
		Atomic_JSON_Store::write( $path, array( 'count' => 1 ) );
		chmod( $path, 0600 );

		Atomic_JSON_Store::write( $path, array( 'count' => 2 ) );
		clearstatcache( true, $path );

		$this->assertSame( 0600, fileperms( $path ) & 0777 );
	}

	/**
	 * Concurrent processes cannot overwrite one another's increments.
	 *
	 * @return void
	 */
	public function test_concurrent_updates_do_not_lose_data(): void {
		$path       = $this->directory . '/counter.json';
		$workers    = array();
		$processes  = 4;
		$iterations = 40;
		Atomic_JSON_Store::write( $path, array( 'count' => 0 ) );

		for ( $index = 0; $index < $processes; ++$index ) {
			$command = escapeshellarg( PHP_BINARY ) . ' '
				. escapeshellarg( __DIR__ . '/fixtures/atomic-worker.php' ) . ' '
				. escapeshellarg( $path ) . ' '
				. $iterations;
			$workers[] = proc_open(
				$command,
				array(
					0 => array( 'pipe', 'r' ),
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				),
				$pipes
			);
			foreach ( $pipes as $pipe ) {
				fclose( $pipe );
			}
		}

		foreach ( $workers as $worker ) {
			$this->assertSame( 0, proc_close( $worker ) );
		}

		$result = Atomic_JSON_Store::read( $path );
		$this->assertTrue( $result['success'] );
		$this->assertSame( $processes * $iterations, $result['data']['count'] );
	}
}
