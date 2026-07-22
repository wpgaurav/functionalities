<?php
/**
 * SVG Icons admin controller.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route the SVG Icons custom workflow.
 */
class SVG_Icons_Controller {
	/**
	 * Render the module page.
	 *
	 * @param array $module Module metadata.
	 * @return void
	 */
	public static function render( array $module ): void {
		Module_Controller::render_module_svg_icons( $module );
	}
}
