<?php
/**
 * SVG Icons Module.
 *
 * Allows users to upload/paste SVG icons and insert them inline in the block editor.
 * Icons inherit the size of the surrounding text element (headings, paragraphs, etc.).
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.11.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG Icons class for managing custom SVG icon library.
 *
 * ## Features
 *
 * - Upload or paste SVG icons with custom namespaces
 * - Insert icons inline in the block editor via RichText toolbar
 * - Icons automatically inherit font size from parent element
 * - Secure SVG sanitization to prevent XSS attacks
 * - Zero frontend footprint when no icons are used on a page
 *
 * ## Filters
 *
 * ### functionalities_svg_icons_enabled
 * Controls whether the SVG icons feature is active.
 *
 * @since 0.11.0
 * @param bool $enabled Whether the feature is enabled.
 *
 * ### functionalities_svg_icons_list
 * Filters the list of available icons.
 *
 * @since 0.11.0
 * @param array $icons Array of icon data.
 *
 * ### functionalities_svg_icons_sanitize
 * Filters the sanitized SVG content before saving.
 *
 * @since 0.11.0
 * @param string $svg   The sanitized SVG content.
 * @param string $slug  The icon slug/namespace.
 *
 * @since 0.11.0
 */
class SVG_Icons {

	/**
	 * Allowed SVG elements for sanitization.
	 *
	 * @var array
	 */
	private static $allowed_elements = array(
		'svg',
		'g',
		'path',
		'circle',
		'ellipse',
		'rect',
		'line',
		'polyline',
		'polygon',
		'defs',
		'clipPath',
		'mask',
		'use',
		'symbol',
		'title',
		'desc',
		'linearGradient',
		'radialGradient',
		'stop',
	);

	/**
	 * Allowed SVG attributes for sanitization.
	 * Note: All attributes are lowercase for case-insensitive comparison.
	 *
	 * @var array
	 */
	private static $allowed_attributes = array(
		'id',
		'class',
		'style',
		'xmlns',
		'xmlns:xlink',
		'viewbox',
		'width',
		'height',
		'fill',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-opacity',
		'fill-opacity',
		'fill-rule',
		'clip-rule',
		'opacity',
		'transform',
		'd',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'x',
		'x1',
		'x2',
		'y',
		'y1',
		'y2',
		'points',
		'clip-path',
		'mask',
		'xlink:href',
		'href',
		'gradientunits',
		'gradienttransform',
		'spreadmethod',
		'offset',
		'stop-color',
		'stop-opacity',
		'preserveaspectratio',
		'version',
		'xml:space',
		'enable-background',
	);

	/**
	 * Initialize the SVG icons module.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		// Check if module is enabled.
		$enabled = ! empty( $opts['enabled'] );

		/**
		 * Filters whether the SVG icons feature is enabled.
		 *
		 * @since 0.11.0
		 * @param bool $enabled Whether the feature is enabled.
		 */
		if ( ! \apply_filters( 'functionalities_svg_icons_enabled', $enabled ) ) {
			return;
		}

		// Register block editor assets.
		\add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );

		// Register AJAX handlers.
		\add_action( 'wp_ajax_functionalities_svg_icon_save', array( __CLASS__, 'ajax_save_icon' ) );
		\add_action( 'wp_ajax_functionalities_svg_icon_delete', array( __CLASS__, 'ajax_delete_icon' ) );

		// Add shortcode for icon rendering (fallback for non-JS).
		\add_shortcode( 'func_icon', array( __CLASS__, 'render_shortcode' ) );

		// Filter content to render inline SVG icons.
		\add_filter( 'the_content', array( __CLASS__, 'render_icons_in_content' ), 20 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.11.0
	 * @return array Module options.
	 */
	public static function get_options() : array {
		$defaults = array(
			'enabled' => false,
			'icons'   => array(),
		);
		$opts = (array) \get_option( 'functionalities_svg_icons', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get the list of icons.
	 *
	 * @since 0.11.0
	 * @return array Array of icons with slug => data.
	 */
	public static function get_icons() : array {
		$opts  = self::get_options();
		$icons = isset( $opts['icons'] ) && is_array( $opts['icons'] ) ? $opts['icons'] : array();

		/**
		 * Filters the list of available icons.
		 *
		 * @since 0.11.0
		 * @param array $icons Array of icon data.
		 */
		return \apply_filters( 'functionalities_svg_icons_list', $icons );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function enqueue_editor_assets() : void {
		$icons = self::get_icons();

		// Register editor script.
		\wp_enqueue_script(
			'functionalities-svg-icons-editor',
			FUNCTIONALITIES_URL . 'assets/js/svg-icons-editor.js',
			array( 'wp-rich-text', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-dom-ready' ),
			FUNCTIONALITIES_VERSION,
			true
		);

		// Pass icons data to JavaScript.
		\wp_localize_script(
			'functionalities-svg-icons-editor',
			'functionalitiesSvgIcons',
			array(
				'icons'   => array_values( $icons ),
				'nonce'   => \wp_create_nonce( 'functionalities_svg_icons' ),
				'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'insertIcon'  => \__( 'Insert Icon', 'functionalities' ),
					'searchIcons' => \__( 'Search icons...', 'functionalities' ),
					'noIcons'     => \__( 'No icons found. Add icons in Functionalities > SVG Icons.', 'functionalities' ),
				),
			)
		);

		// Register editor styles.
		\wp_enqueue_style(
			'functionalities-svg-icons-editor',
			FUNCTIONALITIES_URL . 'assets/css/svg-icons-editor.css',
			array(),
			FUNCTIONALITIES_VERSION
		);
	}

	/**
	 * Sanitize SVG content to prevent XSS attacks.
	 *
	 * @since 0.11.0
	 * @param string $svg Raw SVG content.
	 * @return string Sanitized SVG content.
	 */
	public static function sanitize_svg( string $svg ) : string {
		// Remove any PHP tags.
		$svg = preg_replace( '/<\?.*?\?>/s', '', $svg );

		// Remove scripts.
		$svg = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg );

		// Remove event handlers (onclick, onload, etc.).
		$svg = preg_replace( '/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg );
		$svg = preg_replace( '/\bon\w+\s*=\s*[^\s>]*/i', '', $svg );

		// Remove javascript: URLs.
		$svg = preg_replace( '/javascript\s*:/i', '', $svg );

		// Remove data: URLs (can contain scripts).
		$svg = preg_replace( '/data\s*:/i', '', $svg );

		// Parse the SVG.
		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();
		$doc->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();

		if ( ! $doc->documentElement ) {
			return '';
		}

		// Process the DOM.
		self::sanitize_node( $doc->documentElement );

		// Get the sanitized SVG.
		$result = $doc->saveXML( $doc->documentElement );

		return $result ? $result : '';
	}

	/**
	 * Recursively sanitize a DOM node.
	 *
	 * @since 0.11.0
	 * @param \DOMElement $node The DOM node to sanitize.
	 * @return void
	 */
	private static function sanitize_node( \DOMElement $node ) : void {
		$nodeName = strtolower( $node->nodeName );

		// Remove disallowed elements.
		if ( ! in_array( $nodeName, self::$allowed_elements, true ) ) {
			$node->parentNode->removeChild( $node );
			return;
		}

		// Remove disallowed attributes.
		$attrs_to_remove = array();
		foreach ( $node->attributes as $attr ) {
			$attrName = strtolower( $attr->nodeName );
			if ( ! in_array( $attrName, self::$allowed_attributes, true ) ) {
				$attrs_to_remove[] = $attr->nodeName;
			}
		}
		foreach ( $attrs_to_remove as $attr ) {
			$node->removeAttribute( $attr );
		}

		// Process child nodes.
		$children = array();
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}
		foreach ( $children as $child ) {
			if ( $child instanceof \DOMElement ) {
				self::sanitize_node( $child );
			}
		}
	}

	/**
	 * AJAX handler for saving an icon.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function ajax_save_icon() : void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_svg_icons' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		// Get and validate input.
		$slug = isset( $_POST['slug'] ) ? \sanitize_key( $_POST['slug'] ) : '';
		$name = isset( $_POST['name'] ) ? \sanitize_text_field( $_POST['name'] ) : '';
		$svg  = isset( $_POST['svg'] ) ? \wp_unslash( $_POST['svg'] ) : '';

		if ( empty( $slug ) || empty( $svg ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Slug and SVG content are required.', 'functionalities' ) ) );
			return;
		}

		// Sanitize the SVG.
		$sanitized_svg = self::sanitize_svg( $svg );

		if ( empty( $sanitized_svg ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid SVG content.', 'functionalities' ) ) );
			return;
		}

		/**
		 * Filters the sanitized SVG content before saving.
		 *
		 * @since 0.11.0
		 * @param string $sanitized_svg The sanitized SVG content.
		 * @param string $slug          The icon slug/namespace.
		 */
		$sanitized_svg = \apply_filters( 'functionalities_svg_icons_sanitize', $sanitized_svg, $slug );

		// Get current options.
		$opts = self::get_options();
		if ( ! isset( $opts['icons'] ) || ! is_array( $opts['icons'] ) ) {
			$opts['icons'] = array();
		}

		// Add or update the icon.
		$opts['icons'][ $slug ] = array(
			'slug' => $slug,
			'name' => $name ?: $slug,
			'svg'  => $sanitized_svg,
		);

		// Save options.
		\update_option( 'functionalities_svg_icons', $opts );

		\wp_send_json_success(
			array(
				'message' => \__( 'Icon saved successfully.', 'functionalities' ),
				'icon'    => $opts['icons'][ $slug ],
			)
		);
	}

	/**
	 * AJAX handler for deleting an icon.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function ajax_delete_icon() : void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_svg_icons' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return;
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return;
		}

		// Get slug.
		$slug = isset( $_POST['slug'] ) ? \sanitize_key( $_POST['slug'] ) : '';

		if ( empty( $slug ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Icon slug is required.', 'functionalities' ) ) );
			return;
		}

		// Get current options.
		$opts = self::get_options();

		// Remove the icon.
		if ( isset( $opts['icons'][ $slug ] ) ) {
			unset( $opts['icons'][ $slug ] );
			\update_option( 'functionalities_svg_icons', $opts );
		}

		\wp_send_json_success( array( 'message' => \__( 'Icon deleted successfully.', 'functionalities' ) ) );
	}

	/**
	 * Render the icon shortcode.
	 *
	 * @since 0.11.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered icon HTML.
	 */
	public static function render_shortcode( array $atts ) : string {
		$atts = \shortcode_atts(
			array(
				'name'  => '',
				'class' => '',
			),
			$atts,
			'func_icon'
		);

		$slug = \sanitize_key( $atts['name'] );
		if ( empty( $slug ) ) {
			return '';
		}

		return self::render_icon( $slug, $atts['class'] );
	}

	/**
	 * Render an icon by slug.
	 *
	 * @since 0.11.0
	 * @param string $slug       The icon slug.
	 * @param string $extra_class Optional extra CSS class.
	 * @return string The rendered SVG HTML.
	 */
	public static function render_icon( string $slug, string $extra_class = '' ) : string {
		$icons = self::get_icons();

		if ( ! isset( $icons[ $slug ] ) ) {
			return '';
		}

		$svg = $icons[ $slug ]['svg'];

		// Add inline styles for size inheritance and proper alignment.
		$svg = preg_replace(
			'/<svg\b/',
			'<svg class="func-svg-icon' . ( $extra_class ? ' ' . \esc_attr( $extra_class ) : '' ) . '" style="width:1em;height:1em;vertical-align:-0.125em;fill:currentColor" aria-hidden="true"',
			$svg,
			1
		);

		// Remove any existing width/height attributes to allow CSS control.
		$svg = preg_replace( '/\s(width|height)="[^"]*"/', '', $svg );

		return $svg;
	}

	/**
	 * Render icons in post content.
	 *
	 * Converts <span class="func-icon" data-icon="slug"></span> to inline SVG.
	 *
	 * @since 0.11.0
	 * @param string $content The post content.
	 * @return string Modified content with SVG icons.
	 */
	public static function render_icons_in_content( string $content ) : string {
		// Match icon placeholders.
		$pattern = '/<span\s+class="func-icon"\s+data-icon="([^"]+)"[^>]*><\/span>/i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$slug = \sanitize_key( $matches[1] );
				return self::render_icon( $slug );
			},
			$content
		);
	}
}
