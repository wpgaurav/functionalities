<?php
/**
 * Fonts feature: output @font-face rules (supports variable fonts) in head (frontend + admin).
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Fonts {
	public static function init() : void {
		\add_action( 'wp_head', [ __CLASS__, 'print_fonts_css' ], 20 );
		\add_action( 'admin_head', [ __CLASS__, 'print_fonts_css' ], 20 );
	}

	protected static function get_options() : array {
		$defaults = [
			'enabled' => false,
			'items' => [], // each: family, style, display, weight, weight_range, is_variable, woff2_url, woff_url
		];
		$opts = (array) \get_option( 'functionalities_fonts', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function print_fonts_css() : void {
		$o = self::get_options();
		if ( empty( $o['enabled'] ) || empty( $o['items'] ) || ! is_array( $o['items'] ) ) { return; }
		$css = self::build_css( $o['items'] );
		if ( $css === '' ) { return; }
		echo '<style id="functionalities-fonts">' . $css . '</style>';
	}

	protected static function build_css( array $items ) : string {
		$parts = [];
		foreach ( $items as $it ) {
			$family = trim( (string) ( $it['family'] ?? '' ) );
			$style  = trim( (string) ( $it['style'] ?? 'normal' ) );
			$display = trim( (string) ( $it['display'] ?? 'swap' ) );
			$weight = trim( (string) ( $it['weight'] ?? '' ) );
			$weight_range = trim( (string) ( $it['weight_range'] ?? '' ) );
			$is_variable = ! empty( $it['is_variable'] );
			$woff2 = trim( (string) ( $it['woff2_url'] ?? '' ) );
			$woff  = trim( (string) ( $it['woff_url'] ?? '' ) );
			if ( $family === '' || $woff2 === '' ) { continue; }

			$src = 'url(' . $woff2 . ') format("woff2")';
			if ( $woff !== '' ) {
				$src .= ', url(' . $woff . ') format("woff")';
			}
			$wprop = '';
			if ( $is_variable || ( $weight_range !== '' ) ) {
				$range = $weight_range !== '' ? $weight_range : '100 900';
				$wprop = 'font-weight:' . $range . ';';
			} elseif ( $weight !== '' ) {
				$wprop = 'font-weight:' . $weight . ';';
			}
			$parts[] = '@font-face{font-family:"' . $family . '";font-style:' . $style . ';font-display:' . ($display ?: 'swap') . ';' . $wprop . 'src:' . $src . ';}';
		}
		return implode("\n", $parts);
	}
}
