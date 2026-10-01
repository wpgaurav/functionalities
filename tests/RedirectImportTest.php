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

	public function test_import_rejects_cycles_including_existing_rules_and_wildcards(): void {
		foreach ( array(
			array( array( 'from' => '/a', 'to' => '/b' ), array( 'from' => '/b', 'to' => '/a' ) ),
			array( array( 'from' => '/old*', 'to' => '/old/new' ) ),
		) as $rows ) {
			$preview = \Functionalities\Features\Redirect_Manager::prepare_import( $rows, array() );
			$this->assertFalse( $preview['success'] );
			$this->assertContains( 'redirect_loop', array_column( $preview['errors'], 'code' ) );
		}
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import(
			array( array( 'from' => '/b', 'to' => '/a' ) ),
			array( array( 'from' => '/a', 'to' => '/b', 'enabled' => true ) )
		);
		$this->assertFalse( $preview['success'] );
	}

	public function test_external_same_path_is_allowed_and_disabled_state_survives_csv(): void {
		$parsed = \Functionalities\Features\Redirect_Manager::parse_csv( "source,target,type,enabled,hits\n/article,https://other.example/article,301,0,12\n" );
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import( $parsed['rows'], array() );
		$this->assertTrue( $preview['success'] );
		$this->assertFalse( $preview['rows'][0]['enabled'] );
		$this->assertSame( 12, $preview['rows'][0]['hits'] );
	}

	public function test_import_rejects_scalar_entries_without_throwing(): void {
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import( array( 7 ), array() );
		$this->assertFalse( $preview['success'] );
		$this->assertSame( 'invalid_row', $preview['errors'][0]['code'] );
	}

	public function test_protocol_relative_local_loops_reject_and_external_root_redirects_allow(): void {
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import(
			array( array( 'from' => '/old', 'to' => '//example.test/old' ) ),
			array()
		);
		$this->assertFalse( $preview['success'] );
		$preview = \Functionalities\Features\Redirect_Manager::prepare_import(
			array( array( 'from' => '/', 'to' => 'https://other.example/' ) ),
			array()
		);
		$this->assertTrue( $preview['success'] );
	}
}
