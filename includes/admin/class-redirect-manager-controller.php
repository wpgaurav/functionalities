<?php
/**
 * Redirect Manager admin controller.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route the Redirect Manager custom workflow.
 */
class Redirect_Manager_Controller {
	/**
	 * Render the module page.
	 *
	 * @param array $module Module metadata.
	 * @return void
	 */
	public static function render( array $module ): void {
		Module_Controller::render_module_redirect_manager( $module );
	}
}
