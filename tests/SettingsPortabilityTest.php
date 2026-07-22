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
		require_once dirname( __DIR__ ) . '/includes/admin/class-settings-portability-controller.php';
	}

	protected function setUp(): void {
		$GLOBALS['functionalities_test_options'] = array(
			'functionalities_misc' => array( 'enabled' => false ),
			'functionalities_snippets' => array( 'enabled' => false, 'header_snippets' => array( 'existing-code' ) ),
		);
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
				'settings' => array( 'snippets' => array( 'enabled' => true, 'header_snippets' => array( '<script>secret()</script>' ) ) ),
			)
		);

		$this->assertSame( array( 'existing-code' ), $preview['validated']['snippets']['header_snippets'] );
		$this->assertContains( 'header_snippets', $preview['skipped']['snippets'] );
	}
}
