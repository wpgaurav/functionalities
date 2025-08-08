<?php
/**
 * Block Cleanup features (frontend behaviors).
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Cleanup {
	public static function init() : void {
		$opts = self::get_options();
		if ( ! empty( $opts['remove_heading_block_class'] ) || ! empty( $opts['remove_list_block_class'] ) || ! empty( $opts['remove_image_block_class'] ) ) {
			\add_filter( 'the_content', [ __CLASS__, 'filter_content_cleanup' ], 12 );
		}
	}

	protected static function get_options() : array {
		$defaults = [
			'remove_heading_block_class' => false,
			'remove_list_block_class'    => false,
			'remove_image_block_class'   => false,
		];
		$opts = (array) \get_option( 'functionalities_block_cleanup', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function filter_content_cleanup( string $content ) : string {
		if ( \is_admin() || \is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}
		if ( trim( $content ) === '' ) { return $content; }

		$opts = self::get_options();
		$removeHead = ! empty( $opts['remove_heading_block_class'] );
		$removeList = ! empty( $opts['remove_list_block_class'] );
		$removeImg  = ! empty( $opts['remove_image_block_class'] );
		if ( ! $removeHead && ! $removeList && ! $removeImg ) { return $content; }

		$libxml_prev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$html = '<div id="__functionalities_wrapper">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		$xpath = new \DOMXPath( $dom );

		if ( $removeHead ) {
			$nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );
			self::strip_class_from_nodes( $nodes, 'wp-block-heading' );
		}
		if ( $removeList ) {
			$nodes = $xpath->query( '//ul|//ol' );
			self::strip_class_from_nodes( $nodes, 'wp-block-list' );
		}
		if ( $removeImg ) {
			$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-image ")]' );
			self::strip_class_from_nodes( $nodes, 'wp-block-image' );
		}

		$out = '';
		$wrapper = $dom->getElementById( '__functionalities_wrapper' );
		if ( $wrapper ) {
			foreach ( $wrapper->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_prev );
		return $out !== '' ? $out : $content;
	}

	protected static function strip_class_from_nodes( $nodes, string $class ) : void {
		if ( ! ( $nodes instanceof \DOMNodeList ) ) { return; }
		foreach ( $nodes as $el ) {
			if ( ! $el instanceof \DOMElement ) { continue; }
			$cls = $el->getAttribute( 'class' );
			if ( $cls === '' ) { continue; }
			$parts = preg_split( '/\s+/', $cls );
			$parts = array_filter( $parts, function( $c ) use ( $class ) { return strtolower( (string) $c ) !== strtolower( $class ); } );
			$parts = array_values( array_unique( $parts ) );
			if ( empty( $parts ) ) {
				$el->removeAttribute( 'class' );
			} else {
				$el->setAttribute( 'class', implode( ' ', $parts ) );
			}
		}
	}
}
