<?php
/**
 * Schema Microdata Module.
 *
 * Adds Schema.org microdata attributes to HTML elements for improved
 * structured data and SEO.
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
 * Schema class for adding microdata to HTML.
 *
 * Automatically adds itemscope, itemtype, and itemprop attributes to
 * appropriate HTML elements. Supports WebPage, WPHeader, WPFooter,
 * and Article schemas.
 *
 * ## Features
 *
 * - Add WebPage schema to html element
 * - Add WPHeader schema to header element
 * - Add WPFooter schema to footer element
 * - Add Article schema to content wrapper
 * - Automatic headline, date, and author itemprop assignment
 *
 * ## Filters
 *
 * ### functionalities_schema_enabled
 * Controls whether schema output is active.
 *
 * @since 0.8.0
 * @param bool $enabled Whether schema is enabled.
 *
 * ### functionalities_schema_site_itemtype
 * Filters the Schema.org type for the html element.
 *
 * @since 0.8.0
 * @param string $type The schema type (e.g., 'WebPage', 'WebSite').
 *
 * @example
 * // Change page type based on template
 * add_filter( 'functionalities_schema_site_itemtype', function( $type ) {
 *     if ( is_front_page() ) {
 *         return 'WebSite';
 *     }
 *     return $type;
 * } );
 *
 * ### functionalities_schema_article_itemtype
 * Filters the Schema.org type for article content.
 *
 * @since 0.8.0
 * @param string $type The article type (e.g., 'Article', 'BlogPosting').
 *
 * @example
 * // Use BlogPosting for posts
 * add_filter( 'functionalities_schema_article_itemtype', function( $type ) {
 *     if ( is_singular( 'post' ) ) {
 *         return 'BlogPosting';
 *     }
 *     return $type;
 * } );
 *
 * ### functionalities_schema_language_attributes
 * Filters the language attributes output.
 *
 * @since 0.8.0
 * @param string $output The language attributes string.
 *
 * ### functionalities_schema_article_content
 * Filters the content after article schema has been applied.
 *
 * @since 0.8.0
 * @param string $content   The processed content.
 * @param string $original  The original content.
 *
 * ## Actions
 *
 * ### functionalities_schema_before_buffer
 * Fires before output buffering starts for schema processing.
 *
 * @since 0.8.0
 *
 * @since 0.3.0
 */
class Schema {

	/**
	 * Initialize the schema module.
	 *
	 * Registers filters for language attributes, output buffering
	 * for header/footer schemas, and content filtering for articles.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public static function init() : void {
		// Add itemscope/itemtype to html element.
		\add_filter( 'language_attributes', array( __CLASS__, 'filter_language_attributes' ), 20 );

		// Start output buffering for header/footer schema injection.
		\add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ) );

		// Add article schema to content.
		\add_filter( 'the_content', array( __CLASS__, 'filter_article' ), 14 );

		// Add breadcrumbs JSON-LD.
		\add_action( 'wp_footer', array( __CLASS__, 'output_breadcrumbs' ) );
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
	 * @since 0.3.0
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enable_site_schema'  => true,
			'site_itemtype'       => 'WebPage',
			'enable_header_part'  => true,
			'enable_footer_part'  => true,
			'enable_article'      => true,
			'article_itemtype'    => 'Article',
			'add_headline'        => true,
			'add_dates'           => true,
			'add_author'          => true,
			'enable_breadcrumbs'  => false,
		);
		$opts = (array) \get_option( 'functionalities_schema', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Filter language attributes to add site schema.
	 *
	 * Adds itemscope and itemtype attributes to the html element
	 * if not already present.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filters for customization.
	 *
	 * @param string $output The language attributes string.
	 * @return string Modified attributes string.
	 */
	public static function filter_language_attributes( string $output ) : string {
		$opts = self::get_options();

		/**
		 * Filters whether site schema should be added.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether schema is enabled.
		 */
		if ( ! \apply_filters( 'functionalities_schema_enabled', ! empty( $opts['enable_site_schema'] ) ) ) {
			return $output;
		}

		/**
		 * Filters the site schema itemtype.
		 *
		 * @since 0.8.0
		 *
		 * @param string $type The Schema.org type.
		 */
		$type     = \apply_filters( 'functionalities_schema_site_itemtype', $opts['site_itemtype'] ?? 'WebPage' );
		$type     = preg_replace( '/[^A-Za-z]/', '', (string) $type );
		$itemtype = 'https://schema.org/' . $type;

		// Add itemscope if not present.
		if ( strpos( $output, 'itemscope' ) === false ) {
			$output .= ' itemscope';
		}

		// Add itemtype if not present.
		if ( strpos( $output, 'itemtype=' ) === false ) {
			$output .= ' itemtype="' . \esc_attr( $itemtype ) . '"';
		}

		/**
		 * Filters the final language attributes output.
		 *
		 * @since 0.8.0
		 *
		 * @param string $output The modified attributes string.
		 */
		return \apply_filters( 'functionalities_schema_language_attributes', $output );
	}

	/**
	 * Start output buffering for header/footer schema injection.
	 *
	 * Skips buffering in admin and feeds where schema is not needed.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added action for extensibility.
	 *
	 * @return void
	 */
	public static function start_buffer() : void {
		if ( \is_admin() || \is_feed() ) {
			return;
		}

		$opts = self::get_options();

		if ( empty( $opts['enable_header_part'] ) && empty( $opts['enable_footer_part'] ) ) {
			return;
		}

		/**
		 * Fires before output buffering starts.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_schema_before_buffer' );

		ob_start( array( __CLASS__, 'buffer_callback' ) );
	}

	/**
	 * Process buffered output to add header/footer schemas.
	 *
	 * Parses the complete HTML output and adds WPHeader and WPFooter
	 * schema attributes to the respective elements.
	 *
	 * @since 0.3.0
	 *
	 * @param string $html The complete page HTML.
	 * @return string Modified HTML with schema attributes.
	 */
	public static function buffer_callback( string $html ) : string {
		$opts = self::get_options();

		$enable_header = ! empty( $opts['enable_header_part'] );
		$enable_footer = ! empty( $opts['enable_footer_part'] );

		if ( ! $enable_header && ! $enable_footer ) {
			return $html;
		}

		// Use regex for performance instead of full DOM parsing of the entire page.
		if ( $enable_header ) {
			$html = preg_replace( '/<header\b(?![^>]*itemscope)/i', '<header itemscope itemtype="https://schema.org/WPHeader"', $html, 1 );
		}

		if ( $enable_footer ) {
			$html = preg_replace( '/<footer\b(?![^>]*itemscope)/i', '<footer itemscope itemtype="https://schema.org/WPFooter"', $html, 1 );
		}

		return $html;
	}

	/**
	 * Filter content to add article schema.
	 *
	 * Adds Article (or configured type) schema to the content wrapper,
	 * and assigns appropriate itemprops to headlines, dates, and authors.
	 *
	 * @since 0.3.0
	 * @since 0.8.0 Added filters for customization.
	 *
	 * @param string $content The post content.
	 * @return string Modified content with schema attributes.
	 */
	public static function filter_article( string $content ) : string {
		if ( trim( $content ) === '' || ! \is_singular() ) {
			return $content;
		}

		$opts = self::get_options();

		/** This filter is documented in class-schema.php */
		if ( ! \apply_filters( 'functionalities_schema_enabled', ! empty( $opts['enable_article'] ) ) ) {
			return $content;
		}

		// Store original for filter.
		$original = $content;

		/**
		 * Filters the article schema itemtype.
		 *
		 * @since 0.8.0
		 *
		 * @param string $type The Schema.org article type.
		 */
		$type = \apply_filters( 'functionalities_schema_article_itemtype', $opts['article_itemtype'] ?? 'Article' );
		$type = preg_replace( '/[^A-Za-z]/', '', (string) $type );

		// Parse HTML with DOMDocument.
		$libxml_prev = libxml_use_internal_errors( true );
		$dom         = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="___fwrap">' . $content . '</div>' );
		$xpath = new \DOMXPath( $dom );
		$wrap  = $dom->getElementById( '___fwrap' );

		if ( ! $wrap ) {
			return $content;
		}

		// Find target element: prefer existing <article>, else first element child.
		$target  = null;
		$article = $xpath->query( './/article', $wrap );

		if ( $article instanceof \DOMNodeList && $article->length > 0 ) {
			$node = $article->item( 0 );
			if ( $node instanceof \DOMElement ) {
				$target = $node;
			}
		}

		// Add article schema attributes only if <article> tag exists.
		if ( $target instanceof \DOMElement ) {
			$target->setAttribute( 'itemscope', '' );
			$target->setAttribute( 'itemtype', 'https://schema.org/' . $type );
		}

		// Add headline itemprop.
		if ( ! empty( $opts['add_headline'] ) ) {
			$headings = $xpath->query( './/h1|.//h2' );
			if ( $headings instanceof \DOMNodeList && $headings->length > 0 ) {
				$el = $headings->item( 0 );
				if ( $el instanceof \DOMElement ) {
					$el->setAttribute( 'itemprop', 'headline' );
				}
			}
		}

		// Add date itemprops.
		if ( ! empty( $opts['add_dates'] ) ) {
			$times = $xpath->query( './/time' );
			if ( $times instanceof \DOMNodeList ) {
				foreach ( $times as $time ) {
					if ( $time instanceof \DOMElement ) {
						$cls = strtolower( (string) $time->getAttribute( 'class' ) );
						if ( strpos( $cls, 'published' ) !== false ) {
							$time->setAttribute( 'itemprop', 'datePublished' );
						} elseif ( strpos( $cls, 'updated' ) !== false || strpos( $cls, 'modified' ) !== false ) {
							$time->setAttribute( 'itemprop', 'dateModified' );
						}
					}
				}
			}
		}

		// Add author itemprop.
		if ( ! empty( $opts['add_author'] ) ) {
			$author = $xpath->query( './/*[contains(@class, "author")]' );
			if ( $author instanceof \DOMNodeList && $author->length > 0 ) {
				$el = $author->item( 0 );
				if ( $el instanceof \DOMElement ) {
					$el->setAttribute( 'itemprop', 'author' );
				}
			}
		}

		// Extract processed content.
		$out = '';
		foreach ( $wrap->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_prev );

		$result = $out !== '' ? $out : $content;

		/**
		 * Filters the content after article schema has been applied.
		 *
		 * @since 0.8.0
		 *
		 * @param string $result   The processed content.
		 * @param string $original The original content.
		 */
		return \apply_filters( 'functionalities_schema_article_content', $result, $original );
	}

	/**
	 * Output BreadcrumbList JSON-LD.
	 *
	 * @since 0.13.0
	 * @return void
	 */
	public static function output_breadcrumbs() : void {
		if ( ! \is_singular() || \is_front_page() ) {
			return;
		}

		$opts = self::get_options();
		if ( empty( $opts['enable_breadcrumbs'] ) ) {
			return;
		}

		$items = array();

		// Home.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => \__( 'Home', 'functionalities' ),
			'item'     => \home_url( '/' ),
		);

		// Post type specific hierarchy.
		if ( \is_singular( 'post' ) ) {
			$categories = \get_the_category();
			if ( ! empty( $categories ) ) {
				$cat     = $categories[0];
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $cat->name,
					'item'     => \get_category_link( $cat->term_id ),
				);
			}
		} elseif ( \is_page() ) {
			$ancestors = \get_post_ancestors( \get_the_ID() );
			if ( ! empty( $ancestors ) ) {
				$ancestors = array_reverse( $ancestors );
				$pos       = 2;
				foreach ( $ancestors as $ancestor_id ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos ++,
						'name'     => \get_the_title( $ancestor_id ),
						'item'     => \get_permalink( $ancestor_id ),
					);
				}
			}
		}

		// Current page.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => \get_the_title(),
			'item'     => \get_permalink(),
		);

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);

		echo "\n<!-- Functionalities Breadcrumb Schema -->\n";
		echo '<script type="application/ld+json">' . \wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		echo "\n";
	}
}
