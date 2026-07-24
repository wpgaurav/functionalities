<?php
/**
 * SVG Icons tests.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Features\SVG_Icons;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-svg-icons.php';

/**
 * Verify SVG sanitization and rendering.
 */
final class SvgIconsTest extends TestCase {

	/**
	 * Reset the request-local class caches.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_options'] = array();

		foreach ( array( 'options' => null, 'render_instance' => 0 ) as $property_name => $value ) {
			$property = new ReflectionProperty( SVG_Icons::class, $property_name );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( null, $value );
		}
	}

	/**
	 * Store an icon in the lightweight option stub.
	 *
	 * @param string $slug Icon slug.
	 * @param string $svg  SVG source.
	 * @return void
	 */
	private function storeIcon( string $slug, string $svg ): void {
		$GLOBALS['functionalities_test_options']['functionalities_svg_icons'] = array(
			'enabled' => true,
			'icons'   => array(
				$slug => array(
					'slug' => $slug,
					'name' => 'Test Icon',
					'svg'  => $svg,
				),
			),
		);
	}

	public function testSanitizerRequiresAnSvgRoot(): void {
		$this->assertSame( '', SVG_Icons::sanitize_svg( '<div><svg viewBox="0 0 10 10"/></div>' ) );
	}

	public function testSanitizerKeepsSvgDefinitionsAndRejectsActiveContent(): void {
		$source = '<svg viewBox="0 0 10 10" onload="alert(1)" style="fill:red;position:fixed">'
			. '<script>alert(1)</script>'
			. '<defs><linearGradient id="paint"><stop offset="0" style="stop-color:#fff;behavior:url(x)"/></linearGradient>'
			. '<clipPath id="cut"><path d="M0 0h5v5z"/></clipPath></defs>'
			. '<use href="#cut"/><use href="https://example.com/icon.svg#cut"/>'
			. '<path fill="url(https://example.com/paint.svg#paint)" clip-path="url(#cut)" d="M0 0h10v10z"/></svg>';
		$result = SVG_Icons::sanitize_svg( $source );

		$this->assertStringContainsString( '<linearGradient', $result );
		$this->assertStringContainsString( '<clipPath', $result );
		$this->assertStringContainsString( 'href="#cut"', $result );
		$this->assertStringNotContainsString( 'https://', $result );
		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringNotContainsString( 'onload', $result );
		$this->assertStringNotContainsString( 'position', $result );
		$this->assertStringNotContainsString( 'behavior', $result );
	}

	public function testDecorativeRenderingPrefixesDefinitionIdsPerInstance(): void {
		$this->storeIcon(
			'test',
			'<svg class="source" width="20" height="20" viewBox="0 0 20 20"><defs><linearGradient id="paint"><stop offset="0" stop-color="#fff"/></linearGradient></defs><path fill="url(#paint)" d="M0 0h20v20z"/></svg>'
		);

		$first  = SVG_Icons::render_icon( 'test', 'extra', array( 'color_mode' => 'original' ) );
		$second = SVG_Icons::render_icon( 'test', 'extra', array( 'color_mode' => 'original' ) );

		$this->assertStringContainsString( 'class="func-svg-icon source extra"', $first );
		$this->assertStringContainsString( 'aria-hidden="true"', $first );
		$this->assertStringContainsString( 'focusable="false"', $first );
		$this->assertStringNotContainsString( 'width="20"', $first );
		$this->assertStringContainsString( 'id="func-svg-1-paint"', $first );
		$this->assertStringContainsString( 'url(#func-svg-1-paint)', $first );
		$this->assertStringContainsString( 'id="func-svg-2-paint"', $second );
	}

	public function testInformativeOriginalColorRenderingHasAnAccessibleName(): void {
		$this->storeIcon( 'test', '<svg viewBox="0 0 10 10"><path fill="#ff0000" stroke="none" d="M0 0h10v10z"/></svg>' );

		$result = SVG_Icons::render_icon(
			'test',
			'',
			array(
				'color_mode' => 'original',
				'decorative' => false,
				'label'      => 'Status: complete',
			)
		);

		$this->assertStringContainsString( 'role="img"', $result );
		$this->assertStringContainsString( 'aria-label="Status: complete"', $result );
		$this->assertStringContainsString( 'fill="#ff0000"', $result );
		$this->assertStringContainsString( 'stroke="none"', $result );
		$this->assertStringNotContainsString( 'aria-hidden', $result );
	}

	public function testBlockRendererClampsSizeAndRejectsCssInjection(): void {
		$this->storeIcon( 'test', '<svg viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>' );

		$result = SVG_Icons::render_block(
			array(
				'iconSlug' => 'test',
				'size'     => 9999,
				'sizeUnit' => 'px',
				'align'    => 'right;background:url(javascript:alert(1))',
				'color'    => '#fff;background:red',
			)
		);

		$this->assertStringContainsString( '--func-icon-size:512px', $result );
		$this->assertStringNotContainsString( 'javascript', $result );
		$this->assertStringNotContainsString( 'background', $result );
		$this->assertStringContainsString( 'fill="currentColor"', $result );
	}
}
