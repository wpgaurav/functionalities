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
class Link_Health_Controller {
	public static function render( array $module ): void {
		Module_Controller::render_module_link_health( $module );
	}
}
