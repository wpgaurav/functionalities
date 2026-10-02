<?php
/**
 * Lightweight test bootstrap.
 *
 * @package FunctionalitiesTests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
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
		return $GLOBALS['functionalities_test_filter_values'][ $hook_name ] ?? $value;
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
		$GLOBALS['functionalities_http_calls'] = (int) ( $GLOBALS['functionalities_http_calls'] ?? 0 ) + 1;
		if ( isset( $GLOBALS['functionalities_http_handler'] ) && is_callable( $GLOBALS['functionalities_http_handler'] ) ) {
			return call_user_func( $GLOBALS['functionalities_http_handler'], $url, $args );
		}

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

		public function get_error_code() {
			return $this->code;
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
		if ( null !== $autoload ) {
			$GLOBALS['functionalities_test_autoload'][ $name ] = $autoload;
		}
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['functionalities_test_options'][ $name ] );
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value, $deprecated = '', $autoload = true ) {
		if ( array_key_exists( $name, $GLOBALS['functionalities_test_options'] ) ) {
			return false;
		}
		return update_option( $name, $value, $autoload );
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return (int) ( $GLOBALS['functionalities_test_blog_id'] ?? 1 );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $path ) {
		return is_dir( $path ) || mkdir( $path, 0755, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $path ) {
		return ! file_exists( $path ) || unlink( $path );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $value ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = '' ) {
		return 'test-nonce' === $nonce;
	}
}

class Functionalities_Test_Response extends RuntimeException {
	public $data;
	public $success;
	public function __construct( $data, $success ) {
		parent::__construct( 'JSON response' );
		$this->data = $data;
		$this->success = $success;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null ) {
		throw new Functionalities_Test_Response( $data, true );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null ) {
		throw new Functionalities_Test_Response( $data, false );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	function wp_remote_retrieve_headers( $response ) {
		return is_array( $response ) ? ( $response['headers'] ?? array() ) : array();
	}
}

if ( ! function_exists( 'wp_safe_remote_get' ) ) {
	function wp_safe_remote_get( $url, $args = array() ) {
		$args['method'] = 'GET';
		return wp_remote_get( $url, $args );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) {
		return wp_kses( strip_tags( $content, '<p><a><div><span><strong><em><ul><ol><li>' ), array() );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$value = $GLOBALS['functionalities_test_post_meta'][ $post_id ][ $key ] ?? '';
		if ( ! $single ) {
			return '' === $value ? array() : array( $value );
		}
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['functionalities_test_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['functionalities_test_post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'excerpt_remove_blocks' ) ) {
	function excerpt_remove_blocks( $content ) {
		return preg_replace( '/<!--.*?-->/s', '', $content );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $content, $remove_breaks = false ) {
		return strip_tags( $content );
	}
}

if ( ! function_exists( 'has_blocks' ) ) {
	function has_blocks( $content ) {
		return false !== strpos( (string) $content, '<!-- wp:' );
	}
}

if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( $content ) {
		throw new LogicException( 'Use a real WordPress block parser for block fixtures.' );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['functionalities_test_action_calls'][ $hook ] = (int) ( $GLOBALS['functionalities_test_action_calls'][ $hook ] ?? 0 ) + 1;
		if ( isset( $GLOBALS['functionalities_action_handler'] ) && is_callable( $GLOBALS['functionalities_action_handler'] ) ) {
			call_user_func( $GLOBALS['functionalities_action_handler'], $hook, ...$args );
		}
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) ( $GLOBALS['functionalities_test_user_id'] ?? 1 );
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action, $query_arg = false, $stop = true ) {
		return $GLOBALS['functionalities_test_nonce_valid'] ?? true;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( $response, $name ) {
		foreach ( wp_remote_retrieve_headers( $response ) as $key => $value ) {
			if ( strtolower( $key ) === strtolower( $name ) ) {
				return $value;
			}
		}
		return '';
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
		public $ID = 0;
		public $post_content = '';
		public $post_title = '';
		public $post_excerpt = '';
		public $post_password = '';
		public $post_name = '';
		public $post_parent = 0;
		public $post_author = 1;
		public $menu_order = 0;
		public $comment_status = 'closed';
		public $ping_status = 'closed';

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
	if ( ! class_exists( 'WpOrg\\Requests\\Requests' ) ) {
		require_once $functionalities_wp_dir . '/wp-includes/Requests/src/Autoload.php';
		WpOrg\Requests\Autoload::register();
	}
	require_once $functionalities_wp_dir . '/wp-includes/class-wp-http.php';
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

// Native APIs exercised by the utility modules; the real-core integration test
// below uses actual WordPress persistence and capability checks independently.
if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $type ) {
		return in_array( $type, array( 'post', 'page' ), true ) ? (object) array( 'cap' => (object) array( 'create_posts' => 'page' === $type ? 'edit_pages' : 'edit_posts' ) ) : null;
	}
}
if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $type, $output = 'names' ) {
		return $GLOBALS['functionalities_test_taxonomies'] ?? array();
	}
}
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $id, $taxonomy, $args = array() ) {
		return $GLOBALS['functionalities_test_terms'][ $id ][ $taxonomy ] ?? array();
	}
}
if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $id, $terms, $taxonomy ) {
		if ( ! empty( $GLOBALS['functionalities_test_terms_fail'] ) ) {
			return new WP_Error( 'terms_failed', 'Terms failed.' );
		}
		$GLOBALS['functionalities_test_terms'][ $id ][ $taxonomy ] = $terms;
		return $terms;
	}
}
if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
	}
}
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $data, $error = false ) {
		$GLOBALS['functionalities_test_inserted_data'] = $data;
		if ( ! empty( $GLOBALS['functionalities_test_insert_fail'] ) ) {
			return new WP_Error( 'insert_failed', 'Insert failed.' );
		}
		$id = (int) ( $GLOBALS['functionalities_test_next_id'] ?? 100 );
		$post = new WP_Post();
		foreach ( $data as $key => $value ) {
			$post->$key = is_string( $value ) ? stripslashes( $value ) : $value;
		}
		$post->ID = $id;
		$GLOBALS['functionalities_test_posts'][ $id ] = $post;
		return $id;
	}
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		$GLOBALS['functionalities_test_deleted_posts'][] = $id;
		unset( $GLOBALS['functionalities_test_posts'][ $id ], $GLOBALS['functionalities_test_post_meta'][ $id ] );
		return true;
	}
}
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( $id, $key, $value, $unique = false ) {
		if ( $unique && array_key_exists( $key, $GLOBALS['functionalities_test_post_meta'][ $id ] ?? array() ) ) {
			return false;
		}
		if ( ! empty( $GLOBALS['functionalities_test_meta_fail'] ) ) {
			return false;
		}
		$GLOBALS['functionalities_test_post_meta'][ $id ][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value;
		return 1;
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id ) {
		return 'https://example.test/post-' . $id . '/';
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $id ) {
		return $GLOBALS['functionalities_test_posts'][ $id ]->post_title ?? '';
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return bin2hex( random_bytes( 16 ) );
	}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['functionalities_test_schedule'][ $hook ]['time'] ?? false;
	}
}
if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $time, $hook ) {
		$GLOBALS['functionalities_test_schedule'][ $hook ] = array( 'time' => $time );
		return true;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $time, $recurrence, $hook ) {
		$GLOBALS['functionalities_test_schedule'][ $hook ] = array( 'time' => $time, 'recurrence' => $recurrence );
		return true;
	}
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook ) {
		unset( $GLOBALS['functionalities_test_schedule'][ $hook ] );
		return true;
	}
}
if ( ! function_exists( 'wp_safe_remote_head' ) ) {
	function wp_safe_remote_head( $url, $args = array() ) {
		$args['method'] = 'HEAD';
		return wp_remote_get( $url, $args );
	}
}
if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && ! wp_parse_url( $url, PHP_URL_USER ) ? $url : false;
	}
}
