<?php
/**
 * WordPress 7 integration tests.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Core\WordPress_7_Integration;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/core/class-wordpress-7-integration.php';

/**
 * Verify progressive WordPress 7 metadata and binding contracts.
 */
final class WordPress7IntegrationTest extends TestCase {

	/**
	 * SVG content fields should be available to Block Bindings and overrides.
	 *
	 * @return void
	 */
	public function test_svg_icon_binding_attributes_are_registered(): void {
		$attributes = WordPress_7_Integration::register_svg_binding_attributes( array( 'existing' ) );

		$this->assertSame( array( 'existing', 'iconSlug', 'coreIcon', 'label' ), $attributes );
	}

	/**
	 * The SVG Icon block must use the iframed-editor-compatible Block API.
	 *
	 * @return void
	 */
	public function test_svg_icon_block_uses_api_v3_and_content_roles(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads bundled test metadata.
		$metadata = json_decode(
			(string) file_get_contents( dirname( __DIR__ ) . '/assets/blocks/svg-icon/block.json' ),
			true
		);

		$this->assertSame( 3, $metadata['apiVersion'] );
		$this->assertSame( '1.5.0', $metadata['version'] );
		$this->assertSame( 'content', $metadata['attributes']['iconSlug']['role'] );
		$this->assertSame( 'content', $metadata['attributes']['coreIcon']['role'] );
		$this->assertSame( 'content', $metadata['attributes']['label']['role'] );
	}
}
