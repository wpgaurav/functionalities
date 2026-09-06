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
	 * Transient holding the resolved exception preset.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	const PRESET_TRANSIENT = 'functionalities_link_preset';

	/**
	 * Option holding the last successfully resolved preset.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	const PRESET_FALLBACK_OPTION = 'functionalities_link_preset_last_good';

	/**
	 * Initialize link management features.
	 *
	 * @return void
	 */
	public static function init(): void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Apply to content, widgets, and comments. The JSON preset is resolved
		// lazily from process_content() so a request that renders no links never
		// pays for reading (or fetching) it.
		\add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999 );
		\add_filter( 'widget_text', array( __CLASS__, 'filter_content' ), 999 );
		\add_filter( 'comment_text', array( __CLASS__, 'filter_content' ), 999 );

		// Keep the cached preset in step with everything that can change it.
		\add_action( 'update_option_functionalities_link_management', array( __CLASS__, 'flush_preset_cache' ) );
		\add_action( 'add_option_functionalities_link_management', array( __CLASS__, 'flush_preset_cache' ) );
		\add_action( 'save_post', array( __CLASS__, 'flush_preset_cache_on_save' ), 10, 2 );
		\add_action( 'deleted_post', array( __CLASS__, 'flush_preset_cache' ) );
		\add_action( 'switch_theme', array( __CLASS__, 'flush_preset_cache' ) );
	}

	/**
	 * Drop the cached preset whenever a post or page is edited.
	 *
	 * Content edits are the moment an author expects a freshly edited exception
	 * list to take effect, so every real save invalidates the cache. Autosaves
	 * and revisions are skipped because they do not change published content and
	 * fire on a timer while the editor is open.
	 *
	 * @since 1.6.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function flush_preset_cache_on_save( $post_id, $post = null ): void {
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( $post instanceof \WP_Post && 'auto-draft' === $post->post_status ) {
			return;
		}

		self::flush_preset_cache();
	}

	/**
	 * Clear the cached exception preset.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public static function flush_preset_cache(): void {
		self::$cached_exceptions = null;
		\delete_transient( self::PRESET_TRANSIENT );
	}

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Request-local copy of the resolved preset exceptions.
	 *
	 * Null means "not resolved yet this request".
	 *
	 * @var array|null
	 */
	private static $cached_exceptions = null;

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
	protected static function get_options(): array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults      = array(
			'enabled'                     => false,
			'nofollow_external'           => false,
			'exceptions'                  => '',
			'open_external_new_tab'       => false,
			'open_internal_new_tab'       => false,
			'internal_new_tab_exceptions' => '',
			'json_preset_url'             => '',
			'enable_developer_filters'    => false,
		);
		$opts          = (array) \get_option( 'functionalities_link_management', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Return the exception list from the JSON preset, using the cached copy.
	 *
	 * Resolution is cached in a transient so a remote preset URL is fetched at
	 * most once per cache window instead of once per page load. The cache is
	 * dropped whenever the module settings change, a post or page is edited, or
	 * the theme changes — see flush_preset_cache_on_save().
	 *
	 * @since 1.6.0
	 *
	 * @return array Exception URL list.
	 */
	public static function get_preset_exceptions(): array {
		if ( null !== self::$cached_exceptions ) {
			return self::$cached_exceptions;
		}

		$cached = \get_transient( self::PRESET_TRANSIENT );
		if ( is_array( $cached ) ) {
			self::$cached_exceptions = $cached;
			return self::$cached_exceptions;
		}

		/**
		 * Filters how long a resolved exception preset stays cached.
		 *
		 * @since 1.6.0
		 *
		 * @param int $ttl Cache lifetime in seconds.
		 */
		$ttl      = (int) \apply_filters( 'functionalities_link_preset_ttl', 15 * MINUTE_IN_SECONDS );
		$ttl      = max( 60, $ttl );
		$resolved = self::resolve_json_preset();

		if ( null === $resolved ) {
			// The source could not be read. Keep serving the last good list and
			// retry sooner rather than caching an empty result for the full TTL.
			$fallback                = (array) \get_option( self::PRESET_FALLBACK_OPTION, array() );
			self::$cached_exceptions = $fallback;
			\set_transient( self::PRESET_TRANSIENT, $fallback, min( $ttl, 5 * MINUTE_IN_SECONDS ) );
			return self::$cached_exceptions;
		}

		self::$cached_exceptions = $resolved;
		\set_transient( self::PRESET_TRANSIENT, $resolved, $ttl );
		\update_option( self::PRESET_FALLBACK_OPTION, $resolved, false );

		return self::$cached_exceptions;
	}

	/**
	 * Load JSON preset file for exceptions.
	 *
	 * Retained for backward compatibility with code that called this directly.
	 * Resolution now happens through get_preset_exceptions().
	 *
	 * @return void
	 */
	public static function load_json_preset(): void {
		self::get_preset_exceptions();
	}

	/**
	 * Resolve the JSON preset from its configured source.
	 *
	 * Checks the following locations in order:
	 * 1. User-provided custom URL/path (if set)
	 * 2. Developer filter (functionalities_json_preset_path)
	 * 3. Active child theme's exception-urls.json
	 * 4. Parent theme's exception-urls.json
	 * 5. Plugin's default exception-urls.json
	 *
	 * @since 1.6.0
	 *
	 * @return array|null Exception list, or null when the source could not be read.
	 */
	private static function resolve_json_preset(): ?array {
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

		// No valid JSON path found: nothing to load, and nothing failed.
		if ( empty( $json_path ) ) {
			return array();
		}

		// Load JSON content - handle both local files and URLs.
		$json_content = self::get_json_content( $json_path );
		if ( false === $json_content ) {
			return null;
		}

		$preset = json_decode( $json_content, true );
		if ( ! is_array( $preset ) ) {
			return null;
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

		return array_values( array_filter( array_map( 'strval', $json_urls ) ) );
	}

	/**
	 * Check if a JSON source is valid (file exists or is a valid URL).
	 *
	 * @param string $source The file path or URL to check.
	 * @return bool True if valid source.
	 */
	private static function is_valid_json_source( string $source ): bool {
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, administrator-configured file path.
		return file_get_contents( $source );
	}

	/**
	 * Filter content to modify links (WordPress filter callback).
	 *
	 * Skips processing in admin, feeds, and REST contexts.
	 * For direct use in themes/plugins (ACF fields, shortcodes, custom templates),
	 * use {@see process_content()} instead.
	 *
	 * @param string $content The content to filter.
	 * @return string Filtered content.
	 */
	public static function filter_content( string $content ): string {
		// Skip in admin, feeds, and REST requests.
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return $content;
		}

		return self::process_content( $content );
	}

	/**
	 * Process arbitrary HTML content to apply link management rules.
	 *
	 * Public helper for use in themes and plugins. Works with any HTML string
	 * regardless of context — ACF fields, shortcode output, custom templates, etc.
	 *
	 * Usage:
	 *   // ACF field
	 *   echo Link_Management::process_content( get_field( 'my_wysiwyg' ) );
	 *
	 *   // Shortcode output
	 *   echo Link_Management::process_content( do_shortcode( $content ) );
	 *
	 *   // Any HTML string
	 *   echo Link_Management::process_content( $html );
	 *
	 * @since 1.3.0
	 *
	 * @param string $content HTML content containing links to process.
	 * @return string Processed content with nofollow/target attributes applied.
	 */
	public static function process_content( string $content ): string {
		if ( trim( $content ) === '' || false === strpos( $content, '<a' ) ) {
			return $content;
		}

		// The HTML API edits attributes in place. Unlike the DOMDocument pass this
		// replaced in 1.6.0 it never reserializes the document, so Vue and Alpine
		// directives, mustache interpolation, and any other unknown attribute
		// survive untouched — which is what the old JS-framework guard existed to
		// work around.
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $content;
		}

		$opts              = self::get_options();
		$manual_exceptions = self::parse_exceptions( (string) $opts['exceptions'] );
		$exceptions        = array_unique( array_merge( $manual_exceptions, self::get_preset_exceptions() ) );
		$internal_ex       = self::parse_exceptions( (string) $opts['internal_new_tab_exceptions'] );
		$site_host         = (string) \wp_parse_url( \home_url(), PHP_URL_HOST );

		$nofollow_external = ! empty( $opts['nofollow_external'] );
		$external_new_tab  = ! empty( $opts['open_external_new_tab'] );
		$internal_new_tab  = ! empty( $opts['open_internal_new_tab'] );

		if ( ! $nofollow_external && ! $external_new_tab && ! $internal_new_tab ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'A' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) || '' === trim( $href ) ) {
				continue;
			}

			$is_external = self::is_external_url( $href, $site_host );
			$rel_tokens  = self::rel_tokens( $processor->get_attribute( 'rel' ) );
			$rel_changed = false;

			if ( $is_external && $nofollow_external && ! self::is_exception( $href, $exceptions ) && ! in_array( 'nofollow', $rel_tokens, true ) ) {
				$rel_tokens[] = 'nofollow';
				$rel_changed  = true;
			}

			if ( $is_external && $external_new_tab ) {
				$processor->set_attribute( 'target', '_blank' );
				if ( ! in_array( 'noopener', $rel_tokens, true ) ) {
					$rel_tokens[] = 'noopener';
					$rel_changed  = true;
				}
			}

			if ( ! $is_external && $internal_new_tab && ! self::host_matches_exception( $href, $internal_ex ) ) {
				$processor->set_attribute( 'target', '_blank' );
			}

			if ( $rel_changed ) {
				$processor->set_attribute( 'rel', implode( ' ', $rel_tokens ) );
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Split a rel attribute into lowercase tokens.
	 *
	 * @since 1.6.0
	 *
	 * @param string|true|null $rel Raw rel attribute value.
	 * @return array
	 */
	private static function rel_tokens( $rel ): array {
		if ( ! is_string( $rel ) || '' === trim( $rel ) ) {
			return array();
		}

		$parts = preg_split( '/\s+/', strtolower( trim( $rel ) ) );

		return array_values( array_filter( array_unique( (array) $parts ) ) );
	}

	/**
	 * Check whether an internal link host is on the new-tab exception list.
	 *
	 * @since 1.6.0
	 *
	 * @param string $href       Link URL.
	 * @param array  $exceptions Exception domains.
	 * @return bool
	 */
	private static function host_matches_exception( string $href, array $exceptions ): bool {
		$test = 0 === strpos( $href, '//' ) ? 'http:' . $href : $href;
		$host = strtolower( (string) \wp_parse_url( $test, PHP_URL_HOST ) );

		foreach ( $exceptions as $domain ) {
			$domain = trim( (string) $domain );
			if ( '' === $domain ) {
				continue;
			}
			if ( $host === $domain ) {
				return true;
			}
			if ( '' !== $host && substr( $host, - ( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
				return true;
			}
		}

		return false;
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
	protected static function parse_exceptions( string $raw ): array {
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

		// Apply developer filters.
		$opts = self::get_options();
		if ( ! empty( $opts['enable_developer_filters'] ) ) {
			$items = \apply_filters( 'functionalities_exception_domains', $items );
			$items = \apply_filters( 'functionalities_exception_urls', $items );
		}

		self::$runtime_exceptions_cache[ $cache_key ] = $items;
		return $items;
	}

	/**
	 * Bulk update links in database for a specific URL.
	 * Bulk update links in database.
	 *
	 * @param string $target_url The URL to add nofollow to.
	 * @param int    $after_id   Only consider posts with an ID above this cursor.
	 * @return array Results with success count and errors.
	 */
	public static function update_links_in_database( string $target_url, int $after_id = 0 ): array {
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

		// Page by ascending ID. Without a cursor the same first batch is returned
		// on every run, because posts that already carry nofollow still match the
		// LIKE, so a site with more matches than the batch limit could never
		// finish.
		$after_id = max( 0, $after_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk operation.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND ID > %d
				AND post_status IN ('publish', 'draft', 'pending', 'future', 'private')
				AND post_type NOT IN ('revision', 'nav_menu_item')
				ORDER BY ID ASC
				LIMIT %d",
				'%' . $wpdb->esc_like( $target_url ) . '%',
				$after_id,
				$batch_limit
			)
		);

		if ( empty( $posts ) ) {
			return array(
				'success'  => true,
				'count'    => 0,
				'has_more' => false,
				'last_id'  => $after_id,
				'message'  => $after_id > 0
					? \__( 'Finished. No further posts contain this URL.', 'functionalities' )
					: \__( 'No posts found containing this URL.', 'functionalities' ),
			);
		}

		$updated_count = 0;
		$processed     = 0;
		$last_id       = $after_id;

		foreach ( $posts as $post ) {
			++$processed;
			$last_id = (int) $post->ID;

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
				++$updated_count;
			}
		}

		$has_more = count( $posts ) >= $batch_limit;

		$message = sprintf(
			/* translators: 1: number of posts updated, 2: number of posts scanned. */
			\__( 'Updated %1$d of %2$d post(s) scanned.', 'functionalities' ),
			$updated_count,
			$processed
		);

		if ( $has_more ) {
			$message .= ' ' . \__( 'More posts remain — continuing.', 'functionalities' );
		}

		return array(
			'success'   => true,
			'count'     => $updated_count,
			'processed' => $processed,
			'has_more'  => $has_more,
			'last_id'   => $last_id,
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
	protected static function add_nofollow_to_url_in_content( string $content, string $target_url ): string {
		// Early exit if no links.
		if ( false === strpos( $content, '<a ' ) ) {
			return $content;
		}

		// Regex to find links with the target URL.
		$pattern = '/<a\s+([^>]*href=["\']' . preg_quote( $target_url, '/' ) . '["\'][^>]*)>/i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
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
			},
			$content
		);
	}

	/**
	 * Check if URL is external.
	 *
	 * @param string $href      The URL to check.
	 * @param string $site_host The site's hostname.
	 * @return bool True if external, false otherwise.
	 */
	protected static function is_external_url( string $href, string $site_host ): bool {
		$href = trim( $href );
		if ( $href === '' ) {
			return false; }
		if ( $href[0] === '#' ) {
			return false; }
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
		if ( $host === '' ) {
			return false; }
		return strcasecmp( $host, $site_host ) !== 0;
	}

	/**
	 * Check if URL matches any exception pattern.
	 *
	 * @param string $href       The URL to check.
	 * @param array  $exceptions Array of exception patterns.
	 * @return bool True if matches exception, false otherwise.
	 */
	protected static function is_exception( string $href, array $exceptions ): bool {
		$h    = strtolower( $href );
		$host = '';
		$test = $href;
		if ( strpos( $href, '//' ) === 0 ) {
			$test = 'http:' . $href; }
		$tmpHost = \wp_parse_url( $test, PHP_URL_HOST );
		if ( is_string( $tmpHost ) ) {
			$host = strtolower( $tmpHost ); }

		foreach ( $exceptions as $ex ) {
			$ex = trim( $ex );
			if ( $ex === '' ) {
				continue; }

			// Full URL match (scheme optional if exception starts with //)
			if ( strpos( $ex, '://' ) !== 0 && strpos( $ex, '//' ) === 0 ) {
				$needle = $ex;
				$hay    = preg_replace( '#^https?:#', '', $h );
				if ( strpos( $hay, $needle ) === 0 ) {
					return true;
				}
			} elseif ( strpos( $ex, '://' ) !== false ) {
				if ( stripos( $h, $ex ) === 0 ) {
					return true;
				}
			} elseif ( strpos( $ex, '/' ) === false && strpos( $ex, '.' ) !== false ) {
				// Domain match.
				if ( $host === $ex || ( $host !== '' && substr( $host, - ( strlen( $ex ) + 1 ) ) === '.' . $ex ) ) {
					return true;
				}
			}
			// Partial value match
			if ( stripos( $h, $ex ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
