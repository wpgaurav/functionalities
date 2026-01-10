<?php
/**
 * Link Management features (frontend behaviors).
 *
 * Provides comprehensive link management including:
 * - Automatic nofollow for external links with exceptions
 * - Open external/internal links in new tab
 * - Customizable exception lists
 * - Smart pattern matching for domains and URLs
 *
 * @package Functionalities\Features
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Management class.
 */
class Link_Management {

	/**
	 * Initialize link management features.
	 *
	 * @return void
	 */
	public static function init() : void {
		// Load JSON preset immediately (we're already on init hook).
		self::load_json_preset();

		// Apply to content, widgets, and comments (GT Nofollow Manager compatibility).
		\add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999 );
		\add_filter( 'widget_text', array( __CLASS__, 'filter_content' ), 999 );
		\add_filter( 'comment_text', array( __CLASS__, 'filter_content' ), 999 );
	}

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Cached exceptions.
	 *
	 * @var array
	 */
	private static $cached_exceptions = array();

	/**
	 * Cached internal exceptions.
	 *
	 * @var array
	 */
	private static $cached_internal_exceptions = array();

	/**
	 * Get link management options.
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'nofollow_external'             => false,
			'exceptions'                    => '',
			'open_external_new_tab'         => false,
			'open_internal_new_tab'         => false,
			'internal_new_tab_exceptions'   => '',
			'json_preset_url'               => '',
			'enable_developer_filters'      => false,
		);
		$opts = (array) \get_option( 'functionalities_link_management', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Load JSON preset file for exceptions.
	 *
	 * Checks the following locations in order:
	 * 1. User-provided custom URL/path (if set)
	 * 2. Developer filter (functionalities_json_preset_path)
	 * 3. Active child theme's exception-urls.json
	 * 4. Parent theme's exception-urls.json
	 * 5. Plugin's default exception-urls.json
	 *
	 * @return void
	 */
	public static function load_json_preset() : void {
		$opts = self::get_options();

		$json_path = '';

		// Priority 1: User-provided custom URL/path.
		if ( ! empty( $opts['json_preset_url'] ) && self::is_valid_json_source( $opts['json_preset_url'] ) ) {
			$json_path = $opts['json_preset_url'];
		} else {
			// Priority 2: Developer filter.
			$filtered_path = \apply_filters( 'functionalities_json_preset_path', '' );
			if ( ! empty( $filtered_path ) && self::is_valid_json_source( $filtered_path ) ) {
				$json_path = $filtered_path;
			}
		}

		// Priority 3 & 4: Check theme directories if no custom path set.
		if ( empty( $json_path ) ) {
			// Check child theme first (get_stylesheet_directory).
			$child_theme_json = \get_stylesheet_directory() . '/exception-urls.json';
			if ( file_exists( $child_theme_json ) && is_readable( $child_theme_json ) ) {
				$json_path = $child_theme_json;
			} elseif ( \get_stylesheet_directory() !== \get_template_directory() ) {
				// Check parent theme if using a child theme.
				$parent_theme_json = \get_template_directory() . '/exception-urls.json';
				if ( file_exists( $parent_theme_json ) && is_readable( $parent_theme_json ) ) {
					$json_path = $parent_theme_json;
				}
			}
		}

		// Priority 5: Plugin's default exception-urls.json.
		if ( empty( $json_path ) ) {
			$plugin_json = FUNCTIONALITIES_DIR . 'exception-urls.json';
			if ( file_exists( $plugin_json ) && is_readable( $plugin_json ) ) {
				$json_path = $plugin_json;
			}
		}

		// No valid JSON path found.
		if ( empty( $json_path ) ) {
			return;
		}

		// Load JSON content - handle both local files and URLs.
		$json_content = self::get_json_content( $json_path );
		if ( false === $json_content ) {
			return;
		}

		$preset = json_decode( $json_content, true );
		if ( ! is_array( $preset ) ) {
			return;
		}

		$json_urls = array();
		if ( isset( $preset['urls'] ) && is_array( $preset['urls'] ) ) {
			// Object format: {"urls": ["...", "..."]}
			$json_urls = $preset['urls'];
		} else {
			// Flat array format: ["...", "..."]
			// Filter to ensure only strings are kept.
			$json_urls = array_filter( $preset, 'is_string' );
		}

		if ( empty( $json_urls ) ) {
			return;
		}

		self::$cached_exceptions = $json_urls;
	}

	/**
	 * Check if a JSON source is valid (file exists or is a valid URL).
	 *
	 * @param string $source The file path or URL to check.
	 * @return bool True if valid source.
	 */
	private static function is_valid_json_source( string $source ) : bool {
		// Check if it's a URL.
		if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
			return true;
		}

		// Check if it's a readable local file.
		return file_exists( $source ) && is_readable( $source );
	}

	/**
	 * Get JSON content from a file path or URL.
	 *
	 * @param string $source The file path or URL.
	 * @return string|false JSON content or false on failure.
	 */
	private static function get_json_content( string $source ) {
		// Handle URLs.
		if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
			$response = \wp_remote_get(
				$source,
				array(
					'timeout'   => 10,
					'sslverify' => true,
				)
			);

			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$status_code = \wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code ) {
				return false;
			}

			return \wp_remote_retrieve_body( $response );
		}

		// Handle local files.
		if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
			return false;
		}

		return file_get_contents( $source );
	}

	/**
	 * Filter content to modify links.
	 *
	 * @param string $content The content to filter.
	 * @return string Filtered content.
	 */
	public static function filter_content( string $content ) : string {
		// Skip in admin, feeds, and REST requests.
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return $content;
		}

		if ( trim( $content ) === '' || false === strpos( $content, '<a' ) ) {
			return $content;
		}

		$opts              = self::get_options();
		$manual_exceptions = self::parse_exceptions( (string) $opts['exceptions'] );
		$exceptions        = array_unique( array_merge( $manual_exceptions, self::$cached_exceptions ) );
		$internal_ex       = self::parse_exceptions( (string) $opts['internal_new_tab_exceptions'] );
		$site_host   = (string) \wp_parse_url( \home_url(), PHP_URL_HOST );

		$libxml_previous = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$html = '<div id="__functionalities_wrapper">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//a[@href]' );
		if ( $nodes instanceof \DOMNodeList ) {
			foreach ( $nodes as $a ) {
				$href = (string) $a->getAttribute( 'href' );
				$is_external = self::is_external_url( $href, $site_host );

				// Nofollow external
				if ( $is_external && ! self::is_exception( $href, $exceptions ) && ! empty( $opts['nofollow_external'] ) ) {
					$rel = (string) $a->getAttribute( 'rel' );
					$parts = preg_split( '/\s+/', strtolower( $rel ) );
					$parts = array_filter( array_unique( array_map( 'trim', (array) $parts ) ) );
					if ( ! in_array( 'nofollow', $parts, true ) ) { $parts[] = 'nofollow'; }
					$a->setAttribute( 'rel', implode( ' ', $parts ) );
				}

				// New tab external
				if ( $is_external && ! empty( $opts['open_external_new_tab'] ) ) {
					$a->setAttribute( 'target', '_blank' );
					// add noopener
					$rel = strtolower( (string) $a->getAttribute( 'rel' ) );
					$parts = array_filter( array_unique( preg_split( '/\s+/', $rel ) ) );
					if ( ! in_array( 'noopener', $parts, true ) ) { $parts[] = 'noopener'; }
					$a->setAttribute( 'rel', implode( ' ', $parts ) );
				}

				// New tab internal (same-domain) except certain domains
				if ( ! $is_external && ! empty( $opts['open_internal_new_tab'] ) ) {
					// If link host matches an exception domain, skip
					$test = $href;
					if ( strpos( $href, '//' ) === 0 ) { $test = 'http:' . $href; }
					$host = (string) \wp_parse_url( $test, PHP_URL_HOST );
					$host = strtolower( $host );
					$skip = false;
					foreach ( $internal_ex as $exd ) {
						if ( $exd === '' ) { continue; }
						if ( $host === $exd || ( $host !== '' && substr( $host, - ( strlen( $exd ) + 1 ) ) === '.' . $exd ) ) {
							$skip = true; break;
						}
					}
					if ( ! $skip ) {
						$a->setAttribute( 'target', '_blank' );
					}
				}
			}
		}

		$out = '';
		$wrapper = $dom->getElementById( '__functionalities_wrapper' );
		if ( $wrapper ) {
			foreach ( $wrapper->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );
		return $out !== '' ? $out : $content;
	}

	/**
	 * Runtime cache for parsed exceptions.
	 *
	 * @var array
	 */
	private static $runtime_exceptions_cache = array();

	/**
	 * Parse exception list from raw text.
	 *
	 * @param string $raw Raw exception text (one per line or comma-separated).
	 * @return array Array of exceptions.
	 */
	protected static function parse_exceptions( string $raw ) : array {
		$cache_key = md5( $raw );
		if ( isset( self::$runtime_exceptions_cache[ $cache_key ] ) ) {
			return self::$runtime_exceptions_cache[ $cache_key ];
		}

		$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
		$items = array();
		foreach ( $lines as $line ) {
			$line = strtolower( trim( $line ) );
			if ( $line === '' ) {
				continue;
			}
			$items[] = $line;
		}

		// Apply developer filters (GT Nofollow Manager compatibility).
		$opts = self::get_options();
		if ( ! empty( $opts['enable_developer_filters'] ) ) {
			$items = \apply_filters( 'functionalities_exception_domains', $items );
			$items = \apply_filters( 'functionalities_exception_urls', $items );

			// Legacy GT Nofollow Manager filter names for backward compatibility.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name for backward compatibility.
			$items = \apply_filters( 'gtnf_exception_domains', $items );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name for backward compatibility.
			$items = \apply_filters( 'gtnf_exception_urls', $items );
		}

		self::$runtime_exceptions_cache[ $cache_key ] = $items;
		return $items;
	}

	/**
	 * Bulk update links in database for a specific URL.
	 * GT Nofollow Manager compatibility feature.
	 *
	 * @param string $target_url The URL to add nofollow to.
	 * @return array Results with success count and errors.
	 */
	public static function update_links_in_database( string $target_url ) : array {
		global $wpdb;

		$target_url = trim( $target_url );
		if ( empty( $target_url ) ) {
			return array(
				'success' => false,
				'message' => \__( 'Please provide a valid URL.', 'functionalities' ),
			);
		}

		/**
		 * Maximum number of posts to process in a single request.
		 * Prevents timeout on large sites.
		 *
		 * @since 0.9.9
		 *
		 * @param int $limit Maximum posts to process.
		 */
		$batch_limit = \apply_filters( 'functionalities_link_update_batch_limit', 100 );

		// Query posts containing the target URL with limit for performance.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk operation.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_status IN ('publish', 'draft', 'pending', 'future', 'private')
				AND post_type NOT IN ('revision', 'nav_menu_item')
				LIMIT %d",
				'%' . $wpdb->esc_like( $target_url ) . '%',
				$batch_limit
			)
		);

		if ( empty( $posts ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => \__( 'No posts found containing this URL.', 'functionalities' ),
			);
		}

		$updated_count = 0;
		$processed     = 0;

		foreach ( $posts as $post ) {
			$processed++;

			// Process links in content.
			$new_content = self::add_nofollow_to_url_in_content( $post->post_content, $target_url );

			if ( $new_content !== $post->post_content ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk update.
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
				\clean_post_cache( $post->ID );
				$updated_count++;
			}

			// Prevent timeout on large batches.
			if ( $processed >= $batch_limit ) {
				break;
			}
		}

		// translators: %d: Number of posts updated.
		$message = sprintf(
			\__( 'Successfully updated %d post(s).', 'functionalities' ),
			$updated_count
		);

		// Indicate if there may be more posts to process.
		if ( count( $posts ) >= $batch_limit ) {
			$message .= ' ' . \__( 'There may be more posts - run again to continue.', 'functionalities' );
		}

		return array(
			'success'   => true,
			'count'     => $updated_count,
			'processed' => $processed,
			'has_more'  => count( $posts ) >= $batch_limit,
			'message'   => $message,
		);
	}

	/**
	 * Add nofollow to specific URL in content.
	 *
	 * @param string $content    Post content.
	 * @param string $target_url URL to add nofollow to.
	 * @return string Modified content.
	 */
	protected static function add_nofollow_to_url_in_content( string $content, string $target_url ) : string {
		// Early exit if no links.
		if ( false === strpos( $content, '<a ' ) ) {
			return $content;
		}

		// Regex to find links with the target URL.
		$pattern = '/<a\s+([^>]*href=["\']' . preg_quote( $target_url, '/' ) . '["\'][^>]*)>/i';

		return preg_replace_callback( $pattern, function( $matches ) {
			$tag        = $matches[0];
			$attributes = $matches[1];

			// Check if already has nofollow.
			if ( preg_match( '/rel=["\']([^"\']*nofollow[^"\']*)["\']/i', $attributes ) ) {
				return $tag;
			}

			// Add or append nofollow.
			if ( preg_match( '/rel=["\']/i', $attributes ) ) {
				return preg_replace( '/rel=["\']([^"\']*)["\']/i', 'rel="$1 nofollow"', $tag );
			} else {
				return str_replace( '<a ', '<a rel="nofollow" ', $tag );
			}
		}, $content );
	}

	/**
	 * Check if URL is external.
	 *
	 * @param string $href      The URL to check.
	 * @param string $site_host The site's hostname.
	 * @return bool True if external, false otherwise.
	 */
	protected static function is_external_url( string $href, string $site_host ) : bool {
		$href = trim( $href );
		if ( $href === '' ) { return false; }
		if ( $href[0] === '#' ) { return false; }
		$lower = strtolower( $href );
		if ( strpos( $lower, 'mailto:' ) === 0 || strpos( $lower, 'tel:' ) === 0 || strpos( $lower, 'javascript:' ) === 0 ) {
			return false;
		}
		// Relative URL
		if ( strpos( $href, 'http://' ) !== 0 && strpos( $href, 'https://' ) !== 0 && strpos( $href, '//' ) !== 0 ) {
			return false; // treat as internal
		}
		$test = $href;
		if ( strpos( $href, '//' ) === 0 ) {
			$test = 'http:' . $href;
		}
		$host = (string) \wp_parse_url( $test, PHP_URL_HOST );
		if ( $host === '' ) { return false; }
		return strcasecmp( $host, $site_host ) !== 0;
	}

	/**
	 * Check if URL matches any exception pattern.
	 *
	 * @param string $href       The URL to check.
	 * @param array  $exceptions Array of exception patterns.
	 * @return bool True if matches exception, false otherwise.
	 */
	protected static function is_exception( string $href, array $exceptions ) : bool {
		$h = strtolower( $href );
		$host = '';
		$test = $href;
		if ( strpos( $href, '//' ) === 0 ) { $test = 'http:' . $href; }
		$tmpHost = \wp_parse_url( $test, PHP_URL_HOST );
		if ( is_string( $tmpHost ) ) { $host = strtolower( $tmpHost ); }

		foreach ( $exceptions as $ex ) {
			$ex = trim( $ex );
			if ( $ex === '' ) { continue; }

			// Full URL match (scheme optional if exception starts with //)
			if ( strpos( $ex, '://' ) !== 0 && strpos( $ex, '//' ) === 0 ) {
				$needle = $ex;
				$hay = preg_replace( '#^https?:#', '', $h );
				if ( strpos( $hay, $needle ) === 0 ) { return true; }
			}
			elseif ( strpos( $ex, '://' ) !== false ) {
				if ( stripos( $h, $ex ) === 0 ) { return true; }
			}
			// Domain match
			elseif ( strpos( $ex, '/' ) === false && strpos( $ex, '.' ) !== false ) {
				if ( $host === $ex || ( $host !== '' && substr( $host, - ( strlen( $ex ) + 1 ) ) === '.' . $ex ) ) {
					return true;
				}
			}
			// Partial value match
			if ( stripos( $h, $ex ) !== false ) { return true; }
		}
		return false;
	}
}
