<?php
/**
 * Custom Fonts Module.
 *
 * Generates @font-face CSS rules for custom web fonts, with full support
 * for variable fonts and multiple font formats.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.3.0
 * @version    0.8.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fonts class for custom @font-face rule generation.
 *
 * Provides a user interface for configuring custom fonts with proper
 * @font-face declarations. Supports both static and variable fonts,
 * WOFF2 and WOFF formats, and various font-display strategies.
 *
 * ## Features
 *
 * - Variable font support with weight ranges
 * - WOFF2 and WOFF format support
 * - Configurable font-display strategy
 * - Font-style (normal, italic, oblique, oblique with angles) support
 * - Output in both frontend and admin
 *
 * ## Filters
 *
 * ### functionalities_fonts_enabled
 * Controls whether font CSS is output.
 *
 * @since 0.8.0
 * @param bool $enabled Whether fonts are enabled.
 *
 * ### functionalities_fonts_items
 * Filters the array of font definitions before CSS generation.
 *
 * @since 0.8.0
 * @param array $items Array of font definitions.
 *
 * @example
 * // Add a font dynamically
 * add_filter( 'functionalities_fonts_items', function( $items ) {
 *     $items[] = array(
 *         'family'       => 'Dynamic Font',
 *         'woff2_url'    => '/path/to/font.woff2',
 *         'is_variable'  => true,
 *         'weight_range' => '100 900',
 *     );
 *     return $items;
 * } );
 *
 * ### functionalities_fonts_css
 * Filters the generated @font-face CSS.
 *
 * @since 0.8.0
 * @param string $css   The generated CSS.
 * @param array  $items The font items array.
 *
 * ## Actions
 *
 * ### functionalities_fonts_before_output
 * Fires before font CSS is output in the head.
 *
 * @since 0.8.0
 *
 * @since 0.3.0
 */
class Fonts {

	/**
	 * Initialize the fonts module.
	 *
	 * Registers hooks for outputting @font-face CSS in wp_head
	 * and admin_head for both frontend and admin contexts.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public static function init() : void {
		// Preload fonts early.
		\add_action( 'wp_head', array( __CLASS__, 'preload_fonts' ), 1 );

		\add_action( 'wp_head', array( __CLASS__, 'print_fonts_css' ), 20 );
		\add_action( 'admin_head', array( __CLASS__, 'print_fonts_css' ), 20 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.3.0
	 *
	 * @return array {
	 *     Fonts options.
	 *
	 *     @type bool  $enabled Whether font output is enabled.
	 *     @type array $items   Array of font definitions, each containing:
	 *                          - family: Font family name
	 *                          - style: Font style (normal, italic)
	 *                          - display: Font-display value
	 *                          - weight: Static font weight
	 *                          - weight_range: Variable font weight range
	 *                          - is_variable: Whether font is variable
	 *                          - woff2_url: URL to WOFF2 file
	 *                          - woff_url: URL to WOFF file (optional)
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
	 * @since 0.3.0
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enabled' => false,
			'items'   => array(),
		);
		$opts = (array) \get_option( 'functionalities_fonts', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Output @font-face CSS rules.
	 *
	 * Generates and outputs inline CSS containing all configured
	 * @font-face declarations.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filters for extensibility.
	 *
	 * @return void
	 */
	public static function print_fonts_css() : void {
		$opts = self::get_options();

		/**
		 * Filters whether font CSS should be output.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether fonts are enabled.
		 */
		if ( ! \apply_filters( 'functionalities_fonts_enabled', ! empty( $opts['enabled'] ) ) ) {
			return;
		}

		if ( empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return;
		}

		/**
		 * Fires before font CSS is output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_fonts_before_output' );

		/**
		 * Filters the font items before CSS generation.
		 *
		 * @since 0.8.0
		 *
		 * @param array $items Array of font definitions.
		 */
		$items = \apply_filters( 'functionalities_fonts_items', $opts['items'] );

		$css = self::build_css( $items );

		if ( $css === '' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized in build_css().
		echo '<style id="functionalities-fonts">' . self::sanitize_css( $css ) . '</style>';
	}

	/**
	 * Preload fonts in head.
	 *
	 * @since 0.13.0
	 * @return void
	 */
	public static function preload_fonts() : void {
		if ( \is_admin() ) {
			return;
		}

		$opts = self::get_options();

		if ( ! \apply_filters( 'functionalities_fonts_enabled', ! empty( $opts['enabled'] ) ) ) {
			return;
		}

		if ( empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return;
		}

		$items = \apply_filters( 'functionalities_fonts_items', $opts['items'] );

		foreach ( $items as $item ) {
			if ( ! empty( $item['preload'] ) && ! empty( $item['woff2_url'] ) ) {
				echo '<link rel="preload" href="' . \esc_url( $item['woff2_url'] ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
			}
		}
	}

	/**
	 * Build @font-face CSS from font items.
	 *
	 * Generates properly formatted @font-face rules for each configured font,
	 * handling variable fonts, weight ranges, and multiple source formats.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filter for CSS output.
	 *
	 * @param array $items Array of font definitions.
	 * @return string Generated CSS containing @font-face rules.
	 */
	protected static function build_css( array $items ) : string {
		$parts = array();

		foreach ( $items as $item ) {
			$family       = trim( (string) ( $item['family'] ?? '' ) );
			$style        = trim( (string) ( $item['style'] ?? 'normal' ) );
			$display      = trim( (string) ( $item['display'] ?? 'swap' ) );
			$weight       = trim( (string) ( $item['weight'] ?? '' ) );
			$weight_range = trim( (string) ( $item['weight_range'] ?? '' ) );
			$is_variable  = ! empty( $item['is_variable'] );
			$woff2        = trim( (string) ( $item['woff2_url'] ?? '' ) );
			$woff         = trim( (string) ( $item['woff_url'] ?? '' ) );

			// Skip if required fields are missing.
			if ( $family === '' || $woff2 === '' ) {
				continue;
			}

			// Build source declaration.
			$src = 'url(' . $woff2 . ') format("woff2")';
			if ( $woff !== '' ) {
				$src .= ', url(' . $woff . ') format("woff")';
			}

			// Determine weight property.
			$weight_prop = '';
			if ( $is_variable || $weight_range !== '' ) {
				// Variable font with weight range.
				$range       = $weight_range !== '' ? $weight_range : '100 900';
				$weight_prop = 'font-weight:' . $range . ';';
			} elseif ( $weight !== '' ) {
				// Static font with single weight.
				$weight_prop = 'font-weight:' . $weight . ';';
			}

			// Build the @font-face rule.
			$parts[] = '@font-face{' .
				'font-family:"' . $family . '";' .
				'font-style:' . $style . ';' .
				'font-display:' . ( $display ?: 'swap' ) . ';' .
				$weight_prop .
				'src:' . $src . ';' .
			'}';
		}

		$css = implode( "\n", $parts );

		/**
		 * Filters the generated @font-face CSS.
		 *
		 * @since 0.8.0
		 *
		 * @param string $css   The generated CSS.
		 * @param array  $items The font items array.
		 */
		return \apply_filters( 'functionalities_fonts_css', $css, $items );
	}

	/**
	 * Sanitize CSS output to prevent injection.
	 *
	 * Removes potentially dangerous CSS content including:
	 * - HTML tags
	 * - Style closing tags
	 * - JavaScript expressions
	 * - Import statements with external URLs
	 *
	 * @since 0.9.9
	 *
	 * @param string $css The CSS to sanitize.
	 * @return string Sanitized CSS.
	 */
	protected static function sanitize_css( string $css ) : string {
		// Remove any HTML tags.
		$css = wp_strip_all_tags( $css );

		// Remove style closing tags that could break out of style context.
		$css = preg_replace( '/<\/style\s*>/i', '', $css );

		// Remove JavaScript expressions (legacy IE).
		$css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css );

		// Remove JavaScript URLs.
		$css = preg_replace( '/javascript\s*:/i', '', $css );

		// Remove behavior property (legacy IE).
		$css = preg_replace( '/behavior\s*:\s*url\s*\([^)]*\)/i', '', $css );

		return $css;
	}
}
