<?php
/**
 * Utility module workspace router.
 *
 * @package Functionalities\Admin
 */
namespace Functionalities\Admin;

use Functionalities\Features\Link_Health;
use Functionalities\Features\Link_Health_Editor;
use Functionalities\Features\Link_Health_Report;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Route a native utility workspace. */
class Link_Health_Controller {
	const NONCE = 'functionalities_link_health_live';

	public static function init(): void {
		\add_action( 'wp_ajax_functionalities_link_health_live', array( __CLASS__, 'ajax' ) );
	}

	/** Preserve percent-encoded URLs; downstream checks validate protocol and source membership. */
	public static function request_url( string $field = 'url' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Authorized callers validate URLs; sanitize_text_field would corrupt percent-encoded paths and queries.
		return isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? trim( \wp_unslash( $_POST[ $field ] ) ) : '';
	}

	/** Load only in the Link Health workspace, including its submenu alias. */
	public static function enqueue(): void {
		\wp_enqueue_script( 'functionalities-link-health', FUNCTIONALITIES_URL . 'assets/js/admin-link-health.js', array(), FUNCTIONALITIES_VERSION . '-' . filemtime( FUNCTIONALITIES_DIR . 'assets/js/admin-link-health.js' ), true );
		\wp_enqueue_script( 'functionalities-link-editor', FUNCTIONALITIES_URL . 'assets/js/admin-link-health-editor.js', array( 'functionalities-link-health' ), FUNCTIONALITIES_VERSION . '-' . filemtime( FUNCTIONALITIES_DIR . 'assets/js/admin-link-health-editor.js' ), true );
		\wp_localize_script(
			'functionalities-link-health',
			'functionalitiesLinkHealth',
			array(
				'ajaxUrl'         => \admin_url( 'admin-ajax.php' ),
				'nonce'           => \wp_create_nonce( self::NONCE ),
				'checking'        => \__( 'Checking links…', 'functionalities' ),
				'stopping'        => \__( 'Finishing the current batch…', 'functionalities' ),
				'retry'           => \__( 'Retry scan', 'functionalities' ),
				'resume'          => \__( 'Resume scan', 'functionalities' ),
				'connectionError' => \__( 'Live updates were interrupted. Reconnecting… Your background scan can continue.', 'functionalities' ),
				'requestError'    => \__( 'The request failed. Refresh this page and try again.', 'functionalities' ),
				'previewing'      => \__( 'Preparing preview…', 'functionalities' ),
				'applying'        => \__( 'Saving link changes…', 'functionalities' ),
				/* translators: 1: matching link count, 2: source post title. */
				'previewReplace'  => \__( 'Replace %1$d matching link(s) in “%2$s”. Existing fragments are kept unless the new URL specifies one.', 'functionalities' ),
				/* translators: 1: matching link count, 2: source post title. */
				'previewUnlink'   => \__( 'Unlink %1$d matching link(s) in “%2$s”, preserving their text and media.', 'functionalities' ),
				/* translators: 1: changed link count, 2: source post title. */
				'saved'           => \__( 'Updated %1$d link(s) in “%2$s”.', 'functionalities' ),
				'rescan'          => \__( 'Run a new scan to refresh this post’s report.', 'functionalities' ),
			)
		);
	}

	/** A translated, non-sensitive snapshot for both initial HTML and live updates. */
	public static function progress(): array {
		$data          = Link_Health::progress();
		$labels        = array(
			'idle'      => \__( 'Not started', 'functionalities' ),
			'checking'  => \__( 'Checking links…', 'functionalities' ),
			'waiting'   => \__( 'Waiting for the next batch', 'functionalities' ),
			'stopping'  => \__( 'Finishing the current batch…', 'functionalities' ),
			'stopped'   => \__( 'Stopped', 'functionalities' ),
			'completed' => \__( 'Scan completed', 'functionalities' ),
			'disabled'  => \__( 'Link Health is disabled', 'functionalities' ),
			'error'     => \__( 'Scan needs attention. Check private storage and retry.', 'functionalities' ),
		);
		$data['label'] = $labels[ $data['phase'] ] ?? $labels['idle'];
		/* translators: 1: completed posts, 2: total posts, 3: processed links. */
		$data['summary'] = sprintf( \__( '%1$d of %2$d posts completed · %3$d links checked', 'functionalities' ), $data['posts'], $data['total'], $data['links'] );
		return $data;
	}

	/** Each mutation requires an admin, a nonce, an enabled module, and the current run. */
	public static function ajax(): void {
		if ( ! \current_user_can( 'manage_options' ) || ! \check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Refresh this page and sign in as an administrator.', 'functionalities' ) ), 403 );
			return;
		}
		$operation = \sanitize_key( \wp_unslash( $_POST['operation'] ?? 'status' ) );
		$run       = \sanitize_text_field( \wp_unslash( $_POST['run'] ?? '' ) );
		if ( ! in_array( $operation, array( 'status', 'start', 'batch', 'resume', 'stop', 'recheck', 'ignore', 'preview_edit', 'apply_edit' ), true ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Unknown action.', 'functionalities' ) ), 400 );
			return;
		}
		if ( 'status' !== $operation && ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Enable Link Health before running a scan.', 'functionalities' ) ), 403 );
			return;
		}
		if ( in_array( $operation, array( 'batch', 'resume', 'stop' ), true ) && '' === $run ) {
			\wp_send_json_error( array( 'message' => \__( 'Refresh the scan status before continuing.', 'functionalities' ) ), 400 );
			return;
		}
		$result = true;
		switch ( $operation ) {
			case 'start':
				$result = Link_Health::start_scan();
				break;
			case 'resume':
				$result = 'stopped' === ( Link_Health::state()['status'] ?? '' ) ? Link_Health::resume_scan( $run ) : Link_Health::run_batch( $run );
				break;
			case 'batch':
				$result = Link_Health::run_batch( $run );
				break;
			case 'stop':
				$result = Link_Health::stop_scan( $run );
				break;
			case 'recheck':
			case 'ignore':
				$post_id = \absint( $_POST['post_id'] ?? 0 );
				$url     = self::request_url();
				$result  = 'recheck' === $operation ? Link_Health::recheck( $post_id, $url ) : Link_Health::set_ignored( $post_id, $url, true );
				break;
			case 'preview_edit':
				$result = Link_Health_Editor::preview( \absint( $_POST['post_id'] ?? 0 ), self::request_url(), \sanitize_key( \wp_unslash( $_POST['edit_mode'] ?? '' ) ), self::request_url( 'replacement' ) );
				break;
			case 'apply_edit':
				$result = Link_Health_Editor::apply( \absint( $_POST['post_id'] ?? 0 ), \sanitize_text_field( \wp_unslash( $_POST['token'] ?? '' ) ) );
				break;
		}
		if ( \is_wp_error( $result ) && ! ( 'batch' === $operation && 'update_aborted' === $result->get_error_code() ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
			return;
		}
		$data = array( 'progress' => self::progress() );
		if ( in_array( $operation, array( 'preview_edit', 'apply_edit' ), true ) ) {
			$data['edit'] = $result; }
		if ( ! empty( $_POST['report'] ) && \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			ob_start();
			Module_Controller::render_link_health_report( max( 1, \absint( $_POST['report_page'] ?? 1 ) ), Link_Health_Report::filters( \wp_unslash( $_POST ) ) );
			$data['html'] = ob_get_clean();
		}
		\wp_send_json_success( $data );
	}

	public static function render( array $module ): void {
		Module_Controller::render_module_link_health( $module );
	}
}
