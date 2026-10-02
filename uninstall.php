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

$functionalities_cleanup_site = static function (): void {
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

	// Uninstall removes the code these events execute, even when data is kept.
	wp_clear_scheduled_hook( 'functionalities_assumption_background_scan' );
	wp_clear_scheduled_hook( 'functionalities_redirect_flush_buffer' );
	wp_clear_scheduled_hook( 'functionalities_link_health_batch' );
	wp_clear_scheduled_hook( 'functionalities_link_health_weekly' );
	wp_clear_scheduled_hook( 'functionalities_activity_prune' );

	// Check if user opted in to full data removal.
	if ( ! get_option( 'functionalities_delete_data_on_uninstall', false ) ) {
		return;
	}

	// Capture the site's owned path before deleting the key option. Never remove
	// the shared parent: other sites may keep their data for a future reinstall.
	$functionalities_data_base = rtrim( (string) apply_filters( 'functionalities_data_base_dir', WP_CONTENT_DIR . '/functionalities' ), '/\\' );
	$functionalities_data_key  = (string) get_option( 'functionalities_data_key', '' );

	// --- Plugin options ---
	$functionalities_options = array(
		'functionalities_content_tools',
		'functionalities_link_health',
		'functionalities_site_activity',
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
		'functionalities_wordpress_7',
		'functionalities_data_key',
		'functionalities_version',
		'functionalities_link_preset_last_good',
		'functionalities_redirect_hit_buffer',
		// Assumption detection data.
		'functionalities_assumptions_detected',
		'functionalities_assumptions_ignored',
		'functionalities_inline_css_baseline',
		'functionalities_assumptions_last_run',
		'functionalities_assumption_audit',
		'functionalities_assumption_scan_summary',
		'functionalities_assumption_scan_status',
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
		'_functionalities_link_health',
		'_functionalities_link_health_count',
		'_functionalities_link_health_ignored',
		'_functionalities_content_snapshot',
		'_functionalities_regression_settings',
		'_functionalities_regression_status',
		'_functionalities_content_audit',
		'_gt_content_license',
	);

	foreach ( $functionalities_meta_keys as $functionalities_meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Uninstall cleanup requires direct meta_key query; runs once on uninstall only.
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

	// Deduplicated Link Health URL checks (bounded expiring cache).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_functionalities_link_health_' ) . '%', $wpdb->esc_like( '_transient_timeout_functionalities_link_health_' ) . '%' ) );

	// Redirect manager cache transient.
	delete_transient( 'func_redirects_json' );

	// Link Management exception preset cache.
	delete_transient( 'functionalities_link_preset' );

	// Assumption detection schedule transient.
	delete_transient( 'functionalities_run_assumption_detection' );

	// --- Filesystem data ---
	if ( '' === $functionalities_data_base ) {
		return;
	}

	$functionalities_paths = array();
	if ( preg_match( '/^[A-Za-z0-9]{8,64}$/', $functionalities_data_key ) ) {
		$functionalities_paths[] = $functionalities_data_base . '/' . $functionalities_data_key;
		$functionalities_paths[] = $functionalities_data_base . '/.migration-' . $functionalities_data_key . '.lock';
	}
	$functionalities_paths[] = $functionalities_data_base . '/.directory-key-' . get_current_blog_id() . '.lock';

	// Legacy fixed paths have no site ownership marker. They can be removed on a
	// single-site installation, but must be retained on multisite to avoid deleting
	// a different site's data before it has migrated.
	if ( ! is_multisite() ) {
		foreach ( array( 'redirects.json', '404-log.json', 'tasks', 'redirects.json.lock', '404-log.json.lock', '.directory-key.lock' ) as $functionalities_legacy_name ) {
			$functionalities_paths[] = $functionalities_data_base . '/' . $functionalities_legacy_name;
		}
	}

	foreach ( $functionalities_paths as $functionalities_data_path ) {
		if ( ! file_exists( $functionalities_data_path ) ) {
			continue;
		}
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( WP_Filesystem() && $wp_filesystem ) {
			$wp_filesystem->delete( $functionalities_data_path, true );
		}
	}
};

if ( is_multisite() ) {
	// Bound memory on large networks and honor each site's own preference.
	$functionalities_offset = 0;
	do {
		$functionalities_blog_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $functionalities_offset,
			)
		);
		foreach ( $functionalities_blog_ids as $functionalities_blog_id ) {
			switch_to_blog( (int) $functionalities_blog_id );
			try {
				$functionalities_cleanup_site();
			} finally {
				restore_current_blog();
			}
		}
		$functionalities_blog_count = count( $functionalities_blog_ids );
		$functionalities_offset    += $functionalities_blog_count;
	} while ( 100 === $functionalities_blog_count );
} else {
	$functionalities_cleanup_site();
}
