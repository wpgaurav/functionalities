<?php
/**
 * Icons feature: remove Font Awesome assets and convert FA tags to SVG using a sprite.
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Icons {
	public static function init() : void {
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'strip_fa_assets' ], 100 );
		\add_action( 'admin_enqueue_scripts', [ __CLASS__, 'strip_fa_assets' ], 100 );
		\add_filter( 'style_loader_src', [ __CLASS__, 'block_fa_src' ], 10, 2 );
		\add_filter( 'script_loader_src', [ __CLASS__, 'block_fa_src' ], 10, 2 );
		\add_filter( 'the_content', [ __CLASS__, 'convert_fa_to_svg' ], 13 );
	}

	protected static function get_options() : array {
		$defaults = [
			'remove_fontawesome_assets' => false,
			'convert_fa_to_svg' => false,
			'svg_sprite_url' => '',
		];
		$opts = (array) \get_option( 'functionalities_icons', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function strip_fa_assets() : void {
		$o = self::get_options();
		if ( empty( $o['remove_fontawesome_assets'] ) ) { return; }
		// Best-effort deregister common FA handles
		$handles = [ 'font-awesome', 'fontawesome', 'fa', 'fas', 'far', 'fab', 'fontawesome-all' ];
		foreach ( $handles as $h ) {
			\wp_deregister_style( $h );
			\wp_dequeue_style( $h );
			\wp_deregister_script( $h );
			\wp_dequeue_script( $h );
		}
	}

	public static function block_fa_src( $src, $handle ) {
		$o = self::get_options();
		if ( empty( $o['remove_fontawesome_assets'] ) ) { return $src; }
		if ( is_string( $src ) && ( stripos( $src, 'fontawesome' ) !== false || stripos( $src, 'font-awesome' ) !== false ) ) {
			return false;
		}
		return $src;
	}

	public static function convert_fa_to_svg( string $content ) : string {
		$o = self::get_options();
		if ( empty( $o['convert_fa_to_svg'] ) ) { return $content; }
		$sprite = trim( (string) ( $o['svg_sprite_url'] ?? '' ) );
		if ( $sprite === '' ) { return $content; }

		$prev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="__fx">' . $content . '</div>' );
		$xp = new \DOMXPath( $dom );
		$nodes = $xp->query( './/*[contains(concat(" ", normalize-space(@class), " "), " fa ") or contains(@class, "fa-") or contains(@class, "fas") or contains(@class, "far") or contains(@class, "fab")]' );
		if ( $nodes instanceof \DOMNodeList ) {
			foreach ( $nodes as $el ) {
				if ( ! ($el instanceof \DOMElement) ) { continue; }
				$cls = ' ' . strtolower( (string) $el->getAttribute( 'class' ) ) . ' ';
				// Extract first class starting with fa-XXX (not fa, fas, far, fab, fal, fad, fat, fa-fw)
				$icon = '';
				foreach ( preg_split( '/\s+/', trim( $cls ) ) as $c ) {
					if ( $c === '' ) { continue; }
					if ( in_array( $c, [ 'fa', 'fas', 'far', 'fab', 'fal', 'fad', 'fat', 'fa-fw' ], true ) ) { continue; }
					if ( strpos( $c, 'fa-' ) === 0 ) { $icon = $c; break; }
				}
				if ( $icon === '' ) { continue; }
				$svg = $dom->createElement( 'svg' );
				$svg->setAttribute( 'class', 'icon ' . $icon );
				$svg->setAttribute( 'aria-hidden', 'true' );
				$use = $dom->createElement( 'use' );
				$use->setAttribute( 'href', $sprite . '#' . $icon );
				$svg->appendChild( $use );
				$el->parentNode->replaceChild( $svg, $el );
			}
		}
		$out = '';
		$wrap = $dom->getElementById( '__fx' );
		if ( $wrap ) {
			foreach ( $wrap->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $out !== '' ? $out : $content;
	}
}
