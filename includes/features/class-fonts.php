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
 * ## Loading paths (single source of truth)
 *
 * Every path below renders @font-face from the SAME build_css() output, so the
 * markup can't drift between contexts. Only the theme.json path builds its own
 * structured fontFace array (WP requires data, not a CSS string) — the shared
 * normalize_weight_range() keeps its weights in step with build_css().
 *
 * | Context                      | Hook / filter                | Channel                          |
 * |------------------------------|------------------------------|----------------------------------|
 * | Front end                    | wp_head (print_fonts_css)    | inline <style> via build_css()   |
 * | Front end (preload)          | wp_head (preload_fonts)      | <link rel=preload>               |
 * | Block editor canvas (iframe) | block_editor_settings_all    | styles[] css via build_css()     |
 * | Block editor font picker     | wp_theme_json_data_theme     | theme.json fontFamilies/fontFace |
 * | Bricks builder canvas        | wp_enqueue_scripts           | inline <style> via build_css()   |
 * | Bricks font picker           | init (bricks_register_fonts) | Custom_Fonts cache via build_css |
 *
 * The block editor canvas is an iframe (WP 6.3+/7.x): a src-less enqueued inline
 * style does NOT cross into it, which is why the canvas uses the editor `styles`
 * setting. There is deliberately no admin_head path — it reaches only the parent
 * admin document, never the editor iframe, so it was dead weight once the
 * block_editor_settings_all channel landed (1.4.7).
 *
 * @since 0.3.0
 */
class Fonts {

	use \Functionalities\Traits\CSS_Sanitizer;

	/**
	 * System-font fallback stack appended after an assigned family.
	 *
	 * Keeps text readable in the editor canvas before the web font loads (or if
	 * it fails). Mirrors WordPress's own "system font" preset.
	 *
	 * @since 1.4.7
	 *
	 * @var string
	 */
	const ASSIGN_FONT_FALLBACK = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif';

	/**
	 * Initialize the fonts module.
	 *
	 * Wires the font-loading paths documented in the "Loading paths" matrix in
	 * the class header: front-end print + preload, the block editor canvas and
	 * picker, and Bricks.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public static function init() : void {
		// Preload fonts early.
		\add_action( 'wp_head', array( __CLASS__, 'preload_fonts' ), 1 );

		// Front-end @font-face. The block editor canvas (an iframe) is served by
		// add_editor_settings_fonts() below; an admin_head print would reach only
		// the parent admin document, never the iframe, so there is no such path.
		\add_action( 'wp_head', array( __CLASS__, 'print_fonts_css' ), 20 );
		\add_filter( 'block_editor_settings_all', array( __CLASS__, 'add_editor_settings_fonts' ) );

		// Typography assignments via theme.json data layer.
		\add_filter( 'wp_theme_json_data_theme', array( __CLASS__, 'inject_typography_theme_json' ) );

		// Bricks Builder support.
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'bricks_enqueue_fonts' ) );
		\add_action( 'init', array( __CLASS__, 'bricks_register_fonts' ), 99 );

		// Allow WOFF/WOFF2 uploads in the media library.
		\add_filter( 'upload_mimes', array( __CLASS__, 'allow_font_mimes' ) );
		\add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'verify_font_filetype' ), 10, 5 );

		// Invalidate the per-request options cache when the Fonts option is updated mid-request,
		// so any subsequent call (Bricks register, theme.json, output) sees the fresh value.
		\add_action( 'update_option_functionalities_fonts', array( __CLASS__, 'flush_options_cache' ) );
		\add_action( 'add_option_functionalities_fonts', array( __CLASS__, 'flush_options_cache' ) );
	}

	/**
	 * Invalidate the static options cache.
	 *
	 * Called automatically when the `functionalities_fonts` option is added or updated.
	 *
	 * @since 1.4.6
	 *
	 * @return void
	 */
	public static function flush_options_cache() : void {
		self::$options = null;
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
			'enabled'        => false,
			'items'          => array(),
			'assign_enabled' => false,
			'body_font'      => '',
			'heading_font'   => '',
			'per_heading'    => false,
			'heading_fonts'  => array(),
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

		\wp_register_style( 'functionalities-fonts', false, array(), FUNCTIONALITIES_VERSION );
		\wp_enqueue_style( 'functionalities-fonts' );
		\wp_add_inline_style( 'functionalities-fonts', self::sanitize_css( $css ) );
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
	 * Inject custom @font-face CSS into the block editor's style settings.
	 *
	 * The editor canvas is iframed (WP 6.3+/7.x). The reliable way to get custom
	 * @font-face rules into that iframe is the editor `styles` setting — the same
	 * channel the Font Library and add_editor_style() feed, which WordPress copies
	 * verbatim into the iframe document. This replaces two weaker approaches:
	 *
	 *   - A src-less enqueued inline style (`wp_register_style( $h, false )` +
	 *     `wp_add_inline_style`) is not reliably carried into the iframe; the
	 *     plugin's own SVG-icons feature works only because it anchors to a real
	 *     CSS file.
	 *   - theme.json `fontFamilies` (see inject_typography_theme_json) registers
	 *     the fonts in the picker, but WP silently DROPS any `fontFace` that fails
	 *     its sanitiser — e.g. a variable weight range such as "1 900" — so faces
	 *     can go missing in the canvas.
	 *
	 * build_css() emits every configured face verbatim, so all fonts load in the
	 * editor regardless of theme.json validation. @font-face is an at-rule with no
	 * selector, so the editor's style scoper leaves it intact.
	 *
	 * When the body/heading assignment is active, the assignment font-family rules
	 * (see build_assignment_css) ride this same channel. The editor scopes their
	 * selectors to .editor-styles-wrapper for us, so the assigned fonts render in
	 * the canvas even when the theme.json typography assignment doesn't reach it.
	 *
	 * @since 1.4.7
	 *
	 * @param array $settings Block editor settings (from block_editor_settings_all).
	 * @return array Modified settings.
	 */
	public static function add_editor_settings_fonts( $settings ) {
		$opts = self::get_options();

		if ( ! \apply_filters( 'functionalities_fonts_enabled', ! empty( $opts['enabled'] ) ) ) {
			return $settings;
		}

		if ( empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return $settings;
		}

		$items = \apply_filters( 'functionalities_fonts_items', $opts['items'] );
		$css   = self::build_css( $items );

		// Style the editor canvas directly when body/heading assignment is on.
		$assignment = self::build_assignment_css( $opts );
		if ( $assignment !== '' ) {
			$css = trim( $css . "\n" . $assignment );
		}

		$css = self::sanitize_css( $css );

		if ( $css === '' ) {
			return $settings;
		}

		if ( empty( $settings['styles'] ) || ! is_array( $settings['styles'] ) ) {
			$settings['styles'] = array();
		}

		$settings['styles'][] = array( 'css' => $css );

		return $settings;
	}

	/**
	 * Build font-family CSS for the body/heading assignment.
	 *
	 * Emits plain `font-family` declarations (the assigned family plus a system
	 * fallback) for body text and headings, mirroring the theme.json typography
	 * assignment so the editor canvas matches the front end. Selectors are left
	 * UNSCOPED on purpose: when this rides the block_editor_settings_all `styles`
	 * channel the editor scopes them to .editor-styles-wrapper itself (`body`
	 * maps to the wrapper, `h1`…`h6` to `.editor-styles-wrapper h1`…).
	 *
	 * Returns an empty string unless assignment is enabled and at least one font
	 * is chosen.
	 *
	 * @since 1.4.7
	 *
	 * @param array $opts Fonts options.
	 * @return string Assignment CSS, or '' when nothing to assign.
	 */
	protected static function build_assignment_css( array $opts ) : string {
		if ( empty( $opts['assign_enabled'] ) ) {
			return '';
		}

		$fallback = self::ASSIGN_FONT_FALLBACK;
		$parts    = array();

		// Body / content font. Mirrors the admin's documented scope; form controls
		// are listed explicitly because they don't inherit font-family.
		$body_font = trim( (string) ( $opts['body_font'] ?? '' ) );
		if ( $body_font !== '' ) {
			$parts[] = 'body,p,li,td,input,textarea,select,button{font-family:"' . $body_font . '",' . $fallback . ';}';
		}

		// Default headings font (h1–h6).
		$heading_font = trim( (string) ( $opts['heading_font'] ?? '' ) );
		if ( $heading_font !== '' ) {
			$parts[] = 'h1,h2,h3,h4,h5,h6{font-family:"' . $heading_font . '",' . $fallback . ';}';
		}

		// Per-heading overrides — emitted after the default so they win on equal specificity.
		if ( ! empty( $opts['per_heading'] ) && ! empty( $opts['heading_fonts'] ) && is_array( $opts['heading_fonts'] ) ) {
			for ( $i = 1; $i <= 6; $i++ ) {
				$key  = 'h' . $i;
				$font = trim( (string) ( $opts['heading_fonts'][ $key ] ?? '' ) );
				if ( $font !== '' ) {
					$parts[] = $key . '{font-family:"' . $font . '",' . $fallback . ';}';
				}
			}
		}

		return implode( "\n", $parts );
	}

	/**
	 * Register custom fonts in Bricks Builder.
	 *
	 * Injects font families into Bricks' Custom_Fonts cache so they appear
	 * in the builder's font picker under "Custom Fonts" and generate
	 * @font-face rules that Bricks loads automatically.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function bricks_register_fonts() : void {
		if ( ! defined( 'BRICKS_VERSION' ) || ! class_exists( '\Bricks\Custom_Fonts' ) ) {
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

		// Trigger Bricks to load its own custom fonts first.
		$existing = \Bricks\Custom_Fonts::get_custom_fonts();
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		foreach ( $items as $item ) {
			$family = trim( (string) ( $item['family'] ?? '' ) );
			$woff2  = trim( (string) ( $item['woff2_url'] ?? '' ) );
			if ( $family === '' || $woff2 === '' ) {
				continue;
			}

			// Build @font-face CSS rule for Bricks to load.
			$font_face_css = self::build_css( array( $item ) );

			// Key must equal family name — Bricks uses the key as the CSS font-family value.
			$existing[ $family ] = array(
				'id'        => $family,
				'family'    => $family,
				'fontFaces' => $font_face_css,
			);
		}

		// Write back into Bricks' static cache.
		\Bricks\Custom_Fonts::$fonts = $existing;
	}

	/**
	 * Enqueue font CSS inside Bricks Builder canvas.
	 *
	 * Bricks renders its builder inside an iframe that fires wp_enqueue_scripts.
	 * This ensures @font-face rules are available in the builder preview.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function bricks_enqueue_fonts() : void {
		// Only run inside Bricks builder context.
		if ( ! defined( 'BRICKS_VERSION' ) ) {
			return;
		}

		// Skip if not in builder — fonts already load via wp_head on the frontend.
		if ( ! function_exists( 'bricks_is_builder' ) || ! bricks_is_builder() ) {
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
		$css   = self::build_css( $items );

		if ( $css === '' ) {
			return;
		}

		\wp_register_style( 'functionalities-fonts-bricks', false, array(), FUNCTIONALITIES_VERSION );
		\wp_enqueue_style( 'functionalities-fonts-bricks' );
		\wp_add_inline_style( 'functionalities-fonts-bricks', self::sanitize_css( $css ) );
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
	/**
	 * Inject font families and typography assignments into the theme.json data layer.
	 *
	 * Uses the `wp_theme_json_data_theme` filter to register custom font families
	 * (with fontFace definitions) and assign body/heading typography — the native
	 * WordPress way to make fonts available in the block editor and frontend.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_Theme_JSON_Data $theme_json The theme.json data object.
	 * @return \WP_Theme_JSON_Data Modified theme.json data.
	 */
	public static function inject_typography_theme_json( $theme_json ) {
		$opts = self::get_options();

		if ( ! \apply_filters( 'functionalities_fonts_enabled', ! empty( $opts['enabled'] ) ) ) {
			return $theme_json;
		}

		if ( empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return $theme_json;
		}

		$items = \apply_filters( 'functionalities_fonts_items', $opts['items'] );

		// Build fontFamilies array grouped by family name.
		$families_map = array();
		foreach ( $items as $item ) {
			$family = trim( (string) ( $item['family'] ?? '' ) );
			$woff2  = trim( (string) ( $item['woff2_url'] ?? '' ) );
			if ( $family === '' || $woff2 === '' ) {
				continue;
			}

			$slug = sanitize_title( $family );

			if ( ! isset( $families_map[ $slug ] ) ) {
				$families_map[ $slug ] = array(
					'name'       => $family,
					'slug'       => $slug,
					'fontFamily' => '"' . $family . '", sans-serif',
					'fontFace'   => array(),
				);
			}

			$face = array(
				'fontFamily' => $family,
				'fontStyle'  => trim( (string) ( $item['style'] ?? 'normal' ) ),
				'fontDisplay' => trim( (string) ( $item['display'] ?? 'swap' ) ),
				'src'        => array( $woff2 ),
			);

			$woff = trim( (string) ( $item['woff_url'] ?? '' ) );
			if ( $woff !== '' ) {
				$face['src'][] = $woff;
			}

			$unicode = trim( (string) ( $item['unicode_range'] ?? '' ) );
			if ( $unicode !== '' ) {
				$face['unicodeRange'] = $unicode;
			}

			// Weight: variable range or static.
			$is_variable  = ! empty( $item['is_variable'] );
			$weight_range = trim( (string) ( $item['weight_range'] ?? '' ) );
			$weight       = trim( (string) ( $item['weight'] ?? '' ) );

			if ( $is_variable || $weight_range !== '' ) {
				$face['fontWeight'] = self::normalize_weight_range( $weight_range !== '' ? $weight_range : '100 900' );
			} elseif ( $weight !== '' ) {
				$face['fontWeight'] = $weight;
			}

			$families_map[ $slug ]['fontFace'][] = $face;
		}

		if ( empty( $families_map ) ) {
			return $theme_json;
		}

		$font_families = array_values( $families_map );

		$new_data = array(
			'version'  => 3,
			'settings' => array(
				'typography' => array(
					'fontFamilies' => $font_families,
				),
			),
		);

		// Typography assignments: body and heading fonts.
		if ( ! empty( $opts['assign_enabled'] ) ) {
			$body_font     = trim( (string) ( $opts['body_font'] ?? '' ) );
			$heading_font  = trim( (string) ( $opts['heading_font'] ?? '' ) );
			$per_heading   = ! empty( $opts['per_heading'] );
			$heading_fonts = isset( $opts['heading_fonts'] ) && is_array( $opts['heading_fonts'] ) ? $opts['heading_fonts'] : array();

			$styles = array();

			if ( $body_font !== '' ) {
				$body_slug = sanitize_title( $body_font );
				$styles['typography'] = array(
					'fontFamily' => 'var(--wp--preset--font-family--' . $body_slug . ')',
				);
			}

			// Heading element styles.
			$elements = array();

			if ( $heading_font !== '' ) {
				$heading_slug = sanitize_title( $heading_font );
				$elements['heading'] = array(
					'typography' => array(
						'fontFamily' => 'var(--wp--preset--font-family--' . $heading_slug . ')',
					),
				);
			}

			// Per-heading overrides.
			if ( $per_heading && ! empty( $heading_fonts ) ) {
				for ( $i = 1; $i <= 6; $i++ ) {
					$key  = 'h' . $i;
					$font = trim( (string) ( $heading_fonts[ $key ] ?? '' ) );
					if ( $font !== '' ) {
						$font_slug = sanitize_title( $font );
						$elements[ $key ] = array(
							'typography' => array(
								'fontFamily' => 'var(--wp--preset--font-family--' . $font_slug . ')',
							),
						);
					}
				}
			}

			if ( ! empty( $styles ) || ! empty( $elements ) ) {
				$new_data['styles'] = array();
				if ( ! empty( $styles ) ) {
					$new_data['styles']['typography'] = $styles['typography'];
				}
				if ( ! empty( $elements ) ) {
					$new_data['styles']['elements'] = $elements;
				}
			}
		}

		return $theme_json->update_with( $new_data );
	}

	/**
	 * Normalize a variable-font weight range to weights WordPress accepts.
	 *
	 * Variable fonts can advertise an axis such as "1 900", but WordPress's
	 * theme.json sanitiser silently DROPS any fontFace whose fontWeight falls
	 * below its floor — empirically a low bound of 1 is rejected while 100 is
	 * kept — taking the face (and its font-picker entry) with it. Clamping each
	 * numeric bound to [100, 1000] keeps real ranges intact ("100 800" →
	 * unchanged) while rescuing out-of-range ones ("1 900" → "100 900").
	 * Empty or non-numeric input (e.g. a keyword) is returned untouched.
	 *
	 * Applied in both build_css() and inject_typography_theme_json() so the CSS
	 * output and the theme.json picker registration never disagree.
	 *
	 * @since 1.4.7
	 *
	 * @param string $range A single weight ("400") or space-separated range ("1 900").
	 * @return string Normalized weight or range.
	 */
	protected static function normalize_weight_range( string $range ) : string {
		$range = trim( $range );
		if ( $range === '' ) {
			return '';
		}

		$parts      = preg_split( '/\s+/', $range );
		$normalized = array();

		foreach ( (array) $parts as $part ) {
			if ( ! is_numeric( $part ) ) {
				// Leave keywords (normal, bold) or anything unexpected as-is.
				return $range;
			}
			$normalized[] = (string) max( 100, min( 1000, (int) $part ) );
		}

		return implode( ' ', $normalized );
	}

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
			$unicode      = trim( (string) ( $item['unicode_range'] ?? '' ) );

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
				// Variable font with weight range; clamp to weights theme.json accepts
				// so the front end, editor canvas, and picker all agree.
				$range       = self::normalize_weight_range( $weight_range !== '' ? $weight_range : '100 900' );
				$weight_prop = 'font-weight:' . $range . ';';
			} elseif ( $weight !== '' ) {
				// Static font with single weight.
				$weight_prop = 'font-weight:' . $weight . ';';
			}

			// Optional unicode-range (character subset).
			$unicode_prop = $unicode !== '' ? 'unicode-range:' . $unicode . ';' : '';

			// Build the @font-face rule.
			$parts[] = '@font-face{' .
				'font-family:"' . $family . '";' .
				'font-style:' . $style . ';' .
				'font-display:' . ( $display ?: 'swap' ) . ';' .
				$weight_prop .
				'src:' . $src . ';' .
				$unicode_prop .
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

	/**
	 * Add WOFF and WOFF2 to allowed upload MIME types.
	 *
	 * Enables font file uploads through the WordPress media library
	 * so users can upload fonts directly from the Fonts admin UI.
	 *
	 * @since 1.4.5
	 *
	 * @param array $mimes Associative array of allowed MIME types.
	 * @return array Modified MIME types with font formats added.
	 */
	public static function allow_font_mimes( array $mimes ) : array {
		if ( ! \current_user_can( 'upload_files' ) ) {
			return $mimes;
		}

		$mimes['woff']  = 'font/woff';
		$mimes['woff2'] = 'font/woff2';

		return $mimes;
	}

	/**
	 * Verify WOFF/WOFF2 file type and extension on upload.
	 *
	 * WordPress may fail to detect the real MIME type of font files
	 * via finfo/getimagesize. This callback validates font files by
	 * checking their binary magic bytes (signature) to ensure the
	 * uploaded file is genuinely a WOFF or WOFF2 font.
	 *
	 * @since 1.4.5
	 *
	 * @param array       $wp_check Array of file data (ext, type, proper_filename).
	 * @param string      $file     Full path to the file.
	 * @param string      $filename The name of the file.
	 * @param string[]    $mimes    Allowed MIME types keyed by extension.
	 * @param string|false $real_mime The real MIME type or false.
	 * @return array Modified file check data.
	 */
	public static function verify_font_filetype( $wp_check, $file, $filename, $mimes, $real_mime = false ) {
		if ( ! empty( $wp_check['ext'] ) && ! empty( $wp_check['type'] ) ) {
			return $wp_check;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'woff' !== $ext && 'woff2' !== $ext ) {
			return $wp_check;
		}

		// Validate binary signature (magic bytes).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local temp file.
		$header = file_get_contents( $file, false, null, 0, 8 );
		if ( false === $header || strlen( $header ) < 4 ) {
			return $wp_check;
		}

		$valid = false;

		if ( 'woff2' === $ext ) {
			// WOFF2 signature: 0x774F4632 ("wOF2").
			$valid = ( substr( $header, 0, 4 ) === 'wOF2' );
		} elseif ( 'woff' === $ext ) {
			// WOFF signature: 0x774F4646 ("wOFF").
			$valid = ( substr( $header, 0, 4 ) === 'wOFF' );
		}

		if ( $valid ) {
			$wp_check['ext']  = $ext;
			$wp_check['type'] = 'woff2' === $ext ? 'font/woff2' : 'font/woff';
		}

		return $wp_check;
	}
}
