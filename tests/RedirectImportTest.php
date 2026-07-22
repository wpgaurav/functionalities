<?php
/**
 * Redirect CSV and import validation tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class RedirectImportTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__ ) . '/includes/features/class-redirect-manager.php';
	}

	public function test_common_csv_headers_are_mapped(): void {
		$parsed = \Functionalities\Features\Redirect_Manager::parse_csv( "old_url,new_url,status_code\n/old,/new,308\n" );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( '/old', $parsed['rows'][0]['from'] );
		$this->assertSame( '/new', $parsed['rows'][0]['to'] );
		$this->assertSame( 308, $parsed['rows'][0]['type'] );
	}

	public function test_import_rejects_duplicates_and_loops(): void {
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import(
			array(
				array( 'from' => '/taken', 'to' => '/new' ),
				array( 'from' => '/loop', 'to' => '/loop' ),
			),
			array( array( 'from' => '/taken', 'to' => '/existing' ) )
		);

		$this->assertFalse( $preview['success'] );
		$this->assertSame( array( 'duplicate_source', 'redirect_loop' ), array_column( $preview['errors'], 'code' ) );
	}

	public function test_import_reports_redirect_chains(): void {
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import(
			array(
				array( 'from' => '/a', 'to' => '/b' ),
				array( 'from' => '/b', 'to' => '/c' ),
			),
			array()
		);

		$this->assertTrue( $preview['success'] );
		$this->assertSame( 'redirect_chain', $preview['warnings'][0]['code'] );
	}
}
