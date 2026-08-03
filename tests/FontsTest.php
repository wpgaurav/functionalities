<?php
/**
 * Custom Fonts tests.
 *
 * @package FunctionalitiesTests
 */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['functionalities_test_actions'][] = array( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['functionalities_test_filters'][] = array( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action() {
		// No-op in the lightweight test environment.
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_print_inline_style_tag' ) ) {
	function wp_print_inline_style_tag( $data, $attributes = array() ) {
		$id = isset( $attributes['id'] ) ? ' id="' . esc_attr( $attributes['id'] ) . '"' : '';
		echo '<style' . $id . '>' . $data . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

require_once dirname( __DIR__ ) . '/includes/traits/trait-css-sanitizer.php';
require_once dirname( __DIR__ ) . '/includes/features/class-fonts.php';

/**
 * Verify critical frontend font declaration behavior.
 */
final class FontsTest extends PHPUnit\Framework\TestCase {

	/**
	 * Reset the request-local cache and hook ledgers.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['functionalities_test_options'] = array();
		$GLOBALS['functionalities_test_actions'] = array();
		$GLOBALS['functionalities_test_filters'] = array();

		$property = new ReflectionProperty( Functionalities\Features\Fonts::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	public function testFrontendFontFacesPrintAsOneInlineStyle(): void {
		$GLOBALS['functionalities_test_options']['functionalities_fonts'] = array(
			'enabled' => true,
			'items'   => array(
				array(
					'family'        => 'Valley Sans',
					'style'         => 'normal',
					'display'       => 'optional',
					'weight'        => '400',
					'woff2_url'     => 'https://cdn.example.com/valley.woff2',
					'unicode_range' => 'U+0000-00FF',
				),
			),
		);

		ob_start();
		Functionalities\Features\Fonts::print_fonts_css();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, 'id="functionalities-fonts-inline-css"' ) );
		$this->assertStringContainsString( 'font-family:"Valley Sans"', $output );
		$this->assertStringContainsString( 'font-display:optional', $output );
		$this->assertStringContainsString( 'unicode-range:U+0000-00FF', $output );
		$this->assertStringNotContainsString( '<link', $output );
	}

	public function testFrontendFontFacesAreHookedImmediatelyAfterPreloads(): void {
		Functionalities\Features\Fonts::init();

		$head_hooks = array_values(
			array_filter(
				$GLOBALS['functionalities_test_actions'],
				static function ( $hook ) {
					return 'wp_head' === $hook[0];
				}
			)
		);

		$this->assertCount( 2, $head_hooks );
		$this->assertSame( 'preload_fonts', $head_hooks[0][1][1] );
		$this->assertSame( 1, $head_hooks[0][2] );
		$this->assertSame( 'print_fonts_css', $head_hooks[1][1][1] );
		$this->assertSame( 2, $head_hooks[1][2] );
	}
}
