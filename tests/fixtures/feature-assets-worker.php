<?php
/**
 * Isolated hook/style integration harness without WordPress database access.
 *
 * @package FunctionalitiesTests
 */

$wp_root = rtrim( $argv[1], '/' ) . '/';
$module  = $argv[2];
$mode    = $argv[3];
define( 'ABSPATH', $wp_root );
define( 'WPINC', 'wp-includes' );
define( 'FUNCTIONALITIES_VERSION', 'audit-test' );

function is_admin() { return 'admin' === $GLOBALS['mode']; }
function wp_installing() { return false; }
function current_theme_supports() { return true; }
function get_site_option() { return false; }
function __( $text ) { return $text; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return esc_attr( $text ); }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
function wp_upload_dir() { return array( 'error' => 'Exercise the inline fallback without filesystem writes.' ); }
function wp_kses_uri_attributes() { return array( 'action', 'cite', 'data', 'formaction', 'href', 'longdesc', 'poster', 'src', 'xmlns' ); }
function wp_has_noncharacters( $value ) { return (bool) preg_match( '/[\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}]/u', (string) $value ); }

require $wp_root . 'wp-includes/plugin.php';
// Current WordPress uses the HTML API to print style tags; older releases do not.
foreach ( array(
	'wp-includes/class-wp-token-map.php',
	'wp-includes/html-api/class-wp-html-span.php',
	'wp-includes/html-api/class-wp-html-text-replacement.php',
	'wp-includes/html-api/class-wp-html-attribute-token.php',
	'wp-includes/html-api/html5-named-character-references.php',
	'wp-includes/html-api/class-wp-html-decoder.php',
	'wp-includes/html-api/class-wp-html-tag-processor.php',
) as $html_file ) {
	if ( is_file( $wp_root . $html_file ) ) {
		require_once $wp_root . $html_file;
	}
}
require $wp_root . 'wp-includes/script-loader.php';
require dirname( __DIR__, 2 ) . '/includes/core/class-module-registry.php';

$options = array();
do_action( 'init' );

if ( 'components' === $module ) {
	$options['functionalities_components'] = array(
		'enabled' => true,
		'items'   => array( array( 'class' => '.audit', 'css' => 'color:red' ) ),
	);
	require dirname( __DIR__, 2 ) . '/includes/traits/trait-css-sanitizer.php';
	require dirname( __DIR__, 2 ) . '/includes/features/class-components.php';
	if ( 'file' === $mode ) {
		$file = new ReflectionProperty( \Functionalities\Features\Components::class, 'css_file_info' );
		if ( PHP_VERSION_ID < 80100 ) {
			$file->setAccessible( true );
		}
		$file->setValue( null, array( 'url' => 'https://example.test/components.css', 'ver' => 'test' ) );
	}
	$wp_styles           = new WP_Styles();
	$concatenate_scripts = false;
	$compress_scripts    = false;
	$compress_css        = false;
	add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
	add_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
	\Functionalities\Features\Components::init();
	ob_start();
	if ( 'admin' === $mode ) {
		do_action( 'admin_enqueue_scripts', 'post.php' );
		wp_print_styles();
	} else {
		do_action( 'wp_enqueue_scripts' );
		do_action( 'wp_footer' );
	}
	$html     = ob_get_clean();
	$settings = apply_filters( 'block_editor_settings_all', array( 'styles' => array() ) );
	echo json_encode( array( 'html' => $html, 'printed_styles' => $wp_styles->done, 'editor_css' => $settings['styles'][0]['css'] ?? '' ) );
} else {
	$options['functionalities_editor_links'] = array(
		'enabled'      => true,
		'enable_limit' => true,
		'post_types'   => 'subtypes' === $mode ? array( 'post', 'page' ) : array( 'page' ),
	);
	require dirname( __DIR__, 2 ) . '/includes/features/class-editor-links.php';
	\Functionalities\Features\Editor_Links::init();
	$search     = apply_filters( 'rest_post_search_query', array( 'post_type' => 'subtypes' === $mode ? array( 'post' ) : array( 'post', 'page' ) ), null );
	$excluded   = apply_filters( 'rest_post_search_query', array( 'post_type' => array( 'attachment' ) ), null );
	$collection = apply_filters( 'rest_post_query', array( 'post_type' => 'post' ), null );
	$classic    = apply_filters( 'wp_link_query_args', array( 'post_type' => array( 'post', 'page' ) ) );
	echo json_encode( array( 'search' => $search, 'excluded' => $excluded, 'collection' => $collection, 'classic' => $classic ) );
}
