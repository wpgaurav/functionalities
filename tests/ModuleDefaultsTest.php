<?php
/**
 * Module default tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class ModuleDefaultsTest extends TestCase {
	/**
	 * A fresh install must not enable SVG Icons implicitly.
	 *
	 * @return void
	 */
	public function test_svg_icons_are_disabled_by_default() : void {
		require_once dirname( __DIR__ ) . '/includes/features/class-svg-icons.php';

		$options = \Functionalities\Features\SVG_Icons::get_options();

		$this->assertFalse( $options['enabled'] );
	}
}
