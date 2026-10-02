<?php
/**
 * Settings layout preserves native forms while collecting module guidance.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Admin\Admin_UI;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/admin/class-admin-ui.php';

final class AdminLayoutTest extends TestCase {
	public function test_guidance_is_outside_the_complete_settings_form(): void {
		ob_start();
		Admin_UI::render_settings_layout( static function () {
			echo '<form id="settings" action="options.php"><input type="hidden" name="_wpnonce" value="test-nonce">';
			Admin_UI::render_docs_section( 'Supported types', '<p>WebPage</p>', 'usage', true );
			echo '<div class="notice notice-warning"><p>Check these settings.</p></div><input name="functionalities_schema[enabled]" type="checkbox"><button type="submit">Save</button></form>';
		} );
		$html = ob_get_clean();
		$dom  = new DOMDocument();
		$dom->loadHTML( $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		$path = new DOMXPath( $dom );
		$this->assertSame( 1, $path->query( '//form[@id="settings"]//input[@name="_wpnonce"]' )->length );
		$this->assertSame( 1, $path->query( '//form[@id="settings"]//input[@name="functionalities_schema[enabled]"]' )->length );
		$this->assertSame( 1, $path->query( '//form[@id="settings"]//div[@class="notice notice-warning"]' )->length );
		$this->assertSame( 0, $path->query( '//form//details' )->length );
		$this->assertSame( 1, $path->query( '//aside//details[@open]' )->length );
		$this->assertSame( 0, $path->query( '//aside//input | //aside//button | //aside//form' )->length );
	}

	public function test_screens_without_guidance_do_not_gain_an_empty_sidebar(): void {
		ob_start();
		Admin_UI::render_settings_layout( static function () {
			echo '<form><input name="setting"></form>';
		} );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<aside', $html );
		$this->assertStringContainsString( '<form><input name="setting"></form>', $html );
		ob_start();
		Admin_UI::render_docs_section( 'Help', '<p>Standalone help remains visible.</p>' );
		$this->assertStringContainsString( 'Standalone help remains visible.', ob_get_clean() );
	}

	public function test_a_failed_render_restores_buffers_and_guidance_state(): void {
		$level = ob_get_level();
		try {
			Admin_UI::render_settings_layout( static function () {
				Admin_UI::render_docs_section( 'Discarded', '<p>Discarded guidance</p>' );
				ob_start();
				echo 'Partial nested output';
				throw new RuntimeException( 'Render failed' );
			} );
			$this->fail( 'The render exception must propagate.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'Render failed', $error->getMessage() );
		}
		$this->assertSame( $level, ob_get_level() );
		ob_start();
		Admin_UI::render_docs_section( 'Help', '<p>Help after failure</p>' );
		$this->assertStringContainsString( 'Help after failure', ob_get_clean() );
	}
}
