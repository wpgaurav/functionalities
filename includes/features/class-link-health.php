<?php
/**
 * Resumable, bounded checks of links in stored public content.
 *
 * @package Functionalities\Features
 */
namespace Functionalities\Features;

use Functionalities\Storage\Atomic_JSON_Store;
use Functionalities\Storage\Data_Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Scan links without making requests during content rendering. */
class Link_Health {
	const CRON_HOOK      = 'functionalities_link_health_batch';
	const WEEKLY_HOOK    = 'functionalities_link_health_weekly';
	const META_KEY       = '_functionalities_link_health';
	const IGNORE_KEY     = '_functionalities_link_health_ignored';
	const CACHE_PREFIX   = 'functionalities_link_health_';
	const MAX_POST_LINKS = 1000;

	/** Attach worker hooks only when enabled. */
	public static function init(): void {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			return;
		}
		\add_action( self::CRON_HOOK, array( __CLASS__, 'run_batch' ) );
		\add_action( self::WEEKLY_HOOK, array( __CLASS__, 'weekly_scan' ) );
		\add_action( 'admin_init', array( __CLASS__, 'sync_schedule' ) );
	}

	/** Synchronize scheduling on enable, disable, and weekly-scan changes. */
	public static function sync_schedule(): void {
		$options = (array) \get_option( 'functionalities_link_health', array() );
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			\wp_clear_scheduled_hook( self::CRON_HOOK );
			\wp_clear_scheduled_hook( self::WEEKLY_HOOK );
			return;
		}
		if ( empty( $options['weekly_scan'] ) ) {
			\wp_clear_scheduled_hook( self::WEEKLY_HOOK );
		} elseif ( ! \wp_next_scheduled( self::WEEKLY_HOOK ) ) {
			\wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::WEEKLY_HOOK );
		}
		if ( 'running' === ( self::state()['status'] ?? '' ) ) {
			self::schedule_batch();
		}
	}

	/** Queue one worker, avoiding duplicate hook arguments. */
	private static function schedule_batch(): void {
		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			\wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/** Resolve URL syntax without fetching or rewriting the source content. */
	public static function normalize_url( string $url, string $base ): string {
		$url = trim( $url );
		if ( '' === $url || '#' === substr( $url, 0, 1 ) || strlen( $url ) > 2048 ) {
			return '';
		}
		$url   = \WP_Http::make_absolute_url( $url, $base );
		$url   = preg_replace( '/#.*$/s', '', $url );
		$parts = \wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return $url;
	}

	/** Extract up to the documented per-post limit with the core HTML API. */
	public static function extract_links( string $content, string $base ): array {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$urls      = array();
		while ( $processor->next_tag( 'A' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) ) {
				continue;
			}
			$url = self::normalize_url( $href, $base );
			if ( '' !== $url ) {
				$urls[ $url ] = true;
				if ( count( $urls ) > self::MAX_POST_LINKS ) {
					break;
				}
			}
		}
		return array_keys( $urls );
	}

	/** Reject obvious private targets in addition to core redirect validation. */
	public static function is_safe_url( string $url ): bool {
		$host = strtolower( trim( (string) \wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.(?:localhost|local|internal)$/', $host ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
		return false !== \wp_http_validate_url( $url );
	}

	/** Check one deduplicated URL; only confirmed GET 404/410 results are broken. */
	public static function check_url( string $url, bool $force = false, ?float $deadline = null ): array {
		$key    = self::CACHE_PREFIX . md5( $url );
		$cached = $force ? false : \get_transient( $key );
		if ( is_array( $cached ) && ( $cached['url'] ?? '' ) === $url ) {
			return $cached;
		}
		$result   = array(
			'url'     => $url,
			'status'  => 'unknown',
			'code'    => 0,
			'checked' => time(),
			'chain'   => array(),
		);
		$current  = $url;
		$deadline = min( $deadline ?? ( microtime( true ) + 8 ), microtime( true ) + 8 );
		for ( $hop = 0; $hop < 4; ++$hop ) {
			if ( ! self::is_safe_url( $current ) ) {
				$result['status'] = 'unsafe';
				break;
			}
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			$args     = array(
				'timeout'             => min( 3, max( 0.1, $deadline - microtime( true ) ) ),
				'redirection'         => 0,
				'limit_response_size' => 4096,
				'cookies'             => array(),
			);
			$response = \wp_safe_remote_head( $current, $args );
			if ( \is_wp_error( $response ) ) {
				break;
			}
			$code      = (int) \wp_remote_retrieve_response_code( $response );
			$confirmed = false;
			if ( in_array( $code, array( 404, 410, 405, 501 ), true ) && microtime( true ) < $deadline ) {
				$args['timeout'] = min( 3, max( 0.1, $deadline - microtime( true ) ) );
				$response        = \wp_safe_remote_get( $current, $args );
				if ( \is_wp_error( $response ) ) {
					break;
				}
				$code      = (int) \wp_remote_retrieve_response_code( $response );
				$confirmed = true;
			}
			$result['code'] = $code;
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$next = self::normalize_url( (string) \wp_remote_retrieve_header( $response, 'location' ), $current );
				if ( '' === $next || in_array( $next, $result['chain'], true ) || $next === $url ) {
					break;
				}
				$result['chain'][] = $next;
				$current           = $next;
				continue;
			}
			if ( $code >= 200 && $code < 300 ) {
				$result['status'] = $result['chain'] ? 'redirected' : 'ok';
			} elseif ( $confirmed && in_array( $code, array( 404, 410 ), true ) ) {
				$result['status'] = 'broken';
			}
			break;
		}
		\set_transient( $key, $result, in_array( $result['status'], array( 'unknown', 'unsafe' ), true ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
		return $result;
	}

	/** Include the URL identity when checking whether a stored report is stale. */
	private static function content_hash( object $post ): string {
		return hash( 'sha256', $post->post_content . "\0" . $post->post_name . "\0" . $post->post_parent . "\0" . \get_permalink( $post->ID ) );
	}

	/** Commit under the scan lock, with no network requests while it is held. */
	private static function write_report( int $post_id, string $hash, array $report, string $token = '', bool $merge = false ): bool {
		$path = Data_Directory::file( 'link-health-state.json' );
		if ( '' === $path ) {
			return false;
		}
		$result = Atomic_JSON_Store::update(
			$path,
			static function ( $state ) use ( $post_id, $hash, $report, $token, $merge ) {
				$active = (int) ( $state['lease']['until'] ?? 0 ) > time();
				if ( '' !== $token ? ( ! $active || ( $state['lease']['token'] ?? '' ) !== $token ) : $active ) {
					return null;
				}
				global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh source read prevents request-local post cache from hiding concurrent edits.
				$post = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_type, post_status, post_password, post_content, post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
				if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || 'publish' !== $post->post_status || '' !== $post->post_password || self::content_hash( $post ) !== $hash ) {
					return null;
				}
				if ( $merge ) {
					$current = (array) \get_post_meta( $post_id, self::META_KEY, true );
					if ( ( $current['hash'] ?? '' ) !== $hash ) {
						return null;
					}
					$current['rows'] = array_replace( (array) ( $current['rows'] ?? array() ), $report['rows'] );
					$report          = $current;
				}
				$saved = \update_post_meta( $post_id, self::META_KEY, $report );
				return false === $saved && $report !== \get_post_meta( $post_id, self::META_KEY, true ) ? null : $state;
			}
		);
		return $result['success'];
	}

	/** Read scan status without making HTTP requests. */
	public static function state(): array {
		$path = Data_Directory::file( 'link-health-state.json' );
		if ( '' === $path ) {
			return array(
				'status' => 'error',
				'error'  => 'storage_unavailable',
			);
		}
		$result = Atomic_JSON_Store::read( $path );
		return $result['success'] ? $result['data'] : array(
			'status' => 'error',
			'error'  => $result['error'],
		);
	}

	/** Start a fresh scan only when no unfinished scan exists. */
	public static function start_scan( bool $scheduled = false ) {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) || ( ! $scheduled && ! \current_user_can( 'manage_options' ) ) ) {
			return new \WP_Error( 'forbidden', \__( 'Enable Link Health and use an administrator account.', 'functionalities' ) );
		}
		$path = Data_Directory::file( 'link-health-state.json' );
		if ( '' === $path ) {
			return new \WP_Error( 'storage_unavailable', \__( 'Private scan storage is unavailable.', 'functionalities' ) );
		}
		$result = Atomic_JSON_Store::update(
			$path,
			static function ( $data ) {
				if ( 'running' === ( $data['status'] ?? '' ) ) {
					return null;
				}
				return array(
					'status'   => 'running',
					'run'      => \wp_generate_uuid4(),
					'cursor'   => 0,
					'post'     => 0,
					'offset'   => 0,
					'posts'    => 0,
					'urls'     => 0,
					'started'  => time(),
					'finished' => 0,
					'lease'    => array(),
				);
			}
		);
		if ( ! $result['success'] ) {
			return new \WP_Error( $result['error'], \__( 'A scan is already running or storage is unavailable. Resume the existing scan.', 'functionalities' ) );
		}
		self::schedule_batch();
		return $result['data'];
	}

	/** Stop a scan between batches, without deleting cached results. */
	public static function stop_scan() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', \__( 'Administrator access is required.', 'functionalities' ) );
		}
		$path = Data_Directory::file( 'link-health-state.json' );
		if ( '' === $path ) {
			return new \WP_Error( 'storage_unavailable', \__( 'Private scan storage is unavailable.', 'functionalities' ) );
		}
		$result = Atomic_JSON_Store::update(
			$path,
			static function ( $data ) {
				if ( (int) ( $data['lease']['until'] ?? 0 ) > time() ) {
					return null;
				}
				$data['status']   = 'stopped';
				$data['finished'] = time();
				$data['lease']    = array();
				return $data;
			}
		);
		if ( ! $result['success'] ) {
			return new \WP_Error( $result['error'], \__( 'Wait for the current batch to finish, then stop the scan.', 'functionalities' ) );
		}
		\wp_clear_scheduled_hook( self::CRON_HOOK );
		return $result['data'];
	}

	/** Weekly scheduling never restarts an unfinished scan. */
	public static function weekly_scan(): void {
		$options = (array) \get_option( 'functionalities_link_health', array() );
		if ( empty( $options['weekly_scan'] ) ) {
			return;
		}
		if ( 'running' !== ( self::state()['status'] ?? '' ) ) {
			self::start_scan( true );
		}
		self::run_batch();
	}

	/** Find the next public post by ID rather than an unstable offset. */
	private static function next_post( int $cursor ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded background keyset pagination of current published content.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('post','page') AND post_status = 'publish' AND post_password = '' ORDER BY ID ASC LIMIT 1", $cursor ) );
	}

	/** Revalidate scope immediately before reading or writing a post's results. */
	public static function public_post( int $post_id ) {
		$post = \get_post( $post_id );
		return $post instanceof \WP_Post && in_array( $post->post_type, array( 'post', 'page' ), true ) && 'publish' === $post->post_status && '' === $post->post_password ? $post : null;
	}

	/** Consume at most four URLs / ten seconds, resuming inside long posts.
	 *
	 * @throws \RuntimeException Internally caught to leave failed writes resumable.
	 */
	public static function run_batch() { // phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing -- Write failures are caught within this worker.
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			return new \WP_Error( 'disabled', \__( 'Link Health is disabled.', 'functionalities' ) );
		}
		$path = Data_Directory::file( 'link-health-state.json' );
		if ( '' === $path ) {
			return new \WP_Error( 'storage_unavailable', \__( 'Private scan storage is unavailable.', 'functionalities' ) );
		}
		$token = \wp_generate_uuid4();
		$claim = Atomic_JSON_Store::update(
			$path,
			static function ( $data ) use ( $token ) {
				if ( 'running' !== ( $data['status'] ?? '' ) || (int) ( $data['lease']['until'] ?? 0 ) > time() ) {
					return null;
				}
				$data['lease'] = array(
					'token' => $token,
					'until' => time() + 90,
				);
				return $data;
			}
		);
		if ( ! $claim['success'] ) {
			return new \WP_Error( $claim['error'], \__( 'The scan is finished or another worker is processing a batch.', 'functionalities' ) );
		}
		$data     = $claim['data'];
		$deadline = microtime( true ) + 10;
		$checked  = 0;
		$visited  = 0;
		try {
			while ( $checked < 4 && $visited < 20 && microtime( true ) < $deadline ) {
				if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
					break;
				}
				if ( empty( $data['post'] ) ) {
					$data['post']   = self::next_post( (int) $data['cursor'] );
					$data['offset'] = 0;
				}
				if ( ! $data['post'] ) {
					$data['status']   = 'completed';
					$data['finished'] = time();
					break;
				}
				++$visited;
				$post = self::public_post( (int) $data['post'] );
				if ( ! $post ) {
					$data['cursor'] = $data['post'];
					$data['post']   = 0;
					continue;
				}
				$hash      = self::content_hash( $post );
				$urls      = self::extract_links( $post->post_content, \get_permalink( $post->ID ) );
				$truncated = count( $urls ) > self::MAX_POST_LINKS;
				$urls      = array_slice( $urls, 0, self::MAX_POST_LINKS );
				$report    = (array) \get_post_meta( $post->ID, self::META_KEY, true );
				if ( 0 === (int) $data['offset'] || ( $report['hash'] ?? '' ) !== $hash ) {
					$data['offset'] = 0;
					$report         = array(
						'hash'      => $hash,
						'rows'      => array(),
						'complete'  => false,
						'truncated' => $truncated,
						'checked'   => time(),
					);
				}
				$url_count = count( $urls );
				$ignored   = (array) \get_post_meta( $post->ID, self::IGNORE_KEY, true );
				while ( (int) $data['offset'] < $url_count && $checked < 4 && microtime( true ) < $deadline ) {
					$url                    = $urls[ $data['offset'] ];
					$key                    = md5( $url );
					$report['rows'][ $key ] = in_array( $key, $ignored, true ) ? array(
						'url'     => $url,
						'status'  => 'ignored',
						'code'    => 0,
						'checked' => 0,
						'chain'   => array(),
					) : self::check_url( $url, false, $deadline );
					++$data['offset'];
					++$data['urls'];
					++$checked;
				}
				$latest = self::public_post( (int) $post->ID );
				if ( ! $latest || self::content_hash( $latest ) !== $hash ) {
					$data['offset'] = 0;
					break;
				}
				$report['complete'] = (int) $data['offset'] >= count( $urls );
				$report['checked']  = time();
				if ( ! self::write_report( (int) $post->ID, $hash, $report, $token ) ) {
					throw new \RuntimeException( 'result_write_failed' );
				}
				if ( $report['complete'] ) {
					$data['cursor'] = $post->ID;
					$data['post']   = 0;
					++$data['posts'];
				}
			}
			unset( $data['error'] );
		} catch ( \Throwable $error ) {
			$data['error']  = 'batch_failed';
			$data['offset'] = 0;
		}
		$finish = Atomic_JSON_Store::update(
			$path,
			static function ( $current ) use ( $data, $token ) {
				if ( ( $current['lease']['token'] ?? '' ) !== $token || ( $current['run'] ?? '' ) !== $data['run'] ) {
					return null;
				}
				$data['lease'] = array();
				return $data;
			}
		);
		if ( 'running' === $data['status'] && \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			self::schedule_batch();
		}
		return $finish['success'] ? $finish['data'] : new \WP_Error( $finish['error'], \__( 'The batch could not be saved. Resume the scan after checking private storage.', 'functionalities' ) );
	}

	/** Validate a UI target against current source content, preventing arbitrary probes. */
	public static function action_target( int $post_id, string $url ) {
		if ( ! \current_user_can( 'manage_options' ) || ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			return new \WP_Error( 'forbidden', \__( 'Administrator access is required.', 'functionalities' ) );
		}
		$post = self::public_post( $post_id );
		if ( ! $post || ! in_array( $url, self::extract_links( $post->post_content, \get_permalink( $post_id ) ), true ) ) {
			return new \WP_Error( 'invalid_target', \__( 'This link is no longer in public source content.', 'functionalities' ) );
		}
		return $post;
	}

	/** Persist one ignored URL hash; rechecking also removes that ignore. */
	public static function set_ignored( int $post_id, string $url, bool $ignored ) {
		$post = self::action_target( $post_id, $url );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}
		$keys = (array) \get_post_meta( $post_id, self::IGNORE_KEY, true );
		$key  = md5( $url );
		$keys = array_values( array_diff( $keys, array( $key ) ) );
		if ( $ignored ) {
			$keys[] = $key;
		}
		\update_post_meta( $post_id, self::IGNORE_KEY, array_slice( $keys, -self::MAX_POST_LINKS ) );
		return true;
	}

	/** Recheck only a current link and retain stale detection for the rest of the report. */
	public static function recheck( int $post_id, string $url ) {
		$post = self::action_target( $post_id, $url );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}
		$report = (array) \get_post_meta( $post_id, self::META_KEY, true );
		$hash   = self::content_hash( $post );
		if ( ( $report['hash'] ?? '' ) !== $hash ) {
			return new \WP_Error( 'stale_report', \__( 'Content changed since the scan. Run a new scan before rechecking.', 'functionalities' ) );
		}
		$result = self::check_url( $url, true );
		$latest = self::public_post( $post_id );
		if ( ! $latest || self::content_hash( $latest ) !== $hash ) {
			return new \WP_Error( 'stale_report', \__( 'Content changed during the check. Run a new scan.', 'functionalities' ) );
		}
		if ( ! self::write_report( $post_id, $hash, array( 'rows' => array( md5( $url ) => $result ) ), '', true ) ) {
			return new \WP_Error( 'report_busy', \__( 'The source changed or a scan is updating this report. Wait for the batch to finish and retry.', 'functionalities' ) );
		}
		self::set_ignored( $post_id, $url, false );
		return $result;
	}

	/** Page through posts with reports; never fetch URLs while viewing results. */
	public static function report_page( int $page = 1 ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'has_password'   => false,
				'meta_key'       => self::META_KEY,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'paged'          => max( 1, $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Paginated explicit reports workspace only.
		$rows  = array();
		foreach ( $query->posts as $post_id ) {
			$post = self::public_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}
			$report  = (array) \get_post_meta( $post_id, self::META_KEY, true );
			$ignored = (array) \get_post_meta( $post_id, self::IGNORE_KEY, true );
			$stale   = ( $report['hash'] ?? '' ) !== self::content_hash( $post );
			foreach ( (array) ( $report['rows'] ?? array() ) as $key => $row ) {
				$row['post_id']   = (int) $post_id;
				$row['title']     = \get_the_title( $post_id );
				$row['stale']     = $stale;
				$row['complete']  = ! empty( $report['complete'] );
				$row['truncated'] = ! empty( $report['truncated'] );
				if ( in_array( $key, $ignored, true ) ) {
					$row['status'] = 'ignored';
				}
				$rows[] = $row;
			}
		}
		return array(
			'rows'  => $rows,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
