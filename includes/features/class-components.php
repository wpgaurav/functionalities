<?php
/**
 * Components CSS Generator Module.
 *
 * Generates custom CSS from user-defined component styles and outputs
 * them either as an external file or inline styles.
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
 * Components class for generating and serving custom CSS.
 *
 * Allows users to define reusable CSS components through the admin interface.
 * The generated CSS is compiled into a single file stored in the uploads
 * directory, with automatic cache invalidation when components change.
 *
 * ## Features
 *
 * - Admin-configurable CSS components
 * - Automatic CSS file generation in uploads directory
 * - Hash-based cache busting for browser caching
 * - Fallback to inline styles if file writing fails
 * - Works on both frontend and admin
 *
 * ## Filters
 *
 * ### functionalities_components_enabled
 * Controls whether components CSS is output.
 *
 * @since 0.8.0
 * @param bool $enabled Whether components are enabled.
 *
 * ### functionalities_components_items
 * Filters the array of component items before CSS generation.
 *
 * @since 0.8.0
 * @param array $items Array of component definitions.
 *
 * @example
 * // Add a dynamic component
 * add_filter( 'functionalities_components_items', function( $items ) {
 *     $items[] = array(
 *         'class' => '.dynamic-component',
 *         'css'   => 'background: var(--color-primary);',
 *     );
 *     return $items;
 * } );
 *
 * ### functionalities_components_css
 * Filters the generated CSS before output or file writing.
 *
 * @since 0.8.0
 * @param string $css   The generated CSS string.
 * @param array  $items The component items array.
 *
 * @example
 * // Add CSS minification
 * add_filter( 'functionalities_components_css', function( $css, $items ) {
 *     return preg_replace( '/\s+/', ' ', $css );
 * }, 10, 2 );
 *
 * ### functionalities_components_file_path
 * Filters the file path for the generated CSS file.
 *
 * @since 0.8.0
 * @param string $path The full file path.
 *
 * ## Actions
 *
 * ### functionalities_components_before_output
 * Fires before component CSS is output.
 *
 * @since 0.8.0
 *
 * ### functionalities_components_updated
 * Fires when component CSS file is regenerated.
 *
 * @since 0.8.0
 * @param string $file_path Path to the generated CSS file.
 * @param string $css       The CSS content written.
 *
 * @since 0.3.0
 */
class Components {

	/**
	 * Initialize the components module.
	 *
	 * Registers hooks for CSS output and settings update handling.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public static function init() : void {
		// Output CSS in footer (both frontend and admin).
		\add_action( 'wp_footer', array( __CLASS__, 'print_footer_link' ), 90 );
		\add_action( 'admin_footer', array( __CLASS__, 'print_footer_link' ), 90 );

		// Regenerate CSS file when settings are updated.
		\add_action( 'update_option_functionalities_components', array( __CLASS__, 'on_option_update' ), 10, 2 );
	}

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Get module options with defaults.
	 *
	 * If no items are configured, loads the default component set.
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
			'css_ver' => '',
		);
		$opts = (array) \get_option( 'functionalities_components', $defaults );
		self::$options = array_merge( $defaults, $opts );

		// Load default components if none configured.
		if ( empty( self::$options['items'] ) || ! is_array( self::$options['items'] ) ) {
			if ( class_exists( '\\Functionalities\\Admin\\Admin' ) ) {
				self::$options['items'] = \Functionalities\Admin\Admin::default_components();
			} else {
				self::$options['items'] = array();
			}
		}

		return self::$options;
	}

	/**
	 * Output CSS link or inline styles in footer.
	 *
	 * Attempts to serve CSS from an external file for optimal caching.
	 * Falls back to inline styles if file writing is not possible.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filters for extensibility.
	 *
	 * @return void
	 */
	public static function print_footer_link() : void {
		$opts = self::get_options();

		/**
		 * Filters whether components CSS should be output.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether components are enabled.
		 */
		if ( ! \apply_filters( 'functionalities_components_enabled', ! empty( $opts['enabled'] ) ) ) {
			return;
		}

		if ( empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return;
		}

		/**
		 * Fires before component CSS is output.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_components_before_output' );

		/**
		 * Filters the component items before CSS generation.
		 *
		 * @since 0.8.0
		 *
		 * @param array $items Array of component definitions.
		 */
		$items = \apply_filters( 'functionalities_components_items', $opts['items'] );

		$css  = self::build_css( $items );
		$file = self::ensure_css_file( $css );

		// Output external file link if available.
		if ( $file && isset( $file['url'], $file['ver'] ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Dynamically generated CSS file from user components.
			echo '<link rel="stylesheet" id="functionalities-components-css" href="' . \esc_url( $file['url'] . '?ver=' . rawurlencode( $file['ver'] ) ) . '" media="all" />';
			return;
		}

		// Fallback to inline styles with CSS sanitization.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized.
		echo '<style id="functionalities-components-inline">' . self::sanitize_css( $css ) . '</style>';
	}

	/**
	 * Build CSS from component items.
	 *
	 * Generates CSS rules from the component definitions array.
	 * Automatically adds marquee keyframes if the marquee component is used.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filter for CSS output.
	 *
	 * @param array $items Array of component definitions.
	 * @return string Generated CSS string.
	 */
	protected static function build_css( array $items ) : string {
		$parts = array();

		foreach ( $items as $item ) {
			$selector = trim( (string) ( $item['class'] ?? '' ) );
			$rules    = (string) ( $item['css'] ?? '' );

			if ( $selector === '' || $rules === '' ) {
				continue;
			}

			$parts[] = $selector . '{' . $rules . '}';
		}

		$out = implode( "\n", $parts );

		// Add marquee animation keyframes if marquee component is used.
		if ( strpos( $out, '.c-marquee' ) !== false ) {
			$out .= "\n@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}";
		}

		/**
		 * Filters the generated CSS before output.
		 *
		 * @since 0.8.0
		 *
		 * @param string $css   The generated CSS string.
		 * @param array  $items The component items array.
		 */
		return \apply_filters( 'functionalities_components_css', rtrim( $out ), $items );
	}

	/**
	 * Cached CSS file info.
	 *
	 * @var array|null
	 */
	private static $css_file_info = null;

	/**
	 * Ensure the components CSS file exists and is up to date.
	 *
	 * @param string $css The CSS content to write.
	 * @return array|null File info array on success, null on failure.
	 */
	protected static function ensure_css_file( string $css ) : ?array {
		if ( null !== self::$css_file_info ) {
			return self::$css_file_info;
		}

		$upload = \wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}

		$dir  = rtrim( (string) $upload['basedir'], '/\\' ) . '/functionalities';
		$file = \apply_filters( 'functionalities_components_file_path', $dir . '/components.css' );
		$hash = md5( $css );

		// Avoid md5_file on every request. Check if file exists first.
		$file_exists = is_file( $file );
		
		// If we have a cached version in options, compare with current hash.
		$opts = self::get_options();
		$needs_update = ! $file_exists || ( ! empty( $opts['css_ver'] ) && $opts['css_ver'] !== $hash );
		
		// If no cached version in options but file exists, we might still want to check once.
		if ( ! $needs_update && $file_exists && empty( $opts['css_ver'] ) ) {
			if ( md5_file( $file ) !== $hash ) {
				$needs_update = true;
			}
		}

		if ( $needs_update ) {
			// Create directory if needed.
			if ( ! is_dir( $dir ) && ! \wp_mkdir_p( $dir ) ) {
				return null;
			}

			$sanitized_css = self::sanitize_css( $css );

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			global $wp_filesystem;
			if ( ! WP_Filesystem() ) {
				$bytes = file_put_contents( $file, $sanitized_css );
				if ( false === $bytes ) {
					return null;
				}
			} else {
				if ( ! $wp_filesystem->put_contents( $file, $sanitized_css, FS_CHMOD_FILE ) ) {
					return null;
				}
			}

			// Update the version in options to avoid future checks.
			$opts['css_ver'] = $hash;
			\update_option( 'functionalities_components', $opts );
			self::$options = $opts; // Update static cache.

			\do_action( 'functionalities_components_updated', $file, $css );
		}

		$url = rtrim( (string) $upload['baseurl'], '/\\' ) . '/functionalities/components.css';

		self::$css_file_info = array(
			'path' => $file,
			'url'  => $url,
			'ver'  => $hash,
		);

		return self::$css_file_info;
	}

	/**
	 * Handle option update to regenerate CSS file.
	 *
	 * Called when the components settings are saved in admin.
	 * Triggers CSS file regeneration to reflect new changes.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public static function on_option_update( $old_value, $value ) : void {
		$opts = is_array( $value ) ? $value : array();

		if ( empty( $opts['enabled'] ) || empty( $opts['items'] ) || ! is_array( $opts['items'] ) ) {
			return;
		}

		$css = self::build_css( $opts['items'] );
		self::ensure_css_file( $css );
	}

	/**
	 * Sanitize CSS output to prevent injection.
	 *
	 * Removes potentially dangerous CSS content including:
	 * - HTML tags
	 * - Style closing tags
	 * - JavaScript expressions
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
