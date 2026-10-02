<?php
/**
 * Integration tests for component styles and editor link search routing.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class FeatureAssetsTest extends TestCase {
	/** Both file-backed and fallback component styles must actually print. */
	public function test_component_styles_print_on_frontend(): void {
		foreach ( array( 'file', 'inline' ) as $channel ) {
			$result = $this->run_worker( 'components', $channel );
			$this->assertContains( 'functionalities-components', $result['printed_styles'] );
			$this->assertStringContainsString( 'functionalities-components', $result['html'] );
			$this->assertStringContainsString( '.audit{color:red}', $result['editor_css'] );
		}
	}

	/** Admin styles still use the ordinary admin enqueue channel. */
	public function test_component_styles_print_in_admin(): void {
		$result = $this->run_worker( 'components', 'admin' );
		$this->assertContains( 'functionalities-components', $result['printed_styles'] );
		$this->assertStringContainsString( '.audit{color:red}', $result['html'] );
	}

	public function test_component_styles_reach_the_canvas_with_or_without_a_generated_file(): void {
		foreach ( array( 'editor-file', 'editor-inline' ) as $mode ) {
			$result = $this->run_worker( 'components', $mode );
			$this->assertContains( 'functionalities-components-editor', $result['printed_styles'] );
			$this->assertStringContainsString( 'assets/css/components-editor.css', $result['html'] );
			$this->assertStringContainsString( '.audit{color:red}', $result['html'] );
		}
	}

	/** Search limiting belongs only to the post search endpoint. */
	public function test_editor_links_limit_search_without_changing_post_collections(): void {
		$result = $this->run_worker( 'editor-links', 'search' );
		$this->assertSame( array( 'page' ), $result['search']['post_type'] );
		$this->assertArrayNotHasKey( 'subtype', $result['search'] );
		$this->assertSame( 'post', $result['collection']['post_type'] );
		$this->assertSame( array( 'page' ), $result['classic']['post_type'] );
	}

	/** An explicit subtype must not broaden beyond the requested types. */
	public function test_editor_links_preserve_explicit_search_subtypes(): void {
		$result = $this->run_worker( 'editor-links', 'subtypes' );
		$this->assertSame( array( 'post' ), $result['search']['post_type'] );
		$this->assertSame( array(), $result['excluded']['post_type'] );
		$this->assertSame( array( 0 ), $result['excluded']['post__in'] );
	}

	/**
	 * Run with WordPress's real hooks and style printer in a separate process.
	 *
	 * @param string $module Module under test.
	 * @param string $mode Test scenario.
	 * @return array
	 */
	private function run_worker( string $module, string $mode ): array {
		$wp_dir = getenv( 'FUNCTIONALITIES_WP_DIR' );
		if ( ! $wp_dir || ! is_file( $wp_dir . '/wp-includes/script-loader.php' ) ) {
			$this->markTestSkipped( 'Set FUNCTIONALITIES_WP_DIR to a WordPress root to run asset integration tests.' );
		}
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/feature-assets-worker.php' )
			. ' ' . escapeshellarg( $wp_dir ) . ' ' . escapeshellarg( $module ) . ' ' . escapeshellarg( $mode );
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result );
		return $result;
	}
}
