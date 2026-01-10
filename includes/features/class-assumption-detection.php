<?php
/**
 * Assumption Detection.
 *
 * Detects when implicit assumptions about a WordPress site stop being true.
 * This module notices silent breakages caused by plugin additions, theme changes,
 * or code snippets.
 *
 * Detectors:
 * - Schema collision (multiple JSON-LD sources)
 * - Analytics duplication (same tracking ID from multiple sources)
 * - Font redundancy (same font family from multiple sources)
 * - Inline CSS growth (performance debt accumulation)
 * - jQuery version conflicts (multiple jQuery versions)
 * - Meta tag duplication (duplicate viewport, robots, etc.)
 * - REST API exposure (user enumeration)
 * - Lazy loading conflicts (multiple implementations)
 *
 * @package Functionalities\Features
 * @since 0.9.0
 * @version 0.9.5
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assumption Detection class.
 */
class Assumption_Detection {

	/**
	 * Option key for storing detected assumptions.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'functionalities_assumptions_detected';

	/**
	 * Option key for ignored assumptions.
	 *
	 * @var string
	 */
	const IGNORED_KEY = 'functionalities_assumptions_ignored';

	/**
	 * Option key for CSS baseline tracking.
	 *
	 * @var string
	 */
	const CSS_BASELINE_KEY = 'functionalities_inline_css_baseline';

	/**
	 * Cached frontend output to avoid multiple calls to wp_head/wp_footer.
	 *
	 * @var array|null
	 */
	private static $frontend_output_cache = null;

	/**
	 * Initialize assumption detection.
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Run detection on admin page load (not every page, just functionalities pages).
		\add_action( 'load-toplevel_page_functionalities', array( __CLASS__, 'run_detection' ) );

		// Run on plugin/theme activation.
		\add_action( 'activated_plugin', array( __CLASS__, 'schedule_detection' ) );
		\add_action( 'switch_theme', array( __CLASS__, 'schedule_detection' ) );

		// Run on header/footer code change.
		\add_action( 'update_option_functionalities_snippets', array( __CLASS__, 'schedule_detection' ) );

		// AJAX handlers.
		\add_action( 'wp_ajax_functionalities_ignore_assumption', array( __CLASS__, 'ajax_ignore_assumption' ) );
		\add_action( 'wp_ajax_functionalities_acknowledge_assumption', array( __CLASS__, 'ajax_acknowledge_assumption' ) );
		\add_action( 'wp_ajax_functionalities_snooze_assumption', array( __CLASS__, 'ajax_snooze_assumption' ) );
	}

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Get module options.
	 *
	 * @return array Options array.
	 */
	public static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enabled'                       => false,
			'detect_schema_collision'       => true,
			'detect_analytics_dupe'         => true,
			'detect_font_redundancy'        => true,
			'detect_inline_css_growth'      => true,
			'inline_css_threshold_kb'       => 50,
			'detect_jquery_conflicts'       => true,
			'detect_meta_duplication'       => true,
			'detect_rest_exposure'          => true,
			'detect_lazy_load_conflict'     => true,
			'detect_mixed_content'          => true,
			'detect_missing_security_headers' => true,
			'detect_debug_exposure'         => true,
			'detect_cron_issues'            => true,
		);
		$opts = (array) \get_option( 'functionalities_assumption_detection', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Schedule detection for later (to avoid running on every hook).
	 *
	 * @return void
	 */
	public static function schedule_detection() : void {
		\set_transient( 'functionalities_run_assumption_detection', true, HOUR_IN_SECONDS );
	}

	/**
	 * Run all enabled detectors.
	 *
	 * @return void
	 */
	public static function run_detection() : void {
		$opts = self::get_options();
		$warnings = array();
		$ignored = self::get_ignored_assumptions();

		// Check if we need to run detection.
		$should_run = \get_transient( 'functionalities_run_assumption_detection' );
		$last_run = \get_option( 'functionalities_assumptions_last_run', 0 );
		$cache_duration = 6 * HOUR_IN_SECONDS;

		// Only run if scheduled or cache expired.
		if ( ! $should_run && ( time() - $last_run ) < $cache_duration ) {
			return;
		}

		// Clear the trigger.
		\delete_transient( 'functionalities_run_assumption_detection' );

		// Run detectors.
		if ( ! empty( $opts['detect_schema_collision'] ) ) {
			$schema_warnings = self::detect_schema_collisions();
			$warnings = array_merge( $warnings, $schema_warnings );
		}

		if ( ! empty( $opts['detect_analytics_dupe'] ) ) {
			$analytics_warnings = self::detect_analytics_duplication();
			$warnings = array_merge( $warnings, $analytics_warnings );
		}

		if ( ! empty( $opts['detect_font_redundancy'] ) ) {
			$font_warnings = self::detect_font_redundancy();
			$warnings = array_merge( $warnings, $font_warnings );
		}

		if ( ! empty( $opts['detect_inline_css_growth'] ) ) {
			$css_warnings = self::detect_inline_css_growth( $opts );
			$warnings = array_merge( $warnings, $css_warnings );
		}

		if ( ! empty( $opts['detect_jquery_conflicts'] ) ) {
			$jquery_warnings = self::detect_jquery_conflicts();
			$warnings = array_merge( $warnings, $jquery_warnings );
		}

		if ( ! empty( $opts['detect_meta_duplication'] ) ) {
			$meta_warnings = self::detect_meta_duplication();
			$warnings = array_merge( $warnings, $meta_warnings );
		}

		if ( ! empty( $opts['detect_rest_exposure'] ) ) {
			$rest_warnings = self::detect_rest_exposure();
			$warnings = array_merge( $warnings, $rest_warnings );
		}

		if ( ! empty( $opts['detect_lazy_load_conflict'] ) ) {
			$lazy_warnings = self::detect_lazy_load_conflicts();
			$warnings = array_merge( $warnings, $lazy_warnings );
		}

		if ( ! empty( $opts['detect_mixed_content'] ) ) {
			$mixed_warnings = self::detect_mixed_content();
			$warnings = array_merge( $warnings, $mixed_warnings );
		}

		if ( ! empty( $opts['detect_missing_security_headers'] ) ) {
			$header_warnings = self::detect_missing_security_headers();
			$warnings = array_merge( $warnings, $header_warnings );
		}

		if ( ! empty( $opts['detect_debug_exposure'] ) ) {
			$debug_warnings = self::detect_debug_exposure();
			$warnings = array_merge( $warnings, $debug_warnings );
		}

		if ( ! empty( $opts['detect_cron_issues'] ) ) {
			$cron_warnings = self::detect_cron_issues();
			$warnings = array_merge( $warnings, $cron_warnings );
		}

		// Filter out ignored warnings.
		$warnings = array_filter( $warnings, function( $warning ) use ( $ignored ) {
			$hash = self::get_warning_hash( $warning );
			return ! isset( $ignored[ $hash ] ) || $ignored[ $hash ]['expires'] < time();
		} );

		// Store results.
		\update_option( self::OPTION_KEY, $warnings );
		\update_option( 'functionalities_assumptions_last_run', time() );
	}

	/**
	 * Detect schema collisions in page output.
	 *
	 * @return array Array of warnings.
	 */
	public static function detect_schema_collisions() : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$full_output = $output['full'];

		// Find all JSON-LD scripts.
		preg_match_all(
			'/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
			$full_output,
			$matches
		);

		if ( empty( $matches[1] ) ) {
			return $warnings;
		}

		$schema_types = array();

		foreach ( $matches[1] as $json_content ) {
			$data = json_decode( $json_content, true );
			if ( ! $data ) {
				continue;
			}

			// Handle @graph structure.
			$items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );

			foreach ( $items as $item ) {
				if ( isset( $item['@type'] ) ) {
					$type = is_array( $item['@type'] ) ? implode( ', ', $item['@type'] ) : $item['@type'];

					if ( ! isset( $schema_types[ $type ] ) ) {
						$schema_types[ $type ] = array();
					}

					// Try to identify the source.
					$source = self::identify_schema_source( $item );
					$schema_types[ $type ][] = $source;
				}
			}
		}

		// Check for duplicates.
		foreach ( $schema_types as $type => $sources ) {
			if ( count( $sources ) > 1 ) {
				$unique_sources = array_unique( $sources );
				if ( count( $unique_sources ) > 1 ) {
					$warnings[] = array(
						'type'      => 'schema_collision',
						'message'   => sprintf(
							/* translators: 1: schema type, 2: source list */
							\__( 'Multiple sources are outputting %1$s schema (%2$s).', 'functionalities' ),
							$type,
							implode( ' + ', $unique_sources )
						),
						'location'  => sprintf(
							/* translators: %s: source list */
							\__( 'Found in page &lt;head&gt; output from: %s', 'functionalities' ),
							implode( ', ', $unique_sources )
						),
						'reason'    => \__( 'Multiple schema sources cause duplicate structured data in search results, potentially confusing search engines and diluting SEO benefits. Only one source should output each schema type.', 'functionalities' ),
						'details'   => array(
							'schema_type' => $type,
							'sources'     => $sources,
							'count'       => count( $sources ),
						),
						'detected'  => time(),
					);
				}
			}
		}

		return $warnings;
	}

	/**
	 * Identify the source of a schema item.
	 *
	 * @param array $item Schema item.
	 * @return string Source identifier.
	 */
	protected static function identify_schema_source( array $item ) : string {
		// Check for known plugin patterns.
		$json = json_encode( $item );

		if ( strpos( $json, 'rank-math' ) !== false || strpos( $json, 'rankMath' ) !== false ) {
			return 'Rank Math';
		}
		if ( strpos( $json, 'yoast' ) !== false ) {
			return 'Yoast SEO';
		}
		if ( strpos( $json, 'seopress' ) !== false ) {
			return 'SEOPress';
		}
		if ( strpos( $json, 'aioseo' ) !== false ) {
			return 'All in One SEO';
		}
		if ( strpos( $json, 'the-seo-framework' ) !== false ) {
			return 'The SEO Framework';
		}
		if ( strpos( $json, 'schema-pro' ) !== false || strpos( $json, 'SchemaPro' ) !== false ) {
			return 'Schema Pro';
		}
		if ( strpos( $json, 'woocommerce' ) !== false || strpos( $json, 'Product' ) !== false ) {
			return 'WooCommerce';
		}

		// Check if it might be from theme or custom code.
		$theme = \wp_get_theme();
		if ( strpos( $json, $theme->get_stylesheet() ) !== false ) {
			return 'Theme';
		}

		return 'Unknown source';
	}

	/**
	 * Detect analytics duplication.
	 *
	 * @return array Array of warnings.
	 */
	public static function detect_analytics_duplication() : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$full_output = $output['full'];

		// Also check enqueued scripts.
		global $wp_scripts;
		$script_urls = array();
		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( $wp_scripts->registered as $handle => $script ) {
				if ( ! empty( $script->src ) ) {
					$script_urls[ $handle ] = $script->src;
				}
			}
		}

		// Patterns to detect.
		$analytics_patterns = array(
			'ga4' => array(
				'pattern' => '/["\']?(G-[A-Z0-9]+)["\']?/i',
				'name'    => 'Google Analytics 4',
			),
			'ua' => array(
				'pattern' => '/["\']?(UA-\d+-\d+)["\']?/i',
				'name'    => 'Universal Analytics',
			),
			'gtm' => array(
				'pattern' => '/["\']?(GTM-[A-Z0-9]+)["\']?/i',
				'name'    => 'Google Tag Manager',
			),
			'fb_pixel' => array(
				'pattern' => '/fbq\([\'"]init[\'"],\s*[\'"](\d+)[\'"]\)/i',
				'name'    => 'Facebook Pixel',
			),
		);

		foreach ( $analytics_patterns as $key => $config ) {
			preg_match_all( $config['pattern'], $full_output, $matches );

			if ( ! empty( $matches[1] ) ) {
				$ids = array_unique( $matches[1] );

				foreach ( $ids as $id ) {
					// Count occurrences.
					$count = preg_match_all( '/' . preg_quote( $id, '/' ) . '/i', $full_output );

					if ( $count > 1 ) {
						$locations = self::find_script_locations( $full_output, $id );

						$warnings[] = array(
							'type'      => 'analytics_duplication',
							'message'   => sprintf(
								/* translators: 1: analytics name, 2: ID, 3: count */
								\__( '%1$s (%2$s) is loaded %3$d times from different sources.', 'functionalities' ),
								$config['name'],
								$id,
								$count
							),
							'location'  => ! empty( $locations )
								? sprintf(
									/* translators: %s: location list */
									\__( 'Detected in: %s', 'functionalities' ),
									implode( ', ', $locations )
								)
								: \__( 'Found in page HTML output', 'functionalities' ),
							'reason'    => \__( 'Duplicate tracking codes cause inflated pageview counts, skewed session data, and can slow page load. Each analytics property should only be initialized once per page.', 'functionalities' ),
							'details'   => array(
								'analytics_type' => $config['name'],
								'tracking_id'    => $id,
								'count'          => $count,
								'locations'      => $locations,
							),
							'detected'  => time(),
						);
					}
				}
			}
		}

		return $warnings;
	}

	/**
	 * Find where a tracking ID appears in output.
	 *
	 * @param string $output Full output.
	 * @param string $id     Tracking ID.
	 * @return array Location descriptions.
	 */
	protected static function find_script_locations( string $output, string $id ) : array {
		$locations = array();

		// Check for gtag.js.
		if ( preg_match( '/googletagmanager\.com\/gtag\/js\?id=' . preg_quote( $id, '/' ) . '/i', $output ) ) {
			$locations[] = 'gtag.js (external)';
		}

		// Check for inline gtag config.
		if ( preg_match( '/gtag\([\'"]config[\'"],\s*[\'"]' . preg_quote( $id, '/' ) . '/i', $output ) ) {
			$locations[] = 'gtag config (inline)';
		}

		// Check for dataLayer push.
		if ( preg_match( '/dataLayer\.push.*' . preg_quote( $id, '/' ) . '/i', $output ) ) {
			$locations[] = 'dataLayer push';
		}

		return $locations;
	}

	/**
	 * Detect font redundancy.
	 *
	 * @return array Array of warnings.
	 */
	public static function detect_font_redundancy() : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$head_output = $output['head'];

		// Check enqueued styles.
		global $wp_styles;
		$style_sources = array();

		if ( $wp_styles instanceof \WP_Styles ) {
			foreach ( $wp_styles->registered as $handle => $style ) {
				if ( ! empty( $style->src ) ) {
					$style_sources[ $handle ] = $style->src;
				}
			}
		}

		$fonts_found = array();

		// Find Google Fonts URLs.
		preg_match_all(
			'/fonts\.googleapis\.com\/css2?\?family=([^"\'&>\s]+)/i',
			$head_output,
			$google_matches
		);

		if ( ! empty( $google_matches[1] ) ) {
			foreach ( $google_matches[1] as $family_string ) {
				// Parse family names.
				$families = explode( '|', urldecode( $family_string ) );
				foreach ( $families as $family ) {
					$family_name = preg_replace( '/[:@].*$/', '', $family );
					$family_name = str_replace( '+', ' ', $family_name );

					if ( ! isset( $fonts_found[ $family_name ] ) ) {
						$fonts_found[ $family_name ] = array();
					}
					$fonts_found[ $family_name ][] = 'Google Fonts';
				}
			}
		}

		// Find @font-face declarations.
		preg_match_all(
			'/@font-face\s*\{[^}]*font-family:\s*[\'"]?([^\'";,}]+)/i',
			$head_output,
			$fontface_matches
		);

		if ( ! empty( $fontface_matches[1] ) ) {
			foreach ( $fontface_matches[1] as $family_name ) {
				$family_name = trim( $family_name );
				if ( ! isset( $fonts_found[ $family_name ] ) ) {
					$fonts_found[ $family_name ] = array();
				}
				$fonts_found[ $family_name ][] = 'Inline @font-face';
			}
		}

		// Check for redundancy.
		foreach ( $fonts_found as $family => $sources ) {
			if ( count( $sources ) > 1 ) {
				$warnings[] = array(
					'type'      => 'font_redundancy',
					'message'   => sprintf(
						/* translators: 1: font family, 2: count */
						\__( 'Font family "%1$s" is loaded from %2$d different sources.', 'functionalities' ),
						$family,
						count( $sources )
					),
					'location'  => sprintf(
						/* translators: %s: source list */
						\__( 'Loaded via: %s', 'functionalities' ),
						implode( ', ', array_unique( $sources ) )
					),
					'reason'    => \__( 'Loading the same font from multiple sources wastes bandwidth and increases page load time. Consolidate font loading to a single source.', 'functionalities' ),
					'details'   => array(
						'font_family' => $family,
						'sources'     => $sources,
						'count'       => count( $sources ),
					),
					'detected'  => time(),
				);
			}
		}

		return $warnings;
	}

	/**
	 * Detect inline CSS growth.
	 *
	 * @param array $opts Module options.
	 * @return array Array of warnings.
	 */
	public static function detect_inline_css_growth( array $opts ) : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$head_output = $output['head'];

		// Find all inline styles.
		preg_match_all(
			'/<style[^>]*>(.*?)<\/style>/is',
			$head_output,
			$style_matches
		);

		$total_size = 0;
		$sources = array();

		if ( ! empty( $style_matches[1] ) ) {
			foreach ( $style_matches[1] as $css ) {
				$size = strlen( $css );
				$total_size += $size;

				// Categorize by common patterns.
				if ( strpos( $css, 'wp-block' ) !== false ) {
					$sources['Block Styles'] = ( $sources['Block Styles'] ?? 0 ) + $size;
				} elseif ( strpos( $css, 'customizer' ) !== false || strpos( $css, 'custom-css' ) !== false ) {
					$sources['Customizer'] = ( $sources['Customizer'] ?? 0 ) + $size;
				} else {
					$sources['Other Inline'] = ( $sources['Other Inline'] ?? 0 ) + $size;
				}
			}
		}

		// Get baseline.
		$baseline = \get_option( self::CSS_BASELINE_KEY, array() );

		// Calculate size in KB.
		$size_kb = round( $total_size / 1024, 1 );
		$threshold_kb = (float) $opts['inline_css_threshold_kb'];

		// Store current size.
		$baseline['history'][] = array(
			'size'      => $total_size,
			'timestamp' => time(),
		);

		// Keep only last 30 entries.
		if ( count( $baseline['history'] ) > 30 ) {
			$baseline['history'] = array_slice( $baseline['history'], -30 );
		}

		// Calculate rolling average.
		$sizes = array_column( $baseline['history'], 'size' );
		$avg_size = count( $sizes ) > 1 ? array_sum( $sizes ) / count( $sizes ) : $total_size;
		$avg_kb = round( $avg_size / 1024, 1 );

		\update_option( self::CSS_BASELINE_KEY, $baseline );

		// Check thresholds.
		if ( $size_kb > $threshold_kb ) {
			// Build sources location string.
			$sources_list = array();
			foreach ( $sources as $source => $bytes ) {
				$sources_list[] = sprintf( '%s (%s KB)', $source, round( $bytes / 1024, 1 ) );
			}

			$warnings[] = array(
				'type'      => 'inline_css_growth',
				'message'   => sprintf(
					/* translators: 1: current size, 2: threshold */
					\__( 'Inline CSS output is %1$s KB (threshold: %2$s KB).', 'functionalities' ),
					$size_kb,
					$threshold_kb
				),
				'location'  => ! empty( $sources_list )
					? sprintf(
						/* translators: %s: source breakdown */
						\__( 'Breakdown: %s', 'functionalities' ),
						implode( ', ', $sources_list )
					)
					: \__( 'In page &lt;head&gt; section', 'functionalities' ),
				'reason'    => \__( 'Large inline CSS blocks the initial page render and cannot be cached by browsers. Consider moving styles to external stylesheets or reducing unused CSS.', 'functionalities' ),
				'details'   => array(
					'current_size_kb'  => $size_kb,
					'threshold_kb'     => $threshold_kb,
					'average_size_kb'  => $avg_kb,
					'sources'          => $sources,
				),
				'detected'  => time(),
			);
		}

		// Check for sharp increase.
		if ( count( $sizes ) > 5 && $size_kb > $avg_kb * 1.5 ) {
			$increase_percent = round( ( $size_kb / $avg_kb - 1 ) * 100 );
			$warnings[] = array(
				'type'      => 'inline_css_spike',
				'message'   => sprintf(
					/* translators: 1: average size, 2: current size */
					\__( 'Inline CSS increased from %1$s KB average to %2$s KB.', 'functionalities' ),
					$avg_kb,
					$size_kb
				),
				'location'  => \__( 'In page &lt;head&gt; section &lt;style&gt; tags', 'functionalities' ),
				'reason'    => sprintf(
					/* translators: %d: percentage increase */
					\__( 'A %d%% spike in inline CSS suggests a plugin, theme update, or content change added significant styles. This may indicate CSS bloat from unused features.', 'functionalities' ),
					$increase_percent
				),
				'details'   => array(
					'current_size_kb' => $size_kb,
					'average_size_kb' => $avg_kb,
					'increase_percent' => $increase_percent,
				),
				'detected'  => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect jQuery version conflicts.
	 *
	 * Checks for multiple versions of jQuery or jQuery being loaded
	 * from different sources (CDN vs local).
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_jquery_conflicts() : array {
		$warnings = array();

		// Check registered scripts.
		global $wp_scripts;
		if ( ! $wp_scripts instanceof \WP_Scripts ) {
			return $warnings;
		}

		$jquery_scripts = array();
		$jquery_sources = array();

		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( empty( $script->src ) ) {
				continue;
			}

			$src = $script->src;

			// Check for jQuery variants.
			if ( preg_match( '/jquery(?:[-.](?:min|core|migrate|slim))?(?:[-.](\d+\.\d+(?:\.\d+)?))?\.(?:min\.)?js/i', $src, $matches ) ) {
				$version = isset( $matches[1] ) ? $matches[1] : 'unknown';

				// Determine source type.
				if ( strpos( $src, 'ajax.googleapis.com' ) !== false ||
					 strpos( $src, 'cdnjs.cloudflare.com' ) !== false ||
					 strpos( $src, 'code.jquery.com' ) !== false ||
					 strpos( $src, 'cdn.jsdelivr.net' ) !== false ) {
					$source_type = 'CDN';
				} elseif ( strpos( $src, 'wp-includes' ) !== false ) {
					$source_type = 'WordPress Core';
				} else {
					$source_type = 'Plugin/Theme';
				}

				$jquery_scripts[ $handle ] = array(
					'version' => $version,
					'source'  => $source_type,
					'src'     => $src,
				);
				$jquery_sources[] = $source_type;
			}
		}

		// Check for conflicts.
		if ( count( $jquery_scripts ) > 1 ) {
			$versions = array_unique( array_column( $jquery_scripts, 'version' ) );
			$sources  = array_unique( $jquery_sources );

			if ( count( $versions ) > 1 || count( $sources ) > 1 ) {
				// Build handle list for location.
				$handle_list = array_keys( $jquery_scripts );

				$warnings[] = array(
					'type'     => 'jquery_conflict',
					'message'  => sprintf(
						/* translators: 1: number of jQuery instances, 2: sources */
						\__( 'Multiple jQuery instances detected (%1$d scripts from: %2$s).', 'functionalities' ),
						count( $jquery_scripts ),
						implode( ', ', $sources )
					),
					'location' => sprintf(
						/* translators: %s: script handle list */
						\__( 'Registered script handles: %s', 'functionalities' ),
						implode( ', ', $handle_list )
					),
					'reason'   => \__( 'Multiple jQuery versions cause JavaScript errors, plugin conflicts, and wasted bandwidth. Only WordPress Core jQuery should be loaded; plugins/themes should use wp_enqueue_script dependencies.', 'functionalities' ),
					'details'  => array(
						'scripts'  => $jquery_scripts,
						'versions' => $versions,
						'sources'  => $sources,
					),
					'detected' => time(),
				);
			}
		}

		return $warnings;
	}

	/**
	 * Detect duplicate meta tags.
	 *
	 * Checks for duplicate viewport, robots, description, and OG meta tags
	 * that might be output by multiple plugins.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_meta_duplication() : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$head_output = $output['head'];

		// Meta tags to check for duplicates.
		$meta_patterns = array(
			'viewport' => array(
				'pattern' => '/<meta[^>]*name=["\']viewport["\'][^>]*>/i',
				'label'   => 'viewport',
			),
			'robots' => array(
				'pattern' => '/<meta[^>]*name=["\']robots["\'][^>]*>/i',
				'label'   => 'robots',
			),
			'description' => array(
				'pattern' => '/<meta[^>]*name=["\']description["\'][^>]*>/i',
				'label'   => 'description',
			),
			'og:title' => array(
				'pattern' => '/<meta[^>]*property=["\']og:title["\'][^>]*>/i',
				'label'   => 'og:title',
			),
			'og:description' => array(
				'pattern' => '/<meta[^>]*property=["\']og:description["\'][^>]*>/i',
				'label'   => 'og:description',
			),
			'og:image' => array(
				'pattern' => '/<meta[^>]*property=["\']og:image["\'][^>]*>/i',
				'label'   => 'og:image',
			),
			'twitter:card' => array(
				'pattern' => '/<meta[^>]*name=["\']twitter:card["\'][^>]*>/i',
				'label'   => 'twitter:card',
			),
		);

		$duplicates = array();

		foreach ( $meta_patterns as $key => $config ) {
			preg_match_all( $config['pattern'], $head_output, $matches );

			if ( ! empty( $matches[0] ) && count( $matches[0] ) > 1 ) {
				$duplicates[ $config['label'] ] = count( $matches[0] );
			}
		}

		if ( ! empty( $duplicates ) ) {
			$dupe_list = array();
			foreach ( $duplicates as $tag => $count ) {
				$dupe_list[] = sprintf( '%s (%d)', $tag, $count );
			}

			$warnings[] = array(
				'type'     => 'meta_duplication',
				'message'  => sprintf(
					/* translators: %s: list of duplicate meta tags */
					\__( 'Duplicate meta tags detected: %s.', 'functionalities' ),
					implode( ', ', $dupe_list )
				),
				'location' => \__( 'In page &lt;head&gt; section &lt;meta&gt; tags', 'functionalities' ),
				'reason'   => \__( 'Duplicate meta tags confuse search engines and social platforms about which value to use. Common causes: multiple SEO plugins, theme meta output, or manual code additions.', 'functionalities' ),
				'details'  => array(
					'duplicates' => $duplicates,
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect REST API exposure risks.
	 *
	 * Checks if the REST API is exposing user information publicly.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_rest_exposure() : array {
		$warnings = array();

		// Check if users endpoint is accessible.
		$rest_url = \rest_url( 'wp/v2/users' );

		// Make a HEAD request to check accessibility.
		$response = \wp_remote_head( $rest_url, array(
			'timeout'   => 5,
			'sslverify' => false,
		) );

		if ( ! \is_wp_error( $response ) ) {
			$status_code = \wp_remote_retrieve_response_code( $response );

			// If users endpoint returns 200, it's publicly accessible.
			if ( $status_code === 200 ) {
				$warnings[] = array(
					'type'     => 'rest_exposure',
					'message'  => \__( 'REST API users endpoint is publicly accessible (user enumeration possible).', 'functionalities' ),
					'location' => sprintf(
						/* translators: %s: REST API URL */
						\__( 'Endpoint: %s', 'functionalities' ),
						$rest_url
					),
					'reason'   => \__( 'Publicly accessible user endpoints allow attackers to enumerate usernames for targeted brute-force or social engineering attacks. Consider restricting REST API access to authenticated users.', 'functionalities' ),
					'details'  => array(
						'endpoint' => $rest_url,
						'status'   => $status_code,
						'risk'     => 'User enumeration allows attackers to discover usernames for brute-force attacks.',
					),
					'detected' => time(),
				);
			}
		}

		// Check for exposed oEmbed data.
		$home_url = \home_url();
		$oembed_url = \rest_url( 'oembed/1.0/embed' ) . '?url=' . urlencode( $home_url );

		$oembed_response = \wp_remote_get( $oembed_url, array(
			'timeout'   => 5,
			'sslverify' => false,
		) );

		if ( ! \is_wp_error( $oembed_response ) ) {
			$body = \wp_remote_retrieve_body( $oembed_response );
			$data = json_decode( $body, true );

			if ( ! empty( $data['author_name'] ) ) {
				$warnings[] = array(
					'type'     => 'oembed_author_exposure',
					'message'  => sprintf(
						/* translators: %s: author name */
						\__( 'oEmbed exposes author name publicly: %s.', 'functionalities' ),
						$data['author_name']
					),
					'location' => \__( 'oEmbed REST endpoint response', 'functionalities' ),
					'reason'   => \__( 'oEmbed data is fetched when your content is embedded elsewhere. Exposing author names can reveal admin usernames to potential attackers.', 'functionalities' ),
					'details'  => array(
						'author_name' => $data['author_name'],
						'endpoint'    => $oembed_url,
					),
					'detected' => time(),
				);
			}
		}

		return $warnings;
	}

	/**
	 * Detect lazy loading conflicts.
	 *
	 * Checks for multiple lazy loading implementations that might conflict.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_lazy_load_conflicts() : array {
		$warnings = array();

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$full_output = $output['full'];

		// Known lazy loading libraries and patterns.
		$lazy_patterns = array(
			'native' => array(
				'pattern' => '/loading=["\']lazy["\']/i',
				'name'    => 'Native Browser Lazy Loading',
			),
			'lazysizes' => array(
				'pattern' => '/lazysizes(?:\.min)?\.js/i',
				'name'    => 'lazysizes.js',
			),
			'lozad' => array(
				'pattern' => '/lozad(?:\.min)?\.js/i',
				'name'    => 'lozad.js',
			),
			'lazyload_vanilla' => array(
				'pattern' => '/vanilla-lazyload|lazyload(?:\.min)?\.js/i',
				'name'    => 'vanilla-lazyload',
			),
			'wp_rocket' => array(
				'pattern' => '/wp-rocket.*lazyload|rocket-lazyload/i',
				'name'    => 'WP Rocket Lazy Load',
			),
			'jetpack' => array(
				'pattern' => '/jetpack.*lazy|lazy-images/i',
				'name'    => 'Jetpack Lazy Images',
			),
			'a3_lazy' => array(
				'pattern' => '/a3-lazy-load/i',
				'name'    => 'a3 Lazy Load',
			),
			'smush' => array(
				'pattern' => '/smush.*lazy|wp-smush-lazy/i',
				'name'    => 'Smush Lazy Load',
			),
			'perfmatters' => array(
				'pattern' => '/perfmatters.*lazy/i',
				'name'    => 'Perfmatters Lazy Load',
			),
		);

		$detected_libs = array();

		foreach ( $lazy_patterns as $key => $config ) {
			if ( preg_match( $config['pattern'], $full_output ) ) {
				$detected_libs[] = $config['name'];
			}
		}

		// Also check registered scripts.
		global $wp_scripts;
		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( $wp_scripts->registered as $handle => $script ) {
				if ( empty( $script->src ) ) {
					continue;
				}
				foreach ( $lazy_patterns as $key => $config ) {
					if ( preg_match( $config['pattern'], $script->src ) ) {
						if ( ! in_array( $config['name'], $detected_libs, true ) ) {
							$detected_libs[] = $config['name'];
						}
					}
				}
			}
		}

		// If more than one lazy loading method detected.
		if ( count( $detected_libs ) > 1 ) {
			$warnings[] = array(
				'type'     => 'lazy_load_conflict',
				'message'  => sprintf(
					/* translators: 1: count, 2: list of libraries */
					\__( 'Multiple lazy loading implementations detected (%1$d): %2$s.', 'functionalities' ),
					count( $detected_libs ),
					implode( ', ', $detected_libs )
				),
				'location' => \__( 'In page scripts and image attributes', 'functionalities' ),
				'reason'   => \__( 'Multiple lazy loading implementations can cause images to not load, flash of invisible content, or JavaScript errors. Disable all but one lazy loading method.', 'functionalities' ),
				'details'  => array(
					'implementations' => $detected_libs,
					'count'           => count( $detected_libs ),
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect mixed content (HTTP resources on HTTPS pages).
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_mixed_content() : array {
		$warnings = array();

		// Only check if site is HTTPS.
		if ( ! \is_ssl() && strpos( \home_url(), 'https://' ) === false ) {
			return $warnings;
		}

		// Use cached frontend output.
		$output = self::get_frontend_output();
		$head_output = $output['head'];

		$http_resources = array();

		// Check for HTTP resources in stylesheets.
		if ( preg_match_all( '/href=["\']http:\/\/[^"\']+["\']/', $head_output, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$http_resources[] = 'stylesheet: ' . substr( $match, 6, -1 );
			}
		}

		// Check for HTTP resources in scripts.
		if ( preg_match_all( '/src=["\']http:\/\/[^"\']+["\']/', $head_output, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$http_resources[] = 'script: ' . substr( $match, 5, -1 );
			}
		}

		if ( ! empty( $http_resources ) ) {
			$warnings[] = array(
				'type'     => 'mixed_content',
				'message'  => sprintf(
					/* translators: %d: number of HTTP resources */
					\__( 'Mixed content detected: %d HTTP resource(s) on HTTPS site.', 'functionalities' ),
					count( $http_resources )
				),
				'location' => sprintf(
					/* translators: %s: list of resources */
					\__( 'Resources: %s', 'functionalities' ),
					implode( '; ', array_slice( $http_resources, 0, 3 ) )
				),
				'reason'   => \__( 'HTTP resources on HTTPS pages cause browser security warnings, may be blocked entirely, and break the secure padlock indicator. Update all resource URLs to HTTPS.', 'functionalities' ),
				'details'  => array(
					'resources' => array_slice( $http_resources, 0, 5 ),
					'count'     => count( $http_resources ),
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect missing security headers.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_missing_security_headers() : array {
		$warnings = array();

		// Make a request to the home URL to check headers.
		$response = \wp_remote_head( \home_url(), array(
			'timeout'   => 5,
			'sslverify' => false,
		) );

		if ( \is_wp_error( $response ) ) {
			return $warnings;
		}

		$headers = \wp_remote_retrieve_headers( $response );
		$headers_array = $headers instanceof \Requests_Utility_CaseInsensitiveDictionary
			? $headers->getAll()
			: (array) $headers;
		$headers_lower = array_change_key_case( $headers_array, CASE_LOWER );

		$missing_headers = array();

		// Check for critical security headers.
		$security_headers = array(
			'x-content-type-options'  => 'X-Content-Type-Options',
			'x-frame-options'         => 'X-Frame-Options',
			'x-xss-protection'        => 'X-XSS-Protection',
			'strict-transport-security' => 'Strict-Transport-Security (HSTS)',
		);

		foreach ( $security_headers as $key => $name ) {
			if ( ! isset( $headers_lower[ $key ] ) ) {
				$missing_headers[] = $name;
			}
		}

		if ( ! empty( $missing_headers ) ) {
			$warnings[] = array(
				'type'     => 'missing_security_headers',
				'message'  => sprintf(
					/* translators: %s: list of missing headers */
					\__( 'Missing security headers: %s.', 'functionalities' ),
					implode( ', ', $missing_headers )
				),
				'location' => \__( 'HTTP response headers from server', 'functionalities' ),
				'reason'   => \__( 'Security headers protect against clickjacking, XSS attacks, and MIME-type sniffing. These are typically configured in .htaccess, nginx config, or via a security plugin.', 'functionalities' ),
				'details'  => array(
					'missing' => $missing_headers,
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect debug mode exposure.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_debug_exposure() : array {
		$warnings = array();
		$issues = array();

		// Check WP_DEBUG.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$issues[] = 'WP_DEBUG is enabled';
		}

		// Check WP_DEBUG_DISPLAY.
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$issues[] = 'WP_DEBUG_DISPLAY is enabled';
		}

		// Check display_errors.
		if ( ini_get( 'display_errors' ) ) {
			$issues[] = 'PHP display_errors is enabled';
		}

		// Check SCRIPT_DEBUG.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$issues[] = 'SCRIPT_DEBUG is enabled';
		}

		if ( ! empty( $issues ) ) {
			$warnings[] = array(
				'type'     => 'debug_exposure',
				'message'  => sprintf(
					/* translators: %s: list of debug issues */
					\__( 'Debug mode exposure: %s.', 'functionalities' ),
					implode( ', ', $issues )
				),
				'location' => \__( 'wp-config.php constants and PHP ini settings', 'functionalities' ),
				'reason'   => \__( 'Debug settings expose sensitive error messages, file paths, and database queries to visitors. These should be disabled on production sites.', 'functionalities' ),
				'details'  => array(
					'issues' => $issues,
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Detect cron issues.
	 *
	 * @since 0.9.2
	 * @return array Array of warnings.
	 */
	public static function detect_cron_issues() : array {
		$warnings = array();
		$issues = array();

		// Check if WP-Cron is disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$issues[] = 'WP-Cron is disabled (DISABLE_WP_CRON)';
		}

		// Check for stuck cron jobs (jobs more than 1 hour overdue).
		$crons = \_get_cron_array();
		$current_time = time();
		$stuck_jobs = 0;

		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( $timestamp < ( $current_time - HOUR_IN_SECONDS ) ) {
					$stuck_jobs += count( $cron );
				}
			}
		}

		if ( $stuck_jobs > 0 ) {
			$issues[] = sprintf(
				/* translators: %d: number of stuck jobs */
				\__( '%d cron job(s) are overdue', 'functionalities' ),
				$stuck_jobs
			);
		}

		// Check cron lock age.
		$cron_lock = \get_transient( 'doing_cron' );
		if ( $cron_lock && ( $current_time - $cron_lock ) > 600 ) {
			$issues[] = 'Cron lock is stale (may indicate stuck process)';
		}

		if ( ! empty( $issues ) ) {
			$warnings[] = array(
				'type'     => 'cron_issues',
				'message'  => sprintf(
					/* translators: %s: list of cron issues */
					\__( 'Cron issues detected: %s.', 'functionalities' ),
					implode( '; ', $issues )
				),
				'location' => \__( 'WordPress scheduled tasks (wp-cron.php)', 'functionalities' ),
				'reason'   => \__( 'Cron issues prevent scheduled tasks like publishing posts, sending emails, or running backups. Check if a server-level cron is configured or if WP-Cron needs to be re-enabled.', 'functionalities' ),
				'details'  => array(
					'issues'     => $issues,
					'stuck_jobs' => $stuck_jobs,
				),
				'detected' => time(),
			);
		}

		return $warnings;
	}

	/**
	 * Get all detected assumptions.
	 *
	 * @return array Detected assumptions.
	 */
	public static function get_detected_assumptions() : array {
		return (array) \get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Get ignored assumptions.
	 *
	 * @return array Ignored assumptions with expiry timestamps.
	 */
	public static function get_ignored_assumptions() : array {
		return (array) \get_option( self::IGNORED_KEY, array() );
	}

	/**
	 * Generate a unique hash for a warning.
	 *
	 * @param array $warning Warning data.
	 * @return string Hash.
	 */
	public static function get_warning_hash( array $warning ) : string {
		$key_parts = array( $warning['type'] );

		if ( isset( $warning['details']['schema_type'] ) ) {
			$key_parts[] = $warning['details']['schema_type'];
		}
		if ( isset( $warning['details']['tracking_id'] ) ) {
			$key_parts[] = $warning['details']['tracking_id'];
		}
		if ( isset( $warning['details']['font_family'] ) ) {
			$key_parts[] = $warning['details']['font_family'];
		}
		// New hash keys for v0.9.2 detectors.
		if ( isset( $warning['details']['duplicates'] ) ) {
			$key_parts[] = implode( ',', array_keys( $warning['details']['duplicates'] ) );
		}
		if ( isset( $warning['details']['endpoint'] ) ) {
			$key_parts[] = $warning['details']['endpoint'];
		}
		if ( isset( $warning['details']['implementations'] ) ) {
			$key_parts[] = implode( ',', $warning['details']['implementations'] );
		}

		return md5( implode( '|', $key_parts ) );
	}

	/**
	 * AJAX: Ignore an assumption permanently.
	 *
	 * @return void
	 */
	public static function ajax_ignore_assumption() : void {
		\check_ajax_referer( 'functionalities_assumptions', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( \wp_unslash( $_POST['hash'] ) ) : '';

		if ( empty( $hash ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid request.', 'functionalities' ) ) );
		}

		$ignored = self::get_ignored_assumptions();
		$ignored[ $hash ] = array(
			'expires' => PHP_INT_MAX, // Never expires.
			'ignored_at' => time(),
		);
		\update_option( self::IGNORED_KEY, $ignored );

		\wp_send_json_success();
	}

	/**
	 * AJAX: Acknowledge (dismiss) an assumption.
	 *
	 * @return void
	 */
	public static function ajax_acknowledge_assumption() : void {
		\check_ajax_referer( 'functionalities_assumptions', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( \wp_unslash( $_POST['hash'] ) ) : '';

		if ( empty( $hash ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid request.', 'functionalities' ) ) );
		}

		// Remove from detected list.
		$detected = self::get_detected_assumptions();
		$detected = array_filter( $detected, function( $warning ) use ( $hash ) {
			return self::get_warning_hash( $warning ) !== $hash;
		} );
		\update_option( self::OPTION_KEY, array_values( $detected ) );

		\wp_send_json_success();
	}

	/**
	 * AJAX: Snooze an assumption for X days.
	 *
	 * @return void
	 */
	public static function ajax_snooze_assumption() : void {
		\check_ajax_referer( 'functionalities_assumptions', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'functionalities' ) ) );
		}

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( \wp_unslash( $_POST['hash'] ) ) : '';
		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 7;

		if ( empty( $hash ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid request.', 'functionalities' ) ) );
		}

		$ignored = self::get_ignored_assumptions();
		$ignored[ $hash ] = array(
			'expires' => time() + ( $days * DAY_IN_SECONDS ),
			'snoozed_at' => time(),
		);
		\update_option( self::IGNORED_KEY, $ignored );

		\wp_send_json_success();
	}

	/**
	 * Get count of active warnings.
	 *
	 * @return int Warning count.
	 */
	public static function get_warning_count() : int {
		$detected = self::get_detected_assumptions();
		$ignored = self::get_ignored_assumptions();

		$active = array_filter( $detected, function( $warning ) use ( $ignored ) {
			$hash = self::get_warning_hash( $warning );
			return ! isset( $ignored[ $hash ] ) || $ignored[ $hash ]['expires'] < time();
		} );

		return count( $active );
	}

	/**
	 * Force run detection (for testing or manual trigger).
	 *
	 * @return array Detected warnings.
	 */
	public static function force_run_detection() : array {
		// Clear the cache to ensure fresh detection.
		self::$frontend_output_cache = null;

		\set_transient( 'functionalities_run_assumption_detection', true, HOUR_IN_SECONDS );
		\delete_option( 'functionalities_assumptions_last_run' );
		self::run_detection();
		return self::get_detected_assumptions();
	}

	/**
	 * Safely capture frontend output (wp_head and wp_footer).
	 *
	 * This method caches the output to avoid multiple expensive calls
	 * to wp_head and wp_footer during a single detection run.
	 *
	 * @return array Array with 'head' and 'footer' keys.
	 */
	protected static function get_frontend_output() : array {
		if ( null !== self::$frontend_output_cache ) {
			return self::$frontend_output_cache;
		}

		$head_output = '';
		$footer_output = '';

		// Suppress any output during capture.
		try {
			// Capture wp_head output.
			ob_start();
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally calling core WordPress hook to detect scripts/styles.
			\do_action( 'wp_head' );
			$head_output = ob_get_clean();

			if ( false === $head_output ) {
				$head_output = '';
			}

			// Capture wp_footer output.
			ob_start();
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally calling core WordPress hook to detect scripts/styles.
			\do_action( 'wp_footer' );
			$footer_output = ob_get_clean();

			if ( false === $footer_output ) {
				$footer_output = '';
			}
		} catch ( \Exception $e ) {
			// Log but don't fail.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log( 'Functionalities Assumption Detection error: ' . $e->getMessage() );
			}
			// Clean any remaining output buffers.
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
		}

		self::$frontend_output_cache = array(
			'head'   => $head_output,
			'footer' => $footer_output,
			'full'   => $head_output . $footer_output,
		);

		return self::$frontend_output_cache;
	}

	/**
	 * Clear the frontend output cache.
	 *
	 * @return void
	 */
	public static function clear_cache() : void {
		self::$frontend_output_cache = null;
	}
}
