<?php
/**
 * Link Management features (frontend behaviors).
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Link_Management {
	public static function init() : void {
		\add_filter( 'the_content', [ __CLASS__, 'filter_content' ], 11 );
	}

	protected static function get_options() : array {
		$defaults = [
			'nofollow_external' => false,
			'exceptions' => '',
			'open_external_new_tab' => false,
			'open_internal_new_tab' => false,
			'internal_new_tab_exceptions' => '',
		];
		$opts = (array) \get_option( 'functionalities_link_management', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function filter_content( string $content ) : string {
		// Skip in admin, feeds, and REST requests.
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return $content;
		}

		if ( trim( $content ) === '' ) {
			return $content;
		}

	$opts = self::get_options();
	$exceptions = self::parse_exceptions( (string) $opts['exceptions'] );
	$internal_ex = self::parse_exceptions( (string) $opts['internal_new_tab_exceptions'] );
		$site_host = (string) parse_url( \home_url(), PHP_URL_HOST );

		$libxml_previous = libxml_use_internal_errors( true );
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$html = '<div id="__functionalities_wrapper">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//a[@href]' );
		if ( $nodes instanceof \DOMNodeList ) {
			foreach ( $nodes as $a ) {
				$href = (string) $a->getAttribute( 'href' );
				$is_external = self::is_external_url( $href, $site_host );

				// Nofollow external
				if ( $is_external && ! self::is_exception( $href, $exceptions ) && ! empty( $opts['nofollow_external'] ) ) {
					$rel = (string) $a->getAttribute( 'rel' );
					$parts = preg_split( '/\s+/', strtolower( $rel ) );
					$parts = array_filter( array_unique( array_map( 'trim', (array) $parts ) ) );
					if ( ! in_array( 'nofollow', $parts, true ) ) { $parts[] = 'nofollow'; }
					$a->setAttribute( 'rel', implode( ' ', $parts ) );
				}

				// New tab external
				if ( $is_external && ! empty( $opts['open_external_new_tab'] ) ) {
					$a->setAttribute( 'target', '_blank' );
					// add noopener
					$rel = strtolower( (string) $a->getAttribute( 'rel' ) );
					$parts = array_filter( array_unique( preg_split( '/\s+/', $rel ) ) );
					if ( ! in_array( 'noopener', $parts, true ) ) { $parts[] = 'noopener'; }
					$a->setAttribute( 'rel', implode( ' ', $parts ) );
				}

				// New tab internal (same-domain) except certain domains
				if ( ! $is_external && ! empty( $opts['open_internal_new_tab'] ) ) {
					// If link host matches an exception domain, skip
					$test = $href;
					if ( strpos( $href, '//' ) === 0 ) { $test = 'http:' . $href; }
					$host = (string) parse_url( $test, PHP_URL_HOST );
					$host = strtolower( $host );
					$skip = false;
					foreach ( $internal_ex as $exd ) {
						if ( $exd === '' ) { continue; }
						if ( $host === $exd || ( $host !== '' && substr( $host, - ( strlen( $exd ) + 1 ) ) === '.' . $exd ) ) {
							$skip = true; break;
						}
					}
					if ( ! $skip ) {
						$a->setAttribute( 'target', '_blank' );
					}
				}
			}
		}

		$out = '';
		$wrapper = $dom->getElementById( '__functionalities_wrapper' );
		if ( $wrapper ) {
			foreach ( $wrapper->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );
		return $out !== '' ? $out : $content;
	}

	protected static function parse_exceptions( string $raw ) : array {
		$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
		$items = [];
		foreach ( $lines as $line ) {
			$line = strtolower( trim( $line ) );
			if ( $line === '' ) { continue; }
			$items[] = $line;
		}
		return $items;
	}

	protected static function is_external_url( string $href, string $site_host ) : bool {
		$href = trim( $href );
		if ( $href === '' ) { return false; }
		if ( $href[0] === '#' ) { return false; }
		$lower = strtolower( $href );
		if ( strpos( $lower, 'mailto:' ) === 0 || strpos( $lower, 'tel:' ) === 0 || strpos( $lower, 'javascript:' ) === 0 ) {
			return false;
		}
		// Relative URL
		if ( strpos( $href, 'http://' ) !== 0 && strpos( $href, 'https://' ) !== 0 && strpos( $href, '//' ) !== 0 ) {
			return false; // treat as internal
		}
		$test = $href;
		if ( strpos( $href, '//' ) === 0 ) {
			$test = 'http:' . $href;
		}
		$host = (string) parse_url( $test, PHP_URL_HOST );
		if ( $host === '' ) { return false; }
		return strcasecmp( $host, $site_host ) !== 0;
	}

	protected static function is_exception( string $href, array $exceptions ) : bool {
		$h = strtolower( $href );
		$host = '';
		$test = $href;
		if ( strpos( $href, '//' ) === 0 ) { $test = 'http:' . $href; }
		$tmpHost = parse_url( $test, PHP_URL_HOST );
		if ( is_string( $tmpHost ) ) { $host = strtolower( $tmpHost ); }

		foreach ( $exceptions as $ex ) {
			$ex = trim( $ex );
			if ( $ex === '' ) { continue; }

			// Full URL match (scheme optional if exception starts with //)
			if ( strpos( $ex, '://' ) !== 0 && strpos( $ex, '//' ) === 0 ) {
				$needle = $ex;
				$hay = preg_replace( '#^https?:#', '', $h );
				if ( strpos( $hay, $needle ) === 0 ) { return true; }
			}
			elseif ( strpos( $ex, '://' ) !== false ) {
				if ( stripos( $h, $ex ) === 0 ) { return true; }
			}
			// Domain match
			elseif ( strpos( $ex, '/' ) === false && strpos( $ex, '.' ) !== false ) {
				if ( $host === $ex || ( $host !== '' && substr( $host, - ( strlen( $ex ) + 1 ) ) === '.' . $ex ) ) {
					return true;
				}
			}
			// Partial value match
			if ( stripos( $h, $ex ) !== false ) { return true; }
		}
		return false;
	}
}
