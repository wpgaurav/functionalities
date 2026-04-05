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

	use \Functionalities\Traits\CSS_Sanitizer;

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
		\add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor_fonts' ) );

		// Typography assignments via theme.json data layer.
		\add_filter( 'wp_theme_json_data_theme', array( __CLASS__, 'inject_typography_theme_json' ) );

		// Bricks Builder support.
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'bricks_enqueue_fonts' ) );
		\add_action( 'init', array( __CLASS__, 'bricks_register_fonts' ), 99 );

		// Allow WOFF/WOFF2 uploads in the media library.
		\add_filter( 'upload_mimes', array( __CLASS__, 'allow_font_mimes' ) );
		\add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'verify_font_filetype' ), 10, 5 );
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
	 * Enqueue font CSS in the block editor iframe for WP 7 compatibility.
	 *
	 * The admin_head hook outputs to the parent frame, but WP 7's iframed
	 * editor needs fonts loaded via enqueue_block_assets to reach the iframe.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function enqueue_editor_fonts() : void {
		if ( ! \is_admin() ) {
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

		\wp_register_style( 'functionalities-fonts-editor', false, array(), FUNCTIONALITIES_VERSION );
		\wp_enqueue_style( 'functionalities-fonts-editor' );
		\wp_add_inline_style( 'functionalities-fonts-editor', self::sanitize_css( $css ) );
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

			// Weight: variable range or static.
			$is_variable  = ! empty( $item['is_variable'] );
			$weight_range = trim( (string) ( $item['weight_range'] ?? '' ) );
			$weight       = trim( (string) ( $item['weight'] ?? '' ) );

			if ( $is_variable || $weight_range !== '' ) {
				$face['fontWeight'] = $weight_range !== '' ? $weight_range : '100 900';
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
