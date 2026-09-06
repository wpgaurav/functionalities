<?php
/**
 * Header and footer snippet output tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-snippets.php';

/**
 * A snippet must reach a logged-out visitor exactly as it was saved.
 */
final class SnippetsOutputTest extends TestCase {

	/**
	 * Reset the request-local option cache.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_options'] = array();
		$GLOBALS['functionalities_test_caps']    = array();

		$property = new ReflectionProperty( \Functionalities\Features\Snippets::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	/**
	 * Render one location and return the printed markup.
	 *
	 * @param string $location Location key.
	 * @return string
	 */
	private function render( string $location ): string {
		$method = new ReflectionMethod( \Functionalities\Features\Snippets::class, 'output_snippets' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invokeArgs( null, array( $location, 'functionalities_snippets_header_code', 'Test' ) );
		return (string) ob_get_clean();
	}

	/**
	 * JavaScript operators survive for an anonymous visitor.
	 *
	 * Output used to be filtered against the capability of whoever was viewing
	 * the page, so `&&` became `&amp;&amp;` and comparison operators were eaten
	 * as tags for every logged-out reader while the administrator saw it work.
	 *
	 * @return void
	 */
	public function test_javascript_survives_for_anonymous_visitors(): void {
		$code = '<script>if (a && b && c < d) { window.x = 1; }</script>';

		$GLOBALS['functionalities_test_options']['functionalities_snippets'] = array(
			'enabled' => true,
			'header'  => array(
				array(
					'code'       => $code,
					'enabled'    => true,
					'unfiltered' => true,
				),
			),
		);

		$output = $this->render( 'header' );

		$this->assertStringContainsString( 'a && b && c < d', $output );
		$this->assertStringNotContainsString( '&amp;&amp;', $output );
	}

	/**
	 * A snippet saved by a user without unfiltered_html is still filtered.
	 *
	 * @return void
	 */
	public function test_snippet_saved_without_the_capability_is_filtered(): void {
		$GLOBALS['functionalities_test_options']['functionalities_snippets'] = array(
			'enabled' => true,
			'header'  => array(
				array(
					'code'       => '<script>ok()</script><div onclick="bad()">x</div>',
					'enabled'    => true,
					'unfiltered' => false,
				),
			),
		);

		$output = $this->render( 'header' );

		$this->assertStringNotContainsString( 'onclick', $output );
	}

	/**
	 * A disabled snippet prints nothing.
	 *
	 * @return void
	 */
	public function test_disabled_snippet_is_not_printed(): void {
		$GLOBALS['functionalities_test_options']['functionalities_snippets'] = array(
			'enabled' => true,
			'header'  => array(
				array(
					'code'       => '<script>never()</script>',
					'enabled'    => false,
					'unfiltered' => true,
				),
			),
		);

		$this->assertSame( '', $this->render( 'header' ) );
	}
}
