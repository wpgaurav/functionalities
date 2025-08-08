<?php
/**
 * Header & Footer snippets (GA4 + custom code).
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippets {
	public static function init() : void {
		\add_action( 'wp_head', [ __CLASS__, 'output_head' ], 20 );
		\add_action( 'wp_footer', [ __CLASS__, 'output_footer' ], 20 );
	}

	protected static function get_options() : array {
		$defaults = [
			'enable_header' => false,
			'header_code'   => '',
			'enable_footer' => false,
			'footer_code'   => '',
			'enable_ga4'    => false,
			'ga4_id'        => '',
		];
		$opts = (array) \get_option( 'functionalities_snippets', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function output_head() : void {
		// Frontend only; skip feeds and REST.
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return;
		}
		$opts = self::get_options();

		// GA4
		if ( ! empty( $opts['enable_ga4'] ) && ! empty( $opts['ga4_id'] ) ) {
			$ga = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( (string) $opts['ga4_id'] ) );
			if ( $ga !== '' ) {
				echo "\n<!-- Functionalities: GA4 -->\n";
				echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga ) . '"></script>' . "\n";
				echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());gtag(\'config\',\'' . esc_js( $ga ) . '\');</script>' . "\n";
			}
		}

		// Custom header code
		if ( ! empty( $opts['enable_header'] ) && ! empty( $opts['header_code'] ) ) {
			echo "\n<!-- Functionalities: custom header code -->\n";
			echo (string) $opts['header_code'];
			echo "\n";
		}
	}

	public static function output_footer() : void {
		$is_rest = \defined( 'REST_REQUEST' ) && \constant( 'REST_REQUEST' );
		if ( \is_admin() || \is_feed() || $is_rest ) {
			return;
		}
		$opts = self::get_options();

		// Custom footer code
		if ( ! empty( $opts['enable_footer'] ) && ! empty( $opts['footer_code'] ) ) {
			echo "\n<!-- Functionalities: custom footer code -->\n";
			echo (string) $opts['footer_code'];
			echo "\n";
		}
	}
}
