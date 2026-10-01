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

	/**
	 * Exercise the author capability boundary with WordPress's actual KSES.
	 *
	 * @return void
	 */
	public function test_restricted_author_cannot_save_or_render_executable_code(): void {
		$wp_dir = getenv( 'FUNCTIONALITIES_WP_DIR' );
		if ( ! $wp_dir || ! is_file( $wp_dir . '/wp-includes/kses.php' ) ) {
			$this->markTestSkipped( 'Set FUNCTIONALITIES_WP_DIR to exercise real WordPress KSES.' );
		}

		$root   = dirname( __DIR__ );
		$script = '<?php require ' . var_export( $wp_dir . '/wp-includes/kses.php', true ) . '; require ' . var_export( $root . '/tests/bootstrap.php', true ) . ';'
			. 'function wp_allowed_protocols() { return array("http", "https"); }'
			. 'require ' . var_export( $root . '/includes/features/class-snippets.php', true ) . ';'
			. 'require ' . var_export( $root . '/includes/admin/trait-admin-sanitizers.php', true ) . ';'
			. 'class AuditRestrictedSnippets { use \\Functionalities\\Admin\\Admin_Sanitizers; }'
			. '$GLOBALS["functionalities_test_caps"] = array("manage_options" => true, "unfiltered_html" => false);'
			. '$payload = "<script src=\"https://attacker.example/payload.js\"></script><script>window.marker=1;</script><iframe src=\"https://attacker.example/\"></iframe><p>Safe text</p>";'
			. '$saved = AuditRestrictedSnippets::sanitize_snippets(array("enabled" => true, "header" => array(array("code" => $payload, "enabled" => true))));'
			. '$method = new ReflectionMethod(\\Functionalities\\Features\\Snippets::class, "escape_snippet"); if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }'
			. '$rendered = $method->invoke(null, $payload, array("unfiltered" => false));'
			. '$GLOBALS["functionalities_test_caps"]["unfiltered_html"] = true;'
			. '$trusted_code = "<script>if (a && b && c < d) { window.marker=1; }</script>";'
			. '$trusted = AuditRestrictedSnippets::sanitize_snippets(array("header"=>array(array("code"=>$trusted_code,"enabled"=>true))));'
			. 'echo json_encode(array("saved"=>$saved["header"][0],"rendered"=>$rendered,"trusted"=>$trusted["header"][0],"trusted_code"=>$trusted_code));';
		$pipes  = array();
		$child  = proc_open( array( PHP_BINARY ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $child );
		fwrite( $pipes[0], $script );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $child ), $error );
		$result = json_decode( $output, true );
		$this->assertFalse( $result['saved']['unfiltered'] );
		$this->assertTrue( $result['trusted']['unfiltered'] );
		$this->assertSame( $result['trusted_code'], $result['trusted']['code'] );
		foreach ( array( $result['saved']['code'], $result['rendered'] ) as $code ) {
			$this->assertStringNotContainsString( '<script', $code );
			$this->assertStringNotContainsString( '<iframe', $code );
			$this->assertStringContainsString( '<p>Safe text</p>', $code );
		}
	}
}
