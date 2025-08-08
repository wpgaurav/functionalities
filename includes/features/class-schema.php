<?php
/**
 * Schema microdata helpers (itemscope/itemtype for html, header, footer, and article).
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {
	public static function init() : void {
		\add_filter( 'language_attributes', [ __CLASS__, 'filter_language_attributes' ], 20 );
		\add_action( 'template_redirect', [ __CLASS__, 'start_buffer' ] );
		\add_filter( 'the_content', [ __CLASS__, 'filter_article' ], 14 );
	}

	protected static function get_options() : array {
		$defaults = [
			'enable_site_schema'  => true,
			'site_itemtype'       => 'WebPage',
			'enable_header_part'  => true,
			'enable_footer_part'  => true,
			'enable_article'      => true,
			'article_itemtype'    => 'Article',
			'add_headline'        => true,
			'add_dates'           => true,
			'add_author'          => true,
		];
		$opts = (array) \get_option( 'functionalities_schema', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function filter_language_attributes( string $output ) : string {
		$o = self::get_options();
		if ( empty( $o['enable_site_schema'] ) ) { return $output; }
		$type = preg_replace( '/[^A-Za-z]/', '', (string) ( $o['site_itemtype'] ?? 'WebPage' ) );
		$itemtype = 'https://schema.org/' . $type;
		if ( strpos( $output, 'itemscope' ) === false ) {
			$output .= ' itemscope';
		}
		if ( strpos( $output, 'itemtype=' ) === false ) {
			$output .= ' itemtype="' . \esc_attr( $itemtype ) . '"';
		}
		return $output;
	}

	public static function start_buffer() : void {
		if ( \is_admin() || \is_feed() ) { return; }
		$o = self::get_options();
		if ( empty( $o['enable_header_part'] ) && empty( $o['enable_footer_part'] ) ) {
			return;
		}
		ob_start( [ __CLASS__, 'buffer_callback' ] );
	}

	public static function buffer_callback( string $html ) : string {
		$o = self::get_options();
		$enableHeader = ! empty( $o['enable_header_part'] );
		$enableFooter = ! empty( $o['enable_footer_part'] );
		if ( ! $enableHeader && ! $enableFooter ) { return $html; }

		$libprev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		$xpath = new \DOMXPath( $dom );

		if ( $enableHeader ) {
			$nodes = $xpath->query( '//header' );
			if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
				$el = $nodes->item(0);
				if ( $el instanceof \DOMElement && ! $el->hasAttribute( 'itemscope' ) ) {
					$el->setAttribute( 'itemscope', '' );
					$el->setAttribute( 'itemtype', 'https://schema.org/WPHeader' );
				}
			}
		}
		if ( $enableFooter ) {
			$nodes = $xpath->query( '//footer' );
			if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
				$el = $nodes->item(0);
				if ( $el instanceof \DOMElement && ! $el->hasAttribute( 'itemscope' ) ) {
					$el->setAttribute( 'itemscope', '' );
					$el->setAttribute( 'itemtype', 'https://schema.org/WPFooter' );
				}
			}
		}

		$out = $dom->saveHTML();
		libxml_clear_errors();
		libxml_use_internal_errors( $libprev );
		return is_string( $out ) && $out !== '' ? $out : $html;
	}

	public static function filter_article( string $content ) : string {
		if ( trim( $content ) === '' || ! \is_singular() ) { return $content; }
		$o = self::get_options();
		if ( empty( $o['enable_article'] ) ) { return $content; }

		$type = preg_replace( '/[^A-Za-z]/', '', (string) ( $o['article_itemtype'] ?? 'Article' ) );
		$libprev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="___fwrap">' . $content . '</div>' );
		$xp = new \DOMXPath( $dom );
		$wrap = $dom->getElementById( '___fwrap' );
		if ( ! $wrap ) { return $content; }

		// Prefer an existing <article>, else first element child under the wrapper
		$target = null;
		$article = $xp->query( './/article', $wrap );
		if ( $article instanceof \DOMNodeList && $article->length > 0 ) {
			$node = $article->item(0);
			if ( $node instanceof \DOMElement ) { $target = $node; }
		}
		if ( ! $target ) {
			foreach ( $wrap->childNodes as $child ) {
				if ( $child instanceof \DOMElement ) { $target = $child; break; }
			}
		}
		if ( $target instanceof \DOMElement ) {
			$target->setAttribute( 'itemscope', '' );
			$target->setAttribute( 'itemtype', 'https://schema.org/' . $type );
		}

		if ( ! empty( $o['add_headline'] ) ) {
			$h = $xp->query( './/h1|.//h2' );
			if ( $h instanceof \DOMNodeList && $h->length > 0 ) {
				$el = $h->item(0);
				if ( $el instanceof \DOMElement ) {
					$el->setAttribute( 'itemprop', 'headline' );
				}
			}
		}
		if ( ! empty( $o['add_dates'] ) ) {
			$times = $xp->query( './/time' );
			if ( $times instanceof \DOMNodeList ) {
				foreach ( $times as $t ) {
					if ( $t instanceof \DOMElement ) {
						$cls = strtolower( (string) $t->getAttribute( 'class' ) );
						if ( strpos( $cls, 'published' ) !== false ) {
							$t->setAttribute( 'itemprop', 'datePublished' );
						} elseif ( strpos( $cls, 'updated' ) !== false || strpos( $cls, 'modified' ) !== false ) {
							$t->setAttribute( 'itemprop', 'dateModified' );
						}
					}
				}
			}
		}
		if ( ! empty( $o['add_author'] ) ) {
			$author = $xp->query( './/*[contains(@class, "author")]' );
			if ( $author instanceof \DOMNodeList && $author->length > 0 ) {
				$el = $author->item(0);
				if ( $el instanceof \DOMElement ) {
					$el->setAttribute( 'itemprop', 'author' );
				}
			}
		}

		$out = '';
		foreach ( $wrap->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $libprev );
		return $out !== '' ? $out : $content;
	}
}
