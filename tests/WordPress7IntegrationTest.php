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

		// Pinning the literal here meant this test failed on every release.
		// VersionConsistencyTest already asserts block.json matches the plugin
		// header, so all this needs to check is that the key is present.
		$this->assertArrayHasKey( 'version', $metadata );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $metadata['version'] );
		$this->assertSame( 'content', $metadata['attributes']['iconSlug']['role'] );
		$this->assertSame( 'content', $metadata['attributes']['coreIcon']['role'] );
		$this->assertSame( 'content', $metadata['attributes']['label']['role'] );
	}

	/**
	 * The workspace bundle must not be handed a module the site has switched off.
	 *
	 * A disabled module returns no data from the REST route and refuses every
	 * write, so rendering the panel offered a create form that could only error.
	 *
	 * @return void
	 */
	public function test_workspace_source_gates_on_module_enabled(): void {
		$php = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-wordpress-7-integration.php' );
		$this->assertStringContainsString(
			"'moduleEnabled'",
			$php,
			'The localized config must expose whether the module is enabled.'
		);
		$this->assertStringContainsString(
			'Module_Registry::is_enabled( $workspace_module )',
			$php,
			'The enabled state must come from the registry, not be assumed.'
		);

		$js = file_get_contents( dirname( __DIR__ ) . '/src/wp7-admin.js' );
		$this->assertStringContainsString(
			'! config.moduleEnabled',
			$js,
			'The workspace must return early when the module is disabled.'
		);

		$bundle = file_get_contents( dirname( __DIR__ ) . '/assets/js/wp7-admin.js' );
		$this->assertStringContainsString(
			'moduleEnabled',
			$bundle,
			'The built bundle is stale; run npm run build.'
		);
	}

	/**
	 * Admin copy should not name the WordPress version that ships a feature.
	 *
	 * "WordPress 7 workspace" labelled the implementation rather than what the
	 * reader was looking at, and dates itself on the next major release.
	 *
	 * @return void
	 */
	public function test_admin_copy_does_not_leak_platform_version(): void {
		$paths = array(
			'/includes/core/class-wordpress-7-integration.php',
			'/includes/admin/class-module-controller.php',
		);
		foreach ( $paths as $path ) {
			$source = file_get_contents( dirname( __DIR__ ) . $path );
			preg_match_all( "/(?:esc_html__|esc_html_e|esc_attr__|__|_e)\(\s*'([^']+)'/", $source, $matches );
			foreach ( $matches[1] as $string ) {
				$this->assertDoesNotMatchRegularExpression(
					'/\bWordPress 7\b/',
					$string,
					sprintf( 'Translatable string in %s names a WordPress version: "%s"', $path, $string )
				);
			}
		}
	}
}
