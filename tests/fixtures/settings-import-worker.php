<?php
/**
 * Isolated persistence-failure worker for multi-module import rollback.
 *
 * @package FunctionalitiesTests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$GLOBALS['options'] = array( 'functionalities_fonts' => array( 'enabled' => false ) );
$GLOBALS['failed_once'] = false;
function __( $text ) { return $text; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) {
	if ( 'functionalities_fonts' === $name && ! $GLOBALS['failed_once'] ) {
		$GLOBALS['failed_once'] = true;
		return false;
	}
	$GLOBALS['options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
require dirname( __DIR__, 2 ) . '/includes/core/class-module-registry.php';
require dirname( __DIR__, 2 ) . '/includes/admin/class-settings-portability-controller.php';
$outcome = \Functionalities\Admin\Settings_Portability_Controller::apply_import(
	array( 'schema' => 1, 'plugin' => 'dynamic-functionalities', 'settings' => array( 'misc' => array( 'enabled' => true ), 'fonts' => array( 'enabled' => true ) ) )
);
echo json_encode( array( 'outcome' => $outcome, 'options' => $GLOBALS['options'] ) );
