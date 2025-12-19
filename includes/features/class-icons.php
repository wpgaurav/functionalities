<?php
/**
 * Icons Module.
 *
 * Provides Font Awesome asset removal and automatic conversion
 * of Font Awesome markup to SVG sprite references.
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
 * Icons class for Font Awesome optimization.
 *
 * Removes Font Awesome CSS/JS assets to reduce page weight and optionally
 * converts Font Awesome icon markup to use an SVG sprite instead.
 * This significantly improves performance while maintaining icon compatibility.
 *
 * ## Features
 *
 * - Remove Font Awesome CSS and JS assets
 * - Block Font Awesome asset loading from enqueue
 * - Convert FA icon markup to SVG sprite references
 * - Works with fa, fas, far, fab prefixes
 *
 * ## Filters
 *
 * ### functionalities_icons_remove_fa_enabled
 * Controls whether Font Awesome asset removal is active.
 *
 * @since 0.8.0
 * @param bool $enabled Whether FA removal is enabled.
 *
 * @example
 * // Keep Font Awesome on specific pages
 * add_filter( 'functionalities_icons_remove_fa_enabled', function( $enabled ) {
 *     if ( is_page( 'icon-showcase' ) ) {
 *         return false;
 *     }
 *     return $enabled;
 * } );
 *
 * ### functionalities_icons_convert_enabled
 * Controls whether FA to SVG conversion is active.
 *
 * @since 0.8.0
 * @param bool $enabled Whether conversion is enabled.
 *
 * ### functionalities_icons_sprite_url
 * Filters the SVG sprite URL used for icon references.
 *
 * @since 0.8.0
 * @param string $url The sprite URL.
 *
 * @example
 * // Use a CDN-hosted sprite
 * add_filter( 'functionalities_icons_sprite_url', function( $url ) {
 *     return 'https://cdn.example.com/sprites/fa-solid.svg';
 * } );
 *
 * ### functionalities_icons_fa_handles
 * Filters the list of Font Awesome script/style handles to deregister.
 *
 * @since 0.8.0
 * @param array $handles Array of handle names.
 *
 * ### functionalities_icons_converted_content
 * Filters the content after FA icons have been converted to SVG.
 *
 * @since 0.8.0
 * @param string $content   The converted content.
 * @param string $original  The original content.
 *
 * @since 0.3.0
 */
class Icons {

	/**
	 * Initialize the icons module.
	 *
	 * Registers hooks for asset removal and content conversion.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public static function init() : void {
		// Deregister FA assets on both frontend and admin.
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_fa_assets' ), 100 );
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'strip_fa_assets' ), 100 );

		// Block FA assets from loading via src filter.
		\add_filter( 'style_loader_src', array( __CLASS__, 'block_fa_src' ), 10, 2 );
		\add_filter( 'script_loader_src', array( __CLASS__, 'block_fa_src' ), 10, 2 );

		// Convert FA markup to SVG in content.
		\add_filter( 'the_content', array( __CLASS__, 'convert_fa_to_svg' ), 13 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.3.0
	 *
	 * @return array {
	 *     Icons options.
	 *
	 *     @type bool   $remove_fontawesome_assets Whether to remove FA assets.
	 *     @type bool   $convert_fa_to_svg         Whether to convert FA to SVG.
	 *     @type string $svg_sprite_url            URL to the SVG sprite file.
	 * }
	 */
	protected static function get_options() : array {
		$defaults = array(
			'remove_fontawesome_assets' => false,
			'convert_fa_to_svg'         => false,
			'svg_sprite_url'            => '',
		);
		$opts = (array) \get_option( 'functionalities_icons', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Deregister and dequeue Font Awesome assets.
	 *
	 * Attempts to remove common Font Awesome script and style handles.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filter for handles list.
	 *
	 * @return void
	 */
	public static function strip_fa_assets() : void {
		$opts = self::get_options();

		/**
		 * Filters whether FA asset removal is enabled.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether removal is enabled.
		 */
		if ( ! \apply_filters( 'functionalities_icons_remove_fa_enabled', ! empty( $opts['remove_fontawesome_assets'] ) ) ) {
			return;
		}

		/**
		 * Filters the list of Font Awesome handles to deregister.
		 *
		 * @since 0.8.0
		 *
		 * @param array $handles Array of script/style handle names.
		 */
		$handles = \apply_filters(
			'functionalities_icons_fa_handles',
			array( 'font-awesome', 'fontawesome', 'fa', 'fas', 'far', 'fab', 'fontawesome-all' )
		);

		foreach ( $handles as $handle ) {
			\wp_deregister_style( $handle );
			\wp_dequeue_style( $handle );
			\wp_deregister_script( $handle );
			\wp_dequeue_script( $handle );
		}
	}

	/**
	 * Block Font Awesome assets from loading via src filter.
	 *
	 * Checks asset URLs and returns false for FA-related resources
	 * to prevent them from being loaded.
	 *
	 * @since 0.3.0
	 *
	 * @param string|bool $src    The source URL of the asset.
	 * @param string      $handle The asset handle name.
	 * @return string|bool The source URL or false to block.
	 */
	public static function block_fa_src( $src, $handle ) {
		$opts = self::get_options();

		/** This filter is documented in class-icons.php */
		if ( ! \apply_filters( 'functionalities_icons_remove_fa_enabled', ! empty( $opts['remove_fontawesome_assets'] ) ) ) {
			return $src;
		}

		// Block URLs containing Font Awesome references.
		if ( is_string( $src ) && ( stripos( $src, 'fontawesome' ) !== false || stripos( $src, 'font-awesome' ) !== false ) ) {
			return false;
		}

		return $src;
	}

	/**
	 * Convert Font Awesome markup to SVG sprite references.
	 *
	 * Parses content and replaces FA icon elements (i/span with fa-* classes)
	 * with SVG elements that reference the configured sprite.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filters for extensibility.
	 *
	 * @param string $content The content to process.
	 * @return string The processed content with SVG icons.
	 */
	public static function convert_fa_to_svg( string $content ) : string {
		$opts = self::get_options();

		/**
		 * Filters whether FA to SVG conversion is enabled.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether conversion is enabled.
		 */
		if ( ! \apply_filters( 'functionalities_icons_convert_enabled', ! empty( $opts['convert_fa_to_svg'] ) ) ) {
			return $content;
		}

		/**
		 * Filters the SVG sprite URL.
		 *
		 * @since 0.8.0
		 *
		 * @param string $url The sprite URL from settings.
		 */
		$sprite = \apply_filters( 'functionalities_icons_sprite_url', trim( (string) ( $opts['svg_sprite_url'] ?? '' ) ) );

		if ( $sprite === '' ) {
			return $content;
		}

		// Store original for filter.
		$original = $content;

		// Parse HTML with DOMDocument.
		$prev = libxml_use_internal_errors( true );
		$dom  = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="__fx">' . $content . '</div>' );
		$xpath = new \DOMXPath( $dom );

		// Find elements with FA classes.
		$nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " fa ") or contains(@class, "fa-") or contains(@class, "fas") or contains(@class, "far") or contains(@class, "fab")]' );

		if ( $nodes instanceof \DOMNodeList ) {
			// Classes that are not icon names.
			$non_icon_classes = array( 'fa', 'fas', 'far', 'fab', 'fal', 'fad', 'fat', 'fa-fw' );

			foreach ( $nodes as $el ) {
				if ( ! ( $el instanceof \DOMElement ) ) {
					continue;
				}

				$cls = ' ' . strtolower( (string) $el->getAttribute( 'class' ) ) . ' ';

				// Extract the icon class (fa-*).
				$icon = '';
				foreach ( preg_split( '/\s+/', trim( $cls ) ) as $c ) {
					if ( $c === '' ) {
						continue;
					}
					if ( in_array( $c, $non_icon_classes, true ) ) {
						continue;
					}
					if ( strpos( $c, 'fa-' ) === 0 ) {
						$icon = $c;
						break;
					}
				}

				if ( $icon === '' ) {
					continue;
				}

				// Create SVG element.
				$svg = $dom->createElement( 'svg' );
				$svg->setAttribute( 'class', 'icon ' . $icon );
				$svg->setAttribute( 'aria-hidden', 'true' );

				// Create use element referencing sprite.
				$use = $dom->createElement( 'use' );
				$use->setAttribute( 'href', $sprite . '#' . $icon );
				$svg->appendChild( $use );

				// Replace original element.
				$el->parentNode->replaceChild( $svg, $el );
			}
		}

		// Extract processed content.
		$out  = '';
		$wrap = $dom->getElementById( '__fx' );
		if ( $wrap ) {
			foreach ( $wrap->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$result = $out !== '' ? $out : $content;

		/**
		 * Filters the content after FA to SVG conversion.
		 *
		 * @since 0.8.0
		 *
		 * @param string $result   The converted content.
		 * @param string $original The original content.
		 */
		return \apply_filters( 'functionalities_icons_converted_content', $result, $original );
	}
}
