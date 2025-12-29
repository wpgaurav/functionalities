<?php
/**
 * Redirect Manager - File-based URL redirect management.
 *
 * @package Functionalities\Features
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Manager class.
 *
 * Provides a file-based redirect management system using a JSON file.
 * Redirects are stored in /wp-content/functionalities/redirects.json
 */
class Redirect_Manager {

	/**
	 * Redirects file path.
	 *
	 * @var string
	 */
	private static $redirects_file = '';

	/**
	 * Cached redirects data.
	 *
	 * @var array|null
	 */
	private static $redirects_cache = null;

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init() : void {
		self::$redirects_file = WP_CONTENT_DIR . '/functionalities/redirects.json';

		// Handle redirects early on frontend.
		\add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );

		// Only register admin handlers in admin.
		if ( ! \is_admin() ) {
			return;
		}

		// AJAX handlers.
		\add_action( 'wp_ajax_functionalities_redirect_add', array( __CLASS__, 'ajax_add_redirect' ) );
		\add_action( 'wp_ajax_functionalities_redirect_update', array( __CLASS__, 'ajax_update_redirect' ) );
		\add_action( 'wp_ajax_functionalities_redirect_delete', array( __CLASS__, 'ajax_delete_redirect' ) );
		\add_action( 'wp_ajax_functionalities_redirect_toggle', array( __CLASS__, 'ajax_toggle_redirect' ) );
		\add_action( 'wp_ajax_functionalities_redirect_import', array( __CLASS__, 'ajax_import_redirects' ) );
		\add_action( 'wp_ajax_functionalities_redirect_export', array( __CLASS__, 'ajax_export_redirects' ) );
	}

	/**
	 * Get or create the redirects directory.
	 *
	 * @return string|false Directory path or false on failure.
	 */
	private static function get_redirects_dir() {
		$dir = dirname( self::$redirects_file );
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			// Security files.
			$index_file = $dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden.' );
			}
		}
		return $dir;
	}

	/**
	 * Get all redirects.
	 *
	 * @return array List of redirects.
	 */
	public static function get_redirects() : array {
		if ( null !== self::$redirects_cache ) {
			return self::$redirects_cache;
		}

		if ( ! file_exists( self::$redirects_file ) ) {
			self::$redirects_cache = array();
			return self::$redirects_cache;
		}

		$content = file_get_contents( self::$redirects_file );
		if ( false === $content ) {
			self::$redirects_cache = array();
			return self::$redirects_cache;
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['redirects'] ) ) {
			self::$redirects_cache = array();
			return self::$redirects_cache;
		}

		self::$redirects_cache = $data['redirects'];
		return self::$redirects_cache;
	}

	/**
	 * Save redirects.
	 *
	 * @param array $redirects Redirects array.
	 * @return bool True on success.
	 */
	public static function save_redirects( array $redirects ) : bool {
		$dir = self::get_redirects_dir();
		if ( ! $dir ) {
			return false;
		}

		$data = array(
			'version'   => '1.0',
			'modified'  => current_time( 'mysql' ),
			'redirects' => array_values( $redirects ),
		);

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$result = file_put_contents( self::$redirects_file, $json );
		if ( false !== $result ) {
			self::$redirects_cache = $redirects;
			return true;
		}

		return false;
	}

	/**
	 * Generate unique redirect ID.
	 *
	 * @return string Unique ID.
	 */
	private static function generate_id() : string {
		return 'r_' . substr( md5( uniqid( '', true ) ), 0, 10 );
	}

	/**
	 * Add a redirect.
	 *
	 * @param string $from_url Source URL path.
	 * @param string $to_url   Destination URL.
	 * @param int    $type     Redirect type (301 or 302).
	 * @return array|false Redirect data or false on failure.
	 */
	public static function add_redirect( string $from_url, string $to_url, int $type = 301 ) {
		$redirects = self::get_redirects();

		// Normalize source URL.
		$from_url = self::normalize_path( $from_url );
		if ( empty( $from_url ) ) {
			return false;
		}

		// Check for duplicate.
		foreach ( $redirects as $redirect ) {
			if ( $redirect['from'] === $from_url ) {
				return false; // Already exists.
			}
		}

		$redirect = array(
			'id'      => self::generate_id(),
			'from'    => $from_url,
			'to'      => esc_url_raw( $to_url ),
			'type'    => in_array( $type, array( 301, 302, 307, 308 ), true ) ? $type : 301,
			'enabled' => true,
			'hits'    => 0,
			'created' => current_time( 'mysql' ),
		);

		$redirects[] = $redirect;

		if ( self::save_redirects( $redirects ) ) {
			return $redirect;
		}

		return false;
	}

	/**
	 * Update a redirect.
	 *
	 * @param string $id      Redirect ID.
	 * @param array  $updates Updates to apply.
	 * @return array|false Updated redirect or false on failure.
	 */
	public static function update_redirect( string $id, array $updates ) {
		$redirects = self::get_redirects();

		foreach ( $redirects as &$redirect ) {
			if ( $redirect['id'] === $id ) {
				if ( isset( $updates['from'] ) ) {
					$redirect['from'] = self::normalize_path( $updates['from'] );
				}
				if ( isset( $updates['to'] ) ) {
					$redirect['to'] = esc_url_raw( $updates['to'] );
				}
				if ( isset( $updates['type'] ) ) {
					$type = (int) $updates['type'];
					$redirect['type'] = in_array( $type, array( 301, 302, 307, 308 ), true ) ? $type : 301;
				}
				if ( isset( $updates['enabled'] ) ) {
					$redirect['enabled'] = (bool) $updates['enabled'];
				}

				if ( self::save_redirects( $redirects ) ) {
					return $redirect;
				}
				break;
			}
		}

		return false;
	}

	/**
	 * Delete a redirect.
	 *
	 * @param string $id Redirect ID.
	 * @return bool True on success.
	 */
	public static function delete_redirect( string $id ) : bool {
		$redirects = self::get_redirects();

		$redirects = array_filter( $redirects, function( $r ) use ( $id ) {
			return $r['id'] !== $id;
		});

		return self::save_redirects( $redirects );
	}

	/**
	 * Toggle redirect enabled state.
	 *
	 * @param string $id Redirect ID.
	 * @return bool|null New state or null on failure.
	 */
	public static function toggle_redirect( string $id ) {
		$redirects = self::get_redirects();
		$new_state = null;

		foreach ( $redirects as &$redirect ) {
			if ( $redirect['id'] === $id ) {
				$redirect['enabled'] = ! $redirect['enabled'];
				$new_state = $redirect['enabled'];
				break;
			}
		}

		if ( null !== $new_state && self::save_redirects( $redirects ) ) {
			return $new_state;
		}

		return null;
	}

	/**
	 * Normalize URL path.
	 *
	 * @param string $path URL path.
	 * @return string Normalized path.
	 */
	private static function normalize_path( string $path ) : string {
		// Remove domain if present.
		$path = preg_replace( '#^https?://[^/]+#i', '', $path );

		// Ensure starts with /.
		$path = '/' . ltrim( $path, '/' );

		// Remove trailing slash (except for root).
		if ( $path !== '/' ) {
			$path = rtrim( $path, '/' );
		}

		// Sanitize.
		$path = sanitize_text_field( $path );

		return $path;
	}

	/**
	 * Handle redirects on frontend.
	 *
	 * @return void
	 */
	public static function handle_redirect() : void {
		// Don't redirect in admin.
		if ( is_admin() ) {
			return;
		}

		$redirects = self::get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

		// Get current path.
		$current_path = self::normalize_path( $_SERVER['REQUEST_URI'] ?? '' );

		foreach ( $redirects as $redirect ) {
			if ( empty( $redirect['enabled'] ) ) {
				continue;
			}

			$from = $redirect['from'];

			// Check for wildcard match.
			if ( substr( $from, -1 ) === '*' ) {
				$prefix = rtrim( $from, '*' );
				if ( strpos( $current_path, $prefix ) === 0 ) {
					self::do_redirect( $redirect );
					return;
				}
			} elseif ( $current_path === $from ) {
				self::do_redirect( $redirect );
				return;
			}
		}
	}

	/**
	 * Perform the redirect.
	 *
	 * @param array $redirect Redirect data.
	 * @return void
	 */
	private static function do_redirect( array $redirect ) : void {
		// Increment hit counter.
		self::increment_hits( $redirect['id'] );

		$status = isset( $redirect['type'] ) ? (int) $redirect['type'] : 301;

		\wp_redirect( $redirect['to'], $status );
		exit;
	}

	/**
	 * Increment redirect hit counter.
	 *
	 * @param string $id Redirect ID.
	 * @return void
	 */
	private static function increment_hits( string $id ) : void {
		$redirects = self::get_redirects();

		foreach ( $redirects as &$redirect ) {
			if ( $redirect['id'] === $id ) {
				$redirect['hits'] = ( $redirect['hits'] ?? 0 ) + 1;
				break;
			}
		}

		self::save_redirects( $redirects );
	}

	/**
	 * Get redirect statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_stats() : array {
		$redirects = self::get_redirects();

		$total   = count( $redirects );
		$enabled = 0;
		$hits    = 0;

		foreach ( $redirects as $r ) {
			if ( ! empty( $r['enabled'] ) ) {
				$enabled++;
			}
			$hits += $r['hits'] ?? 0;
		}

		return array(
			'total'    => $total,
			'enabled'  => $enabled,
			'disabled' => $total - $enabled,
			'hits'     => $hits,
		);
	}

	// AJAX Handlers.

	/**
	 * Verify AJAX request.
	 *
	 * @return bool True if valid.
	 */
	private static function verify_ajax() : bool {
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_redirect_manager' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return false;
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * AJAX: Add redirect.
	 */
	public static function ajax_add_redirect() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$from = isset( $_POST['from'] ) ? sanitize_text_field( $_POST['from'] ) : '';
		$to   = isset( $_POST['to'] ) ? esc_url_raw( $_POST['to'] ) : '';
		$type = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;

		if ( empty( $from ) || empty( $to ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Source and destination URLs are required.', 'functionalities' ) ) );
			return;
		}

		$redirect = self::add_redirect( $from, $to, $type );
		if ( $redirect ) {
			\wp_send_json_success( array(
				'message'  => \__( 'Redirect added.', 'functionalities' ),
				'redirect' => $redirect,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to add redirect. URL may already exist.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Update redirect.
	 */
	public static function ajax_update_redirect() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$id      = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';
		$updates = array();

		if ( isset( $_POST['from'] ) ) {
			$updates['from'] = sanitize_text_field( $_POST['from'] );
		}
		if ( isset( $_POST['to'] ) ) {
			$updates['to'] = esc_url_raw( $_POST['to'] );
		}
		if ( isset( $_POST['type'] ) ) {
			$updates['type'] = (int) $_POST['type'];
		}

		if ( empty( $id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Redirect ID is required.', 'functionalities' ) ) );
			return;
		}

		$redirect = self::update_redirect( $id, $updates );
		if ( $redirect ) {
			\wp_send_json_success( array(
				'message'  => \__( 'Redirect updated.', 'functionalities' ),
				'redirect' => $redirect,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to update redirect.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Delete redirect.
	 */
	public static function ajax_delete_redirect() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$id = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';

		if ( empty( $id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Redirect ID is required.', 'functionalities' ) ) );
			return;
		}

		if ( self::delete_redirect( $id ) ) {
			\wp_send_json_success( array( 'message' => \__( 'Redirect deleted.', 'functionalities' ) ) );
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to delete redirect.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Toggle redirect.
	 */
	public static function ajax_toggle_redirect() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$id = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';

		if ( empty( $id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Redirect ID is required.', 'functionalities' ) ) );
			return;
		}

		$new_state = self::toggle_redirect( $id );
		if ( null !== $new_state ) {
			\wp_send_json_success( array(
				'message' => \__( 'Redirect updated.', 'functionalities' ),
				'enabled' => $new_state,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to toggle redirect.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Import redirects.
	 */
	public static function ajax_import_redirects() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$json = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
		if ( empty( $json ) ) {
			\wp_send_json_error( array( 'message' => \__( 'JSON data is required.', 'functionalities' ) ) );
			return;
		}

		$data = json_decode( $json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid JSON format.', 'functionalities' ) . ' ' . json_last_error_msg() ) );
			return;
		}

		$imported = 0;
		$redirects_to_import = isset( $data['redirects'] ) ? $data['redirects'] : $data;

		if ( ! is_array( $redirects_to_import ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid redirect data format.', 'functionalities' ) ) );
			return;
		}

		foreach ( $redirects_to_import as $r ) {
			if ( isset( $r['from'], $r['to'] ) ) {
				$type = isset( $r['type'] ) ? (int) $r['type'] : 301;
				if ( self::add_redirect( $r['from'], $r['to'], $type ) ) {
					$imported++;
				}
			}
		}

		\wp_send_json_success( array(
			'message' => sprintf( \__( 'Imported %d redirect(s).', 'functionalities' ), $imported ),
			'count'   => $imported,
		));
	}

	/**
	 * AJAX: Export redirects.
	 */
	public static function ajax_export_redirects() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$redirects = self::get_redirects();
		$data = array(
			'version'   => '1.0',
			'exported'  => current_time( 'mysql' ),
			'redirects' => $redirects,
		);

		\wp_send_json_success( array(
			'json' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
		));
	}
}
