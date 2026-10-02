<?php
/**
 * Utility module workspace router.
 *
 * @package Functionalities\Admin
 */
namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Route a native utility workspace. */
class Site_Activity_Controller {
	public static function render( array $module ): void {
		Module_Controller::render_module_site_activity( $module );
	}
}
