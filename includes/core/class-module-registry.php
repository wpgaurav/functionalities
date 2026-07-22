<?php
/**
 * Central module registry and lazy loader.
 *
 * @package Functionalities\Core
 */

namespace Functionalities\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define modules once and load feature classes only when needed.
 */
class Module_Registry {

	/**
	 * Modules booted during this request.
	 *
	 * @var array
	 */
	private static $booted = array();

	/**
	 * Return all registered modules in dashboard order.
	 *
	 * @return array
	 */
	public static function get_definitions(): array {
		return array(
			'task-manager'         => self::definition( __( 'Task Manager', 'functionalities' ), __( 'File-based project task management with JSON storage.', 'functionalities' ), 'dashicons-yes-alt', 'Task_Manager', true ),
			'misc'                 => self::definition( __( 'Performance & Cleanup', 'functionalities' ), __( 'Disable bloat, emojis, embeds, heartbeat, and more.', 'functionalities' ), 'dashicons-performance', 'Misc' ),
			'snippets'             => self::definition( __( 'Header & Footer', 'functionalities' ), __( 'Add GA4, custom header and footer code.', 'functionalities' ), 'dashicons-editor-code', 'Snippets' ),
			'link-management'      => self::definition( __( 'Link Management', 'functionalities' ), __( 'Control nofollow, new tabs, and link behavior.', 'functionalities' ), 'dashicons-admin-links', 'Link_Management' ),
			'redirect-manager'     => self::definition( __( 'Redirect Manager', 'functionalities' ), __( 'Create and manage 301/302 URL redirects.', 'functionalities' ), 'dashicons-randomize', 'Redirect_Manager', true ),
			'block-cleanup'        => self::definition( __( 'Block Cleanup', 'functionalities' ), __( 'Strip block classes from frontend output.', 'functionalities' ), 'dashicons-block-default', 'Block_Cleanup' ),
			'schema'               => self::definition( __( 'Schema Settings', 'functionalities' ), __( 'Add microdata to key areas and content.', 'functionalities' ), 'dashicons-networking', 'Schema' ),
			'content-regression'   => self::definition( __( 'Content Integrity', 'functionalities' ), __( 'Detect structural regressions when posts are updated.', 'functionalities' ), 'dashicons-shield', 'Content_Regression' ),
			'assumption-detection' => self::definition( __( 'Assumption Detection', 'functionalities' ), __( 'Notice when implicit site assumptions stop being true.', 'functionalities' ), 'dashicons-visibility', 'Assumption_Detection' ),
			'login-security'       => self::definition( __( 'Login Security', 'functionalities' ), __( 'Limit login attempts, customize login page, block XML-RPC.', 'functionalities' ), 'dashicons-lock', 'Login_Security' ),
			'meta'                 => self::definition( __( 'Meta & Copyright', 'functionalities' ), __( 'Copyright, Dublin Core, licensing, and SEO plugin integration.', 'functionalities' ), 'dashicons-media-text', 'Meta' ),
			'components'           => self::definition( __( 'Components', 'functionalities' ), __( 'Define reusable CSS components.', 'functionalities' ), 'dashicons-layout', 'Components' ),
			'fonts'                => self::definition( __( 'Fonts', 'functionalities' ), __( 'Register custom font families.', 'functionalities' ), 'dashicons-editor-textcolor', 'Fonts' ),
			'editor-links'         => self::definition( __( 'Editor Link Suggestions', 'functionalities' ), __( 'Limit link suggestions to selected post types.', 'functionalities' ), 'dashicons-editor-unlink', 'Editor_Links' ),
			'svg-icons'            => self::definition( __( 'SVG Icons', 'functionalities' ), __( 'Upload custom SVG icons and insert them inline in the block editor.', 'functionalities' ), 'dashicons-flag', 'SVG_Icons', true ),
			'pwa'                  => self::definition( __( 'Progressive Web App', 'functionalities' ), __( 'Make your site installable and work offline.', 'functionalities' ), 'dashicons-smartphone', 'PWA' ),
		);
	}

	/**
	 * Return module metadata for the admin dashboard.
	 *
	 * @return array
	 */
	public static function get_admin_modules(): array {
		$modules = array();
		foreach ( self::get_definitions() as $slug => $definition ) {
			$modules[ $slug ] = array(
				'title'       => $definition['title'],
				'description' => $definition['description'],
				'icon'        => $definition['icon'],
				'custom_page' => $definition['custom_page'],
				'controller'  => $definition['controller'],
			);
		}
		return $modules;
	}

	/**
	 * Load every module required for the current request.
	 *
	 * @return void
	 */
	public static function boot(): void {
		foreach ( self::get_definitions() as $slug => $definition ) {
			if ( self::is_enabled( $slug ) || self::is_admin_context( $slug ) ) {
				self::boot_module( $slug );
			}
		}
	}

	/**
	 * Load and initialize one module.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function boot_module( string $slug ): bool {
		$definitions = self::get_definitions();
		if ( isset( self::$booted[ $slug ] ) || ! isset( $definitions[ $slug ] ) ) {
			return isset( self::$booted[ $slug ] );
		}

		$class = $definitions[ $slug ]['class'];
		if ( ! class_exists( $class ) || ! is_callable( array( $class, 'init' ) ) ) {
			return false;
		}

		self::$booted[ $slug ] = true;
		call_user_func( array( $class, 'init' ) );
		return true;
	}

	/**
	 * Check a module's stored and filtered enabled state without loading it.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function is_enabled( string $slug ): bool {
		$definitions = self::get_definitions();
		if ( ! isset( $definitions[ $slug ] ) ) {
			return false;
		}

		$options = (array) get_option( $definitions[ $slug ]['option'], array( 'enabled' => $definitions[ $slug ]['default_enabled'] ) );
		$enabled = ! empty( $options['enabled'] );
		$enabled = (bool) apply_filters( 'functionalities_' . str_replace( '-', '_', $slug ) . '_enabled', $enabled );

		return (bool) apply_filters( 'functionalities_module_enabled', $enabled, $slug, $options );
	}

	/**
	 * Return a module option name.
	 *
	 * @param string $slug Module slug.
	 * @return string
	 */
	public static function get_option_name( string $slug ): string {
		$definitions = self::get_definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ]['option'] : '';
	}

	/**
	 * React to settings updates that require feature-specific maintenance.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function handle_option_update( string $option, $old_value, $value ): void {
		if ( 'functionalities_pwa' !== $option ) {
			return;
		}

		self::boot_module( 'pwa' );
		if ( is_callable( array( '\Functionalities\Features\PWA', 'on_option_update' ) ) ) {
			\Functionalities\Features\PWA::on_option_update( $old_value, $value );
		}
	}

	/**
	 * Build one normalized definition.
	 *
	 * @param string $title       English title.
	 * @param string $description English description.
	 * @param string $icon        Dashicon class.
	 * @param string $class_name  Feature class basename.
	 * @param bool   $custom_page Whether the module has a custom admin page.
	 * @return array
	 */
	private static function definition( string $title, string $description, string $icon, string $class_name, bool $custom_page = false ): array {
		$slug = strtolower( str_replace( '_', '-', $class_name ) );
		return array(
			'title'           => $title,
			'description'     => $description,
			'icon'            => $icon,
			'class'           => '\\Functionalities\\Features\\' . $class_name,
			'option'          => 'functionalities_' . str_replace( '-', '_', $slug ),
			'default_enabled' => false,
			'custom_page'     => $custom_page,
			'controller'      => $custom_page ? '\\Functionalities\\Admin\\' . $class_name . '_Controller' : '',
		);
	}

	/**
	 * Check whether an otherwise-disabled module is needed in wp-admin.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	private static function is_admin_context( string $slug ): bool {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing context.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing context.
		$module = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin-ajax action routing.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( $slug === $module || 'functionalities-' . $slug === $page ) {
			return true;
		}

		$prefixes = array(
			'task-manager'         => 'functionalities_task_',
			'redirect-manager'     => 'functionalities_redirect_',
			'svg-icons'            => 'functionalities_svg_icon_',
			'assumption-detection' => 'functionalities_run_detection',
		);

		return isset( $prefixes[ $slug ] ) && 0 === strpos( $action, $prefixes[ $slug ] );
	}
}
