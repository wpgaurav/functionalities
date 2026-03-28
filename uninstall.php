<?php
/**
 * Uninstall script for Functionalities plugin.
 *
 * Removes all plugin data when the user has opted in via the
 * "Delete all plugin data when uninstalling" checkbox on the dashboard.
 *
 * @package Functionalities
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Always remove the generated Components CSS file.
if ( function_exists( 'wp_upload_dir' ) ) {
	$functionalities_upload = wp_upload_dir();
	if ( empty( $functionalities_upload['error'] ) ) {
		$functionalities_css_dir  = rtrim( (string) $functionalities_upload['basedir'], '/\\' ) . '/functionalities';
		$functionalities_css_file = $functionalities_css_dir . '/components.css';
		if ( is_file( $functionalities_css_file ) ) {
			wp_delete_file( $functionalities_css_file );
		}
		// Attempt to remove directory if empty.
		if ( is_dir( $functionalities_css_dir ) ) {
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( $wp_filesystem ) {
				$wp_filesystem->rmdir( $functionalities_css_dir );
			}
		}
	}
}

// Check if user opted in to full data removal.
if ( ! get_option( 'functionalities_delete_data_on_uninstall', false ) ) {
	return;
}

// --- Plugin options ---
$functionalities_options = array(
	'functionalities_link_management',
	'functionalities_block_cleanup',
	'functionalities_editor_links',
	'functionalities_snippets',
	'functionalities_schema',
	'functionalities_components',
	'functionalities_fonts',
	'functionalities_misc',
	'functionalities_login_security',
	'functionalities_meta',
	'functionalities_content_regression',
	'functionalities_assumption_detection',
	'functionalities_pwa',
	'functionalities_task_manager',
	'functionalities_redirect_manager',
	'functionalities_svg_icons',
	// Assumption detection data.
	'functionalities_assumptions_detected',
	'functionalities_assumptions_ignored',
	'functionalities_inline_css_baseline',
	'functionalities_assumptions_last_run',
	// Login security data.
	'functionalities_login_lockouts',
	// Uninstall preference itself.
	'functionalities_delete_data_on_uninstall',
);

foreach ( $functionalities_options as $functionalities_opt ) {
	delete_option( $functionalities_opt );
}

// --- Post meta ---
global $wpdb;

$functionalities_meta_keys = array(
	'_functionalities_content_snapshot',
	'_functionalities_regression_settings',
	'_gt_content_license',
);

foreach ( $functionalities_meta_keys as $functionalities_meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $functionalities_meta_key ) );
}

// --- Transients ---
// Login security transients (hashed IP keys).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_funct_login_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_funct_login_' ) . '%'
	)
);

// Redirect manager cache transient.
delete_transient( 'func_redirects_json' );

// Assumption detection schedule transient.
delete_transient( 'functionalities_run_assumption_detection' );

// --- Filesystem data ---
$functionalities_data_dir = WP_CONTENT_DIR . '/functionalities';
if ( is_dir( $functionalities_data_dir ) ) {
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $functionalities_data_dir, true );
	}
}
