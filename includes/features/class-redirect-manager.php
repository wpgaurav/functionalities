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
	 * Option holding buffered hit counts and 404 aggregates.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	const BUFFER_OPTION = 'functionalities_redirect_hit_buffer';

	/**
	 * Cron hook that flushes the write buffer.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	const FLUSH_HOOK = 'functionalities_redirect_flush_buffer';

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
	 * Site and data-directory identity for request-local caches.
	 *
	 * @var string
	 */
	private static $storage_context = '';

	/** Resolve the active site's files and reset caches after switch_to_blog(). */
	private static function ensure_storage(): bool {
		$context = ( \function_exists( 'get_current_blog_id' ) ? \get_current_blog_id() : 1 ) . ':' . \Functionalities\Storage\Data_Directory::base();
		if ( self::$storage_context !== $context ) {
			self::$redirects_cache = null;
			self::$index           = null;
			self::$storage_error   = '';
			self::$storage_context = $context;
		}
		self::$redirects_file = \Functionalities\Storage\Data_Directory::file( 'redirects.json' );
		self::$log_file       = \Functionalities\Storage\Data_Directory::file( '404-log.json' );
		if ( '' === self::$redirects_file || '' === self::$log_file ) {
			self::$storage_error = implode( ', ', \Functionalities\Storage\Data_Directory::get_errors() );
			return false;
		}
		return true;
	}

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::ensure_storage();
		\add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush_buffer' ) );

		$opts = (array) \get_option( 'functionalities_redirect_manager', array( 'enabled' => false ) );

		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'redirect-manager' ) ) {
			return;
		}

		// Handle redirects before the main query runs. template_redirect fires
		// after WordPress has already queried the database for a page it is about
		// to throw away.
		\add_action( 'parse_request', array( __CLASS__, 'handle_redirect' ), 1 );
		if ( ! empty( $opts['monitor_404'] ) ) {
			\add_action( 'template_redirect', array( __CLASS__, 'maybe_log_404' ), 99 );
		}

		// Buffered hits and 404 aggregates reach disk in batches.
		if ( ! \wp_next_scheduled( self::FLUSH_HOOK ) ) {
			\wp_schedule_event( time() + 300, 'hourly', self::FLUSH_HOOK );
		}

		// Only register admin handlers in admin.
		if ( ! \is_admin() ) {
			return;
		}

		// Show current numbers on the management screen.
		\add_action( 'load-toplevel_page_functionalities', array( __CLASS__, 'flush_buffer' ) );
		\add_action( 'load-functionalities_page_functionalities-redirect-manager', array( __CLASS__, 'flush_buffer' ) );

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
		if ( ! self::ensure_storage() ) {
			return false;
		}
		$dir = dirname( self::$redirects_file );
		if ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		\Functionalities\Storage\Data_Directory::harden( $dir );

		return $dir;
	}

	/**
	 * Get all redirects.
	 *
	 * @return array List of redirects.
	 */
	public static function get_redirects(): array {
		if ( ! self::ensure_storage() ) {
			return array();
		}
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
		self::ensure_storage();
		return self::$storage_error;
	}

	/**
	 * Atomically mutate the redirects list.
	 *
	 * @param callable $mutator Redirect-list mutator.
	 * @return array Storage operation result.
	 */
	private static function mutate_redirects( callable $mutator, string $batch_id = '', array $pending_ids = array() ): array {
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
			static function ( array $data ) use ( $mutator, $batch_id, $pending_ids ) {
				if ( '' !== $batch_id && isset( $data['applied_batches'][ $batch_id ] ) ) {
					return $data;
				}
				if ( ! isset( $data['redirects'] ) || ! is_array( $data['redirects'] ) ) {
					return false;
				}
				$redirects = $data['redirects'];
				$next      = call_user_func( $mutator, $redirects );

				if ( ! is_array( $next ) ) {
					return false;
				}

				$data['version']   = '1.0';
				$data['modified']  = current_time( 'mysql' );
				$data['redirects'] = array_values( $next );
				if ( '' !== $batch_id ) {
					$data['applied_batches']              = array_intersect_key( $data['applied_batches'] ?? array(), array_fill_keys( $pending_ids, true ) );
					$data['applied_batches'][ $batch_id ] = true;
				}
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
		if ( '' === trim( $from_url ) || '' === trim( esc_url_raw( $to_url ) ) ) {
			return false;
		}
		// Normalize source URL.
		$from_url = self::normalize_path( $from_url );
		if ( empty( $from_url ) ) {
			return false;
		}

		// Prevent redirect loops (source === destination).
		$to_path = self::local_target_path( $to_url );
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

				$preview = self::prepare_import( array( $redirect ), $redirects );
				if ( ! $preview['success'] ) {
					return false;
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
		if ( ( isset( $updates['from'] ) && '' === trim( $updates['from'] ) ) || ( isset( $updates['to'] ) && '' === trim( esc_url_raw( $updates['to'] ) ) ) ) {
			return false;
		}
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

					$others  = array_values(
						array_filter(
							$redirects,
							static function ( $item ) use ( $id ) {
								return ( $item['id'] ?? '' ) !== $id;
							}
						)
					);
					$preview = self::prepare_import( array( $redirect ), $others );
					if ( ! $preview['success'] ) {
						return false;
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
	public static function toggle_redirect( string $id, ?bool $enabled = null ) {
		$new_state = null;
		$result    = self::mutate_redirects(
			static function ( array $redirects ) use ( $id, $enabled, &$new_state ) {
				foreach ( $redirects as &$redirect ) {
					if ( isset( $redirect['id'] ) && $id === $redirect['id'] ) {
						$redirect['enabled'] = null === $enabled ? empty( $redirect['enabled'] ) : $enabled;
						if ( ! empty( self::cyclic_sources( $redirects ) ) ) {
							return false;
						}
						$new_state = $redirect['enabled'];
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
		// Parse both absolute and protocol-relative HTTP destinations.
		if ( preg_match( '#^(?:https?:)?//#i', $path ) ) {
			$path = (string) \wp_parse_url( $path, PHP_URL_PATH );
		}

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

	/** A target on a different host/port cannot loop through this site's rules. */
	private static function local_target_path( string $target ): ?string {
		$host = \wp_parse_url( $target, PHP_URL_HOST );
		if ( $host ) {
			$home_host = \wp_parse_url( \home_url( '/' ), PHP_URL_HOST );
			if ( strtolower( $host ) !== strtolower( (string) $home_host ) || \wp_parse_url( $target, PHP_URL_PORT ) !== \wp_parse_url( \home_url( '/' ), PHP_URL_PORT ) ) {
				return null;
			}
		}
		return self::normalize_path( $target );
	}

	/** Resolve graph edges with the same exact/longest-wildcard order as routing. */
	private static function cyclic_sources( array $rules ): array {
		$active = array();
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && isset( $rule['from'], $rule['to'] ) && ( ! isset( $rule['enabled'] ) || $rule['enabled'] ) ) {
				$active[ $rule['from'] ] = $rule;
			}
		}
		$edges = array();
		foreach ( $active as $source => $rule ) {
			$target = self::local_target_path( $rule['to'] );
			if ( null === $target ) {
				continue;
			}
			if ( isset( $active[ $target ] ) ) {
				$edges[ $source ] = $target;
				continue;
			}
			$best = '';
			foreach ( $active as $candidate => $unused ) {
				if ( '*' === substr( $candidate, -1 ) && 0 === strpos( $target, rtrim( $candidate, '*' ) ) && strlen( $candidate ) > strlen( $best ) ) {
					$best = $candidate;
				}
			}
			if ( '' !== $best ) {
				$edges[ $source ] = $best;
			}
		}
		$cycles  = array();
		$checked = array();
		foreach ( $edges as $start => $unused ) {
			$seen = array();
			$node = $start;
			while ( isset( $edges[ $node ] ) && ! isset( $checked[ $node ] ) ) {
				if ( isset( $seen[ $node ] ) ) {
					$cycles[] = $node;
					break;
				}
				$seen[ $node ] = true;
				$node          = $edges[ $node ];
			}
			$checked += $seen;
		}

		return array_unique( $cycles );
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
		// Don't redirect in admin, on cron, or on API routes.
		if ( is_admin() || \wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by normalize_path.
		$raw_uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_path = self::normalize_path( $raw_uri );

		// Never redirect WordPress's own entry points. This matters now that the
		// check runs at parse_request, which also serves REST.
		foreach ( array( '/wp-admin', '/wp-json', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php' ) as $reserved ) {
			if ( 0 === strpos( $current_path, $reserved ) ) {
				return;
			}
		}

		$redirects = self::get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

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
		$dest_path = self::local_target_path( $destination );
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
	 * Buffer a hit for the current redirect.
	 *
	 * Until 1.6.0 every redirect rewrote the entire redirects file under an
	 * exclusive lock at shutdown, so a crawler sweeping dead URLs serialised
	 * every request on that lock. Hits now accumulate in a guarded file under an independent lock
	 * and are delivered to the redirect map in durable, retryable batches.
	 *
	 * @param string $id Redirect ID.
	 * @return void
	 */
	private static function defer_hit_increment( string $id ): void {
		$blog = \get_current_blog_id();
		\register_shutdown_function(
			function () use ( $id, $blog ) {
				$switched = $blog !== \get_current_blog_id();
				if ( $switched ) {
					\switch_to_blog( $blog );
				}
				try {
					self::buffer_event( 'hits', $id );
				} finally {
					if ( $switched ) {
						\restore_current_blog();
					}
				}
			}
		);
	}

	/**
	 * Add one event to the write buffer and flush when it is worth the disk I/O.
	 *
	 * @since 1.6.0
	 *
	 * @param string $bucket Buffer bucket: hits or not_found.
	 * @param string $key    Redirect ID or request path.
	 * @param array  $meta   Extra data for the not_found bucket.
	 * @return void
	 */
	private static function buffer_event( string $bucket, string $key, array $meta = array() ): void {
		if ( '' === $key || ! in_array( $bucket, array( 'hits', 'not_found' ), true ) || ! self::ensure_storage() ) {
			return;
		}
		$file   = \Functionalities\Storage\Data_Directory::file( 'redirect-buffer.json' );
		$result = \Functionalities\Storage\Atomic_JSON_Store::update(
			$file,
			static function ( array $buffer ) use ( $bucket, $key, $meta ) {
				if ( 'hits' === $bucket ) {
					$buffer['hits'][ $key ] = (int) ( $buffer['hits'][ $key ] ?? 0 ) + 1;
				} else {
					$existing                    = $buffer['not_found'][ $key ] ?? array();
					$buffer['not_found'][ $key ] = array(
						'count'     => (int) ( $existing['count'] ?? 0 ) + 1,
						'last_seen' => time(),
						'origin'    => $meta['origin'] ?? ( $existing['origin'] ?? '' ),
					);
				}
				$buffer['started'] = $buffer['started'] ?? time();
				return $buffer;
			},
			array()
		);
		if ( ! $result['success'] ) {
			self::$storage_error = $result['error'];
			return;
		}
		$buffer    = $result['data'];
		$pending   = count( $buffer['hits'] ?? array() ) + count( $buffer['not_found'] ?? array() );
		$threshold = max( 1, (int) \apply_filters( 'functionalities_redirect_buffer_threshold', 25 ) );
		if ( $pending >= $threshold || time() - (int) $buffer['started'] > 5 * MINUTE_IN_SECONDS ) {
			self::flush_buffer();
		}
	}

	/**
	 * Deliver durable batches; acknowledge each bucket only after its file write.
	 * Destination batch markers make retries safe even if a process stops between
	 * the destination write and acknowledgement. Producers keep appending under
	 * the separate buffer lock while one flusher holds the delivery lock.
	 */
	public static function flush_buffer(): void {
		if ( ! self::ensure_storage() ) {
			return;
		}
		$file = \Functionalities\Storage\Data_Directory::file( 'redirect-buffer.json' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$lock = fopen( dirname( $file ) . '/.redirect-buffer-flush.lock', 'c+' );
		if ( false === $lock ) {
			self::$storage_error = 'buffer_lock_failed';
			return;
		}
		try {
			if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
				return;
			}
			// Retain the earlier option until its contents are durably queued.
			$legacy    = (array) \get_option( self::BUFFER_OPTION, array() );
			$legacy_id = empty( $legacy ) ? '' : 'legacy-' . hash( 'sha256', (string) wp_json_encode( $legacy ) );
			$result    = \Functionalities\Storage\Atomic_JSON_Store::update(
				$file,
				static function ( array $buffer ) use ( $legacy, $legacy_id ) {
					if ( '' !== $legacy_id && ( $buffer['legacy_snapshot'] ?? '' ) !== $legacy_id ) {
						$buffer['pending'][ $legacy_id ] = array(
							'hits'      => $legacy['hits'] ?? array(),
							'not_found' => $legacy['not_found'] ?? array(),
						);
						$buffer['legacy_snapshot']       = $legacy_id;
					}
					if ( ! empty( $buffer['hits'] ) || ! empty( $buffer['not_found'] ) ) {
						$id                       = bin2hex( random_bytes( 16 ) );
						$buffer['pending'][ $id ] = array(
							'hits'      => $buffer['hits'] ?? array(),
							'not_found' => $buffer['not_found'] ?? array(),
						);
						unset( $buffer['hits'], $buffer['not_found'], $buffer['started'] );
					}
					return $buffer;
				},
				array()
			);
			if ( ! $result['success'] ) {
				self::$storage_error = $result['error'];
				return;
			}
			if ( '' !== $legacy_id ) {
				\delete_option( self::BUFFER_OPTION );
			}
			$pending_ids = array_keys( $result['data']['pending'] ?? array() );
			foreach ( $result['data']['pending'] ?? array() as $id => $batch ) {
				$acknowledged = array();
				if ( ! empty( $batch['hits'] ) ) {
					$hits    = $batch['hits'];
					$written = self::mutate_redirects(
						static function ( array $redirects ) use ( $hits ) {
							foreach ( $redirects as &$redirect ) {
								$key = $redirect['id'] ?? '';
								if ( isset( $hits[ $key ] ) ) {
									$redirect['hits'] = (int) ( $redirect['hits'] ?? 0 ) + (int) $hits[ $key ];
								}
							}
							unset( $redirect );
							return $redirects;
						},
						$id,
						$pending_ids
					);
					if ( $written['success'] ) {
						$acknowledged[] = 'hits';
					}
				}
				if ( ! empty( $batch['not_found'] ) && self::write_not_found( $batch['not_found'], $id, $pending_ids ) ) {
					$acknowledged[] = 'not_found';
				}
				$ack = \Functionalities\Storage\Atomic_JSON_Store::update(
					$file,
					static function ( array $buffer ) use ( $id, $acknowledged ) {
						foreach ( $acknowledged as $bucket ) {
							unset( $buffer['pending'][ $id ][ $bucket ] );
						}
						if ( empty( $buffer['pending'][ $id ]['hits'] ) && empty( $buffer['pending'][ $id ]['not_found'] ) ) {
							unset( $buffer['pending'][ $id ] );
						}
						return $buffer;
					},
					array()
				);
				if ( ! $ack['success'] ) {
					self::$storage_error = $ack['error'];
					return;
				}
			}
		} finally {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $lock );
		}
	}

	/** Return active and unacknowledged hit counts. */
	public static function get_buffered_hits(): array {
		if ( ! self::ensure_storage() ) {
			return array();
		}
		$result = \Functionalities\Storage\Atomic_JSON_Store::read( \Functionalities\Storage\Data_Directory::file( 'redirect-buffer.json' ) );
		if ( ! $result['success'] ) {
			self::$storage_error = $result['error'];
			return array();
		}
		$hits        = $result['data']['hits'] ?? array();
		$destination = \Functionalities\Storage\Atomic_JSON_Store::read( self::$redirects_file );
		$applied     = $destination['success'] ? ( $destination['data']['applied_batches'] ?? array() ) : array();
		foreach ( $result['data']['pending'] ?? array() as $batch_id => $batch ) {
			if ( isset( $applied[ $batch_id ] ) ) {
				continue;
			}
			foreach ( $batch['hits'] ?? array() as $id => $count ) {
				$hits[ $id ] = (int) ( $hits[ $id ] ?? 0 ) + (int) $count;
			}
		}
		$legacy    = (array) \get_option( self::BUFFER_OPTION, array() );
		$legacy_id = empty( $legacy ) ? '' : 'legacy-' . hash( 'sha256', (string) wp_json_encode( $legacy ) );
		foreach ( ( $result['data']['legacy_snapshot'] ?? '' ) === $legacy_id ? array() : ( $legacy['hits'] ?? array() ) as $id => $count ) {
			$hits[ $id ] = (int) ( $hits[ $id ] ?? 0 ) + (int) $count;
		}
		return $hits;
	}

	/**
	 * Get redirect statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_stats(): array {
		$redirects = self::get_redirects();

		$total    = count( $redirects );
		$enabled  = 0;
		$hits     = 0;
		$buffered = self::get_buffered_hits();

		foreach ( $redirects as $r ) {
			if ( ! empty( $r['enabled'] ) ) {
				++$enabled;
			}
			$hits += (int) ( $r['hits'] ?? 0 );
			$id    = $r['id'] ?? '';
			if ( '' !== $id && isset( $buffered[ $id ] ) ) {
				$hits += (int) $buffered[ $id ];
			}
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$stream = fopen( 'php://temp', 'w+' );
		if ( false === $stream ) {
			return array(
				'rows'   => array(),
				'errors' => array( 'csv_read_failed' ),
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV records are parsed from an in-memory stream.
		fwrite( $stream, preg_replace( '/^\xEF\xBB\xBF/', '', trim( $csv ) ) );
		rewind( $stream );
		$header = fgetcsv( $stream, 0, ',', '"', '' );
		if ( ! $header ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the in-memory CSV stream.
			fclose( $stream );
			return array(
				'rows'   => array(),
				'errors' => array( 'csv_requires_header_and_row' ),
			);
		}

		$headers = array_map( 'sanitize_key', $header );
		$aliases = array(
			'from'    => array( 'source', 'from', 'source_url', 'old_url', 'request', 'url' ),
			'to'      => array( 'target', 'to', 'target_url', 'new_url', 'destination', 'redirect_to' ),
			'type'    => array( 'type', 'status', 'status_code', 'code', 'action_code' ),
			'enabled' => array( 'enabled' ),
			'hits'    => array( 'hits' ),
			'created' => array( 'created' ),
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
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the in-memory CSV stream.
			fclose( $stream );
			return array(
				'rows'    => array(),
				'errors'  => array( 'missing_source_or_target_column' ),
				'headers' => $headers,
			);
		}

		$rows        = array();
		$line_number = 2;
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Read until the CSV stream reaches EOF.
		while ( false !== ( $columns = fgetcsv( $stream, 0, ',', '"', '' ) ) ) {
			if ( array( null ) === $columns ) {
				++$line_number;
				continue;
			}
			$row = array( 'line' => $line_number++ );
			foreach ( $indexes as $field => $index ) {
				$row[ $field ] = 'type' === $field ? (int) ( $columns[ $index ] ?? 301 ) : ( $columns[ $index ] ?? '' );
			}
			$rows[] = $row;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the in-memory CSV stream.
		fclose( $stream );

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
			if ( ! is_array( $row ) || ( isset( $row['from'] ) && ! is_string( $row['from'] ) ) || ( isset( $row['to'] ) && ! is_string( $row['to'] ) ) ) {
				$errors[] = array(
					'line' => $index + 1,
					'code' => 'invalid_row',
				);
				continue;
			}
			$line = isset( $row['line'] ) ? (int) $row['line'] : $index + 1;
			$from = self::normalize_path( (string) ( $row['from'] ?? '' ) );
			$to   = esc_url_raw( trim( (string) ( $row['to'] ?? '' ) ) );
			$type = (int) ( $row['type'] ?? 301 );

			if ( '' === trim( (string) ( $row['from'] ?? '' ) ) || '' === $to ) {
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
			$enabled = isset( $row['enabled'] ) ? filter_var( $row['enabled'], FILTER_VALIDATE_BOOLEAN ) : true;
			if ( $enabled && self::local_target_path( $to ) === $from ) {
				$errors[] = array(
					'line'   => $line,
					'code'   => 'redirect_loop',
					'source' => $from,
				);
				continue;
			}

			$sources[ $from ] = true;
			$targets[ $from ] = self::local_target_path( $to );
			$clean[]          = array(
				'id'      => self::generate_id(),
				'from'    => $from,
				'to'      => $to,
				'type'    => in_array( $type, array( 301, 302, 307, 308 ), true ) ? $type : 301,
				'enabled' => $enabled,
				'hits'    => max( 0, (int) ( $row['hits'] ?? 0 ) ),
				'created' => sanitize_text_field( $row['created'] ?? current_time( 'mysql' ) ),
			);
		}

		foreach ( self::cyclic_sources( array_merge( $existing, $clean ) ) as $source ) {
			$errors[] = array(
				'code'   => 'redirect_loop',
				'source' => $source,
			);
		}

		foreach ( $targets as $from => $target ) {
			if ( null !== $target && isset( $sources[ $target ] ) ) {
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
				if ( ! self::prepare_import( $rows, $redirects )['success'] ) {
					return false;
				}
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

		self::buffer_event( 'not_found', $path, array( 'origin' => $origin ) );
	}

	/**
	 * Merge buffered 404 aggregates into the bounded log file.
	 *
	 * @since 1.6.0
	 *
	 * @param array $entries Path to aggregate data.
	 * @return void
	 */
	private static function write_not_found( array $entries, string $batch_id = '', array $pending_ids = array() ): bool {
		if ( ! self::ensure_storage() ) {
			return false;
		}
		$options   = (array) \get_option( 'functionalities_redirect_manager', array() );
		$cap       = max( 25, min( 2000, (int) ( $options['monitor_cap'] ?? 500 ) ) );
		$retention = max( 1, min( 365, (int) ( $options['monitor_retention_days'] ?? 30 ) ) );
		$cutoff    = time() - ( $retention * DAY_IN_SECONDS );
		$ignored   = (array) ( $options['monitor_ignored_paths'] ?? array() );

		$result = \Functionalities\Storage\Atomic_JSON_Store::update(
			self::$log_file,
			static function ( array $data ) use ( $entries, $cap, $cutoff, $batch_id, $pending_ids, $ignored ) {
				if ( '' !== $batch_id && isset( $data['applied_batches'][ $batch_id ] ) ) {
					return $data;
				}
				$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
				$items = array_filter(
					$items,
					static function ( $item ) use ( $cutoff ) {
						return is_array( $item ) && (int) ( $item['last_seen'] ?? 0 ) >= $cutoff;
					}
				);

				foreach ( $entries as $path => $entry ) {
					if ( in_array( $path, $ignored, true ) ) {
						continue;
					}
					$count  = (int) ( $entry['count'] ?? 1 );
					$origin = (string) ( $entry['origin'] ?? '' );
					$seen   = (int) ( $entry['last_seen'] ?? time() );

					if ( isset( $items[ $path ] ) ) {
						$previous_seen               = (int) $items[ $path ]['last_seen'];
						$items[ $path ]['count']     = (int) $items[ $path ]['count'] + $count;
						$items[ $path ]['last_seen'] = max( $previous_seen, $seen );
						if ( '' !== $origin && $seen >= $previous_seen ) {
							$items[ $path ]['referrer_origin'] = $origin;
						}
						continue;
					}

					$items[ $path ] = array(
						'path'            => $path,
						'count'           => $count,
						'last_seen'       => $seen,
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
				if ( '' !== $batch_id ) {
					$data['applied_batches']              = array_intersect_key( $data['applied_batches'] ?? array(), array_fill_keys( $pending_ids, true ) );
					$data['applied_batches'][ $batch_id ] = true;
				}
				return $data;
			},
			array(
				'version' => 1,
				'items'   => array(),
			)
		);
		if ( ! $result['success'] ) {
			self::$storage_error = $result['error'];
		}
		return $result['success'];
	}

	/**
	 * Read the current bounded 404 log.
	 *
	 * @return array
	 */
	public static function get_404_log(): array {
		if ( ! self::ensure_storage() ) {
			return array();
		}
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$enabled = isset( $_POST['enabled'] ) ? filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		if ( isset( $_POST['enabled'] ) && null === $enabled ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid enabled state.', 'functionalities' ) ) );
			return;
		}
		$new_state = self::toggle_redirect( $id, $enabled );
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
		$result = self::clear_not_found();
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
		$result = self::clear_not_found( $path );
		$result['success'] ? \wp_send_json_success() : \wp_send_json_error( array( 'message' => $result['error'] ) );
	}

	/** Clear buffered/logged 404s together while no delivery can race the action. */
	private static function clear_not_found( ?string $path = null ): array {
		self::flush_buffer();
		if ( ! self::ensure_storage() ) {
			return array(
				'success' => false,
				'error'   => self::$storage_error,
			);
		}
		$file = \Functionalities\Storage\Data_Directory::file( 'redirect-buffer.json' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$lock = fopen( dirname( $file ) . '/.redirect-buffer-flush.lock', 'c+' );
		if ( false === $lock ) {
			return array(
				'success' => false,
				'error'   => 'buffer_lock_failed',
			);
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				return array(
					'success' => false,
					'error'   => 'buffer_lock_failed',
				);
			}
			$log_result = array();
			$result     = \Functionalities\Storage\Atomic_JSON_Store::update(
				$file,
				static function ( array $buffer ) use ( $path, &$log_result ) {
					$log_result = \Functionalities\Storage\Atomic_JSON_Store::update(
						self::$log_file,
						static function ( array $data ) use ( $path ) {
							if ( null === $path ) {
								$data['items'] = array();
							} else {
								unset( $data['items'][ $path ] );
							}
							return $data;
						},
						array(
							'version' => 1,
							'items'   => array(),
						)
					);
					if ( ! $log_result['success'] ) {
						return false;
					}
					if ( null === $path ) {
						unset( $buffer['not_found'] );
					} else {
						unset( $buffer['not_found'][ $path ] );
					}
					foreach ( $buffer['pending'] ?? array() as $id => $batch ) {
						if ( null === $path ) {
							unset( $buffer['pending'][ $id ]['not_found'] );
						} else {
							unset( $buffer['pending'][ $id ]['not_found'][ $path ] );
						}
						if ( empty( $buffer['pending'][ $id ]['hits'] ) && empty( $buffer['pending'][ $id ]['not_found'] ) ) {
							unset( $buffer['pending'][ $id ] );
						}
					}
					return $buffer;
				},
				array()
			);
			return ! empty( $log_result ) && ! $log_result['success'] ? $log_result : $result;
		} finally {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $lock );
		}
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
