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
 *
 * @package Functionalities\Features
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
	 * Get module options.
	 *
	 * @return array Options array.
	 */
	public static function get_options() : array {
		$defaults = array(
			'enabled'                  => false,
			'detect_schema_collision'  => true,
			'detect_analytics_dupe'    => true,
			'detect_font_redundancy'   => true,
			'detect_inline_css_growth' => true,
			'inline_css_threshold_kb'  => 50,
		);
		$opts = (array) \get_option( 'functionalities_assumption_detection', $defaults );
		return array_merge( $defaults, $opts );
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

		// Capture wp_head output.
		ob_start();
		\do_action( 'wp_head' );
		$head_output = ob_get_clean();

		// Capture wp_footer output.
		ob_start();
		\do_action( 'wp_footer' );
		$footer_output = ob_get_clean();

		$full_output = $head_output . $footer_output;

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

		// Capture wp_head output.
		ob_start();
		\do_action( 'wp_head' );
		$head_output = ob_get_clean();

		// Capture wp_footer output.
		ob_start();
		\do_action( 'wp_footer' );
		$footer_output = ob_get_clean();

		$full_output = $head_output . $footer_output;

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

		// Capture wp_head output.
		ob_start();
		\do_action( 'wp_head' );
		$head_output = ob_get_clean();

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

		// Capture wp_head output.
		ob_start();
		\do_action( 'wp_head' );
		$head_output = ob_get_clean();

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
			$warnings[] = array(
				'type'      => 'inline_css_growth',
				'message'   => sprintf(
					/* translators: 1: current size, 2: threshold */
					\__( 'Inline CSS output is %1$s KB (threshold: %2$s KB).', 'functionalities' ),
					$size_kb,
					$threshold_kb
				),
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
			$warnings[] = array(
				'type'      => 'inline_css_spike',
				'message'   => sprintf(
					/* translators: 1: average size, 2: current size */
					\__( 'Inline CSS increased from %1$s KB average to %2$s KB.', 'functionalities' ),
					$avg_kb,
					$size_kb
				),
				'details'   => array(
					'current_size_kb' => $size_kb,
					'average_size_kb' => $avg_kb,
					'increase_percent' => round( ( $size_kb / $avg_kb - 1 ) * 100 ),
				),
				'detected'  => time(),
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
	protected static function get_warning_hash( array $warning ) : string {
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

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( $_POST['hash'] ) : '';

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

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( $_POST['hash'] ) : '';

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

		$hash = isset( $_POST['hash'] ) ? \sanitize_text_field( $_POST['hash'] ) : '';
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
		\set_transient( 'functionalities_run_assumption_detection', true, HOUR_IN_SECONDS );
		\delete_option( 'functionalities_assumptions_last_run' );
		self::run_detection();
		return self::get_detected_assumptions();
	}
}
