<?php
/**
 * Components CSS generator from admin-defined items.
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Components {
	public static function init() : void {
		// Print stylesheet link in the footer on frontend and admin; compile on demand.
		\add_action( 'wp_footer', [ __CLASS__, 'print_footer_link' ], 90 );
		\add_action( 'admin_footer', [ __CLASS__, 'print_footer_link' ], 90 );
		// Regenerate when settings are updated.
		\add_action( 'update_option_functionalities_components', [ __CLASS__, 'on_option_update' ], 10, 2 );
	}

	protected static function get_options() : array {
		$defaults = [ 'enabled' => true, 'items' => [] ];
		$opts = (array) \get_option( 'functionalities_components', $defaults );
		$out = array_merge( $defaults, $opts );
		if ( empty( $out['items'] ) || ! is_array( $out['items'] ) ) {
			if ( class_exists( '\\Functionalities\\Admin\\Admin' ) ) {
				$out['items'] = \Functionalities\Admin\Admin::default_components();
			} else {
				$out['items'] = [];
			}
		}
		return $out;
	}

	// Print link tag in footer (frontend and admin). Falls back to inline <style> if file cannot be written.
	public static function print_footer_link() : void {
		$o = self::get_options();
		if ( empty( $o['enabled'] ) || empty( $o['items'] ) || ! is_array( $o['items'] ) ) { return; }
		$css = self::build_css( $o['items'] );

		$file = self::ensure_css_file( $css );
		if ( $file && isset( $file['url'], $file['ver'] ) ) {
			echo '<link rel="stylesheet" href="' . \esc_url( $file['url'] . '?ver=' . rawurlencode( $file['ver'] ) ) . '" media="all" />';
			return;
		}
		// Fallback inline if file writing failed.
		echo '<style id="functionalities-components-inline">' . $css . '</style>';
	}

	protected static function build_css( array $items ) : string {
		$parts = [];
		foreach ( $items as $item ) {
			$selector = trim( (string) ( $item['class'] ?? '' ) );
			$rules = (string) ( $item['css'] ?? '' );
			if ( $selector === '' || $rules === '' ) { continue; }
			$parts[] = $selector . '{' . $rules . '}';
		}
		$out = implode("\n", $parts);
		// add marquee keyframes if user uses .c-marquee
		if ( strpos( $out, '.c-marquee' ) !== false ) {
			$out .= "\n@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}";
		}
		return rtrim( $out );
	}

	protected static function ensure_css_file( string $css ) : ?array {
		$upload = \wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) { return null; }
		$dir = rtrim( (string) $upload['basedir'], '/\\' ) . '/functionalities';
		if ( ! is_dir( $dir ) && ! \wp_mkdir_p( $dir ) ) { return null; }
		$file = $dir . '/components.css';
		$hash = md5( $css );
		$existing_hash = is_file( $file ) ? md5_file( $file ) : '';
		if ( $hash !== $existing_hash ) {
			// Attempt to write file
			$bytes = @file_put_contents( $file, $css );
			if ( false === $bytes ) { return null; }
		}
		$url = rtrim( (string) $upload['baseurl'], '/\\' ) . '/functionalities/components.css';
		return [ 'path' => $file, 'url' => $url, 'ver' => $hash ];
	}

	public static function on_option_update( $old_value, $value ) : void {
		$o = is_array( $value ) ? $value : [];
		if ( empty( $o['enabled'] ) || empty( $o['items'] ) || ! is_array( $o['items'] ) ) {
			return;
		}
		$css = self::build_css( $o['items'] );
		self::ensure_css_file( $css );
	}
}
