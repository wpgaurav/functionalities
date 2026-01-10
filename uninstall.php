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
	$functionalities_upload = wp_upload_dir();
	if ( empty( $functionalities_upload['error'] ) ) {
		$functionalities_dir = rtrim( (string) $functionalities_upload['basedir'], '/\\' ) . '/functionalities';
		$functionalities_file = $functionalities_dir . '/components.css';
		if ( is_file( $functionalities_file ) ) {
			wp_delete_file( $functionalities_file );
		}
		// Attempt to remove directory if empty.
		if ( is_dir( $functionalities_dir ) ) {
			// Initialize WP_Filesystem.
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( $wp_filesystem ) {
				$wp_filesystem->rmdir( $functionalities_dir );
			}
		}
	}
}
