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
	 * Get module options with defaults.
	 *
	 * If no items are configured, loads the default component set.
	 *
	 * @since 0.3.0
	 *
	 * @return array {
	 *     Components options.
	 *
	 *     @type bool  $enabled Whether components output is enabled.
	 *     @type array $items   Array of component definitions.
	 * }
	 */
	protected static function get_options() : array {
		$defaults = array(
			'enabled' => true,
			'items'   => array(),
		);
		$opts = (array) \get_option( 'functionalities_components', $defaults );
		$out  = array_merge( $defaults, $opts );

		// Load default components if none configured.
		if ( empty( $out['items'] ) || ! is_array( $out['items'] ) ) {
			if ( class_exists( '\\Functionalities\\Admin\\Admin' ) ) {
				$out['items'] = \Functionalities\Admin\Admin::default_components();
			} else {
				$out['items'] = array();
			}
		}

		return $out;
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
			echo '<link rel="stylesheet" id="functionalities-components-css" href="' . \esc_url( $file['url'] . '?ver=' . rawurlencode( $file['ver'] ) ) . '" media="all" />';
			return;
		}

		// Fallback to inline styles.
		echo '<style id="functionalities-components-inline">' . $css . '</style>';
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
	 * Ensure CSS file exists and is up-to-date.
	 *
	 * Creates or updates the CSS file in the uploads directory.
	 * Uses MD5 hash comparison to avoid unnecessary writes.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filter for file path, action on update.
	 *
	 * @param string $css The CSS content to write.
	 * @return array|null {
	 *     File info array on success, null on failure.
	 *
	 *     @type string $path Full file system path.
	 *     @type string $url  Public URL to the file.
	 *     @type string $ver  Version hash for cache busting.
	 * }
	 */
	protected static function ensure_css_file( string $css ) : ?array {
		$upload = \wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return null;
		}

		$dir = rtrim( (string) $upload['basedir'], '/\\' ) . '/functionalities';

		// Create directory if needed.
		if ( ! is_dir( $dir ) && ! \wp_mkdir_p( $dir ) ) {
			return null;
		}

		/**
		 * Filters the path to the components CSS file.
		 *
		 * @since 0.8.0
		 *
		 * @param string $path Full path to the CSS file.
		 */
		$file = \apply_filters( 'functionalities_components_file_path', $dir . '/components.css' );

		$hash          = md5( $css );
		$existing_hash = is_file( $file ) ? md5_file( $file ) : '';

		// Only write if content has changed.
		if ( $hash !== $existing_hash ) {
			$bytes = @file_put_contents( $file, $css );

			if ( false === $bytes ) {
				return null;
			}

			/**
			 * Fires when the components CSS file is regenerated.
			 *
			 * @since 0.8.0
			 *
			 * @param string $file Path to the generated CSS file.
			 * @param string $css  The CSS content written.
			 */
			\do_action( 'functionalities_components_updated', $file, $css );
		}

		$url = rtrim( (string) $upload['baseurl'], '/\\' ) . '/functionalities/components.css';

		return array(
			'path' => $file,
			'url'  => $url,
			'ver'  => $hash,
		);
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
}
