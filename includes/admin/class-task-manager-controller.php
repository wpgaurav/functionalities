<?php
/**
 * Task Manager admin controller.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route the Task Manager custom workflow.
 */
class Task_Manager_Controller {
	/**
	 * Render the module page without exposing routing details to Admin.
	 *
	 * @param array $module Module metadata.
	 * @return void
	 */
	public static function render( array $module ): void {
		Module_Controller::render_module_task_manager( $module );
	}
}
