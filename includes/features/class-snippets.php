<?php
/**
 * Header & Footer Snippets Module.
 *
 * Enables insertion of custom code snippets in the site header and footer,
 * including built-in Google Analytics 4 integration.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.2.0
 * @version    0.8.0
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
 * - Custom header code injection (scripts, styles, meta tags)
 * - Custom footer code injection (scripts, tracking pixels)
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
 * Filters the custom header code before output.
 *
 * @since 0.8.0
 * @param string $code The header code to output.
 *
 * ### functionalities_snippets_footer_code
 * Filters the custom footer code before output.
 *
 * @since 0.8.0
 * @param string $code The footer code to output.
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
	 * Initialize the snippets module.
	 *
	 * Registers hooks for outputting code in wp_head and wp_footer.
	 * Uses priority 20 to allow theme output to complete first.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function init() : void {
		\add_action( 'wp_head', array( __CLASS__, 'output_head' ), 20 );
		\add_action( 'wp_body_open', array( __CLASS__, 'output_body_open' ), 20 );
		\add_action( 'wp_footer', array( __CLASS__, 'output_footer' ), 20 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.2.0
	 *
	 * @return array {
	 *     Snippets options.
	 *
	 *     @type bool   $enable_header Whether header code is enabled.
	 *     @type string $header_code   Custom header code content.
	 *     @type bool   $enable_footer Whether footer code is enabled.
	 *     @type string $footer_code   Custom footer code content.
	 *     @type bool   $enable_ga4    Whether GA4 tracking is enabled.
	 *     @type string $ga4_id        GA4 measurement ID (G-XXXXXXXXXX).
	 * }
	 */
	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.2.0
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enable_header'    => false,
			'header_code'      => '',
			'enable_body_open' => false,
			'body_open_code'   => '',
			'enable_footer'    => false,
			'footer_code'      => '',
			'enable_ga4'       => false,
			'ga4_id'           => '',
		);
		$opts = (array) \get_option( 'functionalities_snippets', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
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
		// Skip in admin, feeds, and REST requests.
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
	 * Output header snippets.
	 *
	 * Outputs GA4 tracking code and custom header code in the wp_head hook.
	 * GA4 is output first as it often needs to load early for accurate tracking.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filters and actions for extensibility.
	 *
	 * @return void
	 */
	public static function output_head() : void {
		if ( ! self::should_output() ) {
			return;
		}

		$opts = self::get_options();

		/**
		 * Fires before header snippets are output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_before_header_snippets' );

		// Output Google Analytics 4.
		if ( ! empty( $opts['enable_ga4'] ) && ! empty( $opts['ga4_id'] ) ) {
			// Sanitize the GA4 ID to only allow valid characters.
			$ga4_id = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( (string) $opts['ga4_id'] ) );

			/**
			 * Filters whether GA4 tracking should be output.
			 *
			 * @since 0.8.0
			 *
			 * @param bool   $enabled Whether GA4 is enabled.
			 * @param string $ga4_id  The sanitized GA4 measurement ID.
			 */
			$ga4_enabled = \apply_filters( 'functionalities_snippets_ga4_enabled', true, $ga4_id );

			if ( $ga4_enabled && $ga4_id !== '' ) {
				echo "\n<!-- Functionalities: GA4 -->\n";
				echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga4_id ) . '"></script>' . "\n";
				echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}';
				echo "gtag('js',new Date());gtag('config','" . esc_js( $ga4_id ) . "');</script>\n";
			}
		}

		// Output custom header code.
		if ( ! empty( $opts['enable_header'] ) && ! empty( $opts['header_code'] ) ) {
			/**
			 * Filters the custom header code before output.
			 *
			 * @since 0.8.0
			 *
			 * @param string $code The header code to output.
			 */
			$header_code = \apply_filters( 'functionalities_snippets_header_code', (string) $opts['header_code'] );

			if ( ! empty( $header_code ) ) {
				echo "\n<!-- Functionalities: Custom Header Code -->\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-provided code snippets are intentionally output raw.
				echo $header_code;
				echo "\n";
			}
		}

		/**
		 * Fires after header snippets are output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_after_header_snippets' );
	}

	/**
	 * Output footer snippets.
	 *
	 * Outputs custom footer code in the wp_footer hook. Useful for
	 * tracking pixels, chat widgets, and deferred scripts.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filters and actions for extensibility.
	 *
	 * @return void
	 */
	public static function output_footer() : void {
		if ( ! self::should_output() ) {
			return;
		}

		$opts = self::get_options();

		// Skip if footer code is not enabled.
		if ( empty( $opts['enable_footer'] ) || empty( $opts['footer_code'] ) ) {
			return;
		}

		/**
		 * Fires before footer snippets are output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_before_footer_snippets' );

		/**
		 * Filters the custom footer code before output.
		 *
		 * @since 0.8.0
		 *
		 * @param string $code The footer code to output.
		 */
		$footer_code = \apply_filters( 'functionalities_snippets_footer_code', (string) $opts['footer_code'] );

		if ( ! empty( $footer_code ) ) {
			echo "\n<!-- Functionalities: Custom Footer Code -->\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-provided code snippets are intentionally output raw.
			echo $footer_code;
			echo "\n";
		}

		/**
		 * Fires after footer snippets are output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_after_footer_snippets' );
	}

	/**
	 * Output body open snippets.
	 *
	 * Outputs custom code in the wp_body_open hook.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public static function output_body_open() : void {
		if ( ! self::should_output() ) {
			return;
		}

		$opts = self::get_options();

		if ( empty( $opts['enable_body_open'] ) || empty( $opts['body_open_code'] ) ) {
			return;
		}

		/**
		 * Filters the custom body open code before output.
		 *
		 * @since 0.13.0
		 *
		 * @param string $code The body open code.
		 */
		$body_open_code = \apply_filters( 'functionalities_snippets_body_open_code', (string) $opts['body_open_code'] );

		if ( empty( $body_open_code ) ) {
			return;
		}

		echo "\n<!-- Functionalities: Custom Body Open Code -->\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-provided code snippets are intentionally output raw.
		echo $body_open_code;
		echo "\n";
	}
}
