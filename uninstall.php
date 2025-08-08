<?php
/**
 * Uninstall script for Functionalities plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// No persistent data to remove. Add cleanup here if options are added in the future.

// Remove generated Components CSS file, if present.
if ( function_exists( 'wp_upload_dir' ) ) {
	$upload = wp_upload_dir();
	if ( empty( $upload['error'] ) ) {
		$dir = rtrim( (string) $upload['basedir'], '/\\' ) . '/functionalities';
		$file = $dir . '/components.css';
		if ( is_file( $file ) ) {
			@unlink( $file );
		}
		// Attempt to remove directory if empty
		if ( is_dir( $dir ) ) {
			@rmdir( $dir );
		}
	}
}
