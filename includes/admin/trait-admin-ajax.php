<?php
/**
 * Admin AJAX handlers trait.
 *
 * @package Functionalities\Admin
 * @since   0.16.0
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for AJAX handler methods.
 */
trait Admin_Ajax {

	/**
	 * AJAX handler for database update tool.
	 *
	 * @return void
	 */
	public static function ajax_update_database() : void {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization for verification.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_db_update' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		// Get URL from request.
		$url = isset( $_POST['url'] ) ? \sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';

		// Call the update method.
		$result = \Functionalities\Features\Link_Management::update_links_in_database( $url );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for creating JSON file in theme directory.
	 *
	 * @return void
	 */
	public static function ajax_create_json_file() : void {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization for verification.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_create_json' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		// Get content from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON content needs to remain raw for parsing.
		$content = isset( $_POST['content'] ) ? \wp_unslash( $_POST['content'] ) : '';

		// Validate JSON with proper error handling.
		$decoded = json_decode( $content, true );
		if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid JSON format.', 'functionalities' ) . ' ' . json_last_error_msg() ) );
			return;
		}

		// Validate structure.
		if ( ! isset( $decoded['urls'] ) || ! is_array( $decoded['urls'] ) ) {
			\wp_send_json_error( array( 'message' => \__( 'JSON must contain a "urls" array.', 'functionalities' ) ) );
			return;
		}

		// Get theme directory.
		$theme_dir = \get_stylesheet_directory();
		$file_path = $theme_dir . '/exception-urls.json';

		// Check if theme directory is writable.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Pre-check for writability, WP_Filesystem has no equivalent.
		if ( ! is_writable( $theme_dir ) ) {
			\wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: directory path */
						\__( 'Theme directory is not writable: %s', 'functionalities' ),
						$theme_dir
					),
				)
			);
		}

		// Format JSON nicely.
		$formatted_content = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		// Write file via WP_Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			\wp_send_json_error( array( 'message' => \__( 'Could not initialize filesystem.', 'functionalities' ) ) );
			return;
		}
		$result = $wp_filesystem->put_contents( $file_path, $formatted_content, FS_CHMOD_FILE );

		if ( ! $result ) {
			\wp_send_json_error( array( 'message' => \__( 'Failed to write file.', 'functionalities' ) ) );
		}

		\wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: file path */
					\__( 'JSON file created successfully: %s', 'functionalities' ),
					$file_path
				),
				'path'    => $file_path,
			)
		);
	}

	/**
	 * AJAX handler for toggling delete-data-on-uninstall option.
	 *
	 * @return void
	 */
	public static function ajax_toggle_delete_data() : void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization for verification.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_delete_data_toggle' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		$enabled = ! empty( $_POST['enabled'] );
		\update_option( 'functionalities_delete_data_on_uninstall', $enabled );

		\wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	/**
	 * AJAX handler for running assumption detection.
	 *
	 * @return void
	 */
	public static function ajax_run_detection() : void {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization for verification.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_run_detection' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		// Run detection.
		$warnings = \Functionalities\Features\Assumption_Detection::force_run_detection();

		\wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of warnings */
					\__( 'Detection complete. Found %d issue(s).', 'functionalities' ),
					count( $warnings )
				),
				'count'   => count( $warnings ),
			)
		);
	}
}
