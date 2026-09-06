<?php
/**
 * Abilities API permission and schema tests.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Core\WordPress_7_Integration;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/core/class-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/core/class-wordpress-7-integration.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	/**
	 * Record a registered ability instead of talking to core.
	 *
	 * @param string $name Ability name.
	 * @param array  $args Ability arguments.
	 * @return void
	 */
	function wp_register_ability( $name, $args ) {
		$GLOBALS['functionalities_test_abilities'][ $name ] = $args;
	}
}

/**
 * Guard the permission model behind the Abilities API.
 */
final class AbilitiesPermissionTest extends TestCase {

	/**
	 * Reset capability and post stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_abilities'] = array();
		$GLOBALS['functionalities_test_caps']      = array();
		$GLOBALS['functionalities_test_posts']     = array();
		$GLOBALS['functionalities_test_options']   = array();
	}

	/**
	 * Build a stub post of a given type.
	 *
	 * @param string $type Post type.
	 * @return WP_Post
	 */
	private static function post( string $type ): WP_Post {
		$post            = new WP_Post();
		$post->post_type = $type;
		return $post;
	}

	/**
	 * Register every ability and return the recorded arguments.
	 *
	 * @return array
	 */
	private function abilities(): array {
		WordPress_7_Integration::register_abilities();
		return $GLOBALS['functionalities_test_abilities'];
	}

	/**
	 * Site-wide abilities must require manage_options, not a post capability.
	 *
	 * A single shared callback used to fall back to `edit_post` whenever the
	 * input carried a post_id, so a contributor with one draft could reach every
	 * administrator-only operation by adding that property to the request.
	 *
	 * @return void
	 */
	public function test_site_abilities_require_manage_options(): void {
		$abilities = $this->abilities();

		$site_wide = array(
			'functionalities/get-module-status',
			'functionalities/run-diagnostics',
			'functionalities/scan-assumptions',
			'functionalities/preview-redirect-import',
			'functionalities/create-redirect',
			'functionalities/create-task',
			'functionalities/toggle-module',
			'functionalities/explain-finding',
		);

		foreach ( $site_wide as $name ) {
			$this->assertArrayHasKey( $name, $abilities );
			$this->assertSame(
				array( WordPress_7_Integration::class, 'permission_manage_options' ),
				$abilities[ $name ]['permission_callback'],
				$name . ' must not use a post-scoped permission callback'
			);
		}
	}

	/**
	 * A contributor cannot widen a site-wide ability by supplying a post_id.
	 *
	 * @return void
	 */
	public function test_smuggled_post_id_does_not_grant_access(): void {
		$GLOBALS['functionalities_test_caps']  = array( 'edit_post' => true );
		$GLOBALS['functionalities_test_posts'] = array( 12 => self::post( 'post' ) );

		$this->assertFalse( WordPress_7_Integration::permission_manage_options() );

		$abilities = $this->abilities();
		$callback  = $abilities['functionalities/toggle-module']['permission_callback'];

		$this->assertFalse(
			call_user_func( $callback, array( 'module' => 'pwa', 'enabled' => false, 'post_id' => 12 ) )
		);
	}

	/**
	 * Every input schema rejects properties it does not declare.
	 *
	 * Core only enforces this when additionalProperties is false, which is what
	 * made the smuggled property possible in the first place.
	 *
	 * @return void
	 */
	public function test_input_schemas_reject_unknown_properties(): void {
		foreach ( $this->abilities() as $name => $args ) {
			if ( ! isset( $args['input_schema'] ) ) {
				continue;
			}
			$this->assertArrayHasKey( 'additionalProperties', $args['input_schema'], $name );
			$this->assertFalse( $args['input_schema']['additionalProperties'], $name );
		}
	}

	/**
	 * The content-integrity ability checks the post type as well as the capability.
	 *
	 * @return void
	 */
	public function test_content_integrity_permission_checks_the_post_type(): void {
		$GLOBALS['functionalities_test_caps']    = array( 'edit_post' => true );
		$GLOBALS['functionalities_test_posts']   = array(
			5 => self::post( 'post' ),
			9 => self::post( 'attachment' ),
		);
		$GLOBALS['functionalities_test_options'] = array(
			'functionalities_content_regression' => array( 'post_types' => array( 'post', 'page' ) ),
		);

		$this->assertTrue( WordPress_7_Integration::permission_edit_monitored_post( array( 'post_id' => 5 ) ) );
		$this->assertFalse( WordPress_7_Integration::permission_edit_monitored_post( array( 'post_id' => 9 ) ) );
		$this->assertFalse( WordPress_7_Integration::permission_edit_monitored_post( array( 'post_id' => 404 ) ) );
		$this->assertFalse( WordPress_7_Integration::permission_edit_monitored_post( array() ) );
	}
}
