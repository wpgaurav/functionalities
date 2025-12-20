<?php
/**
 * Content Regression Detection.
 *
 * Detects unintentional structural damage to content when posts are updated.
 * Compares current version against the post's own historical baseline.
 *
 * Features:
 * - Internal link drop detection
 * - Word count regression detection
 * - Heading structure break detection
 *
 * @package Functionalities\Features
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Regression Detection class.
 */
class Content_Regression {

	/**
	 * Post meta key for storing snapshots.
	 *
	 * @var string
	 */
	const META_KEY = '_functionalities_content_snapshot';

	/**
	 * Post meta key for per-post settings.
	 *
	 * @var string
	 */
	const POST_SETTINGS_KEY = '_functionalities_regression_settings';

	/**
	 * Initialize content regression detection.
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Hook into post save to capture snapshots.
		\add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 3 );

		// Register REST API endpoints for the editor.
		\add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// Enqueue editor scripts.
		\add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );

		// Add post list column.
		\add_action( 'admin_init', array( __CLASS__, 'setup_post_columns' ) );

		// AJAX handlers for per-post actions.
		\add_action( 'wp_ajax_functionalities_mark_intentional', array( __CLASS__, 'ajax_mark_intentional' ) );
		\add_action( 'wp_ajax_functionalities_reset_baseline', array( __CLASS__, 'ajax_reset_baseline' ) );
		\add_action( 'wp_ajax_functionalities_get_regression_status', array( __CLASS__, 'ajax_get_regression_status' ) );
	}

	/**
	 * Get module options.
	 *
	 * @return array Options array.
	 */
	public static function get_options() : array {
		$defaults = array(
			'enabled'                     => false,
			'post_types'                  => array( 'post', 'page' ),
			// Internal link detection.
			'link_drop_enabled'           => true,
			'link_drop_percent'           => 30,
			'link_drop_absolute'          => 3,
			'exclude_nofollow_links'      => false,
			// Word count detection.
			'word_count_enabled'          => true,
			'word_count_drop_percent'     => 35,
			'word_count_min_age_days'     => 30,
			'word_count_compare_average'  => false,
			'exclude_shortcodes'          => false,
			// Heading detection.
			'heading_enabled'             => true,
			'detect_missing_h1'           => true,
			'detect_multiple_h1'          => true,
			'detect_skipped_levels'       => true,
			// Snapshot settings.
			'snapshot_rolling_count'      => 5,
			// UI settings.
			'show_post_column'            => true,
		);
		$opts = (array) \get_option( 'functionalities_content_regression', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get per-post settings.
	 *
	 * @param int $post_id Post ID.
	 * @return array Per-post settings.
	 */
	public static function get_post_settings( int $post_id ) : array {
		$defaults = array(
			'detection_disabled' => false,
			'is_short_form'      => false,
		);
		$settings = \get_post_meta( $post_id, self::POST_SETTINGS_KEY, true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return array_merge( $defaults, $settings );
	}

	/**
	 * Update per-post settings.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $settings Settings to save.
	 * @return void
	 */
	public static function update_post_settings( int $post_id, array $settings ) : void {
		$current = self::get_post_settings( $post_id );
		$merged = array_merge( $current, $settings );
		\update_post_meta( $post_id, self::POST_SETTINGS_KEY, $merged );
	}

	/**
	 * Hook into post save to capture snapshots.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public static function on_save_post( int $post_id, \WP_Post $post, bool $update ) : void {
		// Skip autosaves and revisions.
		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only capture on publish.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$opts = self::get_options();

		// Check if post type is enabled.
		$enabled_types = (array) $opts['post_types'];
		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return;
		}

		// Check per-post settings.
		$post_settings = self::get_post_settings( $post_id );
		if ( ! empty( $post_settings['detection_disabled'] ) ) {
			return;
		}

		// Capture and store snapshot.
		$snapshot = self::capture_snapshot( $post );
		self::store_snapshot( $post_id, $snapshot, $opts );
	}

	/**
	 * Capture a structural snapshot of the post content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Snapshot data.
	 */
	public static function capture_snapshot( \WP_Post $post ) : array {
		$content = $post->post_content;
		$opts = self::get_options();

		// Get rendered content for accurate parsing.
		$rendered = \apply_filters( 'the_content', $content );

		// Parse the content.
		$links = self::parse_links( $rendered, $opts );
		$word_count = self::count_words( $content, $opts );
		$headings = self::parse_headings( $content );

		return array(
			'internal_link_count'  => $links['internal'],
			'external_link_count'  => $links['external'],
			'word_count'           => $word_count,
			'heading_map'          => $headings['map'],
			'h1_count'             => $headings['h1_count'],
			'timestamp'            => time(),
			'is_stable_version'    => true,
		);
	}

	/**
	 * Parse links from content.
	 *
	 * @param string $content Rendered HTML content.
	 * @param array  $opts    Module options.
	 * @return array Link counts.
	 */
	public static function parse_links( string $content, array $opts ) : array {
		$internal = 0;
		$external = 0;

		if ( empty( $content ) ) {
			return array( 'internal' => 0, 'external' => 0 );
		}

		$site_host = (string) \wp_parse_url( \home_url(), PHP_URL_HOST );

		// Use DOMDocument for reliable parsing.
		$libxml_previous = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$html = '<div id="__functionalities_regression_wrapper">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xpath = new \DOMXPath( $dom );

		// Exclude navigation, footer, and reusable block areas.
		$exclude_selectors = array(
			'//nav//a',
			'//footer//a',
			'//*[contains(@class, "navigation")]//a',
			'//*[contains(@class, "nav-")]//a',
			'//*[contains(@class, "menu")]//a',
			'//*[contains(@class, "footer")]//a',
		);

		// Get all links.
		$all_links = $xpath->query( '//a[@href]' );
		$excluded_links = array();

		// Build list of excluded links.
		foreach ( $exclude_selectors as $selector ) {
			$excluded = $xpath->query( $selector );
			if ( $excluded instanceof \DOMNodeList ) {
				foreach ( $excluded as $node ) {
					$excluded_links[] = spl_object_hash( $node );
				}
			}
		}

		if ( $all_links instanceof \DOMNodeList ) {
			foreach ( $all_links as $link ) {
				// Skip excluded links.
				if ( in_array( spl_object_hash( $link ), $excluded_links, true ) ) {
					continue;
				}

				$href = (string) $link->getAttribute( 'href' );
				$rel = (string) $link->getAttribute( 'rel' );

				// Skip empty hrefs and anchors.
				if ( empty( $href ) || '#' === $href[0] ) {
					continue;
				}

				// Skip mailto, tel, javascript.
				$lower = strtolower( $href );
				if ( 0 === strpos( $lower, 'mailto:' ) || 0 === strpos( $lower, 'tel:' ) || 0 === strpos( $lower, 'javascript:' ) ) {
					continue;
				}

				// Optionally exclude nofollow links.
				if ( ! empty( $opts['exclude_nofollow_links'] ) && false !== strpos( strtolower( $rel ), 'nofollow' ) ) {
					continue;
				}

				// Determine if internal or external.
				$is_internal = self::is_internal_url( $href, $site_host );

				if ( $is_internal ) {
					++$internal;
				} else {
					++$external;
				}
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );

		return array(
			'internal' => $internal,
			'external' => $external,
		);
	}

	/**
	 * Check if URL is internal.
	 *
	 * @param string $href      URL to check.
	 * @param string $site_host Site hostname.
	 * @return bool True if internal.
	 */
	protected static function is_internal_url( string $href, string $site_host ) : bool {
		$href = trim( $href );

		// Relative URLs are internal.
		if ( 0 !== strpos( $href, 'http://' ) && 0 !== strpos( $href, 'https://' ) && 0 !== strpos( $href, '//' ) ) {
			return true;
		}

		$test = $href;
		if ( 0 === strpos( $href, '//' ) ) {
			$test = 'http:' . $href;
		}

		$host = (string) parse_url( $test, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return true;
		}

		return 0 === strcasecmp( $host, $site_host );
	}

	/**
	 * Count words in content.
	 *
	 * @param string $content Raw post content.
	 * @param array  $opts    Module options.
	 * @return int Word count.
	 */
	public static function count_words( string $content, array $opts ) : int {
		// Optionally remove shortcodes.
		if ( ! empty( $opts['exclude_shortcodes'] ) ) {
			$content = \strip_shortcodes( $content );
		}

		// Remove blocks if present.
		$content = \excerpt_remove_blocks( $content );

		// Strip HTML tags.
		$content = \wp_strip_all_tags( $content );

		// Remove extra whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		if ( empty( $content ) ) {
			return 0;
		}

		// Count words.
		return str_word_count( $content );
	}

	/**
	 * Parse headings from content.
	 *
	 * @param string $content Raw post content (block editor or classic).
	 * @return array Heading data with map and h1_count.
	 */
	public static function parse_headings( string $content ) : array {
		$heading_map = array();
		$h1_count = 0;

		if ( empty( $content ) ) {
			return array( 'map' => $heading_map, 'h1_count' => $h1_count );
		}

		// Try to parse blocks first (for block editor content).
		if ( \has_blocks( $content ) ) {
			$blocks = \parse_blocks( $content );
			self::extract_headings_from_blocks( $blocks, $heading_map, $h1_count );
		} else {
			// Fallback to HTML parsing.
			$rendered = \apply_filters( 'the_content', $content );
			self::extract_headings_from_html( $rendered, $heading_map, $h1_count );
		}

		return array(
			'map'      => $heading_map,
			'h1_count' => $h1_count,
		);
	}

	/**
	 * Extract headings from parsed blocks.
	 *
	 * @param array $blocks      Parsed blocks.
	 * @param array $heading_map Reference to heading map array.
	 * @param int   $h1_count    Reference to H1 count.
	 * @return void
	 */
	protected static function extract_headings_from_blocks( array $blocks, array &$heading_map, int &$h1_count ) : void {
		foreach ( $blocks as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
				$heading_map[] = $level;
				if ( 1 === $level ) {
					++$h1_count;
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::extract_headings_from_blocks( $block['innerBlocks'], $heading_map, $h1_count );
			}
		}
	}

	/**
	 * Extract headings from HTML content.
	 *
	 * @param string $html        HTML content.
	 * @param array  $heading_map Reference to heading map array.
	 * @param int    $h1_count    Reference to H1 count.
	 * @return void
	 */
	protected static function extract_headings_from_html( string $html, array &$heading_map, int &$h1_count ) : void {
		if ( empty( $html ) ) {
			return;
		}

		$libxml_previous = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xpath = new \DOMXPath( $dom );
		$headings = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

		if ( $headings instanceof \DOMNodeList ) {
			foreach ( $headings as $heading ) {
				$level = (int) substr( $heading->nodeName, 1 );
				$heading_map[] = $level;
				if ( 1 === $level ) {
					++$h1_count;
				}
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );
	}

	/**
	 * Store snapshot with rolling history.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $snapshot Snapshot data.
	 * @param array $opts     Module options.
	 * @return void
	 */
	protected static function store_snapshot( int $post_id, array $snapshot, array $opts ) : void {
		$existing = \get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $existing ) ) {
			$existing = array(
				'snapshots'       => array(),
				'rolling_average' => array(),
			);
		}

		// Add new snapshot.
		$existing['snapshots'][] = $snapshot;

		// Keep only last N snapshots.
		$keep_count = max( 1, (int) $opts['snapshot_rolling_count'] );
		$existing['snapshots'] = array_slice( $existing['snapshots'], -$keep_count );

		// Calculate rolling average.
		$existing['rolling_average'] = self::calculate_rolling_average( $existing['snapshots'] );

		\update_post_meta( $post_id, self::META_KEY, $existing );
	}

	/**
	 * Calculate rolling average from snapshots.
	 *
	 * @param array $snapshots Array of snapshots.
	 * @return array Rolling average values.
	 */
	protected static function calculate_rolling_average( array $snapshots ) : array {
		$stable_snapshots = array_filter( $snapshots, function( $s ) {
			return ! empty( $s['is_stable_version'] );
		} );

		if ( empty( $stable_snapshots ) ) {
			return array();
		}

		$count = count( $stable_snapshots );
		$totals = array(
			'internal_link_count' => 0,
			'external_link_count' => 0,
			'word_count'          => 0,
		);

		foreach ( $stable_snapshots as $snapshot ) {
			$totals['internal_link_count'] += (int) ( $snapshot['internal_link_count'] ?? 0 );
			$totals['external_link_count'] += (int) ( $snapshot['external_link_count'] ?? 0 );
			$totals['word_count'] += (int) ( $snapshot['word_count'] ?? 0 );
		}

		return array(
			'internal_link_count' => round( $totals['internal_link_count'] / $count ),
			'external_link_count' => round( $totals['external_link_count'] / $count ),
			'word_count'          => round( $totals['word_count'] / $count ),
		);
	}

	/**
	 * Get the last stable snapshot for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Last stable snapshot or null.
	 */
	public static function get_last_stable_snapshot( int $post_id ) : ?array {
		$data = \get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $data ) || empty( $data['snapshots'] ) ) {
			return null;
		}

		// Find last stable snapshot.
		$snapshots = array_reverse( $data['snapshots'] );
		foreach ( $snapshots as $snapshot ) {
			if ( ! empty( $snapshot['is_stable_version'] ) ) {
				return $snapshot;
			}
		}

		return null;
	}

	/**
	 * Get rolling average for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Rolling average or null.
	 */
	public static function get_rolling_average( int $post_id ) : ?array {
		$data = \get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $data ) || empty( $data['rolling_average'] ) ) {
			return null;
		}

		return $data['rolling_average'];
	}

	/**
	 * Detect regressions in current content compared to baseline.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of warnings (empty if no issues).
	 */
	public static function detect_regressions( int $post_id ) : array {
		$warnings = array();
		$opts = self::get_options();
		$post_settings = self::get_post_settings( $post_id );

		// Check if detection is disabled for this post.
		if ( ! empty( $post_settings['detection_disabled'] ) ) {
			return $warnings;
		}

		$post = \get_post( $post_id );
		if ( ! $post ) {
			return $warnings;
		}

		// Get baseline (last stable snapshot).
		$baseline = self::get_last_stable_snapshot( $post_id );
		if ( ! $baseline ) {
			return $warnings; // No baseline to compare against.
		}

		// Capture current state.
		$current = self::capture_snapshot( $post );

		// Check internal link drop.
		if ( ! empty( $opts['link_drop_enabled'] ) ) {
			$link_warning = self::check_link_drop( $current, $baseline, $opts );
			if ( $link_warning ) {
				$warnings[] = $link_warning;
			}
		}

		// Check word count regression.
		if ( ! empty( $opts['word_count_enabled'] ) ) {
			$word_warning = self::check_word_count_drop( $post, $current, $baseline, $opts, $post_settings );
			if ( $word_warning ) {
				$warnings[] = $word_warning;
			}
		}

		// Check heading structure.
		if ( ! empty( $opts['heading_enabled'] ) ) {
			$heading_warnings = self::check_heading_structure( $current, $opts );
			$warnings = array_merge( $warnings, $heading_warnings );
		}

		return $warnings;
	}

	/**
	 * Check for internal link drop.
	 *
	 * @param array $current  Current snapshot.
	 * @param array $baseline Baseline snapshot.
	 * @param array $opts     Module options.
	 * @return array|null Warning data or null.
	 */
	protected static function check_link_drop( array $current, array $baseline, array $opts ) : ?array {
		$current_count = (int) ( $current['internal_link_count'] ?? 0 );
		$baseline_count = (int) ( $baseline['internal_link_count'] ?? 0 );

		// No warning if baseline had no links.
		if ( 0 === $baseline_count ) {
			return null;
		}

		$drop = $baseline_count - $current_count;
		$drop_percent = ( $drop / $baseline_count ) * 100;

		$threshold_percent = (float) $opts['link_drop_percent'];
		$threshold_absolute = (int) $opts['link_drop_absolute'];

		// Trigger if drop exceeds percentage OR absolute threshold.
		if ( $drop_percent >= $threshold_percent || $drop >= $threshold_absolute ) {
			return array(
				'type'     => 'link_drop',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: 1: previous link count, 2: current link count */
					\__( 'This update reduced internal links from %1$d to %2$d compared to the previous version.', 'functionalities' ),
					$baseline_count,
					$current_count
				),
				'before'   => $baseline_count,
				'after'    => $current_count,
				'baseline_timestamp' => $baseline['timestamp'] ?? 0,
			);
		}

		return null;
	}

	/**
	 * Check for word count regression.
	 *
	 * @param \WP_Post $post          Post object.
	 * @param array    $current       Current snapshot.
	 * @param array    $baseline      Baseline snapshot.
	 * @param array    $opts          Module options.
	 * @param array    $post_settings Per-post settings.
	 * @return array|null Warning data or null.
	 */
	protected static function check_word_count_drop( \WP_Post $post, array $current, array $baseline, array $opts, array $post_settings ) : ?array {
		// Skip if marked as short-form.
		if ( ! empty( $post_settings['is_short_form'] ) ) {
			return null;
		}

		$current_count = (int) ( $current['word_count'] ?? 0 );
		$baseline_count = (int) ( $baseline['word_count'] ?? 0 );

		// No warning if baseline had no words.
		if ( 0 === $baseline_count ) {
			return null;
		}

		// Check post age requirement.
		$min_age_days = (int) $opts['word_count_min_age_days'];
		$post_date = strtotime( $post->post_date_gmt );
		$age_days = ( time() - $post_date ) / DAY_IN_SECONDS;

		if ( $age_days < $min_age_days ) {
			return null;
		}

		// Calculate drop percentage.
		$drop = $baseline_count - $current_count;
		$drop_percent = ( $drop / $baseline_count ) * 100;

		$threshold_percent = (float) $opts['word_count_drop_percent'];

		if ( $drop_percent >= $threshold_percent ) {
			return array(
				'type'     => 'word_count_drop',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: percentage drop */
					\__( 'This post is %d%% shorter than its previous published version.', 'functionalities' ),
					round( $drop_percent )
				),
				'before'   => $baseline_count,
				'after'    => $current_count,
				'drop_percent' => round( $drop_percent ),
				'baseline_timestamp' => $baseline['timestamp'] ?? 0,
			);
		}

		return null;
	}

	/**
	 * Check heading structure for issues.
	 *
	 * @param array $current Current snapshot.
	 * @param array $opts    Module options.
	 * @return array Array of warnings.
	 */
	protected static function check_heading_structure( array $current, array $opts ) : array {
		$warnings = array();
		$heading_map = $current['heading_map'] ?? array();
		$h1_count = $current['h1_count'] ?? 0;

		// Check for missing H1.
		if ( ! empty( $opts['detect_missing_h1'] ) && 0 === $h1_count && ! empty( $heading_map ) ) {
			$warnings[] = array(
				'type'     => 'heading_missing_h1',
				'severity' => 'notice',
				'message'  => \__( 'No H1 heading detected in this post.', 'functionalities' ),
			);
		}

		// Check for multiple H1s.
		if ( ! empty( $opts['detect_multiple_h1'] ) && $h1_count > 1 ) {
			$warnings[] = array(
				'type'     => 'heading_multiple_h1',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: number of H1 headings found */
					\__( 'Multiple H1 headings detected (%d found).', 'functionalities' ),
					$h1_count
				),
				'count'    => $h1_count,
			);
		}

		// Check for skipped heading levels.
		if ( ! empty( $opts['detect_skipped_levels'] ) && ! empty( $heading_map ) ) {
			$prev_level = 0;
			foreach ( $heading_map as $level ) {
				// Only flag if jumping more than one level deeper.
				if ( $prev_level > 0 && $level > $prev_level + 1 ) {
					$warnings[] = array(
						'type'     => 'heading_skipped_level',
						'severity' => 'notice',
						'message'  => sprintf(
							/* translators: 1: previous heading level, 2: current heading level */
							\__( 'Heading level skipped: H%1$d followed by H%2$d.', 'functionalities' ),
							$prev_level,
							$level
						),
						'from'     => $prev_level,
						'to'       => $level,
					);
					break; // Only report first issue.
				}
				$prev_level = $level;
			}
		}

		return $warnings;
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public static function register_rest_routes() : void {
		\register_rest_route( 'functionalities/v1', '/regression/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_get_regression_status' ),
			'permission_callback' => function() {
				return \current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		\register_rest_route( 'functionalities/v1', '/regression/(?P<id>\d+)/mark-intentional', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_mark_intentional' ),
			'permission_callback' => function() {
				return \current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		\register_rest_route( 'functionalities/v1', '/regression/(?P<id>\d+)/reset-baseline', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_reset_baseline' ),
			'permission_callback' => function() {
				return \current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		\register_rest_route( 'functionalities/v1', '/regression/(?P<id>\d+)/settings', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_update_post_settings' ),
			'permission_callback' => function() {
				return \current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );
	}

	/**
	 * REST endpoint: Get regression status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function rest_get_regression_status( \WP_REST_Request $request ) : \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( array( 'error' => 'Post not found' ), 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => 'Permission denied' ), 403 );
		}

		$warnings = self::detect_regressions( $post_id );
		$baseline = self::get_last_stable_snapshot( $post_id );
		$post_settings = self::get_post_settings( $post_id );

		return new \WP_REST_Response( array(
			'warnings'      => $warnings,
			'baseline'      => $baseline,
			'post_settings' => $post_settings,
			'has_baseline'  => ! empty( $baseline ),
		) );
	}

	/**
	 * REST endpoint: Mark change as intentional.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function rest_mark_intentional( \WP_REST_Request $request ) : \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( array( 'error' => 'Post not found' ), 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => 'Permission denied' ), 403 );
		}

		// Mark current snapshot as stable (intentional change).
		$opts = self::get_options();
		$snapshot = self::capture_snapshot( $post );
		$snapshot['is_stable_version'] = true;
		self::store_snapshot( $post_id, $snapshot, $opts );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * REST endpoint: Reset baseline.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function rest_reset_baseline( \WP_REST_Request $request ) : \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( array( 'error' => 'Post not found' ), 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => 'Permission denied' ), 403 );
		}

		// Clear all snapshots and start fresh.
		\delete_post_meta( $post_id, self::META_KEY );

		// Capture new baseline.
		$opts = self::get_options();
		$snapshot = self::capture_snapshot( $post );
		self::store_snapshot( $post_id, $snapshot, $opts );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * REST endpoint: Update per-post settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function rest_update_post_settings( \WP_REST_Request $request ) : \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( array( 'error' => 'Post not found' ), 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => 'Permission denied' ), 403 );
		}

		$params = $request->get_json_params();
		$allowed = array( 'detection_disabled', 'is_short_form' );
		$settings = array();

		foreach ( $allowed as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$settings[ $key ] = (bool) $params[ $key ];
			}
		}

		if ( ! empty( $settings ) ) {
			self::update_post_settings( $post_id, $settings );
		}

		return new \WP_REST_Response( array(
			'success'  => true,
			'settings' => self::get_post_settings( $post_id ),
		) );
	}

	/**
	 * Setup post list columns.
	 *
	 * @return void
	 */
	public static function setup_post_columns() : void {
		$opts = self::get_options();

		if ( empty( $opts['show_post_column'] ) ) {
			return;
		}

		$enabled_types = (array) $opts['post_types'];

		foreach ( $enabled_types as $post_type ) {
			\add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_column' ) );
			\add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Add regression column to post list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_column( array $columns ) : array {
		$columns['functionalities_regression'] = '<span class="dashicons dashicons-shield" title="' . \esc_attr__( 'Content Integrity', 'functionalities' ) . '"></span>';
		return $columns;
	}

	/**
	 * Render regression column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( string $column, int $post_id ) : void {
		if ( 'functionalities_regression' !== $column ) {
			return;
		}

		$warnings = self::detect_regressions( $post_id );

		if ( empty( $warnings ) ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;" title="' . \esc_attr__( 'No issues detected', 'functionalities' ) . '"></span>';
		} else {
			$count = count( $warnings );
			$title = sprintf(
				/* translators: %d: number of warnings */
				\_n( '%d issue detected', '%d issues detected', $count, 'functionalities' ),
				$count
			);
			echo '<span class="dashicons dashicons-warning" style="color:#dba617;" title="' . \esc_attr( $title ) . '"></span>';
		}
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() : void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Check if current post type is enabled.
		global $post;
		if ( ! $post ) {
			return;
		}

		$enabled_types = (array) $opts['post_types'];
		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return;
		}

		\wp_enqueue_script(
			'functionalities-content-regression',
			FUNCTIONALITIES_URL . 'assets/js/content-regression.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
			FUNCTIONALITIES_VERSION,
			true
		);

		\wp_localize_script( 'functionalities-content-regression', 'functionalitiesRegressionData', array(
			'postId'   => $post->ID,
			'restBase' => \rest_url( 'functionalities/v1/regression/' ),
			'nonce'    => \wp_create_nonce( 'wp_rest' ),
			'i18n'     => array(
				'panelTitle'        => \__( 'Content Integrity', 'functionalities' ),
				'noIssues'          => \__( 'No structural issues detected.', 'functionalities' ),
				'noBaseline'        => \__( 'No baseline snapshot available yet. Changes will be tracked after first publish.', 'functionalities' ),
				'reviewChanges'     => \__( 'Review changes', 'functionalities' ),
				'ignoreThisUpdate'  => \__( 'Ignore for this update', 'functionalities' ),
				'markIntentional'   => \__( 'Mark change as intentional', 'functionalities' ),
				'resetBaseline'     => \__( 'Reset baseline', 'functionalities' ),
				'disableDetection'  => \__( 'Disable detection for this post', 'functionalities' ),
				'markAsShortForm'   => \__( 'Mark as short-form content', 'functionalities' ),
				'lastSnapshot'      => \__( 'Last snapshot:', 'functionalities' ),
				'loading'           => \__( 'Checking content integrity...', 'functionalities' ),
			),
		) );

		\wp_enqueue_style(
			'functionalities-content-regression',
			FUNCTIONALITIES_URL . 'assets/css/content-regression.css',
			array(),
			FUNCTIONALITIES_VERSION
		);
	}

	/**
	 * AJAX handler: Mark change as intentional.
	 *
	 * @return void
	 */
	public static function ajax_mark_intentional() : void {
		\check_ajax_referer( 'functionalities_regression', 'nonce' );

		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = \get_post( $post_id );

		if ( ! $post || ! \current_user_can( 'edit_post', $post_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid post.', 'functionalities' ) ) );
		}

		$opts = self::get_options();
		$snapshot = self::capture_snapshot( $post );
		$snapshot['is_stable_version'] = true;
		self::store_snapshot( $post_id, $snapshot, $opts );

		\wp_send_json_success( array( 'message' => \__( 'Change marked as intentional.', 'functionalities' ) ) );
	}

	/**
	 * AJAX handler: Reset baseline.
	 *
	 * @return void
	 */
	public static function ajax_reset_baseline() : void {
		\check_ajax_referer( 'functionalities_regression', 'nonce' );

		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = \get_post( $post_id );

		if ( ! $post || ! \current_user_can( 'edit_post', $post_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid post.', 'functionalities' ) ) );
		}

		\delete_post_meta( $post_id, self::META_KEY );

		$opts = self::get_options();
		$snapshot = self::capture_snapshot( $post );
		self::store_snapshot( $post_id, $snapshot, $opts );

		\wp_send_json_success( array( 'message' => \__( 'Baseline has been reset.', 'functionalities' ) ) );
	}

	/**
	 * AJAX handler: Get regression status.
	 *
	 * @return void
	 */
	public static function ajax_get_regression_status() : void {
		\check_ajax_referer( 'functionalities_regression', 'nonce' );

		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = \get_post( $post_id );

		if ( ! $post || ! \current_user_can( 'edit_post', $post_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid post.', 'functionalities' ) ) );
		}

		$warnings = self::detect_regressions( $post_id );
		$baseline = self::get_last_stable_snapshot( $post_id );
		$post_settings = self::get_post_settings( $post_id );

		\wp_send_json_success( array(
			'warnings'      => $warnings,
			'baseline'      => $baseline,
			'post_settings' => $post_settings,
			'has_baseline'  => ! empty( $baseline ),
		) );
	}
}
