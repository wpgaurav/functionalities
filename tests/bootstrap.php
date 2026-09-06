<?php
/**
 * Lightweight test bootstrap.
 *
 * @package FunctionalitiesTests
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'FUNCTIONALITIES_VERSION', '1.6.0-test' );
define( 'FUNCTIONALITIES_DIR', dirname( __DIR__ ) . '/' );
define( 'FUNCTIONALITIES_URL', 'https://example.test/wp-content/plugins/functionalities/' );

foreach ( array(
	'MINUTE_IN_SECONDS' => 60,
	'HOUR_IN_SECONDS'   => 3600,
	'DAY_IN_SECONDS'    => 86400,
	'WEEK_IN_SECONDS'   => 604800,
) as $functionalities_const => $functionalities_value ) {
	if ( ! defined( $functionalities_const ) ) {
		define( $functionalities_const, $functionalities_value );
	}
}

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

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $value ) {
		return preg_match( '/^#(?:[0-9a-f]{3}){1,2}$/i', (string) $value ) ? $value : null;
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $value ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
	function get_block_wrapper_attributes( $attributes = array() ) {
		$output = array();
		foreach ( $attributes as $name => $value ) {
			$output[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $output );
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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( $function_name, $message, $version ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// No-op in the lightweight test environment.
	}
}

$GLOBALS['functionalities_test_caps']       = array();
$GLOBALS['functionalities_test_transients'] = array();
$GLOBALS['functionalities_test_posts'] = array();

$GLOBALS['functionalities_http_calls']    = 0;
$GLOBALS['functionalities_http_fail']     = false;
$GLOBALS['functionalities_test_autosave'] = false;

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = PHP_INT_MAX ) {
		return random_int( $min, $max );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$out   = '';
		for ( $i = 0; $i < (int) $length; $i++ ) {
			$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $content, $allowed_html ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Enough for the tests: drop event handler attributes.
		return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', (string) $content );
	}
}

if ( ! function_exists( 'wp_kses_no_null' ) ) {
	function wp_kses_no_null( $value ) {
		return str_replace( "\0", '', (string) $value );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		++$GLOBALS['functionalities_http_calls'];

		if ( ! empty( $GLOBALS['functionalities_http_fail'] ) ) {
			return new WP_Error( 'http_request_failed', 'Simulated failure.' );
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"urls":["https://partner.example"]}',
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? ( $response['response']['code'] ?? 0 ) : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal error object for the lightweight test environment.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Build an error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Return the message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return ! empty( $GLOBALS['functionalities_test_autosave'] );
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return false;
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory() {
		return sys_get_temp_dir() . '/functionalities-theme';
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory() {
		return get_stylesheet_directory();
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['functionalities_test_is_admin'] );
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return false;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['functionalities_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['functionalities_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['functionalities_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['functionalities_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['functionalities_test_options'][ $name ] );
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Report a stubbed capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return ! empty( $GLOBALS['functionalities_test_caps'][ $capability ] );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Return a stubbed post object.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	function get_post( $post_id = 0 ) {
		return $GLOBALS['functionalities_test_posts'][ (int) $post_id ] ?? null;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal stand-in so instanceof checks behave.
	 */
	class WP_Post {
		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Post status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes() {
		return array( 'action', 'cite', 'data', 'formaction', 'href', 'longdesc', 'poster', 'src', 'xmlns' );
	}
}

if ( ! function_exists( 'wp_has_noncharacters' ) ) {
	function wp_has_noncharacters( $value ) {
		return (bool) preg_match( '/[\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}]/u', (string) $value );
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $list, $field ) {
		return array_map(
			static function ( $item ) use ( $field ) {
				return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null );
			},
			(array) $list
		);
	}
}

/*
 * Load WordPress's HTML API when a checkout is available, so the tests that
 * cover the ported content filters run for real. Point FUNCTIONALITIES_WP_DIR at
 * a WordPress root (the directory containing wp-includes). Without it those
 * tests skip.
 */
$functionalities_wp_dir = getenv( 'FUNCTIONALITIES_WP_DIR' );
if ( $functionalities_wp_dir ) {
	$functionalities_wp_dir = rtrim( $functionalities_wp_dir, '/' );
	foreach ( array(
		'/wp-includes/class-wp-token-map.php',
		'/wp-includes/html-api/class-wp-html-span.php',
		'/wp-includes/html-api/class-wp-html-text-replacement.php',
		'/wp-includes/html-api/class-wp-html-attribute-token.php',
		'/wp-includes/html-api/html5-named-character-references.php',
		'/wp-includes/html-api/class-wp-html-decoder.php',
		'/wp-includes/html-api/class-wp-html-tag-processor.php',
	) as $functionalities_html_file ) {
		$functionalities_html_path = $functionalities_wp_dir . $functionalities_html_file;
		if ( file_exists( $functionalities_html_path ) ) {
			require_once $functionalities_html_path;
		}
	}
}
