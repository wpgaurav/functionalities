<?php
/**
 * Settings portability validation tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class SettingsPortabilityTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__ ) . '/includes/core/class-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/admin/class-module-controller.php';
		require_once dirname( __DIR__ ) . '/includes/admin/class-settings-portability-controller.php';
		require_once dirname( __DIR__ ) . '/includes/features/class-snippets.php';
	}

	protected function setUp(): void {
		$GLOBALS['functionalities_test_options'] = array(
			'functionalities_misc' => array( 'enabled' => false ),
			'functionalities_snippets' => array(
				'enabled'    => false,
				'ga4_id'     => 'G-EXISTING',
				'header'     => array( array( 'code' => 'existing-code' ) ),
			),
		);
	}

	public function test_new_module_imports_keep_only_supported_config_fields(): void {
		$preview = \Functionalities\Admin\Settings_Portability_Controller::preview_import(
			array( 'schema' => 1, 'plugin' => 'dynamic-functionalities', 'settings' => array(
				'content-tools' => array( 'enabled' => true, 'copy_secrets' => true ),
				'link-health' => array( 'enabled' => true, 'weekly_scan' => true, 'arbitrary_urls' => array( 'http://127.0.0.1/' ) ),
				'site-activity' => array( 'enabled' => true, 'entries' => array( 'forged' ) ),
			) )
		);
		$this->assertTrue( $preview['success'] );
		$this->assertSame( array( 'enabled' => true ), $preview['validated']['content-tools'] );
		$this->assertSame( array( 'enabled' => true, 'weekly_scan' => true ), $preview['validated']['link-health'] );
		$this->assertSame( array( 'enabled' => true ), $preview['validated']['site-activity'] );
	}

	public function test_csv_titles_cannot_execute_spreadsheet_formulas(): void {
		foreach ( array( '=SUM(1,1)', '+cmd', '@cmd', '-cmd', "\t=cmd" ) as $title ) {
			$this->assertSame( "'", substr( \Functionalities\Admin\Module_Controller::csv_safe( $title ), 0, 1 ) );
		}
		$this->assertSame( 'Normal title', \Functionalities\Admin\Module_Controller::csv_safe( 'Normal title' ) );
	}

	public function test_preview_reports_changes_without_updating_options(): void {
		$preview = \Functionalities\Admin\Settings_Portability_Controller::preview_import(
			array(
				'schema'   => 1,
				'plugin'   => 'dynamic-functionalities',
				'settings' => array( 'misc' => array( 'enabled' => true ) ),
			)
		);

		$this->assertTrue( $preview['success'] );
		$this->assertSame( 'change', $preview['changes']['misc']['status'] );
		$this->assertSame( array( 'enabled' ), $preview['changes']['misc']['changed'] );
		$this->assertFalse( $GLOBALS['functionalities_test_options']['functionalities_misc']['enabled'] );
	}

	public function test_future_schema_is_rejected_before_changes(): void {
		$preview = \Functionalities\Admin\Settings_Portability_Controller::preview_import(
			array( 'schema' => 99, 'plugin' => 'dynamic-functionalities', 'settings' => array() )
		);

		$this->assertFalse( $preview['success'] );
		$this->assertSame( 'unsupported_schema', $preview['error'] );
	}

	public function test_custom_code_is_redacted_without_explicit_opt_in(): void {
		$preview = \Functionalities\Admin\Settings_Portability_Controller::preview_import(
			array(
				'schema'   => 1,
				'plugin'   => 'dynamic-functionalities',
				'settings' => array(
					'snippets' => array(
						'enabled' => true,
						'header'  => array( array( 'code' => '<script>secret()</script>' ) ),
					),
				),
			)
		);

		$this->assertSame(
			array( array( 'code' => 'existing-code' ) ),
			$preview['validated']['snippets']['header']
		);
		$this->assertContains( 'header', $preview['skipped']['snippets'] );
	}

	/**
	 * The GA4 measurement ID is an ordinary setting, not custom code.
	 *
	 * @return void
	 */
	public function test_analytics_id_survives_a_default_export(): void {
		$document = \Functionalities\Admin\Settings_Portability_Controller::build_export( array( 'snippets' ) );

		$this->assertSame( 'G-EXISTING', $document['settings']['snippets']['ga4_id'] );
		$this->assertArrayNotHasKey( 'header', $document['settings']['snippets'] );
	}

	public function test_legacy_custom_code_is_redacted_before_export(): void {
		$GLOBALS['functionalities_test_options']['functionalities_snippets'] = array(
			'enabled'        => true,
			'enable_header'  => true,
			'header_code'    => '<script>privateHeader()</script>',
			'body_open_code' => '<script>privateBody()</script>',
			'footer_code'    => '<script>privateFooter()</script>',
		);
		$document = \Functionalities\Admin\Settings_Portability_Controller::build_export( array( 'snippets' ) );

		foreach ( array( 'header_code', 'body_open_code', 'footer_code' ) as $field ) {
			$this->assertArrayNotHasKey( $field, $document['settings']['snippets'] );
		}
		$this->assertStringNotContainsString( 'privateHeader', json_encode( $document ) );
	}

	public function test_svg_import_preserves_picker_slug_and_disables_autoload(): void {
		require_once dirname( __DIR__ ) . '/includes/features/class-svg-icons.php';
		$document = array(
			'schema'   => 1,
			'plugin'   => 'dynamic-functionalities',
			'settings' => array(
				'svg-icons' => array(
					'enabled' => true,
					'icons'   => array( 'circle' => array( 'slug' => 'circle', 'name' => 'Circle', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5"/></svg>' ) ),
				),
			),
		);
		$preview = \Functionalities\Admin\Settings_Portability_Controller::preview_import( $document, true );
		$this->assertSame( 'circle', $preview['validated']['svg-icons']['icons']['circle']['slug'] ?? null );

		$result = \Functionalities\Admin\Settings_Portability_Controller::apply_import( $document, true );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'circle', $GLOBALS['functionalities_test_options']['functionalities_svg_icons']['icons']['circle']['slug'] );
		$this->assertFalse( $GLOBALS['functionalities_test_autoload']['functionalities_svg_icons'] ?? null );
	}

	public function test_legacy_code_export_uses_current_format_without_writing_source(): void {
		$legacy = array( 'enabled' => true, 'enable_header' => true, 'header_code' => '<script>legacy()</script>' );
		$GLOBALS['functionalities_test_options']['functionalities_snippets'] = $legacy;
		$document = \Functionalities\Admin\Settings_Portability_Controller::build_export( array( 'snippets' ), true );
		$this->assertSame( '<script>legacy()</script>', $document['settings']['snippets']['header'][0]['code'] );
		$this->assertArrayNotHasKey( 'header_code', $document['settings']['snippets'] );
		$this->assertSame( $legacy, $GLOBALS['functionalities_test_options']['functionalities_snippets'] );
	}

	public function test_failed_import_restores_absent_and_existing_options(): void {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/settings-import-worker.php' );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertFalse( $result['outcome']['success'] );
		$this->assertArrayNotHasKey( 'functionalities_misc', $result['options'] );
		$this->assertSame( array( 'enabled' => false ), $result['options']['functionalities_fonts'] );
	}
}
