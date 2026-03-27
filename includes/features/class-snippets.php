<?php
/**
 * Header & Footer Snippets Module.
 *
 * Enables insertion of custom code snippets in the site header and footer,
 * including built-in Google Analytics 4 integration. Supports multiple
 * independently-toggleable snippets per location via repeater fields.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.2.0
 * @version    1.4.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippets class for managing header and footer code injection.
 *
 * Provides a safe way to add custom scripts, styles, and tracking codes
 * to the site without modifying theme files. Includes native support for
 * Google Analytics 4 with minimal configuration.
 *
 * ## Features
 *
 * - Google Analytics 4 (GA4) integration with measurement ID
 * - Multiple header snippets with per-snippet enable toggle
 * - Multiple footer snippets with per-snippet enable toggle
 * - Multiple body open snippets with per-snippet enable toggle
 * - Automatic frontend-only output (skips admin, feeds, REST)
 *
 * ## Filters
 *
 * ### functionalities_snippets_output_enabled
 * Controls whether snippets are output on the current page.
 *
 * @since 0.8.0
 * @param bool $enabled Whether to output snippets. Default true on frontend.
 *
 * @example
 * // Disable all snippets on specific pages
 * add_filter( 'functionalities_snippets_output_enabled', function( $enabled ) {
 *     if ( is_page( 'landing-page' ) ) {
 *         return false;
 *     }
 *     return $enabled;
 * } );
 *
 * ### functionalities_snippets_ga4_enabled
 * Controls whether GA4 tracking code is output.
 *
 * @since 0.8.0
 * @param bool   $enabled Whether GA4 is enabled. Default based on settings.
 * @param string $ga4_id  The GA4 measurement ID.
 *
 * @example
 * // Disable GA4 for logged-in users (GDPR compliance)
 * add_filter( 'functionalities_snippets_ga4_enabled', function( $enabled, $id ) {
 *     return $enabled && ! is_user_logged_in();
 * }, 10, 2 );
 *
 * ### functionalities_snippets_header_code
 * Filters each header snippet's code before output.
 *
 * @since 0.8.0
 * @since 1.4.0 Fires per-snippet; receives snippet array as second param.
 * @param string $code    The snippet code to output.
 * @param array  $snippet The full snippet array (label, code, enabled).
 *
 * ### functionalities_snippets_footer_code
 * Filters each footer snippet's code before output.
 *
 * @since 0.8.0
 * @since 1.4.0 Fires per-snippet; receives snippet array as second param.
 * @param string $code    The snippet code to output.
 * @param array  $snippet The full snippet array (label, code, enabled).
 *
 * ### functionalities_snippets_body_open_code
 * Filters each body open snippet's code before output.
 *
 * @since 0.13.0
 * @since 1.4.0 Fires per-snippet; receives snippet array as second param.
 * @param string $code    The snippet code to output.
 * @param array  $snippet The full snippet array (label, code, enabled).
 *
 * ## Actions
 *
 * ### functionalities_before_header_snippets
 * Fires before header snippets are output.
 *
 * @since 0.8.0
 *
 * ### functionalities_after_header_snippets
 * Fires after header snippets are output.
 *
 * @since 0.8.0
 *
 * ### functionalities_before_footer_snippets
 * Fires before footer snippets are output.
 *
 * @since 0.8.0
 *
 * ### functionalities_after_footer_snippets
 * Fires after footer snippets are output.
 *
 * @since 0.8.0
 *
 * @since 0.2.0
 */
class Snippets {

	/**
	 * Cached options.
	 *
	 * @var array|null
	 */
	private static $options = null;

	/**
	 * Initialize the snippets module.
	 *
	 * Registers hooks for outputting code in wp_head, wp_body_open, and wp_footer.
	 * Uses priority 20 to allow theme output to complete first.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		\add_action( 'wp_head', array( __CLASS__, 'output_head' ), 20 );
		\add_action( 'wp_body_open', array( __CLASS__, 'output_body_open' ), 20 );
		\add_action( 'wp_footer', array( __CLASS__, 'output_footer' ), 20 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * Handles in-memory migration from old single-string format to the
	 * new repeater array format. Does NOT persist to database from frontend.
	 *
	 * @since 0.2.0
	 * @since 1.4.0 Updated to repeater array format with in-memory migration.
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enabled'    => false,
			'enable_ga4' => false,
			'ga4_id'     => '',
			'header'     => array(),
			'body_open'  => array(),
			'footer'     => array(),
		);
		$opts = (array) \get_option( 'functionalities_snippets', $defaults );

		// In-memory migration from old single-string format.
		if ( isset( $opts['header_code'] ) || isset( $opts['enable_header'] ) ) {
			$opts = self::migrate_options( $opts );
		}

		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Migrate old single-string option format to repeater arrays.
	 *
	 * @since 1.4.0
	 *
	 * @param array $opts Old-format options.
	 * @return array New-format options.
	 */
	public static function migrate_options( array $opts ) : array {
		$migrated = array(
			'enabled'    => ! empty( $opts['enabled'] ),
			'enable_ga4' => ! empty( $opts['enable_ga4'] ),
			'ga4_id'     => $opts['ga4_id'] ?? '',
			'header'     => array(),
			'body_open'  => array(),
			'footer'     => array(),
		);

		if ( ! empty( $opts['header_code'] ) ) {
			$migrated['header'][] = array(
				'label'   => 'Header Code (migrated)',
				'code'    => (string) $opts['header_code'],
				'enabled' => ! empty( $opts['enable_header'] ),
			);
		}
		if ( ! empty( $opts['body_open_code'] ) ) {
			$migrated['body_open'][] = array(
				'label'   => 'Body Open Code (migrated)',
				'code'    => (string) $opts['body_open_code'],
				'enabled' => ! empty( $opts['enable_body_open'] ),
			);
		}
		if ( ! empty( $opts['footer_code'] ) ) {
			$migrated['footer'][] = array(
				'label'   => 'Footer Code (migrated)',
				'code'    => (string) $opts['footer_code'],
				'enabled' => ! empty( $opts['enable_footer'] ),
			);
		}

		return $migrated;
	}

	/**
	 * Check if we should output snippets on this request.
	 *
	 * Skips output in admin, feeds, and REST API requests to prevent
	 * unintended script execution in those contexts.
	 *
	 * @since 0.8.0
	 *
	 * @return bool True if snippets should be output.
	 */
	protected static function should_output() : bool {
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return false;
		}

		/**
		 * Filters whether snippets should be output on this page.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether to output snippets.
		 */
		return \apply_filters( 'functionalities_snippets_output_enabled', true );
	}

	/**
	 * Output snippets for a given location.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location   Location key: header, body_open, or footer.
	 * @param string $filter     Filter name for each snippet's code.
	 * @param string $comment    HTML comment label.
	 * @return void
	 */
	private static function output_snippets( string $location, string $filter, string $comment ) : void {
		$opts     = self::get_options();
		$snippets = ! empty( $opts[ $location ] ) && \is_array( $opts[ $location ] ) ? $opts[ $location ] : array();
		$parts    = array();

		foreach ( $snippets as $snippet ) {
			if ( empty( $snippet['enabled'] ) || empty( $snippet['code'] ) ) {
				continue;
			}

			/**
			 * Filters a snippet's code before output.
			 *
			 * @since 0.8.0
			 * @since 1.4.0 Fires per-snippet with snippet array as second param.
			 *
			 * @param string $code    The snippet code.
			 * @param array  $snippet The full snippet array.
			 */
			$code = \apply_filters( $filter, (string) $snippet['code'], $snippet );

			if ( ! empty( $code ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped via escape_snippet() with unfiltered_html cap check and kses_with_styles fallback.
				$parts[] = self::escape_snippet( $code );
			}
		}

		if ( ! empty( $parts ) ) {
			echo "\n<!-- Functionalities: " . \esc_html( $comment ) . " -->\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped above via escape_snippet().
			echo implode( "\n", $parts );
			echo "\n";
		}
	}

	/**
	 * Output header snippets.
	 *
	 * Outputs GA4 tracking code and custom header snippets in the wp_head hook.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filters and actions for extensibility.
	 * @since 1.4.0 Updated to iterate snippet arrays.
	 *
	 * @return void
	 */
	public static function output_head() : void {
		if ( ! self::should_output() ) {
			return;
		}

		$opts = self::get_options();

		/** @since 0.8.0 */
		\do_action( 'functionalities_before_header_snippets' );

		// Output Google Analytics 4.
		if ( ! empty( $opts['enable_ga4'] ) && ! empty( $opts['ga4_id'] ) ) {
			$ga4_id = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( (string) $opts['ga4_id'] ) );

			/** @since 0.8.0 */
			$ga4_enabled = \apply_filters( 'functionalities_snippets_ga4_enabled', true, $ga4_id );

			if ( $ga4_enabled && $ga4_id !== '' ) {
				echo "\n<!-- Functionalities: GA4 -->\n";
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- GA4 script must be inline with dynamic ID.
				echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . \esc_attr( $ga4_id ) . '"></script>' . "\n";
				echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}';
				echo "gtag('js',new Date());gtag('config','" . \esc_js( $ga4_id ) . "');</script>\n";
			}
		}

		self::output_snippets( 'header', 'functionalities_snippets_header_code', 'Custom Header Code' );

		/** @since 0.8.0 */
		\do_action( 'functionalities_after_header_snippets' );
	}

	/**
	 * Output footer snippets.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filters and actions for extensibility.
	 * @since 1.4.0 Updated to iterate snippet arrays.
	 *
	 * @return void
	 */
	public static function output_footer() : void {
		if ( ! self::should_output() ) {
			return;
		}

		/** @since 0.8.0 */
		\do_action( 'functionalities_before_footer_snippets' );

		self::output_snippets( 'footer', 'functionalities_snippets_footer_code', 'Custom Footer Code' );

		/** @since 0.8.0 */
		\do_action( 'functionalities_after_footer_snippets' );
	}

	/**
	 * Output body open snippets.
	 *
	 * @since 0.13.0
	 * @since 1.4.0 Updated to iterate snippet arrays.
	 *
	 * @return void
	 */
	public static function output_body_open() : void {
		if ( ! self::should_output() ) {
			return;
		}

		self::output_snippets( 'body_open', 'functionalities_snippets_body_open_code', 'Custom Body Open Code' );
	}

	/**
	 * Escape a code snippet for safe output.
	 *
	 * Users with `unfiltered_html` capability get raw output; others
	 * get content filtered via kses_with_styles() which preserves CSS
	 * inside `<style>` tags while filtering HTML.
	 *
	 * @since 1.0.1
	 * @since 1.4.0 Uses kses_with_styles() to preserve CSS content.
	 *
	 * @param string $code The snippet code.
	 * @return string Escaped snippet.
	 */
	private static function escape_snippet( string $code ): string {
		if ( \current_user_can( 'unfiltered_html' ) ) {
			return $code;
		}

		return self::kses_with_styles(
			$code,
			array(
				'script'   => array(
					'type'        => true,
					'src'         => true,
					'async'       => true,
					'defer'       => true,
					'id'          => true,
					'crossorigin' => true,
					'integrity'   => true,
					'nonce'       => true,
				),
				'noscript' => array(),
				'style'    => array(
					'type'  => true,
					'media' => true,
				),
				'link'     => array(
					'rel'   => true,
					'href'  => true,
					'type'  => true,
					'media' => true,
				),
				'meta'     => array(
					'name'       => true,
					'content'    => true,
					'charset'    => true,
					'http-equiv' => true,
					'property'   => true,
				),
				'img'      => array(
					'src'     => true,
					'alt'     => true,
					'width'   => true,
					'height'  => true,
					'loading' => true,
				),
				'iframe'   => array(
					'src'             => true,
					'width'           => true,
					'height'          => true,
					'frameborder'     => true,
					'allow'           => true,
					'allowfullscreen' => true,
					'loading'         => true,
					'style'           => true,
				),
			)
		);
	}

	/**
	 * Run wp_kses() while preserving CSS content inside `<style>` tags.
	 *
	 * Standard wp_kses() can mangle CSS content because it is designed for
	 * HTML, not CSS selectors (e.g., `>` child combinators, `@media` rules).
	 * This method extracts `<style>` blocks, validates only their tag
	 * attributes via wp_kses(), preserves CSS content (stripping only null
	 * bytes), and reassembles after filtering the rest.
	 *
	 * @since 1.4.0
	 *
	 * @param string $code         The code containing HTML and possibly `<style>` blocks.
	 * @param array  $allowed_tags Allowed tags array for wp_kses().
	 * @return string Filtered code with CSS preserved.
	 */
	public static function kses_with_styles( string $code, array $allowed_tags ): string {
		$style_blocks = array();
		$prefix       = '___FUNC_STYLE_';

		// Extract <style> blocks before wp_kses processing.
		$style_allowed = isset( $allowed_tags['style'] ) ? $allowed_tags['style'] : array( 'type' => true, 'media' => true );
		$code_without_styles = preg_replace_callback(
			'/<style(\s[^>]*)?>(.*?)<\/style>/is',
			function ( $matches ) use ( &$style_blocks, $prefix, $style_allowed ) {
				$index = count( $style_blocks );

				// Validate the <style> tag attributes through wp_kses.
				$attrs    = isset( $matches[1] ) ? $matches[1] : '';
				$tag_html = \wp_kses(
					'<style' . $attrs . '></style>',
					array( 'style' => $style_allowed )
				);
				$open_tag = preg_replace( '/<\/style>$/', '', $tag_html );

				// Strip null bytes from CSS content but preserve everything else.
				$css_content = isset( $matches[2] ) ? \wp_kses_no_null( $matches[2] ) : '';

				$style_blocks[] = $open_tag . $css_content . '</style>';
				return $prefix . $index . '___';
			},
			$code
		);

		// If regex failed, fall back to standard wp_kses.
		if ( null === $code_without_styles ) {
			return \wp_kses( $code, $allowed_tags );
		}

		// Run wp_kses on the remaining non-style HTML.
		$escaped = \wp_kses( $code_without_styles, $allowed_tags );

		// Restore style blocks.
		foreach ( $style_blocks as $i => $block ) {
			$escaped = str_replace( $prefix . $i . '___', $block, $escaped );
		}

		return $escaped;
	}
}
