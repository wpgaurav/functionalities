<?php
/**
 * Admin bootstrap and router.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep the public admin entry point small while preserving existing callbacks.
 */
class Admin extends Module_Controller {

	/**
	 * Initialize admin controllers.
	 *
	 * @return void
	 */
	public static function init(): void {
		parent::init();
		Settings_Portability_Controller::init();
	}
}
