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
		'clippath',
		'mask',
		'use',
		'symbol',
		'title',
		'desc',
		'lineargradient',
		'radialgradient',
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
	 * CSS properties allowed in an imported SVG style attribute.
	 *
	 * @var array
	 */
	private static $allowed_style_properties = array(
		'color',
		'fill',
		'fill-opacity',
		'fill-rule',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-opacity',
		'clip-rule',
		'opacity',
		'stop-color',
		'stop-opacity',
	);

	/**
	 * Request-local counter used to prevent duplicate SVG definition IDs.
	 *
	 * @var int
	 */
	private static $render_instance = 0;

	/**
	 * Initialize the SVG icons module.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function init(): void {
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
		\add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor_styles' ) );
		\add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// Register AJAX handlers.
		\add_action( 'wp_ajax_functionalities_svg_icon_save', array( __CLASS__, 'ajax_save_icon' ) );
		\add_action( 'wp_ajax_functionalities_svg_icon_delete', array( __CLASS__, 'ajax_delete_icon' ) );

		// Add shortcode for icon rendering (backward compatibility).
		\add_shortcode( 'func_icon', array( __CLASS__, 'render_shortcode' ) );

		// Filter content to replace icon placeholders with actual SVG on frontend.
		if ( ! \is_admin() ) {
			\add_filter( 'the_content', array( __CLASS__, 'render_icons_in_content' ), 20 );
		}

		// Register block.
		\add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register the SVG icon block.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$metadata = FUNCTIONALITIES_DIR . 'assets/blocks/svg-icon/block.json';
		if ( ! file_exists( $metadata ) ) {
			return;
		}

		\register_block_type(
			$metadata,
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);

		if ( \function_exists( 'register_block_pattern_category' ) ) {
			\register_block_pattern_category(
				'functionalities',
				array( 'label' => \__( 'Functionalities', 'functionalities' ) )
			);
		}
		if ( \function_exists( 'register_block_pattern' ) && class_exists( '\WP_Icons_Registry' ) && \WP_Icons_Registry::get_instance()->is_registered( 'core/info' ) ) {
			\register_block_pattern(
				'functionalities/icon-callout',
				array(
					'title'       => \__( 'Icon callout', 'functionalities' ),
					'description' => \__( 'A reusable icon and text callout whose icon and label can be overridden in synced patterns.', 'functionalities' ),
					'categories'  => array( 'functionalities', 'text' ),
					'content'     => '<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} --><div class="wp-block-group"><!-- wp:functionalities/svg-icon-block {"iconSource":"core","coreIcon":"core/info","size":32,"sizeUnit":"px"} /--><!-- wp:paragraph --><p>' . \esc_html__( 'Add a concise callout message.', 'functionalities' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
				)
			);
		}
	}

	/**
	 * Render the SVG icon block.
	 *
	 * @since 0.11.0
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public static function render_block( array $attributes ): string {
		$source    = isset( $attributes['iconSource'] ) && 'core' === $attributes['iconSource'] ? 'core' : 'custom';
		$slug      = isset( $attributes['iconSlug'] ) ? \sanitize_key( $attributes['iconSlug'] ) : '';
		$core_icon = isset( $attributes['coreIcon'] ) && preg_match( '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', (string) $attributes['coreIcon'] )
			? (string) $attributes['coreIcon']
			: '';
		if ( ( 'core' === $source && empty( $core_icon ) ) || ( 'custom' === $source && empty( $slug ) ) ) {
			return '';
		}

		$unit = isset( $attributes['sizeUnit'] ) && in_array( $attributes['sizeUnit'], array( 'px', 'em', 'rem' ), true )
			? $attributes['sizeUnit']
			: 'px';
		$size = isset( $attributes['size'] ) && is_numeric( $attributes['size'] )
			? (float) $attributes['size']
			: 48;
		$size = 'px' === $unit
			? max( 8, min( 512, $size ) )
			: max( 0.5, min( 32, $size ) );
		$size = rtrim( rtrim( number_format( $size, 2, '.', '' ), '0' ), '.' );

		$align      = isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'left', 'center', 'right' ), true )
			? $attributes['align']
			: 'none';
		$color      = isset( $attributes['color'] ) ? \sanitize_hex_color( $attributes['color'] ) : '';
		$mode       = isset( $attributes['colorMode'] ) && 'original' === $attributes['colorMode']
			? 'original'
			: 'monochrome';
		$decorative = ! isset( $attributes['decorative'] ) || (bool) $attributes['decorative'];
		$label      = isset( $attributes['label'] ) ? \sanitize_text_field( $attributes['label'] ) : '';

		$render_args = array(
			'block'      => true,
			'color_mode' => $mode,
			'decorative' => $decorative,
			'label'      => $label,
		);
		$svg         = 'core' === $source
			? self::render_core_icon( $core_icon, 'func-svg-icon-block', $render_args )
			: self::render_icon( $slug, 'func-svg-icon-block', $render_args );

		if ( empty( $svg ) ) {
			return '';
		}

		$wrapper_styles  = '--func-icon-size:' . $size . $unit . ';line-height:0;';
		$wrapper_styles .= 'none' !== $align ? 'text-align:' . $align . ';' : '';
		$wrapper_styles .= $color ? 'color:' . $color . ';' : '';

		$wrapper_attributes = array(
			'class' => 'func-svg-icon-block-wrapper is-color-' . $mode,
			'style' => $wrapper_styles,
		);
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			$wrapper = \get_block_wrapper_attributes( $wrapper_attributes );
		} else {
			$wrapper = 'class="' . \esc_attr( $wrapper_attributes['class'] ) . '" style="' . \esc_attr( $wrapper_attributes['style'] ) . '"';
		}

		return '<div ' . $wrapper . '>' . $svg . '</div>';
	}

	/**
	 * Render a WordPress 7 Core Icon through the Functionalities block.
	 *
	 * Core's icon registry is read-only for third parties in WordPress 7.0, so
	 * this consumes the public registry without attempting to modify it.
	 *
	 * @since 1.5.0
	 * @param string $name        Namespaced Core icon name.
	 * @param string $extra_class Optional CSS class.
	 * @param array  $args        Rendering options.
	 * @return string
	 */
	public static function render_core_icon( string $name, string $extra_class = '', array $args = array() ): string {
		if ( ! class_exists( '\WP_Icons_Registry' ) || ! preg_match( '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $name ) ) {
			return '';
		}
		$icon = \WP_Icons_Registry::get_instance()->get_registered_icon( $name );
		if ( ! is_array( $icon ) || empty( $icon['content'] ) ) {
			return '';
		}

		$defaults = array(
			'block'      => true,
			'color_mode' => 'monochrome',
			'decorative' => true,
			'label'      => '',
		);
		$args     = array_merge( $defaults, $args );
		$svg      = self::sanitize_svg( (string) $icon['content'] );
		if ( '' === $svg ) {
			return '';
		}

		$previous_errors = libxml_use_internal_errors( true );
		$doc             = new \DOMDocument();
		$loaded          = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );
		if ( ! $loaded || ! $doc->documentElement ) {
			return '';
		}

		$root    = $doc->documentElement;
		$classes = array( 'func-svg-icon', 'is-core-icon' );
		foreach ( preg_split( '/\s+/', trim( $root->getAttribute( 'class' ) . ' ' . $extra_class ) ) as $class ) {
			$class = \sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}
		$root->setAttribute( 'class', implode( ' ', array_unique( $classes ) ) );
		$root->removeAttribute( 'width' );
		$root->removeAttribute( 'height' );
		$root->setAttribute( 'style', 'display:inline-block;width:var(--func-icon-size);height:var(--func-icon-size);vertical-align:middle' );
		self::apply_monochrome_color( $root );
		$root->setAttribute( 'focusable', 'false' );

		if ( ! empty( $args['decorative'] ) ) {
			$root->setAttribute( 'aria-hidden', 'true' );
			$root->removeAttribute( 'role' );
			$root->removeAttribute( 'aria-label' );
		} else {
			$label = \sanitize_text_field( (string) $args['label'] );
			if ( '' === $label ) {
				$label = isset( $icon['label'] ) ? \sanitize_text_field( (string) $icon['label'] ) : $name;
			}
			$root->removeAttribute( 'aria-hidden' );
			$root->setAttribute( 'role', 'img' );
			$root->setAttribute( 'aria-label', $label );
		}

		$result = $doc->saveXML( $root );
		return $result ? $result : '';
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
	 * @since 0.11.0
	 * @return array Module options.
	 */
	public static function get_options(): array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults      = array(
			'enabled' => false,
			'icons'   => array(),
		);
		$opts          = (array) \get_option( 'functionalities_svg_icons', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Get the list of icons.
	 *
	 * @since 0.11.0
	 * @return array Array of icons with slug => data.
	 */
	public static function get_icons(): array {
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
	public static function enqueue_editor_assets(): void {
		$icons         = self::get_icons();
		$metadata_file = FUNCTIONALITIES_DIR . 'assets/blocks/svg-icon/block.json';
		$metadata      = array();
		if ( file_exists( $metadata_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a bundled local metadata file.
			$metadata = json_decode( (string) file_get_contents( $metadata_file ), true );
		}

		// Register editor script.
		\wp_enqueue_script(
			'functionalities-svg-icons-editor',
			FUNCTIONALITIES_URL . 'assets/js/svg-icons-editor.js',
			array( 'wp-api-fetch', 'wp-rich-text', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-dom-ready', 'wp-blocks' ),
			FUNCTIONALITIES_VERSION,
			true
		);

		// Pass only configuration. Icons are paginated through the REST API when requested.
		\wp_localize_script(
			'functionalities-svg-icons-editor',
			'functionalitiesSvgIcons',
			array(
				'icons'         => array(), // Kept for third-party integrations that inspect this key.
				'iconCount'     => count( $icons ),
				'restPath'      => '/functionalities/v1/svg-icons',
				'coreRestPath'  => '/wp/v2/icons',
				'hasCoreIcons'  => class_exists( '\WP_Icons_Registry' ),
				'blockMetadata' => is_array( $metadata ) ? $metadata : array(),
				'nonce'         => \wp_create_nonce( 'functionalities_svg_icons' ),
				'ajaxUrl'       => \admin_url( 'admin-ajax.php' ),
				'i18n'          => array(
					'insertIcon'      => \__( 'Insert icon shortcode', 'functionalities' ),
					'searchIcons'     => \__( 'Search icons', 'functionalities' ),
					'noIcons'         => \__( 'No icons found. Add icons in Functionalities > SVG Icons.', 'functionalities' ),
					'noMatchingIcons' => \__( 'No matching icons found.', 'functionalities' ),
					'blockTitle'      => \__( 'SVG Icon', 'functionalities' ),
					'blockDesc'       => \__( 'Insert an SVG icon from your library as a block.', 'functionalities' ),
					'changeIcon'      => \__( 'Change icon', 'functionalities' ),
					'iconSettings'    => \__( 'Icon settings', 'functionalities' ),
					'iconSize'        => \__( 'Icon size', 'functionalities' ),
					'sizeUnit'        => \__( 'Size unit', 'functionalities' ),
					'colorMode'       => \__( 'Color mode', 'functionalities' ),
					'monochrome'      => \__( 'Monochrome (inherit text color)', 'functionalities' ),
					'originalColors'  => \__( 'Original SVG colors', 'functionalities' ),
					'decorative'      => \__( 'Decorative icon', 'functionalities' ),
					'decorativeHelp'  => \__( 'Decorative icons are hidden from assistive technology.', 'functionalities' ),
					'accessibility'   => \__( 'Accessibility label', 'functionalities' ),
					'selectIcon'      => \__( 'Select icon', 'functionalities' ),
					'loadingIcons'    => \__( 'Loading icons…', 'functionalities' ),
					'loadMore'        => \__( 'Load more', 'functionalities' ),
					'loadError'       => \__( 'Icons could not be loaded. Try again.', 'functionalities' ),
					'missingIcon'     => \__( 'The selected icon is no longer in the library. Choose a replacement.', 'functionalities' ),
					'recentIcons'     => \__( 'Recent icons', 'functionalities' ),
					'iconSource'      => \__( 'Icon source', 'functionalities' ),
					'customLibrary'   => \__( 'Custom library', 'functionalities' ),
					'coreLibrary'     => \__( 'WordPress Core', 'functionalities' ),
				),
			)
		);

		// CSS loaded via enqueue_editor_styles() on enqueue_block_assets for WP 7 iframe compatibility.
	}

	/**
	 * Enqueue editor CSS via enqueue_block_assets for WP 7 iframe compatibility.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function enqueue_editor_styles(): void {
		if ( ! \is_admin() ) {
			return;
		}

		\wp_enqueue_style(
			'functionalities-svg-icons-editor',
			FUNCTIONALITIES_URL . 'assets/css/svg-icons-editor.css',
			array(),
			FUNCTIONALITIES_VERSION
		);

		$inline_styles = '
			.func-icon-wrapper {
				display: inline-flex !important;
				align-items: center;
				line-height: 0;
			}
			.func-icon-wrapper .func-icon,
			svg.func-icon,
			.func-icon {
				display: inline-block !important;
				width: 1em !important;
				height: 1em !important;
				vertical-align: -0.125em;
			}
		';
		\wp_add_inline_style( 'functionalities-svg-icons-editor', $inline_styles );
	}

	/**
	 * Register the paginated icon-library endpoint used by the editor.
	 *
	 * @since 1.4.8
	 * @return void
	 */
	public static function register_rest_routes(): void {
		\register_rest_route(
			'functionalities/v1',
			'/svg-icons',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return \current_user_can( 'edit_posts' );
				},
				'callback'            => array( __CLASS__, 'rest_get_icons' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 48,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Return a filtered page of icons for the block editor.
	 *
	 * @since 1.4.8
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function rest_get_icons( \WP_REST_Request $request ): \WP_REST_Response {
		$icons    = array_values( self::get_icons() );
		$search   = strtolower( (string) $request->get_param( 'search' ) );
		$included = array_filter( array_map( 'sanitize_key', explode( ',', (string) $request->get_param( 'include' ) ) ) );

		if ( $included ) {
			$icons = array_values(
				array_filter(
					$icons,
					static function ( array $icon ) use ( $included ): bool {
						return isset( $icon['slug'] ) && in_array( $icon['slug'], $included, true );
					}
				)
			);
		} elseif ( '' !== $search ) {
			$icons = array_values(
				array_filter(
					$icons,
					static function ( array $icon ) use ( $search ): bool {
						$name = isset( $icon['name'] ) ? strtolower( (string) $icon['name'] ) : '';
						$slug = isset( $icon['slug'] ) ? strtolower( (string) $icon['slug'] ) : '';
						return false !== strpos( $name, $search ) || false !== strpos( $slug, $search );
					}
				)
			);
		}

		usort(
			$icons,
			static function ( array $first, array $second ): int {
				return strcasecmp( (string) ( $first['name'] ?? $first['slug'] ?? '' ), (string) ( $second['name'] ?? $second['slug'] ?? '' ) );
			}
		);

		$total    = count( $icons );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$icons    = array_slice( $icons, ( $page - 1 ) * $per_page, $per_page );
		$icons    = array_values(
			array_filter(
				array_map(
					static function ( array $icon ): array {
						$svg = isset( $icon['svg'] ) ? self::sanitize_svg( (string) $icon['svg'] ) : '';
						if ( '' === $svg ) {
							return array();
						}
						return array(
							'slug' => isset( $icon['slug'] ) ? \sanitize_key( $icon['slug'] ) : '',
							'name' => isset( $icon['name'] ) ? \sanitize_text_field( $icon['name'] ) : '',
							'svg'  => $svg,
						);
					},
					$icons
				)
			)
		);

		$response = new \WP_REST_Response( $icons );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * Sanitize SVG content to prevent XSS attacks.
	 *
	 * @since 0.11.0
	 * @param string $svg Raw SVG content.
	 * @return string Sanitized SVG content.
	 */
	public static function sanitize_svg( string $svg ): string {
		// Remove any PHP tags.
		$svg = preg_replace( '/\<\?.*?\?\>/s', '', $svg );

		// Remove scripts.
		$svg = preg_replace( '/\<script\b[^\>]*\>.*?\<\/script\>/is', '', $svg );

		// Remove HTML/XML comments (including Font Awesome attribution, etc.).
		$svg = preg_replace( '/\<!--.*?--\>/s', '', $svg );

		// Remove event handlers (onclick, onload, etc.).
		$svg = preg_replace( '/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg );
		$svg = preg_replace( '/\bon\w+\s*=\s*[^\s\>]*/i', '', $svg );

		// Remove javascript: URLs.
		$svg = preg_replace( '/javascript\s*:/i', '', $svg );

		// Remove data: URLs (can contain scripts).
		$svg = preg_replace( '/data\s*:/i', '', $svg );

		// Parse the SVG without resolving external resources.
		$previous_errors = libxml_use_internal_errors( true );
		$doc             = new \DOMDocument();
		$loaded          = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( ! $loaded || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->localName ) ) {
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
	private static function sanitize_node( \DOMElement $node ): void {
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
				continue;
			}

			$value = trim( $attr->nodeValue );
			if ( 'style' === $attrName ) {
				$safe_style = self::sanitize_svg_style( $value );
				if ( '' === $safe_style ) {
					$attrs_to_remove[] = $attr->nodeName;
				} else {
					$attr->nodeValue = $safe_style;
				}
			} elseif ( 'id' === $attrName ) {
				if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $value ) ) {
					$attrs_to_remove[] = $attr->nodeName;
				}
			} elseif ( in_array( $attrName, array( 'href', 'xlink:href' ), true ) ) {
				if ( ! preg_match( '/^#[A-Za-z_][A-Za-z0-9_.:-]*$/', $value ) ) {
					$attrs_to_remove[] = $attr->nodeName;
				}
			} elseif ( in_array( $attrName, array( 'fill', 'stroke', 'stop-color' ), true )
				&& (
					preg_match( '/(?:javascript\s*:|data\s*:|expression\s*\()/i', $value )
					|| ( false !== stripos( $value, 'url(' )
						&& ! preg_match( '/^url\(\s*#[A-Za-z_][A-Za-z0-9_.:-]*\s*\)$/i', $value ) )
				)
			) {
				$attrs_to_remove[] = $attr->nodeName;
			} elseif ( in_array( $attrName, array( 'clip-path', 'mask' ), true )
				&& 'none' !== strtolower( $value )
				&& ! preg_match( '/^url\(\s*#[A-Za-z_][A-Za-z0-9_.:-]*\s*\)$/i', $value )
			) {
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
	 * Sanitize an SVG style attribute using a narrow property allowlist.
	 *
	 * @since 1.4.8
	 * @param string $style Raw style declaration.
	 * @return string Safe declaration list.
	 */
	private static function sanitize_svg_style( string $style ): string {
		if ( preg_match( '/(?:expression|javascript\s*:|data\s*:|@import|behavior\s*:|-moz-binding)/i', $style ) ) {
			return '';
		}

		$safe = array();
		foreach ( explode( ';', $style ) as $declaration ) {
			if ( false === strpos( $declaration, ':' ) ) {
				continue;
			}

			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
			$property                 = strtolower( $property );
			if ( ! in_array( $property, self::$allowed_style_properties, true ) || '' === $value ) {
				continue;
			}

			if ( false !== stripos( $value, 'url(' )
				&& ! preg_match( '/^url\(\s*#[A-Za-z_][A-Za-z0-9_.:-]*\s*\)$/i', $value )
			) {
				continue;
			}

			if ( false !== strpbrk( $value, '<>"\\' ) ) {
				continue;
			}

			$safe[] = $property . ':' . $value;
		}

		return implode( ';', $safe );
	}

	/**
	 * AJAX handler for saving an icon.
	 *
	 * @since 0.11.0
	 * @return void
	 */
	public static function ajax_save_icon(): void {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization.
		$nonce = isset( $_POST['nonce'] ) ? \wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_svg_icons' ) ) {
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
		$name = isset( $_POST['name'] ) ? \sanitize_text_field( \wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- SVG is sanitized with self::sanitize_svg().
		$svg = isset( $_POST['svg'] ) ? \wp_unslash( $_POST['svg'] ) : '';

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
		$sanitized_svg = self::sanitize_svg( (string) $sanitized_svg );
		if ( empty( $sanitized_svg ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid SVG content.', 'functionalities' ) ) );
			return;
		}

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
		self::$options = $opts;

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
	public static function ajax_delete_icon(): void {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't require sanitization.
		$nonce = isset( $_POST['nonce'] ) ? \wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'functionalities_svg_icons' ) ) {
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
			self::$options = $opts;
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
	public static function render_shortcode( array $atts ): string {
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
	 * @param string $slug        The icon slug.
	 * @param string $extra_class Optional extra CSS class.
	 * @param array  $args        Rendering options.
	 * @return string The rendered SVG HTML.
	 */
	public static function render_icon( string $slug, string $extra_class = '', array $args = array() ): string {
		$icons = self::get_icons();

		if ( ! isset( $icons[ $slug ] ) ) {
			return '';
		}

		$defaults = array(
			'block'      => false,
			'color_mode' => 'monochrome',
			'decorative' => true,
			'label'      => '',
		);
		$args     = array_merge( $defaults, $args );
		$svg      = self::sanitize_svg( (string) $icons[ $slug ]['svg'] );
		if ( '' === $svg ) {
			return '';
		}

		$previous_errors = libxml_use_internal_errors( true );
		$doc             = new \DOMDocument();
		$loaded          = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );
		if ( ! $loaded || ! $doc->documentElement ) {
			return '';
		}

		$root = $doc->documentElement;
		++self::$render_instance;
		self::prefix_definition_ids( $root, 'func-svg-' . self::$render_instance . '-' );

		$classes = array( 'func-svg-icon' );
		foreach ( preg_split( '/\s+/', trim( $root->getAttribute( 'class' ) . ' ' . $extra_class ) ) as $class ) {
			$class = \sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}
		$root->setAttribute( 'class', implode( ' ', array_unique( $classes ) ) );
		$root->removeAttribute( 'width' );
		$root->removeAttribute( 'height' );
		$root->setAttribute(
			'style',
			! empty( $args['block'] )
				? 'display:inline-block;width:var(--func-icon-size);height:var(--func-icon-size);vertical-align:middle'
				: 'display:inline-block;width:1em;height:1em;vertical-align:-0.125em'
		);

		if ( 'original' !== $args['color_mode'] ) {
			self::apply_monochrome_color( $root );
		}

		$root->setAttribute( 'focusable', 'false' );
		if ( ! empty( $args['decorative'] ) ) {
			$root->setAttribute( 'aria-hidden', 'true' );
			$root->removeAttribute( 'role' );
			$root->removeAttribute( 'aria-label' );
		} else {
			$label = \sanitize_text_field( (string) $args['label'] );
			if ( '' === $label ) {
				$label = isset( $icons[ $slug ]['name'] )
					? \sanitize_text_field( (string) $icons[ $slug ]['name'] )
					: $slug;
			}
			$root->removeAttribute( 'aria-hidden' );
			$root->setAttribute( 'role', 'img' );
			$root->setAttribute( 'aria-label', $label );
		}

		$result = $doc->saveXML( $root );
		return $result ? $result : '';
	}

	/**
	 * Prefix definition IDs and their local references to avoid DOM collisions.
	 *
	 * @since 1.4.8
	 * @param \DOMElement $root   SVG root.
	 * @param string      $prefix Unique prefix.
	 * @return void
	 */
	private static function prefix_definition_ids( \DOMElement $root, string $prefix ): void {
		$nodes  = array( $root );
		$id_map = array();
		foreach ( $root->getElementsByTagName( '*' ) as $node ) {
			$nodes[] = $node;
		}

		foreach ( $nodes as $node ) {
			if ( $node->hasAttribute( 'id' ) ) {
				$old_id            = $node->getAttribute( 'id' );
				$new_id            = $prefix . $old_id;
				$id_map[ $old_id ] = $new_id;
				$node->setAttribute( 'id', $new_id );
			}
		}

		if ( ! $id_map ) {
			return;
		}

		foreach ( $nodes as $node ) {
			foreach ( array( 'href', 'xlink:href', 'fill', 'stroke', 'clip-path', 'mask', 'style' ) as $attribute ) {
				if ( ! $node->hasAttribute( $attribute ) ) {
					continue;
				}
				$value = $node->getAttribute( $attribute );
				foreach ( $id_map as $old_id => $new_id ) {
					$value = str_replace( '#' . $old_id, '#' . $new_id, $value );
				}
				$node->setAttribute( $attribute, $value );
			}
		}
	}

	/**
	 * Convert SVG paint attributes to currentColor without making fill="none" shapes solid.
	 *
	 * @since 1.4.8
	 * @param \DOMElement $root SVG root.
	 * @return void
	 */
	private static function apply_monochrome_color( \DOMElement $root ): void {
		$nodes = array( $root );
		foreach ( $root->getElementsByTagName( '*' ) as $node ) {
			$nodes[] = $node;
		}

		$fill_shapes = array( 'path', 'circle', 'ellipse', 'rect', 'polygon', 'polyline' );
		foreach ( $nodes as $node ) {
			$has_paint = false;
			foreach ( array( 'fill', 'stroke' ) as $paint ) {
				if ( $node->hasAttribute( $paint ) ) {
					$has_paint = true;
					if ( 'none' !== strtolower( trim( $node->getAttribute( $paint ) ) ) ) {
						$node->setAttribute( $paint, 'currentColor' );
					}
				}
			}

			if ( $node->hasAttribute( 'style' ) ) {
				$style = preg_replace( '/\b(fill|stroke)\s*:\s*(?!none\b)[^;]+/i', '$1:currentColor', $node->getAttribute( 'style' ) );
				$node->setAttribute( 'style', $style );
				$has_paint = $has_paint || preg_match( '/\b(?:fill|stroke)\s*:/i', $style );
			}

			if ( ! $has_paint && in_array( strtolower( $node->localName ), $fill_shapes, true ) ) {
				$node->setAttribute( 'fill', 'currentColor' );
			}
		}
	}

	/**
	 * Render icons in post content.
	 *
	 * Converts <i class="func-icon" data-icon="slug"></i> to inline SVG.
	 * Also supports legacy <span> tags for backward compatibility.
	 *
	 * @since 0.11.0
	 * @param string $content The post content.
	 * @return string Modified content with SVG icons.
	 */
	public static function render_icons_in_content( string $content ): string {
		if ( false === strpos( $content, 'func-icon' ) ) {
			return $content;
		}

		// Match <i> tags with func-icon class (primary format).
		// Supports both attribute orders, slashed/unslashed quotes, and unclosed tags.
		$pattern_i = '/<i[^>]+(?:class=\\\\?"[^"]*func-icon[^"]*\\\\?"[^>]+data-icon=\\\\?"([^"\\\]+)\\\\?"|data-icon=\\\\?"([^"\\\]+)\\\\?"[^>]+class=\\\\?"[^"]*func-icon[^"]*\\\\?")[^>]*>(?:[^<]*<\/i>)?/i';

		$content = preg_replace_callback(
			$pattern_i,
			function ( $matches ) {
				$slug = ! empty( $matches[1] ) ? $matches[1] : ( isset( $matches[2] ) ? $matches[2] : '' );
				return self::render_icon( \sanitize_key( $slug ) );
			},
			$content
		);

		// Legacy support: Match <span> tags with func-icon class.
		$pattern_span = '/<span[^>]+(?:class=\\\\?"[^"]*func-icon[^"]*\\\\?"[^>]+data-icon=\\\\?"([^"\\\]+)\\\\?"|data-icon=\\\\?"([^"\\\]+)\\\\?"[^>]+class=\\\\?"[^"]*func-icon[^"]*\\\\?")[^>]*>(?:[^<]*<\/span>)?/i';

		$content = preg_replace_callback(
			$pattern_span,
			function ( $matches ) {
				$slug = ! empty( $matches[1] ) ? $matches[1] : ( isset( $matches[2] ) ? $matches[2] : '' );
				return self::render_icon( \sanitize_key( $slug ) );
			},
			$content
		);

		return $content;
	}
}
