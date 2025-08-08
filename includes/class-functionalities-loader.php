<?php
/**
 * Core loader for the Functionalities plugin.
 *
 * @package Functionalities
 */

namespace Functionalities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Loader {
	/**
	 * Boot the plugin features.
	 */
	public static function init() : void {
		\add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Register shortcodes.
	 */
	public static function register_shortcodes() : void {
		\add_shortcode( 'functionalities_hello', [ __CLASS__, 'shortcode_hello' ] );
	}

	/**
	 * Render [functionalities_hello name="World"].
	 *
	 * @param array  $atts
	 * @param string $content
	 * @return string
	 */
	public static function shortcode_hello( $atts = [], string $content = '' ) : string {
		$atts = \shortcode_atts( [ 'name' => 'World' ], $atts, 'functionalities_hello' );
		return sprintf(
			'<span class="functionalities-hello">Hello, %s!</span>',
			\esc_html( (string) $atts['name'] )
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_assets() : void {
		\wp_register_style(
			'functionalities-style',
			FUNCTIONALITIES_URL . 'assets/css/style.css',
			[],
			FUNCTIONALITIES_VERSION
		);

		\wp_register_script(
			'functionalities-script',
			FUNCTIONALITIES_URL . 'assets/js/main.js',
			[],
			FUNCTIONALITIES_VERSION,
			true
		);

		\wp_enqueue_style( 'functionalities-style' );
		\wp_enqueue_script( 'functionalities-script' );
	}
}
