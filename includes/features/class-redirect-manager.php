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
	 * Bounded 404 log file path.
	 *
	 * @var string
	 */
	private static $log_file = '';

	/**
	 * Cached redirects data.
	 *
	 * @var array|null
	 */
	private static $redirects_cache = null;

	/**
	 * Last storage error code.
	 *
	 * @var string
	 */
	private static $storage_error = '';

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::$redirects_file = WP_CONTENT_DIR . '/functionalities/redirects.json';
		self::$log_file       = WP_CONTENT_DIR . '/functionalities/404-log.json';

		$opts = (array) \get_option( 'functionalities_redirect_manager', array( 'enabled' => false ) );

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Handle redirects early on frontend.
		\add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );
		if ( ! empty( $opts['monitor_404'] ) ) {
			\add_action( 'template_redirect', array( __CLASS__, 'maybe_log_404' ), 99 );
		}

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
		\add_action( 'wp_ajax_functionalities_redirect_404_purge', array( __CLASS__, 'ajax_purge_404_log' ) );
		\add_action( 'wp_ajax_functionalities_redirect_404_ignore', array( __CLASS__, 'ajax_ignore_404' ) );
	}

	/**
	 * Get the WP_Filesystem instance.
	 *
	 * @return \WP_Filesystem_Base|false Filesystem instance or false on failure.
	 */
	private static function get_filesystem() {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() ) {
			return false;
		}
		return $wp_filesystem;
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
				$fs = self::get_filesystem();
				if ( $fs ) {
					$fs->put_contents( $index_file, '<?php // Silence is golden.', FS_CHMOD_FILE );
				}
			}
		}
		return $dir;
	}

	/**
	 * Get all redirects.
	 *
	 * @return array List of redirects.
	 */
	public static function get_redirects(): array {
		if ( null !== self::$redirects_cache ) {
			return self::$redirects_cache;
		}

		// Use transient to avoid disk I/O on every request.
		$cached = \get_transient( 'func_redirects_json' );
		if ( ! \is_admin() && false !== $cached && is_array( $cached ) ) {
			self::$redirects_cache = $cached;
			return self::$redirects_cache;
		}

		$result = \Functionalities\Storage\Atomic_JSON_Store::read(
			self::$redirects_file,
			array(
				'version'   => '1.0',
				'modified'  => '',
				'redirects' => array(),
			)
		);

		if ( ! $result['success'] || ! isset( $result['data']['redirects'] ) || ! is_array( $result['data']['redirects'] ) ) {
			self::$storage_error   = $result['error'] ?: 'invalid_structure';
			self::$redirects_cache = array();
			return self::$redirects_cache;
		}

		self::$storage_error   = '';
		self::$redirects_cache = $result['data']['redirects'];

		// Cache for 12 hours.
		\set_transient( 'func_redirects_json', self::$redirects_cache, 12 * HOUR_IN_SECONDS );

		return self::$redirects_cache;
	}

	/**
	 * Save redirects.
	 *
	 * @param array $redirects Redirects array.
	 * @return bool True on success.
	 */
	public static function save_redirects( array $redirects ): bool {
		$dir = self::get_redirects_dir();
		if ( ! $dir ) {
			return false;
		}

		$data = array(
			'version'   => '1.0',
			'modified'  => current_time( 'mysql' ),
			'redirects' => array_values( $redirects ),
		);

		$result = \Functionalities\Storage\Atomic_JSON_Store::write( self::$redirects_file, $data );
		if ( $result['success'] ) {
			self::$redirects_cache = $redirects;
			self::$index           = null; // Invalidate index so it's rebuilt on next request.
			self::$storage_error   = '';
			\delete_transient( 'func_redirects_json' );
			return true;
		}

		self::$storage_error = $result['error'];
		return false;
	}

	/**
	 * Return the last machine-readable storage error.
	 *
	 * @return string
	 */
	public static function get_storage_error(): string {
		return self::$storage_error;
	}

	/**
	 * Atomically mutate the redirects list.
	 *
	 * @param callable $mutator Redirect-list mutator.
	 * @return array Storage operation result.
	 */
	private static function mutate_redirects( callable $mutator ): array {
		if ( ! self::get_redirects_dir() ) {
			self::$storage_error = 'directory_failed';
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => self::$storage_error,
				'exists'  => false,
			);
		}
		$default = array(
			'version'   => '1.0',
			'modified'  => '',
			'redirects' => array(),
		);

		$result = \Functionalities\Storage\Atomic_JSON_Store::update(
			self::$redirects_file,
			static function ( array $data ) use ( $mutator ) {
				$redirects = isset( $data['redirects'] ) && is_array( $data['redirects'] ) ? $data['redirects'] : array();
				$next      = call_user_func( $mutator, $redirects );

				if ( ! is_array( $next ) ) {
					return false;
				}

				$data['version']   = '1.0';
				$data['modified']  = current_time( 'mysql' );
				$data['redirects'] = array_values( $next );
				return $data;
			},
			$default
		);

		if ( $result['success'] ) {
			self::$redirects_cache = $result['data']['redirects'];
			self::$index           = null;
			self::$storage_error   = '';
			\delete_transient( 'func_redirects_json' );
		} else {
			self::$storage_error = $result['error'];
		}

		return $result;
	}

	/**
	 * Generate unique redirect ID.
	 *
	 * @return string Unique ID.
	 */
	private static function generate_id(): string {
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
		// Normalize source URL.
		$from_url = self::normalize_path( $from_url );
		if ( empty( $from_url ) ) {
			return false;
		}

		// Prevent redirect loops (source === destination).
		$to_path = self::normalize_path( $to_url );
		if ( $from_url === $to_path ) {
			return false;
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

		$added  = false;
		$result = self::mutate_redirects(
			static function ( array $redirects ) use ( $from_url, $redirect, &$added ) {
				foreach ( $redirects as $existing ) {
					if ( isset( $existing['from'] ) && $from_url === $existing['from'] ) {
						return false;
					}
				}

				$redirects[] = $redirect;
				$added       = true;
				return $redirects;
			}
		);

		if ( $result['success'] && $added ) {
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
		$updated = false;
		$result  = self::mutate_redirects(
			static function ( array $redirects ) use ( $id, $updates, &$updated ) {
				foreach ( $redirects as &$redirect ) {
					if ( ! isset( $redirect['id'] ) || $id !== $redirect['id'] ) {
						continue;
					}

					if ( isset( $updates['from'] ) ) {
						$redirect['from'] = self::normalize_path( $updates['from'] );
					}
					if ( isset( $updates['to'] ) ) {
						$redirect['to'] = esc_url_raw( $updates['to'] );
					}
					if ( isset( $updates['type'] ) ) {
						$type             = (int) $updates['type'];
						$redirect['type'] = in_array( $type, array( 301, 302, 307, 308 ), true ) ? $type : 301;
					}
					if ( isset( $updates['enabled'] ) ) {
						$redirect['enabled'] = (bool) $updates['enabled'];
					}

					$updated = $redirect;
					return $redirects;
				}

				return false;
			}
		);

		return $result['success'] ? $updated : false;
	}

	/**
	 * Delete a redirect.
	 *
	 * @param string $id Redirect ID.
	 * @return bool True on success.
	 */
	public static function delete_redirect( string $id ): bool {
		$deleted = false;
		$result  = self::mutate_redirects(
			static function ( array $redirects ) use ( $id, &$deleted ) {
				$next = array();
				foreach ( $redirects as $redirect ) {
					if ( isset( $redirect['id'] ) && $id === $redirect['id'] ) {
						$deleted = true;
						continue;
					}
					$next[] = $redirect;
				}
				return $deleted ? $next : false;
			}
		);

		return $result['success'] && $deleted;
	}

	/**
	 * Toggle redirect enabled state.
	 *
	 * @param string $id Redirect ID.
	 * @return bool|null New state or null on failure.
	 */
	public static function toggle_redirect( string $id ) {
		$new_state = null;
		$result    = self::mutate_redirects(
			static function ( array $redirects ) use ( $id, &$new_state ) {
				foreach ( $redirects as &$redirect ) {
					if ( isset( $redirect['id'] ) && $id === $redirect['id'] ) {
						$redirect['enabled'] = empty( $redirect['enabled'] );
						$new_state           = $redirect['enabled'];
						return $redirects;
					}
				}
				return false;
			}
		);

		if ( $result['success'] && null !== $new_state ) {
			return $new_state;
		}

		return null;
	}

	/**
	 * Normalize URL path.
	 *
	 * Strips scheme/host, query string, and fragment so that
	 * `/old-page?utm_source=google` correctly matches a rule for `/old-page`.
	 *
	 * @param string $path URL path.
	 * @return string Normalized path.
	 */
	private static function normalize_path( string $path ): string {
		// Remove domain if present.
		$path = preg_replace( '#^https?://[^/]+#i', '', $path );

		// Strip query string and fragment.
		$path = strtok( $path, '?#' );

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
	 * Indexed exact-match lookup and wildcard prefixes.
	 *
	 * @var array|null
	 */
	private static $index = null;

	/**
	 * Build a hash-map index for O(1) exact matches.
	 *
	 * Wildcards are kept in a separate list sorted longest-prefix-first
	 * so the most specific rule wins.
	 *
	 * @return void
	 */
	private static function build_index(): void {
		if ( null !== self::$index ) {
			return;
		}

		self::$index = array(
			'exact'    => array(),
			'wildcard' => array(),
		);

		$redirects = self::get_redirects();
		foreach ( $redirects as $redirect ) {
			if ( empty( $redirect['enabled'] ) ) {
				continue;
			}

			$from = $redirect['from'];

			if ( substr( $from, -1 ) === '*' ) {
				self::$index['wildcard'][] = $redirect;
			} else {
				self::$index['exact'][ $from ] = $redirect;
			}
		}

		// Sort wildcards by prefix length descending (most specific first).
		usort(
			self::$index['wildcard'],
			function ( $a, $b ) {
				return strlen( $b['from'] ) - strlen( $a['from'] );
			}
		);
	}

	/**
	 * Handle redirects on frontend.
	 *
	 * Uses an indexed lookup for O(1) exact matches and longest-prefix-first
	 * ordering for wildcard rules. Query strings are preserved through to the
	 * destination URL.
	 *
	 * @return void
	 */
	public static function handle_redirect(): void {
		// Don't redirect in admin.
		if ( is_admin() ) {
			return;
		}

		$redirects = self::get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by normalize_path.
		$raw_uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_path = self::normalize_path( $raw_uri );

		// Preserve the original query string so it can be appended to the destination.
		$query_string = '';
		$qpos         = strpos( $raw_uri, '?' );
		if ( false !== $qpos ) {
			$query_string = substr( $raw_uri, $qpos );
		}

		self::build_index();

		// O(1) exact match.
		if ( isset( self::$index['exact'][ $current_path ] ) ) {
			self::do_redirect( self::$index['exact'][ $current_path ], $query_string );
			return;
		}

		// Wildcard match (longest prefix first).
		foreach ( self::$index['wildcard'] as $redirect ) {
			$prefix = rtrim( $redirect['from'], '*' );
			if ( strpos( $current_path, $prefix ) === 0 ) {
				self::do_redirect( $redirect, $query_string );
				return;
			}
		}
	}

	/**
	 * Perform the redirect.
	 *
	 * Detects redirect loops (source === destination) and passes the original
	 * query string through to the destination URL when it has none of its own.
	 *
	 * @param array  $redirect     Redirect data.
	 * @param string $query_string Original query string including leading '?', or empty.
	 * @return void
	 */
	private static function do_redirect( array $redirect, string $query_string = '' ): void {
		$destination = $redirect['to'];

		// Append original query string if the destination has none.
		if ( '' !== $query_string && false === strpos( $destination, '?' ) ) {
			$destination .= $query_string;
		}

		// Loop detection: if destination resolves to the same path, bail.
		$dest_path = self::normalize_path( $destination );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by normalize_path.
		$current = self::normalize_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		if ( $dest_path === $current ) {
			return;
		}

		// Defer hit counting to shutdown to avoid blocking the redirect.
		self::defer_hit_increment( $redirect['id'] );

		$status = isset( $redirect['type'] ) ? (int) $redirect['type'] : 301;

		// Note: Using wp_redirect instead of wp_safe_redirect because destination
		// URLs may be external domains, which is valid for redirects.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		\wp_redirect( $destination, $status );
		exit;
	}

	/**
	 * Schedule a hit counter increment on shutdown.
	 *
	 * Avoids a full JSON file read/write in the critical redirect path.
	 * The actual disk write happens after the response is sent.
	 *
	 * @param string $id Redirect ID.
	 * @return void
	 */
	private static function defer_hit_increment( string $id ): void {
		\register_shutdown_function(
			function () use ( $id ) {
				self::increment_hits( $id );
			}
		);
	}

	/**
	 * Increment redirect hit counter.
	 *
	 * @param string $id Redirect ID.
	 * @return void
	 */
	private static function increment_hits( string $id ): void {
		self::mutate_redirects(
			static function ( array $redirects ) use ( $id ) {
				foreach ( $redirects as &$redirect ) {
					if ( isset( $redirect['id'] ) && $id === $redirect['id'] ) {
						$redirect['hits'] = (int) ( $redirect['hits'] ?? 0 ) + 1;
						return $redirects;
					}
				}
				return false;
			}
		);
	}

	/**
	 * Get redirect statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_stats(): array {
		$redirects = self::get_redirects();

		$total   = count( $redirects );
		$enabled = 0;
		$hits    = 0;

		foreach ( $redirects as $r ) {
			if ( ! empty( $r['enabled'] ) ) {
				++$enabled;
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

	/**
	 * Parse a CSV export from common redirect plugins.
	 *
	 * @param string $csv     CSV document.
	 * @param array  $mapping Optional source, target, and type header mapping.
	 * @return array Parsed rows and errors.
	 */
	public static function parse_csv( string $csv, array $mapping = array() ): array {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
		if ( ! $lines || count( $lines ) < 2 ) {
			return array(
				'rows'   => array(),
				'errors' => array( 'csv_requires_header_and_row' ),
			);
		}

		$headers = array_map( 'sanitize_key', str_getcsv( array_shift( $lines ), ',', '"', '\\' ) );
		$aliases = array(
			'from' => array( 'source', 'from', 'source_url', 'old_url', 'request', 'url' ),
			'to'   => array( 'target', 'to', 'target_url', 'new_url', 'destination', 'redirect_to' ),
			'type' => array( 'type', 'status', 'status_code', 'code', 'action_code' ),
		);
		$indexes = array();
		foreach ( $aliases as $field => $names ) {
			$requested  = isset( $mapping[ $field ] ) ? sanitize_key( $mapping[ $field ] ) : '';
			$candidates = $requested ? array( $requested ) : $names;
			foreach ( $candidates as $candidate ) {
				$index = array_search( $candidate, $headers, true );
				if ( false !== $index ) {
					$indexes[ $field ] = $index;
					break;
				}
			}
		}

		if ( ! isset( $indexes['from'], $indexes['to'] ) ) {
			return array(
				'rows'    => array(),
				'errors'  => array( 'missing_source_or_target_column' ),
				'headers' => $headers,
			);
		}

		$rows = array();
		foreach ( $lines as $line_number => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$columns = str_getcsv( $line, ',', '"', '\\' );
			$rows[]  = array(
				'from' => $columns[ $indexes['from'] ] ?? '',
				'to'   => $columns[ $indexes['to'] ] ?? '',
				'type' => isset( $indexes['type'], $columns[ $indexes['type'] ] ) ? (int) $columns[ $indexes['type'] ] : 301,
				'line' => $line_number + 2,
			);
		}

		return array(
			'rows'    => $rows,
			'errors'  => array(),
			'headers' => $headers,
		);
	}

	/**
	 * Validate and normalize an all-or-nothing redirect import.
	 *
	 * @param array $rows     Candidate redirect rows.
	 * @param array $existing Existing redirects, or current redirects when omitted.
	 * @return array Import preview.
	 */
	public static function prepare_import( array $rows, ?array $existing = null ): array {
		$existing = null === $existing ? self::get_redirects() : $existing;
		$sources  = array();
		$targets  = array();
		$errors   = array();
		$warnings = array();
		$clean    = array();

		foreach ( $existing as $redirect ) {
			if ( isset( $redirect['from'] ) ) {
				$sources[ $redirect['from'] ] = true;
			}
		}

		foreach ( $rows as $index => $row ) {
			$line = isset( $row['line'] ) ? (int) $row['line'] : $index + 1;
			$from = self::normalize_path( (string) ( $row['from'] ?? '' ) );
			$to   = esc_url_raw( trim( (string) ( $row['to'] ?? '' ) ) );
			$type = (int) ( $row['type'] ?? 301 );

			if ( '/' === $from || '' === $to ) {
				$errors[] = array(
					'line' => $line,
					'code' => 'missing_source_or_target',
				);
				continue;
			}
			if ( false !== strpos( rtrim( $from, '*' ), '*' ) ) {
				$errors[] = array(
					'line' => $line,
					'code' => 'wildcard_must_be_last',
				);
				continue;
			}
			if ( isset( $sources[ $from ] ) ) {
				$errors[] = array(
					'line'   => $line,
					'code'   => 'duplicate_source',
					'source' => $from,
				);
				continue;
			}
			if ( self::normalize_path( $to ) === $from ) {
				$errors[] = array(
					'line'   => $line,
					'code'   => 'redirect_loop',
					'source' => $from,
				);
				continue;
			}

			$sources[ $from ] = true;
			$targets[ $from ] = self::normalize_path( $to );
			$clean[]          = array(
				'id'      => self::generate_id(),
				'from'    => $from,
				'to'      => $to,
				'type'    => in_array( $type, array( 301, 302, 307, 308 ), true ) ? $type : 301,
				'enabled' => true,
				'hits'    => 0,
				'created' => current_time( 'mysql' ),
			);
		}

		foreach ( $targets as $from => $target ) {
			if ( isset( $sources[ $target ] ) ) {
				$warnings[] = array(
					'code'   => 'redirect_chain',
					'source' => $from,
					'via'    => $target,
				);
			}
		}

		return array(
			'success'  => empty( $errors ),
			'rows'     => $clean,
			'errors'   => $errors,
			'warnings' => $warnings,
			'count'    => count( $clean ),
		);
	}

	/**
	 * Atomically append a validated import.
	 *
	 * @param array $rows Normalized rows from prepare_import().
	 * @return bool
	 */
	private static function apply_import( array $rows ): bool {
		$result = self::mutate_redirects(
			static function ( array $redirects ) use ( $rows ) {
				$existing = array();
				foreach ( $redirects as $redirect ) {
					$existing[ $redirect['from'] ] = true;
				}
				foreach ( $rows as $row ) {
					if ( isset( $existing[ $row['from'] ] ) ) {
						return false;
					}
					$existing[ $row['from'] ] = true;
					$redirects[]              = $row;
				}
				return $redirects;
			}
		);
		return $result['success'];
	}

	/**
	 * Record a privacy-conscious 404 aggregate when monitoring is enabled.
	 *
	 * @return void
	 */
	public static function maybe_log_404(): void {
		if ( ! \is_404() || \is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$options = (array) \get_option( 'functionalities_redirect_manager', array() );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only for bot classification.
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( '' === $agent || preg_match( '/bot|crawl|spider|slurp|preview|monitor/i', $agent ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalized immediately.
		$path = self::normalize_path( isset( $_SERVER['REQUEST_URI'] ) ? \wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		if ( in_array( $path, (array) ( $options['monitor_ignored_paths'] ?? array() ), true ) ) {
			return;
		}
		if ( self::is_excluded_404_path( $path, (string) ( $options['monitor_exclusions'] ?? '' ) ) ) {
			return;
		}

		$origin = '';
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the sanitized host is retained.
			$origin = sanitize_text_field( (string) wp_parse_url( \wp_unslash( $_SERVER['HTTP_REFERER'] ), PHP_URL_HOST ) );
		}

		$cap       = max( 25, min( 2000, (int) ( $options['monitor_cap'] ?? 500 ) ) );
		$retention = max( 1, min( 365, (int) ( $options['monitor_retention_days'] ?? 30 ) ) );
		$cutoff    = time() - ( $retention * DAY_IN_SECONDS );
		\Functionalities\Storage\Atomic_JSON_Store::update(
			self::$log_file,
			static function ( array $data ) use ( $path, $origin, $cap, $cutoff ) {
				$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
				$items = array_filter(
					$items,
					static function ( array $item ) use ( $cutoff ) {
						return (int) ( $item['last_seen'] ?? 0 ) >= $cutoff;
					}
				);
				if ( isset( $items[ $path ] ) ) {
					$items[ $path ]['count']     = (int) $items[ $path ]['count'] + 1;
					$items[ $path ]['last_seen'] = time();
					if ( $origin ) {
						$items[ $path ]['referrer_origin'] = $origin;
					}
				} else {
					$items[ $path ] = array(
						'path'            => $path,
						'count'           => 1,
						'last_seen'       => time(),
						'referrer_origin' => $origin,
					);
				}
				uasort(
					$items,
					static function ( array $a, array $b ) {
						return (int) $b['last_seen'] <=> (int) $a['last_seen'];
					}
				);
				$data['items'] = array_slice( $items, 0, $cap, true );
				return $data;
			},
			array(
				'version' => 1,
				'items'   => array(),
			)
		);
	}

	/**
	 * Read the current bounded 404 log.
	 *
	 * @return array
	 */
	public static function get_404_log(): array {
		$result = \Functionalities\Storage\Atomic_JSON_Store::read(
			self::$log_file,
			array(
				'version' => 1,
				'items'   => array(),
			)
		);
		return $result['success'] && isset( $result['data']['items'] ) ? array_values( $result['data']['items'] ) : array();
	}

	/**
	 * Check built-in and configured 404 exclusions.
	 *
	 * @param string $path       Normalized request path.
	 * @param string $additional Newline-separated prefixes.
	 * @return bool
	 */
	private static function is_excluded_404_path( string $path, string $additional ): bool {
		$prefixes = array( '/wp-admin', '/wp-json', '/xmlrpc.php', '/wp-login.php', '/favicon.ico', '/robots.txt' );
		foreach ( preg_split( '/\r\n|\r|\n/', $additional ) as $prefix ) {
			if ( '' !== trim( $prefix ) ) {
				$prefixes[] = self::normalize_path( trim( $prefix ) );
			}
		}
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	// AJAX Handlers.

	/**
	 * Verify AJAX request.
	 *
	 * @return bool True if valid.
	 */
	private static function verify_ajax(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce doesn't need sanitization.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'functionalities_redirect_manager' ) ) {
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
	public static function ajax_add_redirect(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$to = isset( $_POST['to'] ) ? esc_url_raw( wp_unslash( $_POST['to'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$type = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;

		if ( empty( $from ) || empty( $to ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Source and destination URLs are required.', 'functionalities' ) ) );
			return;
		}

		$redirect = self::add_redirect( $from, $to, $type );
		if ( $redirect ) {
			\wp_send_json_success(
				array(
					'message'  => \__( 'Redirect added.', 'functionalities' ),
					'redirect' => $redirect,
				)
			);
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to add redirect. URL may already exist.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Update redirect.
	 */
	public static function ajax_update_redirect(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$id      = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';
		$updates = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		if ( isset( $_POST['from'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['from'] = sanitize_text_field( wp_unslash( $_POST['from'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		if ( isset( $_POST['to'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['to'] = esc_url_raw( wp_unslash( $_POST['to'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		if ( isset( $_POST['type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['type'] = (int) $_POST['type'];
		}

		if ( empty( $id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Redirect ID is required.', 'functionalities' ) ) );
			return;
		}

		$redirect = self::update_redirect( $id, $updates );
		if ( $redirect ) {
			\wp_send_json_success(
				array(
					'message'  => \__( 'Redirect updated.', 'functionalities' ),
					'redirect' => $redirect,
				)
			);
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to update redirect.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Delete redirect.
	 */
	public static function ajax_delete_redirect(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
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
	public static function ajax_toggle_redirect(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$id = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';

		if ( empty( $id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Redirect ID is required.', 'functionalities' ) ) );
			return;
		}

		$new_state = self::toggle_redirect( $id );
		if ( null !== $new_state ) {
			\wp_send_json_success(
				array(
					'message' => \__( 'Redirect updated.', 'functionalities' ),
					'enabled' => $new_state,
				)
			);
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to toggle redirect.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Import redirects.
	 */
	public static function ajax_import_redirects(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_ajax(); document is parsed and validated below.
		$document = isset( $_POST['document'] ) ? wp_unslash( $_POST['document'] ) : ( isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'json';
		if ( empty( $document ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Import data is required.', 'functionalities' ) ) );
			return;
		}

		if ( 'csv' === $format ) {
			$parsed = self::parse_csv( $document );
			if ( ! empty( $parsed['errors'] ) ) {
				\wp_send_json_error( $parsed );
			}
			$rows = $parsed['rows'];
		} else {
			$data = json_decode( $document, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				\wp_send_json_error( array( 'message' => \__( 'Invalid JSON format.', 'functionalities' ) . ' ' . json_last_error_msg() ) );
			}
			$rows = isset( $data['redirects'] ) ? $data['redirects'] : $data;
		}

		if ( ! is_array( $rows ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid redirect data format.', 'functionalities' ) ) );
		}
		$preview = self::prepare_import( $rows );
		if ( ! $preview['success'] ) {
			\wp_send_json_error( $preview );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		if ( ! empty( $_POST['dry_run'] ) ) {
			\wp_send_json_success( $preview );
		}
		if ( ! self::apply_import( $preview['rows'] ) ) {
			\wp_send_json_error( array( 'message' => \__( 'The redirect set changed before import. Preview it again.', 'functionalities' ) ) );
		}
		\wp_send_json_success(
			array(
				/* translators: %d: Number of redirects imported. */
				'message'  => sprintf( \__( 'Imported %d redirect(s).', 'functionalities' ), $preview['count'] ),
				'count'    => $preview['count'],
				'warnings' => $preview['warnings'],
			)
		);
	}

	/**
	 * AJAX: Export redirects.
	 */
	public static function ajax_export_redirects(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		$redirects = self::get_redirects();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'json';
		if ( 'csv' === $format ) {
			$lines = array( 'source,target,type,enabled,hits' );
			foreach ( $redirects as $redirect ) {
				$lines[] = implode(
					',',
					array(
						self::csv_cell( $redirect['from'] ?? '' ),
						self::csv_cell( $redirect['to'] ?? '' ),
						(int) ( $redirect['type'] ?? 301 ),
						empty( $redirect['enabled'] ) ? 0 : 1,
						(int) ( $redirect['hits'] ?? 0 ),
					)
				);
			}
			\wp_send_json_success(
				array(
					'content'  => implode( "\r\n", $lines ) . "\r\n",
					'filename' => 'functionalities-redirects.csv',
				)
			);
		}
		$data = array(
			'version'   => '1.0',
			'exported'  => current_time( 'mysql' ),
			'redirects' => $redirects,
		);

		\wp_send_json_success(
			array(
				'json'     => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'content'  => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'filename' => 'functionalities-redirects.json',
			)
		);
	}

	/**
	 * Purge the bounded 404 log.
	 *
	 * @return void
	 */
	public static function ajax_purge_404_log(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}
		$result = \Functionalities\Storage\Atomic_JSON_Store::write(
			self::$log_file,
			array(
				'version' => 1,
				'items'   => array(),
			)
		);
		$result['success'] ? \wp_send_json_success() : \wp_send_json_error( array( 'message' => $result['error'] ) );
	}

	/**
	 * Remove one path from the 404 log.
	 *
	 * @return void
	 */
	public static function ajax_ignore_404(): void {
		if ( ! self::verify_ajax() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$path                             = isset( $_POST['path'] ) ? self::normalize_path( sanitize_text_field( wp_unslash( $_POST['path'] ) ) ) : '';
		$options                          = (array) \get_option( 'functionalities_redirect_manager', array() );
		$ignored                          = (array) ( $options['monitor_ignored_paths'] ?? array() );
		$ignored[]                        = $path;
		$options['monitor_ignored_paths'] = array_slice( array_values( array_unique( array_filter( $ignored ) ) ), -500 );
		\update_option( 'functionalities_redirect_manager', $options );
		$result = \Functionalities\Storage\Atomic_JSON_Store::update(
			self::$log_file,
			static function ( array $data ) use ( $path ) {
				unset( $data['items'][ $path ] );
				return $data;
			},
			array(
				'version' => 1,
				'items'   => array(),
			)
		);
		$result['success'] ? \wp_send_json_success() : \wp_send_json_error( array( 'message' => $result['error'] ) );
	}

	/**
	 * Escape one RFC 4180-compatible CSV cell.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private static function csv_cell( $value ): string {
		return '"' . str_replace( '"', '""', (string) $value ) . '"';
	}
}
