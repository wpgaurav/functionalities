<?php
/**
 * Lightweight test bootstrap.
 *
 * @package FunctionalitiesTests
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'FUNCTIONALITIES_VERSION', '1.4.8-test' );

$GLOBALS['functionalities_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Return a test option value.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['functionalities_test_options'] )
			? $GLOBALS['functionalities_test_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Return an unmodified test filter value.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Filter value.
	 * @return mixed
	 */
	function apply_filters( $hook_name, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		return $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time() {
		return '2026-07-22 12:00:00';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
