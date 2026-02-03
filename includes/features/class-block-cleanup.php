<?php
/**
 * Block Cleanup Module.
 *
 * Removes WordPress block-specific CSS classes from frontend output to reduce
 * HTML bloat and allow for cleaner, custom styling.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.2.0
 * @version    0.9.2
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block_Cleanup class for stripping Gutenberg block classes.
 *
 * This module removes WordPress block editor classes from frontend HTML output,
 * including heading blocks, list blocks, and image blocks. Useful for themes
 * that prefer custom styling without block-specific class interference.
 *
 * ## Features
 *
 * - Remove `wp-block-heading` from h1-h6 elements
 * - Remove `wp-block-list` from ul/ol elements
 * - Remove `wp-block-image` from figure/div elements
 * - Remove `wp-block-paragraph` from paragraph elements
 * - Remove `wp-block-quote` from blockquote elements
 * - Remove `wp-block-table` from table elements
 * - Remove `wp-block-separator` from hr/separator elements
 * - Remove `wp-block-group` from group containers
 * - Remove `wp-block-columns` and `wp-block-column` from column layouts
 * - Remove `wp-block-buttons` and `wp-block-button` from button elements
 * - Remove `wp-block-cover` from cover blocks
 * - Remove `wp-block-media-text` from media-text blocks
 * - Custom class removal: specify your own classes to remove
 *
 * ## Filters
 *
 * ### functionalities_block_cleanup_enabled
 * Controls whether block cleanup runs on the current request.
 *
 * @since 0.8.0
 * @param bool $enabled Whether block cleanup is enabled. Default based on settings.
 *
 * @example
 * // Disable block cleanup for logged-in users
 * add_filter( 'functionalities_block_cleanup_enabled', function( $enabled ) {
 *     return $enabled && ! is_user_logged_in();
 * } );
 *
 * ### functionalities_block_cleanup_content
 * Filters the content after block classes have been removed.
 *
 * @since 0.8.0
 * @param string $content   The processed content.
 * @param string $original  The original content before processing.
 *
 * @example
 * // Add custom processing after block cleanup
 * add_filter( 'functionalities_block_cleanup_content', function( $content, $original ) {
 *     // Additional processing
 *     return str_replace( 'old-class', 'new-class', $content );
 * }, 10, 2 );
 *
 * ### functionalities_block_cleanup_classes
 * Filters the list of block classes to remove.
 *
 * @since 0.8.0
 * @param array $classes Array of class names to strip from elements.
 *
 * @example
 * // Add additional classes to remove
 * add_filter( 'functionalities_block_cleanup_classes', function( $classes ) {
 *     $classes[] = 'wp-block-paragraph';
 *     return $classes;
 * } );
 *
 * @since 0.2.0
 */
class Block_Cleanup {

	/**
	 * Initialize the block cleanup module.
	 *
	 * Registers the content filter if any cleanup options are enabled.
	 * Uses priority 12 to run after most content filters but before
	 * final output processing.
	 *
	 * @since 0.2.0
	 * @since 0.9.2 Added support for additional block types and custom classes.
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		// Only add filter if at least one cleanup option is enabled.
		$has_enabled_option = ! empty( $opts['remove_heading_block_class'] ) ||
			! empty( $opts['remove_list_block_class'] ) ||
			! empty( $opts['remove_image_block_class'] ) ||
			! empty( $opts['remove_paragraph_block_class'] ) ||
			! empty( $opts['remove_quote_block_class'] ) ||
			! empty( $opts['remove_table_block_class'] ) ||
			! empty( $opts['remove_separator_block_class'] ) ||
			! empty( $opts['remove_group_block_class'] ) ||
			! empty( $opts['remove_columns_block_class'] ) ||
			! empty( $opts['remove_button_block_class'] ) ||
			! empty( $opts['remove_cover_block_class'] ) ||
			! empty( $opts['remove_media_text_block_class'] ) ||
			! empty( trim( $opts['custom_classes_to_remove'] ) );

		if ( $has_enabled_option ) {
			\add_filter( 'the_content', array( __CLASS__, 'filter_content_cleanup' ), 12 );
		}
	}

	/**
	 * Get module options with defaults.
	 *
	 * Retrieves saved options from the database and merges with defaults.
	 *
	 * @since 0.2.0
	 * @since 0.9.2 Added additional block types and custom class removal.
	 *
	 * @return array {
	 *     Block cleanup options.
	 *
	 *     @type bool   $remove_heading_block_class    Remove wp-block-heading from headings.
	 *     @type bool   $remove_list_block_class       Remove wp-block-list from lists.
	 *     @type bool   $remove_image_block_class      Remove wp-block-image from images.
	 *     @type bool   $remove_paragraph_block_class  Remove wp-block-paragraph from paragraphs.
	 *     @type bool   $remove_quote_block_class      Remove wp-block-quote from blockquotes.
	 *     @type bool   $remove_table_block_class      Remove wp-block-table from tables.
	 *     @type bool   $remove_separator_block_class  Remove wp-block-separator from separators.
	 *     @type bool   $remove_group_block_class      Remove wp-block-group from groups.
	 *     @type bool   $remove_columns_block_class    Remove wp-block-columns/column from columns.
	 *     @type bool   $remove_button_block_class     Remove wp-block-button/buttons from buttons.
	 *     @type bool   $remove_cover_block_class      Remove wp-block-cover from covers.
	 *     @type bool   $remove_media_text_block_class Remove wp-block-media-text from media-text.
	 *     @type string $custom_classes_to_remove      Custom classes to remove (one per line).
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
			'remove_heading_block_class'    => false,
			'remove_list_block_class'       => false,
			'remove_image_block_class'      => false,
			'remove_paragraph_block_class'  => false,
			'remove_quote_block_class'      => false,
			'remove_table_block_class'      => false,
			'remove_separator_block_class'  => false,
			'remove_group_block_class'      => false,
			'remove_columns_block_class'    => false,
			'remove_button_block_class'     => false,
			'remove_cover_block_class'      => false,
			'remove_media_text_block_class' => false,
			'custom_classes_to_remove'      => '',
		);
		$opts = (array) \get_option( 'functionalities_block_cleanup', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Filter content to remove block-specific classes.
	 *
	 * Parses the content HTML using DOMDocument and removes specified
	 * block classes from matching elements. Skips processing in admin,
	 * feeds, and REST API requests.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filters for extensibility.
	 * @since 0.9.2 Added support for additional block types and custom classes.
	 *
	 * @param string $content The post content to filter.
	 * @return string The filtered content with block classes removed.
	 */
	public static function filter_content_cleanup( string $content ) : string {
		// Skip in admin, feeds, and REST requests.
		if ( \is_admin() || \is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		// Skip empty content.
		if ( trim( $content ) === '' || false === strpos( $content, 'wp-block-' ) ) {
			return $content;
		}

		$opts = self::get_options();

		/**
		 * Filters whether block cleanup should run on this request.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether cleanup is enabled based on settings.
		 */
		$enabled = \apply_filters( 'functionalities_block_cleanup_enabled', true );
		if ( ! $enabled ) {
			return $content;
		}

		// Build list of classes to remove based on settings.
		$classes_to_remove = array();

		// Original block classes.
		if ( ! empty( $opts['remove_heading_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-heading';
		}
		if ( ! empty( $opts['remove_list_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-list';
		}
		if ( ! empty( $opts['remove_image_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-image';
		}

		// New block classes (v0.9.2).
		if ( ! empty( $opts['remove_paragraph_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-paragraph';
		}
		if ( ! empty( $opts['remove_quote_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-quote';
		}
		if ( ! empty( $opts['remove_table_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-table';
		}
		if ( ! empty( $opts['remove_separator_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-separator';
		}
		if ( ! empty( $opts['remove_group_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-group';
		}
		if ( ! empty( $opts['remove_columns_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-columns';
			$classes_to_remove[] = 'wp-block-column';
		}
		if ( ! empty( $opts['remove_button_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-buttons';
			$classes_to_remove[] = 'wp-block-button';
			$classes_to_remove[] = 'wp-block-button__link';
		}
		if ( ! empty( $opts['remove_cover_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-cover';
			$classes_to_remove[] = 'wp-block-cover__inner-container';
		}
		if ( ! empty( $opts['remove_media_text_block_class'] ) ) {
			$classes_to_remove[] = 'wp-block-media-text';
			$classes_to_remove[] = 'wp-block-media-text__content';
			$classes_to_remove[] = 'wp-block-media-text__media';
		}

		// Custom classes to remove.
		if ( ! empty( $opts['custom_classes_to_remove'] ) ) {
			$custom_classes = preg_split( '/[\r\n]+/', $opts['custom_classes_to_remove'] );
			foreach ( $custom_classes as $class ) {
				$class = trim( $class );
				if ( $class !== '' ) {
					$classes_to_remove[] = $class;
				}
			}
		}

		/**
		 * Filters the list of block classes to remove.
		 *
		 * @since 0.8.0
		 *
		 * @param array $classes_to_remove Array of class names to strip.
		 */
		$classes_to_remove = \apply_filters( 'functionalities_block_cleanup_classes', $classes_to_remove );

		// Early return if nothing to remove.
		if ( empty( $classes_to_remove ) ) {
			return $content;
		}

		// Store original for filter.
		$original = $content;

		// Parse HTML with DOMDocument.
		$libxml_prev = libxml_use_internal_errors( true );
		$dom         = new \DOMDocument( '1.0', 'UTF-8' );
		$html        = '<div id="__functionalities_wrapper">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		$xpath = new \DOMXPath( $dom );

		// Remove all specified classes from all elements.
		foreach ( $classes_to_remove as $class ) {
			$class = trim( $class );
			if ( $class === '' ) {
				continue;
			}

			// Find all elements with this class.
			$escaped_class = addcslashes( $class, '"' );
			$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $escaped_class . ' ")]' );
			self::strip_class_from_nodes( $nodes, $class );
		}

		// Extract processed content.
		$out     = '';
		$wrapper = $dom->getElementById( '__functionalities_wrapper' );
		if ( $wrapper ) {
			foreach ( $wrapper->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_prev );

		$result = $out !== '' ? $out : $content;

		/**
		 * Filters the content after block classes have been removed.
		 *
		 * @since 0.8.0
		 *
		 * @param string $result   The processed content.
		 * @param string $original The original content before processing.
		 */
		return \apply_filters( 'functionalities_block_cleanup_content', $result, $original );
	}

	/**
	 * Strip a specific class from a list of DOM nodes.
	 *
	 * Removes the specified class name from all elements in the node list.
	 * If the element has no remaining classes after removal, the class
	 * attribute is removed entirely.
	 *
	 * @since 0.2.0
	 *
	 * @param \DOMNodeList|false $nodes DOMNodeList of elements to process.
	 * @param string             $class The class name to remove.
	 * @return void
	 */
	protected static function strip_class_from_nodes( $nodes, string $class ) : void {
		if ( ! ( $nodes instanceof \DOMNodeList ) ) {
			return;
		}

		foreach ( $nodes as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}

			$cls = $el->getAttribute( 'class' );
			if ( $cls === '' ) {
				continue;
			}

			// Split classes and filter out the target class.
			$parts = preg_split( '/\s+/', $cls );
			$parts = array_filter(
				$parts,
				function ( $c ) use ( $class ) {
					return strtolower( (string) $c ) !== strtolower( $class );
				}
			);
			$parts = array_values( array_unique( $parts ) );

			// Update or remove class attribute.
			if ( empty( $parts ) ) {
				$el->removeAttribute( 'class' );
			} else {
				$el->setAttribute( 'class', implode( ' ', $parts ) );
			}
		}
	}
}
