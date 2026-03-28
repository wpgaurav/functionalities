<?php
/**
 * Admin pages and settings for Functionalities plugin.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load traits.
require_once __DIR__ . '/trait-admin-ajax.php';
require_once __DIR__ . '/trait-admin-options.php';
require_once __DIR__ . '/trait-admin-sanitizers.php';

/**
 * Admin class for managing plugin settings and UI.
 *
 * This class uses traits to organize its methods:
 * - Admin_Ajax: AJAX handlers
 * - Admin_Options: Options getters
 * - Admin_Sanitizers: Sanitizer methods
 */
class Admin {

	use Admin_Ajax;
	use Admin_Options;
	use Admin_Sanitizers;

	/**
	 * Available modules configuration.
	 *
	 * @var array
	 */
	private static $modules = array();

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init() : void {
		self::define_modules();
		\add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// AJAX handler for database update tool.
		\add_action( 'wp_ajax_functionalities_update_database', array( __CLASS__, 'ajax_update_database' ) );

		// AJAX handler for JSON file creation.
		\add_action( 'wp_ajax_functionalities_create_json_file', array( __CLASS__, 'ajax_create_json_file' ) );

		// AJAX handler for running assumption detection.
		\add_action( 'wp_ajax_functionalities_run_detection', array( __CLASS__, 'ajax_run_detection' ) );

		// AJAX handler for delete-data-on-uninstall toggle.
		\add_action( 'wp_ajax_functionalities_toggle_delete_data', array( __CLASS__, 'ajax_toggle_delete_data' ) );
	}

	/**
	 * Define available modules.
	 *
	 * @return void
	 */
	private static function define_modules() : void {
		// Ordered by typical usage frequency - most used first.
		self::$modules = array(
			'task-manager' => array(
				'title'       => \__( 'Task Manager', 'functionalities' ),
				'description' => \__( 'File-based project task management with JSON storage.', 'functionalities' ),
				'icon'        => 'dashicons-yes-alt',
				'custom_page' => true,
			),
			'misc'            => array(
				'title'       => \__( 'Performance & Cleanup', 'functionalities' ),
				'description' => \__( 'Disable bloat, emojis, embeds, heartbeat, and more.', 'functionalities' ),
				'icon'        => 'dashicons-performance',
			),
			'snippets'        => array(
				'title'       => \__( 'Header & Footer', 'functionalities' ),
				'description' => \__( 'Add GA4, custom header and footer code.', 'functionalities' ),
				'icon'        => 'dashicons-editor-code',
			),
			'link-management' => array(
				'title'       => \__( 'Link Management', 'functionalities' ),
				'description' => \__( 'Control nofollow, new tabs, and link behavior.', 'functionalities' ),
				'icon'        => 'dashicons-admin-links',
			),
			'redirect-manager' => array(
				'title'       => \__( 'Redirect Manager', 'functionalities' ),
				'description' => \__( 'Create and manage 301/302 URL redirects.', 'functionalities' ),
				'icon'        => 'dashicons-randomize',
				'custom_page' => true,
			),
			'block-cleanup'   => array(
				'title'       => \__( 'Block Cleanup', 'functionalities' ),
				'description' => \__( 'Strip block classes from frontend output.', 'functionalities' ),
				'icon'        => 'dashicons-block-default',
			),
			'schema'          => array(
				'title'       => \__( 'Schema Settings', 'functionalities' ),
				'description' => \__( 'Add microdata to key areas and content.', 'functionalities' ),
				'icon'        => 'dashicons-networking',
			),
			'content-regression' => array(
				'title'       => \__( 'Content Integrity', 'functionalities' ),
				'description' => \__( 'Detect structural regressions when posts are updated.', 'functionalities' ),
				'icon'        => 'dashicons-shield',
			),
			'assumption-detection' => array(
				'title'       => \__( 'Assumption Detection', 'functionalities' ),
				'description' => \__( 'Notice when implicit site assumptions stop being true.', 'functionalities' ),
				'icon'        => 'dashicons-visibility',
			),
			'login-security' => array(
				'title'       => \__( 'Login Security', 'functionalities' ),
				'description' => \__( 'Limit login attempts, customize login page, block XML-RPC.', 'functionalities' ),
				'icon'        => 'dashicons-lock',
			),
			'meta'            => array(
				'title'       => \__( 'Meta & Copyright', 'functionalities' ),
				'description' => \__( 'Copyright, Dublin Core, licensing, and SEO plugin integration.', 'functionalities' ),
				'icon'        => 'dashicons-media-text',
			),
			'components'      => array(
				'title'       => \__( 'Components', 'functionalities' ),
				'description' => \__( 'Define reusable CSS components.', 'functionalities' ),
				'icon'        => 'dashicons-layout',
			),
			'fonts'           => array(
				'title'       => \__( 'Fonts', 'functionalities' ),
				'description' => \__( 'Register custom font families.', 'functionalities' ),
				'icon'        => 'dashicons-editor-textcolor',
			),
			'editor-links'    => array(
				'title'       => \__( 'Editor Link Suggestions', 'functionalities' ),
				'description' => \__( 'Limit link suggestions to selected post types.', 'functionalities' ),
				'icon'        => 'dashicons-editor-unlink',
			),
			'svg-icons'       => array(
				'title'       => \__( 'SVG Icons', 'functionalities' ),
				'description' => \__( 'Upload custom SVG icons and insert them inline in the block editor.', 'functionalities' ),
				'icon'        => 'dashicons-flag',
				'custom_page' => true,
			),
			'pwa'             => array(
				'title'       => \__( 'Progressive Web App', 'functionalities' ),
				'description' => \__( 'Make your site installable and work offline.', 'functionalities' ),
				'icon'        => 'dashicons-smartphone',
			),
		);
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function register_menu() : void {
		$parent_slug = 'functionalities';
		\add_menu_page(
			\__( 'Functionalities', 'functionalities' ),
			\__( 'Functionalities', 'functionalities' ),
			'manage_options',
			$parent_slug,
			array( __CLASS__, 'render_main_page' ),
			'dashicons-admin-generic',
			65
		);

		// Add Dashboard as the first submenu.
		\add_submenu_page(
			$parent_slug,
			\__( 'Dashboard', 'functionalities' ),
			\__( 'Dashboard', 'functionalities' ),
			'manage_options',
			$parent_slug,
			array( __CLASS__, 'render_main_page' )
		);

		// Add submenus for top modules.
		$skip_submenus = array( 'misc', 'assumption-detection', 'login-security', 'block-cleanup' );

		foreach ( self::$modules as $slug => $module ) {
			if ( in_array( $slug, $skip_submenus, true ) ) {
				continue;
			}

			\add_submenu_page(
				$parent_slug,
				$module['title'] . ' ‹ ' . \__( 'Functionalities', 'functionalities' ),
				$module['title'],
				'manage_options',
				'functionalities-' . $slug,
				array( __CLASS__, 'render_main_page' )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) : void {
		if ( strpos( $hook, 'functionalities' ) === false ) {
			return;
		}

		$deps = array( 'jquery' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection doesn't require nonce.
		$page = isset( $_GET['page'] ) ? \sanitize_key( $_GET['page'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Module detection doesn't require nonce.
		$module = isset( $_GET['module'] ) ? \sanitize_key( $_GET['module'] ) : '';
		
		if ( 'task-manager' === $module || 'functionalities-task-manager' === $page ) {
			$deps[] = 'jquery-ui-sortable';
		}

		if ( 'pwa' === $module ) {
			\wp_enqueue_media();
			\wp_enqueue_style( 'wp-color-picker' );
			$deps[] = 'wp-color-picker';
		}

		\wp_enqueue_style(
			'functionalities-admin',
			FUNCTIONALITIES_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			FUNCTIONALITIES_VERSION
		);

		\wp_enqueue_script(
			'functionalities-admin',
			FUNCTIONALITIES_URL . 'assets/js/admin.js',
			$deps,
			FUNCTIONALITIES_VERSION,
			true
		);

		// Localize script with AJAX data.
		\wp_localize_script(
			'functionalities-admin',
			'functionalitiesAdmin',
			array(
				'ajaxUrl'            => \admin_url( 'admin-ajax.php' ),
				'runDetectionNonce'  => \wp_create_nonce( 'functionalities_run_detection' ),
				'runningText'        => \__( 'Running...', 'functionalities' ),
				'runDetectionText'   => \__( 'Run Detection Now', 'functionalities' ),
			)
		);
	}

	/**
	 * Render main admin page with module navigation.
	 *
	 * @return void
	 */
	public static function render_main_page() : void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'Insufficient permissions', 'functionalities' ) );
		}

		// Get current module from URL parameter or page slug.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page/module detection doesn't require nonce.
		$current_module = isset( $_GET['module'] ) ? \sanitize_key( $_GET['module'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection doesn't require nonce.
		$page = isset( $_GET['page'] ) ? \sanitize_key( $_GET['page'] ) : '';

		if ( empty( $current_module ) && strpos( $page, 'functionalities-' ) === 0 ) {
			$current_module = str_replace( 'functionalities-', '', $page );
		}

		// If no module or invalid module, show dashboard.
		if ( empty( $current_module ) || ! isset( self::$modules[ $current_module ] ) ) {
			self::render_dashboard();
			return;
		}

		// Render specific module.
		self::render_module( $current_module );
	}

	/**
	 * Render dashboard with module cards.
	 *
	 * @return void
	 */
	/**
	 * Check if a module is enabled.
	 *
	 * @param string $slug Module slug (hyphenated).
	 * @return bool
	 */
	private static function is_module_enabled( string $slug ) : bool {
		$option_name = 'functionalities_' . str_replace( '-', '_', $slug );
		$opts        = (array) \get_option( $option_name, array() );

		return ! empty( $opts['enabled'] );
	}

	private static function render_dashboard() : void {
		?>
		<div class="wrap functionalities-dashboard">
			<h1><?php echo \esc_html__( 'Dynamic Functionalities', 'functionalities' ); ?></h1>
			<p class="description">
				<?php echo \esc_html__( 'All-in-one WordPress optimization toolkit. 15+ modules for performance, security, SEO, and content management.', 'functionalities' ); ?>
			</p>

			<div class="functionalities-modules-grid">
				<?php foreach ( self::$modules as $slug => $module ) :
					$is_active = self::is_module_enabled( $slug );
				?>
					<div class="functionalities-module-card">
						<div class="module-card-header">
							<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
							<h2><?php echo \esc_html( $module['title'] ); ?></h2>
						</div>
						<p class="module-description"><?php echo \esc_html( $module['description'] ); ?></p>
						<div style="display:flex;align-items:center;gap:10px;">
							<a href="<?php echo \esc_url( self::get_module_url( $slug ) ); ?>" class="button button-primary">
								<?php echo \esc_html__( 'Configure', 'functionalities' ); ?>
							</a>
							<?php if ( $is_active ) : ?>
								<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#16a34a;"><span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;"></span><?php echo \esc_html__( 'Active', 'functionalities' ); ?></span>
							<?php else : ?>
								<span style="font-size:12px;color:#94a3b8;"><?php echo \esc_html__( 'Inactive', 'functionalities' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="functionalities-data-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
				<h2 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-database" style="font-size: 24px; width: 24px; height: 24px;"></span>
					<?php echo \esc_html__( 'Data Management', 'functionalities' ); ?>
				</h2>
				<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
					<input
						type="checkbox"
						id="functionalities-delete-data"
						<?php checked( \get_option( 'functionalities_delete_data_on_uninstall', false ) ); ?>
						style="margin-top: 2px;"
					>
					<span>
						<?php echo \esc_html__( 'Delete all plugin data when uninstalling', 'functionalities' ); ?>
						<br>
						<span style="color: #646970; font-size: 12px;">
							<?php echo \esc_html__( 'Removes all options, post metadata, transients, and files created by this plugin. This cannot be undone.', 'functionalities' ); ?>
						</span>
					</span>
				</label>
				<?php \wp_nonce_field( 'functionalities_delete_data_toggle', 'functionalities_delete_data_nonce' ); ?>
			</div>

			<div class="functionalities-help-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
				<h2 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-editor-help" style="font-size: 24px; width: 24px; height: 24px;"></span>
					<?php echo \esc_html__( 'Help & Support', 'functionalities' ); ?>
				</h2>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php echo \esc_html__( 'Need help with Dynamic Functionalities? Check out these resources:', 'functionalities' ); ?>
				</p>
				<div style="display: flex; flex-wrap: wrap; gap: 10px;">
					<a href="https://gauravtiwari.org/circle/course/functionalities-training/lessons" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-book" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Documentation', 'functionalities' ); ?>
					</a>
					<a href="https://wordpress.org/support/plugin/functionalities/" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-sos" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Support', 'functionalities' ); ?>
					</a>
					<a href="https://github.com/wpgaurav/functionalities/issues" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-flag" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Report Issues', 'functionalities' ); ?>
					</a>
				</div>
				<p style="color: #646970; margin-top: 15px; margin-bottom: 0; font-size: 12px;">
					<?php
					printf(
						/* translators: %1$s: Plugin version number, %2$s: Website link */
						\esc_html__( 'Dynamic Functionalities v%1$s | Visit %2$s for more information.', 'functionalities' ),
						\esc_html( FUNCTIONALITIES_VERSION ),
						'<a href="https://functionalities.dev" target="_blank" rel="noopener">functionalities.dev</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render specific module page.
	 *
	 * @param string $module_slug Module identifier.
	 * @return void
	 */
	private static function render_module( $module_slug ) : void {
		$module = self::$modules[ $module_slug ];

		// Handle custom page modules.
		if ( ! empty( $module['custom_page'] ) ) {
			$method = 'render_module_' . str_replace( '-', '_', $module_slug );
			if ( method_exists( __CLASS__, $method ) ) {
				call_user_func( array( __CLASS__, $method ), $module );
				return;
			}
		}

		?>
		<div class="wrap functionalities-module">
			<h1>
				<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
				<?php echo \esc_html( $module['title'] ); ?>
			</h1>

			<nav class="functionalities-breadcrumb">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>">
					<?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?>
				</a>
				<span class="separator">›</span>
				<span class="current"><?php echo \esc_html( $module['title'] ); ?></span>
			</nav>

			<form method="post" action="options.php">
				<?php
				$settings_group = 'functionalities_' . str_replace( '-', '_', $module_slug );
				\settings_fields( $settings_group );
				\do_settings_sections( $settings_group );
				\submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get module URL.
	 *
	 * @param string $module_slug Module identifier.
	 * @return string URL to module page.
	 */
	private static function get_module_url( $module_slug ) : string {
		return \admin_url( 'admin.php?page=functionalities&module=' . \rawurlencode( $module_slug ) );
	}

	public static function register_settings() : void {
		\register_setting(
			'functionalities_link_management',
			'functionalities_link_management',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_link_management' ],
				'default' => [
					'enabled' => false,
					'nofollow_external' => false,
					'exceptions' => '',
					'open_external_new_tab' => false,
					'open_internal_new_tab' => false,
					'internal_new_tab_exceptions' => '',
				],
			]
		);

		\add_settings_section(
			'functionalities_link_management_section',
			\__( 'Link Management Settings', 'functionalities' ),
			array( __CLASS__, 'section_link_management' ),
			'functionalities_link_management'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Link Management', 'functionalities' ),
			function() {
				$o = self::get_link_management_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_link_management[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable link management features', 'functionalities' ) . '</label>';
			},
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		\add_settings_field(
			'nofollow_external',
			\__( 'Add nofollow to external links', 'functionalities' ),
			[ __CLASS__, 'field_nofollow_external' ],
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		\add_settings_field(
			'exceptions',
			\__( 'Exceptions', 'functionalities' ),
			[ __CLASS__, 'field_exceptions' ],
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// New tab options.
		\add_settings_field(
			'open_external_new_tab',
			\__( 'Open external links in new tab', 'functionalities' ),
			array( __CLASS__, 'field_open_external_new_tab' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'open_internal_new_tab',
			\__( 'Open internal links in new tab', 'functionalities' ),
			array( __CLASS__, 'field_open_internal_new_tab' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'internal_new_tab_exceptions',
			\__( 'Internal new-tab exceptions (domains)', 'functionalities' ),
			array( __CLASS__, 'field_internal_new_tab_exceptions' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// Advanced features.
		\add_settings_field(
			'json_preset_url',
			\__( 'JSON Preset File Path', 'functionalities' ),
			array( __CLASS__, 'field_json_preset_url' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'enable_developer_filters',
			\__( 'Enable Developer Filters', 'functionalities' ),
			array( __CLASS__, 'field_enable_developer_filters' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'database_update_tool',
			\__( 'Database Update Tool', 'functionalities' ),
			array( __CLASS__, 'field_database_update_tool' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// Block Cleanup settings
		\register_setting(
			'functionalities_block_cleanup',
			'functionalities_block_cleanup',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_block_cleanup' ],
				'default' => [
					'enabled'                       => false,
					'remove_heading_block_class'    => false,
					'remove_list_block_class'       => false,
					'remove_image_block_class'      => false,
					'remove_paragraph_block_class'  => false,
					'remove_quote_block_class'      => false,
					'remove_table_block_class'      => false,
					'remove_separator_block_class'  => false,
					'remove_group_block_class'      => false,
					'remove_columns_block_class'    => false,
					'remove_button_block_class'     => false,
					'remove_cover_block_class'      => false,
					'remove_media_text_block_class' => false,
					'custom_classes_to_remove'      => '',
				],
			]
		);

		\add_settings_section(
			'functionalities_block_cleanup_section',
			\__( 'Frontend Block Class Cleanup', 'functionalities' ),
			array( __CLASS__, 'section_block_cleanup' ),
			'functionalities_block_cleanup'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Block Cleanup', 'functionalities' ),
			function() {
				$o = self::get_block_cleanup_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable block class cleanup on frontend', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);

		\add_settings_field(
			'remove_heading_block_class',
			\__( 'Headings', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_heading' ],
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_list_block_class',
			\__( 'Lists', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_list' ],
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_image_block_class',
			\__( 'Images', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_image' ],
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_paragraph_block_class',
			\__( 'Paragraphs', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_paragraph_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_paragraph_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-paragraph" from paragraph elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_quote_block_class',
			\__( 'Quotes', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_quote_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_quote_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-quote" from blockquote elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_table_block_class',
			\__( 'Tables', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_table_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_table_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-table" from table elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_separator_block_class',
			\__( 'Separators', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_separator_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_separator_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-separator" from hr/separator elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_group_block_class',
			\__( 'Groups', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_group_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_group_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-group" from group containers', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_columns_block_class',
			\__( 'Columns', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_columns_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_columns_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-columns" and "wp-block-column" from column layouts', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_button_block_class',
			\__( 'Buttons', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_button_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_button_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-button(s)" from button elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_cover_block_class',
			\__( 'Covers', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_cover_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_cover_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-cover" from cover blocks', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_media_text_block_class',
			\__( 'Media & Text', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_media_text_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_media_text_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-media-text" from media-text blocks', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'custom_classes_to_remove',
			\__( 'Custom Classes', 'functionalities' ),
			function() {
				$opts = self::get_block_cleanup_options();
				$val = isset( $opts['custom_classes_to_remove'] ) ? $opts['custom_classes_to_remove'] : '';
				echo '<textarea name="functionalities_block_cleanup[custom_classes_to_remove]" rows="4" cols="40" class="large-text code">' . \esc_textarea( $val ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Enter additional CSS classes to remove from content output (one per line). Example: my-plugin-class', 'functionalities' ) . '</p>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);

		// Editor Link Suggestions settings
		\register_setting(
			'functionalities_editor_links',
			'functionalities_editor_links',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_editor_links' ],
				'default' => [
					'enabled'      => false,
					'enable_limit' => false,
					'post_types'   => self::default_editor_link_post_types(),
				],
			]
		);

		\add_settings_section(
			'functionalities_editor_links_section',
			\__( 'Editor Link Suggestions', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Control which post types appear in the block editor link search suggestions.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Limits link search results to specific post types when inserting links in Gutenberg', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Reduces clutter by hiding unwanted content types from search results', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Works with posts, pages, and custom post types', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Enable limitation, then check only the post types you want to appear when searching for links in the editor. Unchecked post types will be hidden from link search results.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_editor_links_enabled</code> — ' . \esc_html__( 'toggle feature', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_editor_links_post_types</code> — ' . \esc_html__( 'modify allowed post types', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_editor_links'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Editor Link Suggestions', 'functionalities' ),
			function() {
				$o = self::get_editor_links_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_editor_links[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable editor link suggestion filtering', 'functionalities' ) . '</label>';
			},
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);

		\add_settings_field(
			'enable_limit',
			\__( 'Enable limitation', 'functionalities' ),
			[ __CLASS__, 'field_el_enable' ],
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);
		\add_settings_field(
			'post_types',
			\__( 'Allowed post types', 'functionalities' ),
			[ __CLASS__, 'field_el_post_types' ],
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);

		// Header & Footer (Snippets) settings
		\register_setting(
			'functionalities_snippets',
			'functionalities_snippets',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_snippets' ],
				'default' => [
					'enabled'    => false,
					'enable_ga4' => false,
					'ga4_id'     => '',
					'header'     => array(),
					'body_open'  => array(),
					'footer'     => array(),
				],
			]
		);

		\add_settings_section(
			'functionalities_snippets_section',
			\__( 'Header & Footer Code', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Insert custom code snippets into your site header and footer without editing theme files.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Native Google Analytics 4 integration - just enter your Measurement ID', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Multiple snippets per location — each independently toggleable', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Custom code for meta tags, scripts, styles, and tracking codes', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Automatically skips admin pages, feeds, and REST API requests', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Allowed Tags', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-family:monospace;font-size:12px;color:#78350f">';
				echo '&lt;script&gt;, &lt;style&gt;, &lt;link&gt;, &lt;meta&gt;, &lt;noscript&gt;';
				echo '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_output_enabled</code> — ' . \esc_html__( 'disable on specific pages', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_ga4_enabled</code> — ' . \esc_html__( 'control GA4 per user/page', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_header_code</code> — ' . \esc_html__( 'modify header code', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_footer_code</code> — ' . \esc_html__( 'modify footer code', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Actions:', 'functionalities' ) . ' <code>functionalities_before/after_header/footer_snippets</code>';
				echo '</p>';
				echo '</div>';
			},
			'functionalities_snippets'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Header & Footer', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable header and footer code injection', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);

		\add_settings_field(
			'enable_ga4',
			\__( 'Enable Google Analytics 4', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$checked = ! empty( $o['enable_ga4'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enable_ga4]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Insert GA4 gtag in head', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'ga4_id',
			\__( 'GA4 Measurement ID', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$val = isset( $o['ga4_id'] ) ? (string) $o['ga4_id'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_snippets[ga4_id]" value="' . \esc_attr( $val ) . '" placeholder="G-XXXXXXXXXX" />';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'header_snippets',
			\__( 'Header Snippets', 'functionalities' ),
			function() {
				self::field_snippets_repeater( 'header', 'wp_head' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'body_open_snippets',
			\__( 'Body Open Snippets', 'functionalities' ),
			function() {
				self::field_snippets_repeater( 'body_open', 'wp_body_open' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'footer_snippets',
			\__( 'Footer Snippets', 'functionalities' ),
			function() {
				self::field_snippets_repeater( 'footer', 'wp_footer' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);

		// Schema settings
		\register_setting(
			'functionalities_schema',
			'functionalities_schema',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_schema' ],
				'default' => [
					'enabled'             => false,
					'enable_site_schema'  => true,
					'site_itemtype'       => 'WebPage',
					'enable_header_part'  => true,
					'enable_footer_part'  => true,
					'enable_article'      => true,
					'article_itemtype'    => 'Article',
					'add_headline'        => true,
					'add_dates'           => true,
					'add_author'          => true,
					'enable_breadcrumbs'  => false,
				],
			]
		);

		\add_settings_section(
			'functionalities_schema_section',
			\__( 'Schema Settings', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Add Schema.org microdata attributes to improve search engine understanding of your content.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Adds itemscope/itemtype to the HTML element for page-level schema', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Wraps article content with Article/BlogPosting microdata', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Adds structured data for headlines, dates, and authors', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Marks header and footer regions with WPHeader/WPFooter types', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Supported Types', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">';
				echo '<strong>' . \esc_html__( 'Site:', 'functionalities' ) . '</strong> WebPage, AboutPage, ContactPage, Blog, SearchResultsPage<br>';
				echo '<strong>' . \esc_html__( 'Article:', 'functionalities' ) . '</strong> Article, BlogPosting, NewsArticle';
				echo '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_enabled</code> — ' . \esc_html__( 'toggle all schema output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_site_type</code> — ' . \esc_html__( 'modify site itemtype', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_article_type</code> — ' . \esc_html__( 'modify article itemtype', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_content</code> — ' . \esc_html__( 'modify wrapped content', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_schema'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Schema', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable schema microdata output', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);

		\add_settings_field(
			'enable_site_schema',
			\__( 'Enable site schema (html tag)', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_site_schema'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_site_schema]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemscope/itemtype to <html>', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'site_itemtype',
			\__( 'Site itemtype', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$val = $o['site_itemtype'] ?? 'WebPage';
				$opts = [ 'WebPage', 'AboutPage', 'ContactPage', 'Blog', 'SearchResultsPage' ];
				echo '<select name="functionalities_schema[site_itemtype]">';
				foreach ( $opts as $opt ) {
					$sel = selected( $val, $opt, false );
					echo '<option value="' . esc_attr( $opt ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_header_part',
			\__( 'Add WPHeader microdata', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_header_part'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_header_part]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output a microdata hasPart for header', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_footer_part',
			\__( 'Add WPFooter microdata', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_footer_part'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_footer_part]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output a microdata hasPart for footer', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_article',
			\__( 'Enable Article microdata in content', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_article'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_article]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Wrap content with Article microdata on singular', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'article_itemtype',
			\__( 'Article itemtype', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$val = $o['article_itemtype'] ?? 'Article';
				$opts = [ 'Article', 'BlogPosting', 'NewsArticle' ];
				echo '<select name="functionalities_schema[article_itemtype]">';
				foreach ( $opts as $opt ) {
					$sel = selected( $val, $opt, false );
					echo '<option value="' . esc_attr( $opt ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_headline',
			\__( 'Add headline from first heading', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['add_headline'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_headline]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop="headline"', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_dates',
			\__( 'Add published/modified dates', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['add_dates'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_dates]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop dates to time tags', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_author',
			\__( 'Add author microdata', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['add_author'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_author]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop="author" where possible', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_breadcrumbs',
			\__( 'Enable BreadcrumbList', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_breadcrumbs'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_breadcrumbs]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add BreadcrumbList JSON-LD to singular pages', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);

		// Components settings
		\register_setting(
			'functionalities_components',
			'functionalities_components',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_components' ],
				'default' => [
					'enabled' => false,
					'items'   => self::default_components(),
				],
			]
		);

		\add_settings_section(
			'functionalities_components_section',
			\__( 'Components', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Create reusable CSS components that are automatically loaded across your entire site.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Define CSS class names and their style rules in one place', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Components are compiled into a single CSS file for optimal caching', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Available on both frontend and admin pages', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Includes default utility components like visually-hidden, skip-link, and marquee', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Add components by entering a CSS selector (e.g., .my-button) and CSS rules (e.g., background: blue; color: white;). Use the grid below to manage your components.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_enabled</code> — ' . \esc_html__( 'toggle output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_items</code> — ' . \esc_html__( 'add components dynamically', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_css</code> — ' . \esc_html__( 'modify generated CSS', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Action:', 'functionalities' ) . ' <code>functionalities_components_updated</code> — ' . \esc_html__( 'fires when CSS file regenerates', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_components'
		);
		\add_settings_field(
			'enabled',
			\__( 'Enable components CSS', 'functionalities' ),
			function() {
				$o = self::get_components_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_components[enabled]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output components CSS on frontend', 'functionalities' ) . '</label>';
			},
			'functionalities_components',
			'functionalities_components_section'
		);
		\add_settings_field(
			'items',
			\__( 'Component list', 'functionalities' ),
			[ __CLASS__, 'field_components_items' ],
			'functionalities_components',
			'functionalities_components_section'
		);

		// Miscellaneous (bloat control)
		\register_setting(
			'functionalities_misc',
			'functionalities_misc',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_misc' ],
				'default' => [
					'enabled'                          => false,
					'disable_block_widgets'            => false,
					'load_separate_core_block_assets'  => false,
					'disable_emojis'                   => false,
					'disable_embeds'                   => false,
					'remove_rest_api_links_head'       => false,
					'remove_rsd_wlw_shortlink'         => false,
					'remove_generator_meta'            => false,
					'disable_xmlrpc'                   => false,
					'disable_xmlrpc_pingbacks'         => false,
					'disable_feeds'                    => false,
					'disable_gravatars'                => false,
					'disable_self_pingbacks'           => false,
					'remove_query_strings'             => false,
					'remove_dns_prefetch'              => false,
					'remove_recent_comments_css'       => false,
					'limit_revisions'                  => false,
					'disable_dashicons_for_guests'     => false,
					'disable_heartbeat'                => false,
					'disable_admin_bar_front'          => false,
					'remove_jquery_migrate'            => false,
					'enable_prism_admin'               => false,
					'enable_textarea_fullscreen'       => false,
				],
			]
		);

		\add_settings_section(
			'functionalities_misc_section',
			\__( 'Miscellaneous (Bloat Control)', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Remove unnecessary WordPress features to improve performance and security.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Remove bloat like emojis, oEmbeds, and unnecessary meta tags', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Disable security concerns like XML-RPC and version disclosure', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Improve performance by removing unused scripts and styles', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Add useful enhancements like PrismJS and fullscreen textareas', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Caution', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Some options may break functionality if plugins depend on them. Test after enabling. Disable Heartbeat API with care if you use auto-save or real-time features.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_options</code> — ' . \esc_html__( 'modify options before application', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_disable_emojis</code> — ' . \esc_html__( 'control emoji removal', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_disable_embeds</code> — ' . \esc_html__( 'control embed removal', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_misc'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Performance & Cleanup', 'functionalities' ),
			function() {
				$o = self::get_misc_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_misc[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable performance and cleanup features', 'functionalities' ) . '</label>';
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);

		self::add_misc_field( 'disable_block_widgets', \__( 'Disable block-based widget editor (use classic widgets)', 'functionalities' ) );
		self::add_misc_field( 'load_separate_core_block_assets', \__( 'Load core block styles separately (per-block CSS)', 'functionalities' ) );
		self::add_misc_field( 'disable_emojis', \__( 'Disable emojis scripts/styles', 'functionalities' ) );
		self::add_misc_field( 'disable_embeds', \__( 'Disable oEmbed scripts and endpoints', 'functionalities' ) );
		self::add_misc_field( 'remove_rest_api_links_head', \__( 'Remove REST API and oEmbed discovery links from <head>', 'functionalities' ) );
		self::add_misc_field( 'remove_rsd_wlw_shortlink', \__( 'Remove RSD, WLWManifest, and shortlink tags', 'functionalities' ) );
		self::add_misc_field( 'remove_generator_meta', \__( 'Remove WordPress version meta (generator)', 'functionalities' ) );
		self::add_misc_field( 'disable_xmlrpc', \__( 'Disable XML-RPC (complete)', 'functionalities' ) );
		self::add_misc_field( 'disable_xmlrpc_pingbacks', \__( 'Disable only XML-RPC Pingbacks', 'functionalities' ) );
		self::add_misc_field( 'disable_feeds', \__( 'Disable RSS/Atom feeds (redirect to homepage)', 'functionalities' ) );
		self::add_misc_field( 'disable_gravatars', \__( 'Disable Gravatars (site-wide)', 'functionalities' ) );
		self::add_misc_field( 'disable_self_pingbacks', \__( 'Disable self-pingbacks (pings to own site)', 'functionalities' ) );
		self::add_misc_field( 'remove_query_strings', \__( 'Remove query strings from static resources (?ver=)', 'functionalities' ) );
		self::add_misc_field( 'remove_dns_prefetch', \__( 'Remove DNS prefetch links from <head>', 'functionalities' ) );
		self::add_misc_field( 'remove_recent_comments_css', \__( 'Remove Recent Comments inline CSS', 'functionalities' ) );
		self::add_misc_field( 'limit_revisions', \__( 'Limit post revisions to 10', 'functionalities' ) );
		self::add_misc_field( 'disable_dashicons_for_guests', \__( 'Disable Dashicons on frontend for non-logged-in users', 'functionalities' ) );
		self::add_misc_field( 'disable_heartbeat', \__( 'Disable Heartbeat API', 'functionalities' ) );
		self::add_misc_field( 'disable_admin_bar_front', \__( 'Disable admin bar on the frontend', 'functionalities' ) );
		self::add_misc_field( 'remove_jquery_migrate', \__( 'Remove jQuery Migrate from frontend', 'functionalities' ) );
		self::add_misc_field( 'enable_prism_admin', \__( 'Load PrismJS on admin screens (code highlighting where applicable)', 'functionalities' ) );
		self::add_misc_field( 'enable_textarea_fullscreen', \__( 'Enable fullscreen toggle for all backend textareas', 'functionalities' ) );

		// Fonts settings
		\register_setting(
			'functionalities_fonts',
			'functionalities_fonts',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_fonts' ],
				'default' => [
					'enabled' => false,
					'items'   => [],
				],
			]
		);
		\add_settings_section(
			'functionalities_fonts_section',
			\__( 'Font Families', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Self-host custom fonts with automatic @font-face CSS generation.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Generate @font-face CSS rules for self-hosted fonts', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Support for variable fonts with weight ranges (e.g., 100 900)', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'WOFF2 format for modern browsers, optional WOFF fallback', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Configurable font-display strategy (swap, auto, block, etc.)', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Upload font files to your media library or server, then add font entries below with the family name and file URLs. Use the generated font-family name in your CSS.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_enabled</code> — ' . \esc_html__( 'toggle output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_items</code> — ' . \esc_html__( 'add fonts dynamically', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_css</code> — ' . \esc_html__( 'modify generated CSS', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Action:', 'functionalities' ) . ' <code>functionalities_fonts_before_output</code>';
				echo '</p>';
				echo '</div>';
			},
			'functionalities_fonts'
		);
		\add_settings_field(
			'fonts_enabled',
			\__( 'Enable fonts output', 'functionalities' ),
			function() {
				$o = self::get_fonts_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_fonts[enabled]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output @font-face CSS across site (front and admin)', 'functionalities' ) . '</label>';
			},
			'functionalities_fonts',
			'functionalities_fonts_section'
		);
		\add_settings_field(
			'fonts_items',
			\__( 'Families', 'functionalities' ),
			[ __CLASS__, 'field_fonts_items' ],
			'functionalities_fonts',
			'functionalities_fonts_section'
		);
		\add_settings_field(
			'fonts_assignments',
			\__( 'Typography Assignments', 'functionalities' ),
			[ __CLASS__, 'field_fonts_assignments' ],
			'functionalities_fonts',
			'functionalities_fonts_section'
		);

		// Login Security settings.
		\register_setting(
			'functionalities_login_security',
			'functionalities_login_security',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_login_security' ),
				'default'           => array(
					'enabled'                       => false,
					'limit_login_attempts'          => true,
					'max_attempts'                  => 5,
					'lockout_duration'              => 15,
					'disable_xmlrpc_auth'           => true,
					'disable_application_passwords' => false,
					'hide_login_errors'             => true,
					'custom_logo_url'               => '',
					'custom_background_color'       => '',
					'custom_form_background'        => '',
				),
			)
		);
		\add_settings_section(
			'functionalities_login_security_section',
			\__( 'Login Security Settings', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Protect your login page and customize its appearance.', 'functionalities' ) . '</p>';
				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'Security Features', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Limit failed login attempts to prevent brute force attacks', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Disable XML-RPC authentication to block remote login attacks', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Hide specific login errors to prevent username enumeration', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Customize the login page with your logo and colors', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';
				$logs = \Functionalities\Features\Login_Security::get_lockout_log( 5 );
				if ( ! empty( $logs ) ) {
					echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
					echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Recent Lockouts', 'functionalities' ) . '</h4>';
					echo '<ul style="margin:0;padding-left:20px;font-size:13px">';
					foreach ( $logs as $log ) {
						echo '<li><strong>' . \esc_html( $log['ip'] ) . '</strong> — ' . \esc_html( $log['username'] ) . ' (' . \esc_html( $log['time'] ) . ')</li>';
					}
					echo '</ul>';
					echo '</div>';
				}
			},
			'functionalities_login_security'
		);
		\add_settings_field( 'login_enabled', \__( 'Enable Login Security', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<label><input type="checkbox" name="functionalities_login_security[enabled]" value="1" ' . checked( ! empty( $o['enabled'] ), true, false ) . '> ' . \esc_html__( 'Enable login security features', 'functionalities' ) . '</label>';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'limit_login_attempts', \__( 'Limit Login Attempts', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<label><input type="checkbox" name="functionalities_login_security[limit_login_attempts]" value="1" ' . checked( ! empty( $o['limit_login_attempts'] ), true, false ) . '> ' . \esc_html__( 'Block IPs after too many failed attempts', 'functionalities' ) . '</label>';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'max_attempts', \__( 'Max Attempts', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<input type="number" name="functionalities_login_security[max_attempts]" value="' . \esc_attr( $o['max_attempts'] ?? 5 ) . '" min="1" max="20" class="small-text"> ' . \esc_html__( 'failed attempts before lockout', 'functionalities' );
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'lockout_duration', \__( 'Lockout Duration', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<input type="number" name="functionalities_login_security[lockout_duration]" value="' . \esc_attr( $o['lockout_duration'] ?? 15 ) . '" min="1" max="1440" class="small-text"> ' . \esc_html__( 'minutes', 'functionalities' );
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'disable_xmlrpc_auth', \__( 'Disable XML-RPC', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<label><input type="checkbox" name="functionalities_login_security[disable_xmlrpc_auth]" value="1" ' . checked( ! empty( $o['disable_xmlrpc_auth'] ), true, false ) . '> ' . \esc_html__( 'Disable XML-RPC authentication (recommended)', 'functionalities' ) . '</label>';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'disable_application_passwords', \__( 'Disable App Passwords', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<label><input type="checkbox" name="functionalities_login_security[disable_application_passwords]" value="1" ' . checked( ! empty( $o['disable_application_passwords'] ), true, false ) . '> ' . \esc_html__( 'Disable WordPress Application Passwords', 'functionalities' ) . '</label>';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'hide_login_errors', \__( 'Hide Login Errors', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<label><input type="checkbox" name="functionalities_login_security[hide_login_errors]" value="1" ' . checked( ! empty( $o['hide_login_errors'] ), true, false ) . '> ' . \esc_html__( 'Show generic error instead of specific username/password errors', 'functionalities' ) . '</label>';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'custom_logo_url', \__( 'Custom Logo URL', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<input type="url" name="functionalities_login_security[custom_logo_url]" value="' . \esc_attr( $o['custom_logo_url'] ?? '' ) . '" class="regular-text" placeholder="https://example.com/logo.png">';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );
		\add_settings_field( 'custom_background_color', \__( 'Background Color', 'functionalities' ), function() {
			$o = self::get_login_security_options();
			echo '<input type="text" name="functionalities_login_security[custom_background_color]" value="' . \esc_attr( $o['custom_background_color'] ?? '' ) . '" class="small-text" placeholder="#f0f0f1">';
		}, 'functionalities_login_security', 'functionalities_login_security_section' );

		// Meta & Copyright settings.
		\register_setting(
			'functionalities_meta',
			'functionalities_meta',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
				'default'           => array(
					'enabled'                   => false,
					'enable_copyright_meta'     => true,
					'enable_dublin_core'        => true,
					'enable_license_metabox'    => true,
					'enable_schema_integration' => true,
					'default_license'           => 'all-rights-reserved',
					'default_license_url'       => '',
					'post_types'                => array( 'post' ),
					'copyright_holder_type'     => 'author',
					'custom_copyright_holder'   => '',
					'dc_language'               => '',
				),
			)
		);

		\add_settings_section(
			'functionalities_meta_section',
			\__( 'Meta & Copyright Settings', 'functionalities' ),
			array( __CLASS__, 'section_meta' ),
			'functionalities_meta'
		);

		\add_settings_field(
			'meta_enabled',
			\__( 'Enable Meta Module', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable copyright, Dublin Core, and licensing features', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_copyright_meta',
			\__( 'Copyright Meta Tags', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$checked = ! empty( $o['enable_copyright_meta'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_copyright_meta]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Output copyright, author, owner, and rights meta tags', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_dublin_core',
			\__( 'Dublin Core (DCMI) Metadata', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$checked = ! empty( $o['enable_dublin_core'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_dublin_core]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Output Dublin Core metadata (DC.title, DC.creator, DC.rights, etc.)', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_license_metabox',
			\__( 'License Metabox', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$checked = ! empty( $o['enable_license_metabox'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_license_metabox]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Show license selection metabox in post editor', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_schema_integration',
			\__( 'SEO Plugin Schema Integration', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$checked = ! empty( $o['enable_schema_integration'] ) ? 'checked' : '';
				$detected = \Functionalities\Features\Meta::detect_seo_plugin();
				$plugin_names = array(
					'rank-math'     => 'Rank Math',
					'yoast'         => 'Yoast SEO',
					'seo-framework' => 'The SEO Framework',
					'seopress'      => 'SEOPress',
					'aioseo'        => 'All in One SEO',
					'none'          => \__( 'None detected', 'functionalities' ),
				);
				echo '<label><input type="checkbox" name="functionalities_meta[enable_schema_integration]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Add copyright data to SEO plugin Schema.org output', 'functionalities' ) . '</label>';
				echo '<p class="description" style="margin-top:4px">';
				if ( $detected !== 'none' ) {
					echo '<span style="color:#059669">✓ ' . \esc_html__( 'Detected:', 'functionalities' ) . ' <strong>' . \esc_html( $plugin_names[ $detected ] ) . '</strong></span>';
				} else {
					echo \esc_html__( 'Supports: Rank Math, Yoast SEO, The SEO Framework, SEOPress, AIOSEO', 'functionalities' );
				}
				echo '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'default_license',
			\__( 'Default License', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$val = isset( $o['default_license'] ) ? (string) $o['default_license'] : 'all-rights-reserved';
				$licenses = array(
					'all-rights-reserved' => \__( 'All Rights Reserved', 'functionalities' ),
					'cc-by'               => 'CC BY 4.0',
					'cc-by-sa'            => 'CC BY-SA 4.0',
					'cc-by-nc'            => 'CC BY-NC 4.0',
					'cc-by-nc-sa'         => 'CC BY-NC-SA 4.0',
					'cc-by-nd'            => 'CC BY-ND 4.0',
					'cc-by-nc-nd'         => 'CC BY-NC-ND 4.0',
					'cc0'                 => 'CC0 1.0 (Public Domain)',
				);
				echo '<select name="functionalities_meta[default_license]">';
				foreach ( $licenses as $key => $label ) {
					$sel = selected( $val, $key, false );
					echo '<option value="' . \esc_attr( $key ) . '" ' . esc_attr( $sel ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Default license for new posts (can be overridden per-post).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'default_license_url',
			\__( 'Custom License URL', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$val = isset( $o['default_license_url'] ) ? (string) $o['default_license_url'] : '';
				echo '<input type="url" class="regular-text" name="functionalities_meta[default_license_url]" value="' . \esc_attr( $val ) . '" placeholder="https://example.com/terms/" />';
				echo '<p class="description">' . \esc_html__( 'Custom URL for "All Rights Reserved" license (e.g., your terms/disclaimer page).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'meta_post_types',
			\__( 'Post Types', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$selected = isset( $o['post_types'] ) && is_array( $o['post_types'] ) ? $o['post_types'] : array( 'post' );
				$pts = \get_post_types( array( 'public' => true ), 'objects' );
				echo '<fieldset>';
				foreach ( $pts as $name => $obj ) {
					$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
					$label = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_meta[post_types][]" value="' . \esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . \esc_html( $label ) . '</label>';
				}
				echo '</fieldset>';
				echo '<p class="description">' . \esc_html__( 'Select post types where meta tags and license metabox should appear.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'copyright_holder_type',
			\__( 'Copyright Holder', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$val = isset( $o['copyright_holder_type'] ) ? (string) $o['copyright_holder_type'] : 'author';
				$options = array(
					'author' => \__( 'Post Author', 'functionalities' ),
					'site'   => \__( 'Site Name', 'functionalities' ),
					'custom' => \__( 'Custom Name', 'functionalities' ),
				);
				echo '<select name="functionalities_meta[copyright_holder_type]" id="meta_copyright_holder_type">';
				foreach ( $options as $key => $label ) {
					$sel = selected( $val, $key, false );
					echo '<option value="' . \esc_attr( $key ) . '" ' . esc_attr( $sel ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Who should be listed as the copyright holder.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'custom_copyright_holder',
			\__( 'Custom Copyright Holder Name', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$val = isset( $o['custom_copyright_holder'] ) ? (string) $o['custom_copyright_holder'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_meta[custom_copyright_holder]" value="' . \esc_attr( $val ) . '" placeholder="' . \esc_attr__( 'Company Name or Person', 'functionalities' ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Used when "Custom Name" is selected above.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'dc_language',
			\__( 'Dublin Core Language', 'functionalities' ),
			function() {
				$o = self::get_meta_options();
				$val = isset( $o['dc_language'] ) ? (string) $o['dc_language'] : '';
				$site_lang = \get_bloginfo( 'language' );
				echo '<input type="text" class="small-text" name="functionalities_meta[dc_language]" value="' . \esc_attr( $val ) . '" placeholder="' . \esc_attr( $site_lang ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Leave empty to use site language. Use ISO 639 codes (en, en-US, de, etc.).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		// Content Regression Detection settings.
		\register_setting(
			'functionalities_content_regression',
			'functionalities_content_regression',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_content_regression' ),
				'default'           => array(
					'enabled'                     => false,
					'post_types'                  => array( 'post', 'page' ),
					'link_drop_enabled'           => true,
					'link_drop_percent'           => 30,
					'link_drop_absolute'          => 3,
					'exclude_nofollow_links'      => false,
					'word_count_enabled'          => true,
					'word_count_drop_percent'     => 35,
					'word_count_min_age_days'     => 30,
					'word_count_compare_average'  => false,
					'exclude_shortcodes'          => false,
					'heading_enabled'             => true,
					'detect_missing_h1'           => true,
					'detect_multiple_h1'          => true,
					'detect_skipped_levels'       => true,
					'snapshot_rolling_count'      => 5,
					'show_post_column'            => true,
				),
			)
		);

		\add_settings_section(
			'functionalities_content_regression_section',
			\__( 'Content Integrity Settings', 'functionalities' ),
			array( __CLASS__, 'section_content_regression' ),
			'functionalities_content_regression'
		);

		// Enable module.
		\add_settings_field(
			'regression_enabled',
			\__( 'Enable Content Integrity', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Detect structural regressions when posts are updated', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Post types.
		\add_settings_field(
			'regression_post_types',
			\__( 'Post Types', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$selected = isset( $o['post_types'] ) && is_array( $o['post_types'] ) ? $o['post_types'] : array( 'post', 'page' );
				$pts = \get_post_types( array( 'public' => true ), 'objects' );
				echo '<fieldset>';
				foreach ( $pts as $name => $obj ) {
					if ( 'attachment' === $name ) {
						continue;
					}
					$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
					$label = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_content_regression[post_types][]" value="' . \esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . \esc_html( $label ) . '</label>';
				}
				echo '</fieldset>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Link drop detection header.
		\add_settings_field(
			'link_drop_header',
			'<strong>' . \__( 'Internal Link Detection', 'functionalities' ) . '</strong>',
			function() {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect when internal links are removed from content.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_enabled',
			\__( 'Enable Link Detection', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['link_drop_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[link_drop_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when internal links drop significantly', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_percent',
			\__( 'Link Drop Threshold (%)', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$val = isset( $o['link_drop_percent'] ) ? (int) $o['link_drop_percent'] : 30;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[link_drop_percent]" value="' . \esc_attr( $val ) . '" /> %';
				echo '<p class="description">' . \esc_html__( 'Warn if internal links drop by this percentage or more.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_absolute',
			\__( 'Link Drop Absolute Threshold', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$val = isset( $o['link_drop_absolute'] ) ? (int) $o['link_drop_absolute'] : 3;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[link_drop_absolute]" value="' . \esc_attr( $val ) . '" /> ' . \esc_html__( 'links', 'functionalities' );
				echo '<p class="description">' . \esc_html__( 'Also warn if this many links are removed (whichever triggers first).', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'exclude_nofollow_links',
			\__( 'Exclude Nofollow Links', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['exclude_nofollow_links'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_nofollow_links]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Do not count links with rel="nofollow"', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Word count detection header.
		\add_settings_field(
			'word_count_header',
			'<strong>' . \__( 'Word Count Detection', 'functionalities' ) . '</strong>',
			function() {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect when content is shortened significantly.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_enabled',
			\__( 'Enable Word Count Detection', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['word_count_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[word_count_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when word count drops significantly', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_drop_percent',
			\__( 'Word Count Drop Threshold (%)', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$val = isset( $o['word_count_drop_percent'] ) ? (int) $o['word_count_drop_percent'] : 35;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[word_count_drop_percent]" value="' . \esc_attr( $val ) . '" /> %';
				echo '<p class="description">' . \esc_html__( 'Warn if word count drops by this percentage.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_min_age_days',
			\__( 'Minimum Post Age (days)', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$val = isset( $o['word_count_min_age_days'] ) ? (int) $o['word_count_min_age_days'] : 30;
				echo '<input type="number" min="0" max="365" class="small-text" name="functionalities_content_regression[word_count_min_age_days]" value="' . \esc_attr( $val ) . '" /> ' . \esc_html__( 'days', 'functionalities' );
				echo '<p class="description">' . \esc_html__( 'Only check word count for posts older than this (to avoid alerts on new posts).', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'exclude_shortcodes',
			\__( 'Exclude Shortcodes', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['exclude_shortcodes'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_shortcodes]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove shortcode content from word count', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Heading detection header.
		\add_settings_field(
			'heading_header',
			'<strong>' . \__( 'Heading Structure Detection', 'functionalities' ) . '</strong>',
			function() {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect accessibility issues with heading hierarchy.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'heading_enabled',
			\__( 'Enable Heading Detection', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['heading_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[heading_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Check heading structure for accessibility issues', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_missing_h1',
			\__( 'Detect Missing H1', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['detect_missing_h1'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_missing_h1]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when no H1 heading is present', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_multiple_h1',
			\__( 'Detect Multiple H1s', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['detect_multiple_h1'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_multiple_h1]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple H1 headings exist', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_skipped_levels',
			\__( 'Detect Skipped Levels', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['detect_skipped_levels'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_skipped_levels]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when heading levels are skipped (e.g., H2 to H4)', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Advanced settings header.
		\add_settings_field(
			'advanced_header',
			'<strong>' . \__( 'Advanced Settings', 'functionalities' ) . '</strong>',
			function() {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Configure snapshot and display options.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'snapshot_rolling_count',
			\__( 'Snapshots to Keep', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$val = isset( $o['snapshot_rolling_count'] ) ? (int) $o['snapshot_rolling_count'] : 5;
				echo '<input type="number" min="1" max="20" class="small-text" name="functionalities_content_regression[snapshot_rolling_count]" value="' . \esc_attr( $val ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Number of historical snapshots to retain per post.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'show_post_column',
			\__( 'Show Post List Column', 'functionalities' ),
			function() {
				$o = self::get_content_regression_options();
				$checked = ! empty( $o['show_post_column'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[show_post_column]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Display integrity status icon in post list table', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Assumption Detection settings.
		\register_setting(
			'functionalities_assumption_detection',
			'functionalities_assumption_detection',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_assumption_detection' ),
				'default'           => array(
					'enabled'                       => false,
					'detect_schema_collision'       => true,
					'detect_analytics_dupe'         => true,
					'detect_font_redundancy'        => true,
					'detect_inline_css_growth'      => true,
					'inline_css_threshold_kb'       => 50,
					'detect_jquery_conflicts'       => true,
					'detect_meta_duplication'       => true,
					'detect_rest_exposure'          => true,
					'detect_lazy_load_conflict'     => true,
					'detect_mixed_content'          => true,
					'detect_missing_security_headers' => true,
					'detect_debug_exposure'         => true,
					'detect_cron_issues'            => true,
				),
			)
		);

		\add_settings_section(
			'functionalities_assumption_detection_section',
			\__( 'Assumption Detection Settings', 'functionalities' ),
			array( __CLASS__, 'section_assumption_detection' ),
			'functionalities_assumption_detection'
		);

		\add_settings_field(
			'assumption_enabled',
			\__( 'Enable Assumption Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Detect when implicit site assumptions stop being true', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_schema_collision',
			\__( 'Schema Collision Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_schema_collision'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_schema_collision]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple sources output the same schema type', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_analytics_dupe',
			\__( 'Analytics Duplication Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_analytics_dupe'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_analytics_dupe]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when analytics scripts load multiple times', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_font_redundancy',
			\__( 'Font Redundancy Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_font_redundancy'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_font_redundancy]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when fonts load from multiple sources', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_inline_css_growth',
			\__( 'Inline CSS Growth Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_inline_css_growth'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_inline_css_growth]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Track inline CSS size over time', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'inline_css_threshold_kb',
			\__( 'Inline CSS Threshold (KB)', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$val = isset( $o['inline_css_threshold_kb'] ) ? (int) $o['inline_css_threshold_kb'] : 50;
				echo '<input type="number" min="10" max="500" class="small-text" name="functionalities_assumption_detection[inline_css_threshold_kb]" value="' . \esc_attr( $val ) . '" /> KB';
				echo '<p class="description">' . \esc_html__( 'Warn when inline CSS exceeds this size.', 'functionalities' ) . '</p>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_jquery_conflicts',
			\__( 'jQuery Conflict Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_jquery_conflicts'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_jquery_conflicts]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple jQuery versions or sources are loaded', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_meta_duplication',
			\__( 'Meta Tag Duplication Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_meta_duplication'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_meta_duplication]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when duplicate meta tags are detected (viewport, robots, OG tags)', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_rest_exposure',
			\__( 'REST API Exposure Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_rest_exposure'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_rest_exposure]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when REST API exposes user information publicly', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_lazy_load_conflict',
			\__( 'Lazy Loading Conflict Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_lazy_load_conflict'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_lazy_load_conflict]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple lazy loading implementations are detected', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_mixed_content',
			\__( 'Mixed Content Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_mixed_content'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_mixed_content]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when HTTP resources are loaded on HTTPS pages', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_missing_security_headers',
			\__( 'Security Headers Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_missing_security_headers'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_missing_security_headers]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when critical security headers are missing', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_debug_exposure',
			\__( 'Debug Mode Exposure', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_debug_exposure'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_debug_exposure]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when WP_DEBUG or error display is enabled in production', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_cron_issues',
			\__( 'Cron Issues Detection', 'functionalities' ),
			function() {
				$o = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_cron_issues'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_cron_issues]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when WP-Cron is disabled or has stuck jobs', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		// Explicit save button field - ensures visibility.
		\add_settings_field(
			'assumption_save_settings',
			'',
			function() {
				echo '<div class="functionalities-settings-actions" style="padding: 15px 0; border-top: 1px solid #c3c4c7; margin-top: 10px;">';
				\submit_button( \__( 'Save Settings', 'functionalities' ), 'primary', 'submit', false );
				echo ' ';
				echo '<button type="button" class="button button-secondary" id="functionalities-run-detection" style="margin-left: 10px;">';
				echo \esc_html__( 'Run Detection Now', 'functionalities' );
				echo '</button>';
				echo '</div>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detected_assumptions',
			\__( 'Detected Assumptions', 'functionalities' ),
			array( __CLASS__, 'field_detected_assumptions' ),
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		// PWA settings.
		\register_setting(
			'functionalities_pwa',
			'functionalities_pwa',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_pwa' ),
				'default'           => array(
					'enabled' => false,
				),
			)
		);

		\add_settings_section(
			'functionalities_pwa_identity',
			\__( 'App Identity', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Basic information about your progressive web app.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_enabled',
			\__( 'Enable PWA', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable Progressive Web App features', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_app_name',
			\__( 'App Name', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[app_name]" value="' . \esc_attr( $o['app_name'] ) . '" class="regular-text">';
				echo '<p class="description">' . \esc_html__( 'Full name displayed on install. Leave blank to use site title.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_short_name',
			\__( 'Short Name', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[short_name]" value="' . \esc_attr( $o['short_name'] ) . '" class="regular-text" maxlength="12">';
				echo '<p class="description">' . \esc_html__( 'Short name for the home screen (12 chars max).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_description',
			\__( 'Description', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[description]" rows="3" class="large-text">' . \esc_textarea( $o['description'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Shown in app stores and install dialogs. Leave blank to use site tagline.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_start_url',
			\__( 'Start URL', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[start_url]" value="' . \esc_attr( $o['start_url'] ) . '" class="regular-text" placeholder="/">';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_scope',
			\__( 'Scope', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[scope]" value="' . \esc_attr( $o['scope'] ) . '" class="regular-text" placeholder="/">';
				echo '<p class="description">' . \esc_html__( 'Navigation scope. "/" means the entire site.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_categories',
			\__( 'Categories', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[categories]" value="' . \esc_attr( $o['categories'] ) . '" class="regular-text">';
				echo '<p class="description">' . \esc_html__( 'Comma-separated W3C categories (e.g. news, education, business).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_display',
			\__( 'Display Mode', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$modes = array( 'standalone', 'fullscreen', 'minimal-ui', 'browser' );
				echo '<select name="functionalities_pwa[display]">';
				foreach ( $modes as $mode ) {
					echo '<option value="' . \esc_attr( $mode ) . '"' . \selected( $o['display'], $mode, false ) . '>' . \esc_html( $mode ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_orientation',
			\__( 'Orientation', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$orientations = array( 'any', 'portrait', 'landscape', 'portrait-primary', 'landscape-primary' );
				echo '<select name="functionalities_pwa[orientation]">';
				foreach ( $orientations as $orient ) {
					echo '<option value="' . \esc_attr( $orient ) . '"' . \selected( $o['orientation'], $orient, false ) . '>' . \esc_html( $orient ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		// Appearance section.
		\add_settings_section(
			'functionalities_pwa_appearance',
			\__( 'Appearance & Icons', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Theme colors and app icons. Icons must be square PNG files.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_theme_color',
			\__( 'Theme Color', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[theme_color]" value="' . \esc_attr( $o['theme_color'] ) . '" class="func-color-field" data-default-color="#4f46e5">';
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_background_color',
			\__( 'Background Color', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[background_color]" value="' . \esc_attr( $o['background_color'] ) . '" class="func-color-field" data-default-color="#ffffff">';
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_icon_512',
			\__( 'Icon 512x512', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[icon_512]', $o['icon_512'], \__( 'Primary app icon (required for installability).', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_icon_192',
			\__( 'Icon 192x192', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[icon_192]', $o['icon_192'], \__( 'Smaller icon for home screen and splash.', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_maskable_icon_512',
			\__( 'Maskable Icon 512x512', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[maskable_icon_512]', $o['maskable_icon_512'], \__( 'Maskable icon for adaptive icon support (safe zone padding recommended).', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_maskable_icon_192',
			\__( 'Maskable Icon 192x192', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[maskable_icon_192]', $o['maskable_icon_192'], \__( 'Smaller maskable icon.', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		// Install Prompt section.
		\add_settings_section(
			'functionalities_pwa_prompt',
			\__( 'Install Prompt', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Customize the in-page install prompt shown to visitors.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_install_prompt',
			\__( 'Show Install Prompt', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$checked = ! empty( $o['install_prompt'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[install_prompt]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Display a custom install prompt to visitors', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_title',
			\__( 'Prompt Title', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_title]" value="' . \esc_attr( $o['prompt_title'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Install App', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_text',
			\__( 'Prompt Text', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_text]" value="' . \esc_attr( $o['prompt_text'] ) . '" class="large-text" placeholder="' . \esc_attr__( 'Add this site to your home screen for quick access.', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_button',
			\__( 'Button Text', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_button]" value="' . \esc_attr( $o['prompt_button'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Install', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_dismiss',
			\__( 'Dismiss Text', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_dismiss]" value="' . \esc_attr( $o['prompt_dismiss'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Not now', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_position',
			\__( 'Position', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$positions = array(
					'bottom' => \__( 'Bottom', 'functionalities' ),
					'top'    => \__( 'Top', 'functionalities' ),
					'center' => \__( 'Center (modal)', 'functionalities' ),
				);
				echo '<select name="functionalities_pwa[prompt_position]">';
				foreach ( $positions as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['prompt_position'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_style',
			\__( 'Style', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$styles = array(
					'banner' => \__( 'Banner', 'functionalities' ),
					'card'   => \__( 'Card', 'functionalities' ),
				);
				echo '<select name="functionalities_pwa[prompt_style]">';
				foreach ( $styles as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['prompt_style'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_frequency',
			\__( 'Re-show After (days)', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="number" name="functionalities_pwa[prompt_frequency]" value="' . \esc_attr( $o['prompt_frequency'] ) . '" min="1" max="365" class="small-text">';
				echo '<p class="description">' . \esc_html__( 'Days to wait before showing prompt again after dismissal.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		// Offline & Caching section.
		\add_settings_section(
			'functionalities_pwa_caching',
			\__( 'Offline & Caching', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Service worker caching and offline behavior.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_cache_version',
			\__( 'Cache Version', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[cache_version]" value="' . \esc_attr( $o['cache_version'] ) . '" class="small-text" placeholder="v1">';
				echo '<p class="description">' . \esc_html__( 'Increment to force cache refresh (e.g. v1, v2).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_caching'
		);

		\add_settings_field(
			'pwa_precache_urls',
			\__( 'Precache URLs', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[precache_urls]" rows="4" class="large-text" placeholder="/about/&#10;/contact/">' . \esc_textarea( $o['precache_urls'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'One URL per line. These pages will be cached immediately when the service worker installs.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_caching'
		);

		// Shortcuts section.
		\add_settings_section(
			'functionalities_pwa_shortcuts',
			\__( 'Shortcuts', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'App shortcut links shown on long-press of the app icon. Up to 4 shortcuts.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_shortcuts',
			\__( 'Shortcuts', 'functionalities' ),
			array( __CLASS__, 'field_pwa_shortcuts' ),
			'functionalities_pwa',
			'functionalities_pwa_shortcuts'
		);

		// Screenshots section.
		\add_settings_section(
			'functionalities_pwa_screenshots',
			\__( 'Screenshots', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'App screenshots shown in install dialogs and app stores. Provide wide and narrow sizes.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_screenshots',
			\__( 'Screenshots', 'functionalities' ),
			array( __CLASS__, 'field_pwa_screenshots' ),
			'functionalities_pwa',
			'functionalities_pwa_screenshots'
		);

		// Advanced section.
		\add_settings_section(
			'functionalities_pwa_advanced',
			\__( 'Advanced', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Advanced PWA features. Only change these if you know what you\'re doing.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_display_override',
			\__( 'Display Override', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$checked = ! empty( $o['display_override'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[display_override]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable display_override with window-controls-overlay fallback chain', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_edge_side_panel',
			\__( 'Edge Side Panel', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$checked = ! empty( $o['edge_side_panel'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[edge_side_panel]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Allow opening in Microsoft Edge side panel', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_launch_handler',
			\__( 'Launch Handler', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$handlers = array(
					''                => \__( 'Default', 'functionalities' ),
					'focus-existing'  => 'focus-existing',
					'navigate-new'    => 'navigate-new',
					'navigate-existing' => 'navigate-existing',
				);
				echo '<select name="functionalities_pwa[launch_handler]">';
				foreach ( $handlers as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['launch_handler'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Controls behavior when the app is already open and a new navigation is triggered.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_share_target',
			\__( 'Share Target', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				$checked = ! empty( $o['share_target_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[share_target_enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Register as a Web Share Target', 'functionalities' ) . '</label>';
				echo '<div style="margin-top:10px">';
				echo '<label>' . \esc_html__( 'Action URL:', 'functionalities' ) . ' ';
				echo '<input type="text" name="functionalities_pwa[share_target_action]" value="' . \esc_attr( $o['share_target_action'] ) . '" class="regular-text" placeholder="/?shared=1"></label>';
				echo '</div>';
				$methods = array( 'GET' => 'GET', 'POST' => 'POST' );
				echo '<div style="margin-top:5px">';
				echo '<label>' . \esc_html__( 'Method:', 'functionalities' ) . ' ';
				echo '<select name="functionalities_pwa[share_target_method]">';
				foreach ( $methods as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['share_target_method'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select></label>';
				echo '</div>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_advanced_manifest',
			\__( 'Custom Manifest JSON', 'functionalities' ),
			function() {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[advanced_manifest]" rows="6" class="large-text code" placeholder=\'{"key": "value"}\'>' . \esc_textarea( $o['advanced_manifest'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Raw JSON to merge into the manifest. Must be a valid JSON object.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);
	}

	public static function field_nofollow_external() : void {
		$opts = self::get_link_management_options();
		$checked = ! empty( $opts['nofollow_external'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[nofollow_external]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Enable rel="nofollow" for all external links', 'functionalities' ) . '</label>';
	}

	/**
	 * Render exceptions field.
	 *
	 * @return void
	 */
	public static function field_exceptions() : void {
		$opts  = self::get_link_management_options();
		$value = isset( $opts['exceptions'] ) ? (string) $opts['exceptions'] : '';
		echo '<textarea name="functionalities_link_management[exceptions]" rows="6" cols="60" class="large-text code">' . \esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . \esc_html__( 'One per line. Supports full URLs (https://example.com/page), domains (example.com), or partial matches (e.g., /partner/).', 'functionalities' ) . '</p>';
	}

	/**
	 * Render section description for link management.
	 *
	 * @return void
	 */
	public static function section_link_management() : void {
		echo '<p>' . \esc_html__( 'Control how external and internal links are handled across your site.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'link-management' );

		// Render documentation accordions.
		echo '<div class="functionalities-module-docs">';

		if ( ! empty( $docs['features'] ) ) {
			$list = '<ul>';
			foreach ( $docs['features'] as $feature ) {
				$list .= '<li>' . \esc_html( $feature ) . '</li>';
			}
			$list .= '</ul>';
			Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
		}

		if ( ! empty( $docs['hooks'] ) ) {
			$hooks_html = '<dl class="functionalities-hooks-list">';
			foreach ( $docs['hooks'] as $hook ) {
				$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
				$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
			}
			$hooks_html .= '</dl>';
			Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
		}

		echo '</div>';
	}

	/**
	 * Render open external links in new tab field.
	 *
	 * @return void
	 */
	public static function field_open_external_new_tab() : void {
		$opts    = self::get_link_management_options();
		$checked = ! empty( $opts['open_external_new_tab'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[open_external_new_tab]" value="1" ' . esc_attr( $checked ) . '> ';
		echo \esc_html__( 'Adds target="_blank" and rel="noopener" to external links', 'functionalities' ) . '</label>';
	}

	/**
	 * Render open internal links in new tab field.
	 *
	 * @return void
	 */
	public static function field_open_internal_new_tab() : void {
		$opts    = self::get_link_management_options();
		$checked = ! empty( $opts['open_internal_new_tab'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[open_internal_new_tab]" value="1" ' . esc_attr( $checked ) . '> ';
		echo \esc_html__( 'Adds target="_blank" to same-domain links (see exceptions)', 'functionalities' ) . '</label>';
	}

	/**
	 * Render internal new tab exceptions field.
	 *
	 * @return void
	 */
	public static function field_internal_new_tab_exceptions() : void {
		$opts = self::get_link_management_options();
		$val  = isset( $opts['internal_new_tab_exceptions'] ) ? (string) $opts['internal_new_tab_exceptions'] : '';
		echo '<textarea name="functionalities_link_management[internal_new_tab_exceptions]" rows="3" cols="60" class="large-text code">' . \esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">' . \esc_html__( 'One domain per line. Matching hosts will NOT be forced to open in a new tab when internal option is enabled.', 'functionalities' ) . '</p>';
	}

	/**
	 * Render JSON preset URL field.
	 *
	 * @return void
	 */
	public static function field_json_preset_url() : void {
		$opts = self::get_link_management_options();
		$val  = isset( $opts['json_preset_url'] ) ? (string) $opts['json_preset_url'] : '';

		// Check for theme exception-urls.json.
		$theme_json_path      = \get_stylesheet_directory() . '/exception-urls.json';
		$theme_json_exists    = file_exists( $theme_json_path );
		$parent_json_path     = \get_template_directory() . '/exception-urls.json';
		$parent_json_exists   = \get_stylesheet_directory() !== \get_template_directory() && file_exists( $parent_json_path );
		?>
		<div class="functionalities-json-picker">
			<div class="functionalities-json-picker-input">
				<input type="text" id="functionalities_json_preset_url" class="regular-text code" name="functionalities_link_management[json_preset_url]" value="<?php echo \esc_attr( $val ); ?>" placeholder="<?php echo \esc_attr( FUNCTIONALITIES_DIR . 'exception-urls.json' ); ?>" />
				<button type="button" id="functionalities_json_browse_btn" class="button button-secondary">
					<?php echo \esc_html__( 'Browse...', 'functionalities' ); ?>
				</button>
				<button type="button" id="functionalities_json_create_btn" class="button button-secondary">
					<?php echo \esc_html__( 'Create JSON', 'functionalities' ); ?>
				</button>
			</div>

			<p class="description">
				<?php echo \esc_html__( 'Enter a local file path or external URL to a JSON file containing exception URLs.', 'functionalities' ); ?>
			</p>
			<p class="description">
				<?php echo \esc_html__( 'Format: {"urls": ["https://example.com", "https://another.com"]}', 'functionalities' ); ?>
			</p>

			<?php if ( $theme_json_exists || $parent_json_exists ) : ?>
				<div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">
					<strong><?php echo \esc_html__( 'Theme JSON Detected:', 'functionalities' ); ?></strong>
					<?php if ( $theme_json_exists ) : ?>
						<p style="margin: 5px 0;">
							<?php
							printf(
								/* translators: %s: theme name */
								\esc_html__( '✓ Your active theme (%s) has an exception-urls.json file. It will be loaded automatically if no custom path is set.', 'functionalities' ),
								\esc_html( \wp_get_theme()->get( 'Name' ) )
							);
							?>
							<br><code><?php echo \esc_html( $theme_json_path ); ?></code>
						</p>
					<?php endif; ?>
					<?php if ( $parent_json_exists ) : ?>
						<p style="margin: 5px 0;">
							<?php
							printf(
								/* translators: %s: parent theme name */
								\esc_html__( '✓ Parent theme (%s) has an exception-urls.json file.', 'functionalities' ),
								\esc_html( \wp_get_theme( \get_template() )->get( 'Name' ) )
							);
							?>
							<br><code><?php echo \esc_html( $parent_json_path ); ?></code>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="description" style="margin-top: 10px;">
					<strong><?php echo \esc_html__( 'Tip:', 'functionalities' ); ?></strong>
					<?php echo \esc_html__( 'You can place an exception-urls.json file in your active theme\'s root folder and it will be loaded automatically without entering a path here.', 'functionalities' ); ?>
				</p>
			<?php endif; ?>

			<div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">
				<strong><?php echo \esc_html__( '⚠️ Security Warning:', 'functionalities' ); ?></strong>
				<p style="margin: 5px 0;">
					<?php echo \esc_html__( 'If using an external URL, ensure you trust the source completely. Malicious JSON files could add unwanted domains to your exception list, potentially allowing spam links to pass through without nofollow.', 'functionalities' ); ?>
				</p>
				<p style="margin: 5px 0;">
					<?php echo \esc_html__( 'For security, prefer local JSON files within your WordPress installation over external URLs.', 'functionalities' ); ?>
				</p>
			</div>

			<p class="description">
				<?php echo \esc_html__( 'Filter available: functionalities_json_preset_path', 'functionalities' ); ?>
			</p>
		</div>

		<!-- JSON Create Modal -->
		<div id="functionalities-json-create-modal" style="display:none;">
			<div class="functionalities-modal-overlay">
				<div class="functionalities-modal-content">
					<h3><?php echo \esc_html__( 'Create Exception URLs JSON File', 'functionalities' ); ?></h3>
					<p class="description"><?php echo \esc_html__( 'This will create a new exception-urls.json file in your active theme directory.', 'functionalities' ); ?></p>
					<textarea id="functionalities_json_create_content" rows="10" class="large-text code" placeholder='{"urls": ["https://trusted-domain.com", "https://another-trusted.com"]}'><?php echo \esc_textarea( "{\n\t\"urls\": [\n\t\t\"https://example.com\",\n\t\t\"https://another-trusted-site.com\"\n\t]\n}" ); ?></textarea>
					<p class="description"><?php echo \esc_html__( 'Edit the JSON above, then click "Create File" to save it to your theme.', 'functionalities' ); ?></p>
					<div style="margin-top: 15px;">
						<button type="button" id="functionalities_json_create_save" class="button button-primary"><?php echo \esc_html__( 'Create File', 'functionalities' ); ?></button>
						<button type="button" id="functionalities_json_create_cancel" class="button button-secondary"><?php echo \esc_html__( 'Cancel', 'functionalities' ); ?></button>
					</div>
					<div id="functionalities_json_create_result" style="margin-top: 10px;"></div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Media Library Browser.
			$('#functionalities_json_browse_btn').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: '<?php echo \esc_js( \__( 'Select JSON File', 'functionalities' ) ); ?>',
					button: { text: '<?php echo \esc_js( \__( 'Use this file', 'functionalities' ) ); ?>' },
					multiple: false,
					library: { type: 'application/json' }
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#functionalities_json_preset_url').val(attachment.url);
				});
				frame.open();
			});

			// Create JSON Modal.
			$('#functionalities_json_create_btn').on('click', function(e) {
				e.preventDefault();
				$('#functionalities-json-create-modal').show();
			});
			$('#functionalities_json_create_cancel').on('click', function() {
				$('#functionalities-json-create-modal').hide();
				$('#functionalities_json_create_result').html('');
			});
			$('.functionalities-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$('#functionalities-json-create-modal').hide();
					$('#functionalities_json_create_result').html('');
				}
			});

			// Create JSON File.
			$('#functionalities_json_create_save').on('click', function() {
				var content = $('#functionalities_json_create_content').val();
				var $btn = $(this);
				var $result = $('#functionalities_json_create_result');

				// Validate JSON.
				try {
					JSON.parse(content);
				} catch (e) {
					$result.html('<div class="notice notice-error"><p><?php echo \esc_js( \__( 'Invalid JSON format. Please check your syntax.', 'functionalities' ) ); ?></p></div>');
					return;
				}

				$btn.prop('disabled', true).text('<?php echo \esc_js( \__( 'Creating...', 'functionalities' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'functionalities_create_json_file',
						content: content,
						nonce: '<?php echo esc_attr( \wp_create_nonce( 'functionalities_create_json' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
							if (response.data.path) {
								$('#functionalities_json_preset_url').val(response.data.path);
							}
							setTimeout(function() {
								$('#functionalities-json-create-modal').hide();
								$result.html('');
							}, 2000);
						} else {
							$result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function() {
						$result.html('<div class="notice notice-error"><p><?php echo \esc_js( \__( 'An error occurred.', 'functionalities' ) ); ?></p></div>');
					},
					complete: function() {
						$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Create File', 'functionalities' ) ); ?>');
					}
				});
			});
		});
		</script>
		<style>
		.functionalities-json-picker-input {
			display: flex;
			gap: 5px;
			flex-wrap: wrap;
			align-items: center;
			margin-bottom: 10px;
		}
		.functionalities-modal-overlay {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.7);
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.functionalities-modal-content {
			background: #fff;
			padding: 20px;
			border-radius: 4px;
			max-width: 600px;
			width: 90%;
			max-height: 80vh;
			overflow-y: auto;
		}
		.functionalities-modal-content h3 {
			margin-top: 0;
		}
		</style>
		<?php
	}

	/**
	 * Render enable developer filters field.
	 *
	 * @return void
	 */
	public static function field_enable_developer_filters() : void {
		$opts    = self::get_link_management_options();
		$checked = ! empty( $opts['enable_developer_filters'] ) ? 'checked' : '';
		?>
		<label>
			<input type="checkbox" name="functionalities_link_management[enable_developer_filters]" value="1" <?php echo esc_attr( $checked ); ?>>
			<?php echo \esc_html__( 'Enable developer filters for exception customization', 'functionalities' ); ?>
		</label>
		<p class="description">
			<?php echo \esc_html__( 'Available filters: functionalities_exception_domains, functionalities_exception_urls', 'functionalities' ); ?>
		</p>

		<div class="functionalities-code-snippets" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
			<h4 style="margin-top: 0;"><?php echo \esc_html__( 'Code Snippets (copy to your theme\'s functions.php or a custom plugin)', 'functionalities' ); ?></h4>

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
					<?php echo \esc_html__( 'Add Exception Domains', 'functionalities' ); ?>
				</summary>
				<div style="margin-top: 10px;">
					<p class="description"><?php echo \esc_html__( 'Add trusted domains that should never get nofollow:', 'functionalities' ); ?></p>
					<pre class="functionalities-code-block" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><code>&lt;?php
/**
 * Add trusted domains to nofollow exceptions.
 * Links to these domains will NOT have nofollow added.
 */
add_filter( 'functionalities_exception_domains', function( $domains ) {
    // Add your trusted domains here
    $trusted_domains = array(
        'trusted-partner.com',
        'another-trusted-site.org',
        'your-other-website.net',
    );

    return array_merge( $domains, $trusted_domains );
} );</code></pre>
					<button type="button" class="button button-small functionalities-copy-btn" data-target="exception-domains"><?php echo \esc_html__( 'Copy Code', 'functionalities' ); ?></button>
				</div>
			</details>

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
					<?php echo \esc_html__( 'Add Exception URLs', 'functionalities' ); ?>
				</summary>
				<div style="margin-top: 10px;">
					<p class="description"><?php echo \esc_html__( 'Add specific URLs (with wildcards) that should never get nofollow:', 'functionalities' ); ?></p>
					<pre class="functionalities-code-block" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><code>&lt;?php
/**
 * Add specific URLs to nofollow exceptions.
 * These specific URLs will NOT have nofollow added.
 */
add_filter( 'functionalities_exception_urls', function( $urls ) {
    // Add specific URLs or patterns
    $trusted_urls = array(
        'https://example.com/specific-page',
        'https://partner.com/affiliate/*',
        'https://trusted.org/blog/*',
    );

    return array_merge( $urls, $trusted_urls );
} );</code></pre>
					<button type="button" class="button button-small functionalities-copy-btn" data-target="exception-urls"><?php echo \esc_html__( 'Copy Code', 'functionalities' ); ?></button>
				</div>
			</details>

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
					<?php echo \esc_html__( 'Dynamic Exceptions (e.g., based on user role)', 'functionalities' ); ?>
				</summary>
				<div style="margin-top: 10px;">
					<p class="description"><?php echo \esc_html__( 'Add exceptions dynamically based on conditions:', 'functionalities' ); ?></p>
					<pre class="functionalities-code-block" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><code>&lt;?php
/**
 * Dynamically add exception domains based on conditions.
 * Example: Trust all domains for administrators.
 */
add_filter( 'functionalities_exception_domains', function( $domains ) {
    // Skip nofollow for administrators
    if ( current_user_can( 'manage_options' ) ) {
        // Return wildcard to match all domains
        $domains[] = '*';
    }

    // Add domains from a custom option
    $custom_domains = get_option( 'my_trusted_domains', array() );
    if ( is_array( $custom_domains ) ) {
        $domains = array_merge( $domains, $custom_domains );
    }

    // Add sponsor domains on specific post types
    if ( is_singular( 'sponsored_post' ) ) {
        $domains[] = 'sponsor-website.com';
    }

    return $domains;
} );</code></pre>
					<button type="button" class="button button-small functionalities-copy-btn" data-target="dynamic-exceptions"><?php echo \esc_html__( 'Copy Code', 'functionalities' ); ?></button>
				</div>
			</details>

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
					<?php echo \esc_html__( 'Custom JSON File Path', 'functionalities' ); ?>
				</summary>
				<div style="margin-top: 10px;">
					<p class="description"><?php echo \esc_html__( 'Override the JSON file path programmatically:', 'functionalities' ); ?></p>
					<pre class="functionalities-code-block" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><code>&lt;?php
/**
 * Use a custom JSON file path for exception URLs.
 */
add_filter( 'functionalities_json_preset_path', function( $default_path ) {
    // Use a JSON file from your theme
    $theme_json = get_stylesheet_directory() . '/config/exceptions.json';

    if ( file_exists( $theme_json ) ) {
        return $theme_json;
    }

    // Or use an external URL (use with caution!)
    // return 'https://your-cdn.com/exception-urls.json';

    return $default_path;
} );</code></pre>
					<button type="button" class="button button-small functionalities-copy-btn" data-target="json-path"><?php echo \esc_html__( 'Copy Code', 'functionalities' ); ?></button>
				</div>
			</details>


		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.functionalities-copy-btn').on('click', function() {
				var $btn = $(this);
				var $pre = $btn.prev('pre');
				var code = $pre.find('code').text();

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(function() {
						var originalText = $btn.text();
						$btn.text('<?php echo \esc_js( \__( 'Copied!', 'functionalities' ) ); ?>');
						setTimeout(function() {
							$btn.text(originalText);
						}, 2000);
					});
				} else {
					// Fallback for older browsers.
					var textarea = document.createElement('textarea');
					textarea.value = code;
					document.body.appendChild(textarea);
					textarea.select();
					document.execCommand('copy');
					document.body.removeChild(textarea);
					var originalText = $btn.text();
					$btn.text('<?php echo \esc_js( \__( 'Copied!', 'functionalities' ) ); ?>');
					setTimeout(function() {
						$btn.text(originalText);
					}, 2000);
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render database update tool field.
	 *
	 * @return void
	 */
	public static function field_database_update_tool() : void {
		?>
		<div class="functionalities-db-tool">
			<p class="description">
				<?php echo \esc_html__( 'Bulk add nofollow to a specific URL across all posts in the database. Use with caution!', 'functionalities' ); ?>
			</p>
			<input type="text" id="functionalities_db_url" class="regular-text code" placeholder="https://example.com/page" />
			<button type="button" id="functionalities_db_update_btn" class="button button-secondary">
				<?php echo \esc_html__( 'Update Database', 'functionalities' ); ?>
			</button>
			<div id="functionalities_db_result" style="margin-top: 10px;"></div>
			<script>
			jQuery(document).ready(function($) {
				$('#functionalities_db_update_btn').on('click', function() {
					var url = $('#functionalities_db_url').val().trim();
					var $btn = $(this);
					var $result = $('#functionalities_db_result');

					if (!url) {
						$result.html('<div class="notice notice-error"><p><?php echo \esc_js( \__( 'Please enter a URL.', 'functionalities' ) ); ?></p></div>');
						return;
					}

					if (!confirm('<?php echo \esc_js( \__( 'This will update all posts containing this URL. Are you sure?', 'functionalities' ) ); ?>')) {
						return;
					}

					$btn.prop('disabled', true).text('<?php echo \esc_js( \__( 'Processing...', 'functionalities' ) ); ?>');
					$result.html('<div class="notice notice-info"><p><?php echo \esc_js( \__( 'Processing...', 'functionalities' ) ); ?></p></div>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'functionalities_update_database',
							url: url,
							nonce: '<?php echo esc_attr( \wp_create_nonce( 'functionalities_db_update' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								$result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
							} else {
								$result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
							}
						},
						error: function() {
							$result.html('<div class="notice notice-error"><p><?php echo \esc_js( \__( 'An error occurred.', 'functionalities' ) ); ?></p></div>');
						},
						complete: function() {
							$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Update Database', 'functionalities' ) ); ?>');
						}
					});
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Render section description for block cleanup.
	 *
	 * @return void
	 */
	public static function section_block_cleanup() : void {
		echo '<p>' . \esc_html__( 'Remove Gutenberg block-specific CSS classes from your frontend HTML for cleaner markup.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'block-cleanup' );

		// Render documentation accordions.
		echo '<div class="functionalities-module-docs">';

		if ( ! empty( $docs['features'] ) ) {
			$list = '<ul>';
			foreach ( $docs['features'] as $feature ) {
				$list .= '<li>' . \esc_html( $feature ) . '</li>';
			}
			$list .= '</ul>';
			Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
		}

		// Classes removed info.
		$classes_html = '<ul style="font-family:monospace;font-size:12px">';
		$classes_html .= '<li>wp-block-heading → ' . \esc_html__( 'from h1-h6 elements', 'functionalities' ) . '</li>';
		$classes_html .= '<li>wp-block-list → ' . \esc_html__( 'from ul/ol elements', 'functionalities' ) . '</li>';
		$classes_html .= '<li>wp-block-image → ' . \esc_html__( 'from figure/div elements', 'functionalities' ) . '</li>';
		$classes_html .= '</ul>';
		Admin_UI::render_docs_section( \__( 'Classes Removed', 'functionalities' ), $classes_html, 'usage' );

		if ( ! empty( $docs['hooks'] ) ) {
			$hooks_html = '<dl class="functionalities-hooks-list">';
			foreach ( $docs['hooks'] as $hook ) {
				$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
				$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
			}
			$hooks_html .= '</dl>';
			Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
		}

		echo '</div>';
	}

	// Block Cleanup: fields & helpers
	public static function field_bc_remove_heading() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_heading_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_heading_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class "wp-block-heading" from headings (h1–h6)', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_list() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_list_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_list_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class "wp-block-list" from ul/ol', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_image() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_image_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_image_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class ".wp-block-image" from frontend', 'functionalities' ) . '</label>';
	}

	// Editor Links: fields & helpers
	public static function field_el_enable() : void {
		$opts = self::get_editor_links_options();
		$checked = ! empty( $opts['enable_limit'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_editor_links[enable_limit]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Limit editor link suggestions to selected post types', 'functionalities' ) . '</label>';
	}
	public static function field_el_post_types() : void {
		$opts = self::get_editor_links_options();
		$selected = isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : [];
		$pts = get_post_types( [ 'public' => true ], 'objects' );
		echo '<fieldset>';
		foreach ( $pts as $name => $obj ) {
			$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
			$label = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
			echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_editor_links[post_types][]" value="' . esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
	}

	public static function field_components_items() : void {
		$o = self::get_components_options();
		$items = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : [];
		$per_page = 24;
		$total_items = count( $items );
		$total_pages = (int) max( 1, ceil( $total_items / $per_page ) );
		?>
		<style>
			/* Components Grid Container */
			.fc-components {
				margin-bottom: 20px;
			}
			.fc-components__toolbar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 16px;
				padding: 12px 16px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
			}
			.fc-components__count {
				font-size: 14px;
				color: #64748b;
			}
			.fc-components__add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: #3b82f6;
				color: #fff;
				border: none;
				border-radius: 6px;
				font-size: 14px;
				font-weight: 500;
				cursor: pointer;
				transition: background 0.15s;
			}
			.fc-components__add-btn:hover {
				background: #2563eb;
			}

			/* Grid Layout */
			.fc-components__grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
				gap: 16px;
				margin-bottom: 20px;
			}

			/* Component Card */
			.fc-card {
				position: relative;
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				overflow: hidden;
				transition: box-shadow 0.2s, border-color 0.2s;
			}
			.fc-card:hover {
				border-color: #3b82f6;
				box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
			}
			.fc-card.is-editing {
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-card--new {
				border-style: dashed;
				border-color: #94a3b8;
				background: #f8fafc;
			}
			.fc-card--new:hover {
				border-color: #3b82f6;
				background: #eff6ff;
			}

			/* Card Preview */
			.fc-card__preview {
				height: 80px;
				padding: 12px;
				background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
				display: flex;
				align-items: center;
				justify-content: center;
				border-bottom: 1px solid #e2e8f0;
				overflow: hidden;
			}
			.fc-card__preview-box {
				max-width: 100%;
				max-height: 100%;
				font-size: 14px;
				text-align: center;
				word-break: break-word;
			}

			/* Card Content */
			.fc-card__content {
				padding: 12px 14px;
			}
			.fc-card__header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-bottom: 4px;
			}
			.fc-card__name {
				font-weight: 600;
				font-size: 14px;
				color: #1e293b;
				margin: 0;
				line-height: 1.3;
			}
			.fc-card__selector {
				font-family: ui-monospace, monospace;
				font-size: 12px;
				color: #64748b;
				background: #f1f5f9;
				padding: 2px 6px;
				border-radius: 4px;
			}
			.fc-card__actions {
				display: flex;
				gap: 4px;
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid #f1f5f9;
			}
			.fc-card__btn {
				flex: 1;
				padding: 6px 10px;
				font-size: 12px;
				font-weight: 500;
				border: none;
				border-radius: 4px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-card__btn--edit {
				background: #eff6ff;
				color: #3b82f6;
			}
			.fc-card__btn--edit:hover {
				background: #dbeafe;
			}
			.fc-card__btn--delete {
				background: #fef2f2;
				color: #dc2626;
			}
			.fc-card__btn--delete:hover {
				background: #fee2e2;
			}

			/* Edit Form (inline) */
			.fc-card__form {
				display: none;
				padding: 14px;
				background: #fafafa;
				border-top: 1px solid #e2e8f0;
			}
			.fc-card.is-editing .fc-card__form {
				display: block;
			}
			.fc-card.is-editing .fc-card__actions {
				display: none;
			}
			.fc-form__group {
				margin-bottom: 12px;
			}
			.fc-form__group:last-child {
				margin-bottom: 0;
			}
			.fc-form__label {
				display: block;
				font-size: 12px;
				font-weight: 600;
				color: #475569;
				margin-bottom: 4px;
			}
			.fc-form__input {
				width: 100%;
				padding: 8px 10px;
				font-size: 13px;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
				transition: border-color 0.15s, box-shadow 0.15s;
			}
			.fc-form__input:focus {
				outline: none;
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-form__textarea {
				min-height: 80px;
				font-family: ui-monospace, monospace;
				font-size: 12px;
				resize: vertical;
			}
			.fc-form__actions {
				display: flex;
				gap: 8px;
				margin-top: 12px;
			}
			.fc-form__btn {
				padding: 8px 14px;
				font-size: 13px;
				font-weight: 500;
				border: none;
				border-radius: 6px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-form__btn--save {
				background: #3b82f6;
				color: #fff;
			}
			.fc-form__btn--save:hover {
				background: #2563eb;
			}
			.fc-form__btn--cancel {
				background: #f1f5f9;
				color: #64748b;
			}
			.fc-form__btn--cancel:hover {
				background: #e2e8f0;
			}

			/* Pagination */
			.fc-pagination {
				display: flex;
				justify-content: center;
				align-items: center;
				gap: 8px;
				padding: 16px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
			}
			.fc-pagination__btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 36px;
				height: 36px;
				padding: 0 12px;
				font-size: 14px;
				font-weight: 500;
				color: #475569;
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-pagination__btn:hover:not(:disabled) {
				background: #f1f5f9;
				border-color: #cbd5e1;
			}
			.fc-pagination__btn:disabled {
				opacity: 0.5;
				cursor: not-allowed;
			}
			.fc-pagination__btn.is-active {
				background: #3b82f6;
				border-color: #3b82f6;
				color: #fff;
			}
			.fc-pagination__info {
				font-size: 14px;
				color: #64748b;
				margin: 0 8px;
			}

			/* New Card Special */
			.fc-card--new .fc-card__preview {
				background: transparent;
				border-bottom: none;
			}
			.fc-card--new .fc-card__preview-box {
				color: #64748b;
			}
			.fc-card--new .fc-card__content {
				text-align: center;
				padding: 20px;
			}
			.fc-card--new .fc-card__name {
				color: #64748b;
				font-weight: 500;
			}

			/* Hidden items for pagination */
			.fc-card[data-page]:not(.is-visible) {
				display: none;
			}
		</style>

		<div class="fc-components" id="fc-components">
			<div class="fc-components__toolbar">
				<span class="fc-components__count">
					<?php
					/* translators: %d: Number of components. */
					printf( \esc_html__( '%d components', 'functionalities' ), (int) $total_items );
					?>
				</span>
				<button type="button" class="fc-components__add-btn" id="fc-add-new">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
					<?php \esc_html_e( 'Add Component', 'functionalities' ); ?>
				</button>
			</div>

			<div class="fc-components__grid" id="fc-grid">
				<?php
				$i = 0;
				foreach ( $items as $item ) :
					$name  = $item['name'] ?? '';
					$class = $item['class'] ?? '';
					$css   = $item['css'] ?? '';
					$page  = (int) floor( $i / $per_page ) + 1;
					$is_visible = ( 1 === $page ) ? 'is-visible' : '';
				?>
				<div class="fc-card <?php echo \esc_attr( $is_visible ); ?>" data-index="<?php echo (int) $i; ?>" data-page="<?php echo (int) $page; ?>">
					<div class="fc-card__preview">
						<div class="fc-card__preview-box" style="<?php echo \esc_attr( $css ); ?>">
							<?php echo \esc_html( $name ?: 'Preview' ); ?>
						</div>
					</div>
					<div class="fc-card__content">
						<div class="fc-card__header">
							<h4 class="fc-card__name"><?php echo \esc_html( $name ?: \__( 'Untitled', 'functionalities' ) ); ?></h4>
						</div>
						<code class="fc-card__selector"><?php echo \esc_html( $class ?: '.selector' ); ?></code>
						<div class="fc-card__actions">
							<button type="button" class="fc-card__btn fc-card__btn--edit"><?php \esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-card__btn fc-card__btn--delete"><?php \esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-card__form">
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'Name', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo (int) $i; ?>][name]" value="<?php echo \esc_attr( $name ); ?>" placeholder="<?php \esc_attr_e( 'Component Name', 'functionalities' ); ?>">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Selector', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo (int) $i; ?>][class]" value="<?php echo \esc_attr( $class ); ?>" placeholder=".my-component">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Rules', 'functionalities' ); ?></label>
							<textarea class="fc-form__input fc-form__textarea" name="functionalities_components[items][<?php echo (int) $i; ?>][css]" placeholder="background: #fff; padding: 1rem;"><?php echo \esc_textarea( $css ); ?></textarea>
						</div>
						<div class="fc-form__actions">
							<button type="button" class="fc-form__btn fc-form__btn--save"><?php \esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-form__btn fc-form__btn--cancel"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
				<?php
					$i++;
				endforeach;
				?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="fc-pagination" id="fc-pagination">
				<button type="button" class="fc-pagination__btn" data-action="prev" <?php echo ( 1 === 1 ) ? 'disabled' : ''; ?>>
					&larr; <?php \esc_html_e( 'Prev', 'functionalities' ); ?>
				</button>
				<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
				<button type="button" class="fc-pagination__btn <?php echo ( $p === 1 ) ? 'is-active' : ''; ?>" data-page="<?php echo (int) $p; ?>">
					<?php echo (int) $p; ?>
				</button>
				<?php endfor; ?>
				<button type="button" class="fc-pagination__btn" data-action="next" <?php echo ( 1 === $total_pages ) ? 'disabled' : ''; ?>>
					<?php \esc_html_e( 'Next', 'functionalities' ); ?> &rarr;
				</button>
				<span class="fc-pagination__info">
					<?php
					/* translators: 1: current page number, 2: total pages */
					printf( \esc_html__( 'Page %1$d of %2$d', 'functionalities' ), 1, esc_html( $total_pages ) );
					?>
				</span>
			</div>
			<?php endif; ?>

			<!-- Hidden template for new components -->
			<template id="fc-card-template">
				<div class="fc-card is-editing is-visible" data-index="__INDEX__" data-page="__PAGE__">
					<div class="fc-card__preview">
						<div class="fc-card__preview-box">New Component</div>
					</div>
					<div class="fc-card__content">
						<div class="fc-card__header">
							<h4 class="fc-card__name"><?php \esc_html_e( 'New Component', 'functionalities' ); ?></h4>
						</div>
						<code class="fc-card__selector">.new-component</code>
						<div class="fc-card__actions">
							<button type="button" class="fc-card__btn fc-card__btn--edit"><?php \esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-card__btn fc-card__btn--delete"><?php \esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-card__form">
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'Name', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][__INDEX__][name]" value="" placeholder="<?php \esc_attr_e( 'Component Name', 'functionalities' ); ?>">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Selector', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][__INDEX__][class]" value="" placeholder=".my-component">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Rules', 'functionalities' ); ?></label>
							<textarea class="fc-form__input fc-form__textarea" name="functionalities_components[items][__INDEX__][css]" placeholder="background: #fff; padding: 1rem;"></textarea>
						</div>
						<div class="fc-form__actions">
							<button type="button" class="fc-form__btn fc-form__btn--save"><?php \esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-form__btn fc-form__btn--cancel"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
			</template>
		</div>

		<script>
		(function() {
			var container = document.getElementById('fc-components');
			var grid = document.getElementById('fc-grid');
			var pagination = document.getElementById('fc-pagination');
			var addBtn = document.getElementById('fc-add-new');
			var template = document.getElementById('fc-card-template');
			var currentPage = 1;
			var perPage = <?php echo (int) $per_page; ?>;
			var totalItems = <?php echo (int) $total_items; ?>;
			var nextIndex = <?php echo (int) $i; ?>;

			if (!container || !grid) return;

			// Update preview when CSS changes
			function updatePreview(card) {
				var cssInput = card.querySelector('textarea[name*="[css]"]');
				var nameInput = card.querySelector('input[name*="[name]"]');
				var previewBox = card.querySelector('.fc-card__preview-box');
				var nameDisplay = card.querySelector('.fc-card__name');
				var selectorDisplay = card.querySelector('.fc-card__selector');
				var selectorInput = card.querySelector('input[name*="[class]"]');

				if (cssInput && previewBox) {
					previewBox.style.cssText = cssInput.value;
				}
				if (nameInput && previewBox) {
					previewBox.textContent = nameInput.value || 'Preview';
				}
				if (nameInput && nameDisplay) {
					nameDisplay.textContent = nameInput.value || '<?php echo \esc_js( \__( 'Untitled', 'functionalities' ) ); ?>';
				}
				if (selectorInput && selectorDisplay) {
					selectorDisplay.textContent = selectorInput.value || '.selector';
				}
			}

			// Show page
			function showPage(page) {
				var cards = grid.querySelectorAll('.fc-card[data-page]');
				cards.forEach(function(card) {
					if (parseInt(card.dataset.page) === page) {
						card.classList.add('is-visible');
					} else {
						card.classList.remove('is-visible');
						card.classList.remove('is-editing');
					}
				});

				currentPage = page;
				updatePaginationUI();
			}

			// Update pagination buttons
			function updatePaginationUI() {
				if (!pagination) return;

				var totalPages = Math.max(1, Math.ceil(totalItems / perPage));
				var prevBtn = pagination.querySelector('[data-action="prev"]');
				var nextBtn = pagination.querySelector('[data-action="next"]');
				var pageInfo = pagination.querySelector('.fc-pagination__info');
				var pageBtns = pagination.querySelectorAll('[data-page]');

				if (prevBtn) prevBtn.disabled = (currentPage <= 1);
				if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);
				if (pageInfo) pageInfo.textContent = '<?php echo \esc_js( \__( 'Page', 'functionalities' ) ); ?> ' + currentPage + ' <?php echo \esc_js( \__( 'of', 'functionalities' ) ); ?> ' + totalPages;

				pageBtns.forEach(function(btn) {
					btn.classList.toggle('is-active', parseInt(btn.dataset.page) === currentPage);
				});
			}

			// Update component count
			function updateCount() {
				var countEl = container.querySelector('.fc-components__count');
				if (countEl) {
					countEl.textContent = totalItems + ' <?php echo \esc_js( \__( 'components', 'functionalities' ) ); ?>';
				}
			}

			// Event delegation
			container.addEventListener('click', function(e) {
				var card = e.target.closest('.fc-card');

				// Edit button
				if (e.target.closest('.fc-card__btn--edit')) {
					e.preventDefault();
					if (card) card.classList.add('is-editing');
					return;
				}

				// Done/Save button
				if (e.target.closest('.fc-form__btn--save')) {
					e.preventDefault();
					if (card) {
						updatePreview(card);
						card.classList.remove('is-editing');
					}
					return;
				}

				// Cancel button
				if (e.target.closest('.fc-form__btn--cancel')) {
					e.preventDefault();
					if (card) {
						// If it's a new unsaved card with empty values, remove it
						var nameInput = card.querySelector('input[name*="[name]"]');
						var classInput = card.querySelector('input[name*="[class]"]');
						if (nameInput && !nameInput.value && classInput && !classInput.value) {
							card.remove();
							totalItems--;
							updateCount();
						} else {
							card.classList.remove('is-editing');
						}
					}
					return;
				}

				// Delete button
				if (e.target.closest('.fc-card__btn--delete')) {
					e.preventDefault();
					if (card && confirm('<?php echo \esc_js( \__( 'Delete this component?', 'functionalities' ) ); ?>')) {
						// Clear values and hide
						var inputs = card.querySelectorAll('input, textarea');
						inputs.forEach(function(input) {
							input.value = '';
							input.name = '';
						});
						card.remove();
						totalItems--;
						updateCount();
					}
					return;
				}

				// Pagination
				if (e.target.closest('.fc-pagination__btn')) {
					var btn = e.target.closest('.fc-pagination__btn');
					if (btn.disabled) return;

					if (btn.dataset.action === 'prev') {
						showPage(currentPage - 1);
					} else if (btn.dataset.action === 'next') {
						showPage(currentPage + 1);
					} else if (btn.dataset.page) {
						showPage(parseInt(btn.dataset.page));
					}
					return;
				}
			});

			// Live preview updates
			container.addEventListener('input', function(e) {
				if (e.target.matches('input, textarea')) {
					var card = e.target.closest('.fc-card');
					if (card) updatePreview(card);
				}
			});

			// Add new component
			if (addBtn && template) {
				addBtn.addEventListener('click', function() {
					var newPage = Math.ceil((totalItems + 1) / perPage);
					var html = template.innerHTML
						.replace(/__INDEX__/g, nextIndex)
						.replace(/__PAGE__/g, newPage);

					grid.insertAdjacentHTML('beforeend', html);
					totalItems++;
					nextIndex++;

					// Go to the page with the new item and focus
					showPage(newPage);
					updateCount();

					var newCard = grid.lastElementChild;
					if (newCard) {
						newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
						var nameInput = newCard.querySelector('input[name*="[name]"]');
						if (nameInput) nameInput.focus();
					}
				});
			}
		})();
		</script>

		<p class="description" style="margin-top:12px;">
			<?php \esc_html_e( 'Click "Edit" to modify a component. Changes are applied when you click "Save Changes" below. Preview shows how CSS rules are applied.', 'functionalities' ); ?>
		</p>
		<?php
	}

	// Misc helpers
	protected static function add_misc_field( string $key, string $label ) : void {
		\add_settings_field(
			$key,
			$label,
			function() use ( $key, $label ) {
				$opts = self::get_misc_options();
				$checked = ! empty( $opts[ $key ] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_misc[' . esc_attr( $key ) . ']" value="1" ' . esc_attr( $checked ) . '> ' . esc_html( $label ) . '</label>';
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);
	}

	public static function field_fonts_items() : void {
		$o = self::get_fonts_options();
		$items = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : [];
		$total_items = count( $items );
		
		// Enqueue media uploader
		\wp_enqueue_media();
		?>
		<style>
			/* Fonts Manager Styles */
			.fc-fonts {
				max-width: 900px;
			}
			.fc-fonts__toolbar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 16px;
				padding-bottom: 12px;
				border-bottom: 1px solid #e5e7eb;
			}
			.fc-fonts__count {
				font-size: 13px;
				color: #64748b;
			}
			.fc-fonts__add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
				color: #fff;
				border: none;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
				box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
			}
			.fc-fonts__add-btn:hover {
				transform: translateY(-1px);
				box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
			}
			.fc-fonts__grid {
				display: grid;
				gap: 16px;
			}
			.fc-font-card {
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.2s;
			}
			.fc-font-card:hover {
				border-color: #cbd5e1;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
			}
			.fc-font-card.is-editing {
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-font-card__header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 16px 20px;
				background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
				border-bottom: 1px solid #e5e7eb;
			}
			.fc-font-card__title {
				margin: 0;
				font-size: 16px;
				font-weight: 600;
				color: #1e293b;
			}
			.fc-font-card__meta {
				display: flex;
				gap: 8px;
				margin-top: 4px;
			}
			.fc-font-card__badge {
				display: inline-block;
				padding: 2px 8px;
				background: #e0f2fe;
				color: #0369a1;
				border-radius: 4px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.fc-font-card__badge--variable {
				background: #dcfce7;
				color: #15803d;
			}
			.fc-font-card__badge--preload {
				background: #fef3c7;
				color: #b45309;
			}
			.fc-font-card__actions {
				display: flex;
				gap: 8px;
			}
			.fc-font-card__btn {
				padding: 6px 12px;
				border: 1px solid #e5e7eb;
				border-radius: 6px;
				background: #fff;
				color: #475569;
				font-size: 12px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-font-card__btn:hover {
				background: #f8fafc;
				border-color: #cbd5e1;
			}
			.fc-font-card__btn--delete {
				color: #dc2626;
			}
			.fc-font-card__btn--delete:hover {
				background: #fef2f2;
				border-color: #fecaca;
			}
			.fc-font-card__content {
				display: none;
				padding: 20px;
			}
			.fc-font-card.is-editing .fc-font-card__content {
				display: block;
			}
			.fc-font-card.is-editing .fc-font-card__actions .fc-font-card__btn--edit {
				display: none;
			}
			.fc-font-form__row {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 16px;
				margin-bottom: 16px;
			}
			.fc-font-form__group {
				display: flex;
				flex-direction: column;
				gap: 6px;
			}
			.fc-font-form__label {
				font-size: 12px;
				font-weight: 600;
				color: #374151;
			}
			.fc-font-form__input {
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
				transition: border-color 0.15s;
			}
			.fc-font-form__input:focus {
				outline: none;
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-font-form__input--url {
				font-family: ui-monospace, monospace;
				font-size: 13px;
			}
			.fc-font-form__file-row {
				display: flex;
				gap: 8px;
				align-items: flex-start;
			}
			.fc-font-form__file-row input[type="url"] {
				flex: 1;
			}
			.fc-font-form__upload-btn {
				padding: 8px 14px;
				background: #f8fafc;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				color: #475569;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				white-space: nowrap;
				transition: all 0.15s;
			}
			.fc-font-form__upload-btn:hover {
				background: #f1f5f9;
				border-color: #94a3b8;
			}
			.fc-font-form__checkbox {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 0;
			}
			.fc-font-form__checkbox input {
				width: 16px;
				height: 16px;
			}
			.fc-font-form__checkbox label {
				font-size: 13px;
				color: #374151;
			}
			.fc-font-form__actions {
				display: flex;
				gap: 8px;
				padding-top: 16px;
				border-top: 1px solid #e5e7eb;
				margin-top: 8px;
			}
			.fc-font-form__btn {
				padding: 8px 16px;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-font-form__btn--done {
				background: #3b82f6;
				color: #fff;
				border: none;
			}
			.fc-font-form__btn--done:hover {
				background: #2563eb;
			}
			.fc-font-form__btn--cancel {
				background: #fff;
				border: 1px solid #d1d5db;
				color: #475569;
			}
			.fc-font-form__btn--cancel:hover {
				background: #f8fafc;
			}
			.fc-fonts__empty {
				text-align: center;
				padding: 48px 24px;
				background: #f8fafc;
				border: 2px dashed #e5e7eb;
				border-radius: 12px;
			}
			.fc-fonts__empty-icon {
				font-size: 48px;
				margin-bottom: 16px;
			}
			.fc-fonts__empty-title {
				font-size: 16px;
				font-weight: 600;
				color: #374151;
				margin: 0 0 8px;
			}
			.fc-fonts__empty-text {
				font-size: 14px;
				color: #64748b;
				margin: 0;
			}
		</style>

		<div class="fc-fonts" id="fc-fonts">
			<div class="fc-fonts__toolbar">
				<span class="fc-fonts__count" id="fc-fonts-count">
					<?php
					/* translators: %d: number of fonts */
					printf( esc_html( _n( '%d font', '%d fonts', $total_items, 'functionalities' ) ), (int) $total_items );
					?>
				</span>
				<button type="button" class="fc-fonts__add-btn" id="fc-add-font">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
					<?php esc_html_e( 'Add Font', 'functionalities' ); ?>
				</button>
			</div>

			<div class="fc-fonts__grid" id="fc-fonts-grid">
				<?php if ( empty( $items ) ) : ?>
				<div class="fc-fonts__empty" id="fc-fonts-empty">
					<div class="fc-fonts__empty-icon">🔤</div>
					<h4 class="fc-fonts__empty-title"><?php esc_html_e( 'No fonts added yet', 'functionalities' ); ?></h4>
					<p class="fc-fonts__empty-text"><?php esc_html_e( 'Click "Add Font" to start adding custom web fonts to your site.', 'functionalities' ); ?></p>
				</div>
				<?php endif; ?>
				
				<?php
				$i = 0;
				foreach ( $items as $it ) :
					$family = esc_attr( $it['family'] ?? '' );
					$style  = esc_attr( $it['style'] ?? 'normal' );
					$display = esc_attr( $it['display'] ?? 'swap' );
					$weight = esc_attr( $it['weight'] ?? '' );
					$weight_range = esc_attr( $it['weight_range'] ?? '' );
					$is_variable = ! empty( $it['is_variable'] );
					$preload = ! empty( $it['preload'] );
					$woff2 = esc_attr( $it['woff2_url'] ?? '' );
					$woff  = esc_attr( $it['woff_url'] ?? '' );
				?>
				<div class="fc-font-card" data-index="<?php echo (int) $i; ?>">
					<div class="fc-font-card__header">
						<div>
							<h4 class="fc-font-card__title"><?php echo esc_html( $family ?: __( 'Untitled Font', 'functionalities' ) ); ?></h4>
							<div class="fc-font-card__meta">
								<span class="fc-font-card__badge"><?php echo esc_html( $style ); ?></span>
								<?php if ( $is_variable ) : ?>
								<span class="fc-font-card__badge fc-font-card__badge--variable"><?php esc_html_e( 'Variable', 'functionalities' ); ?></span>
								<?php endif; ?>
								<?php if ( $preload ) : ?>
								<span class="fc-font-card__badge fc-font-card__badge--preload"><?php esc_html_e( 'Preload', 'functionalities' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="fc-font-card__actions">
							<button type="button" class="fc-font-card__btn fc-font-card__btn--edit"><?php esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-font-card__btn fc-font-card__btn--delete"><?php esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-card__content">
						<div class="fc-font-form__row">
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Font Family', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][family]" value="<?php echo \esc_attr( $family ); ?>" placeholder="Inter, Roboto...">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Style', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][style]" value="<?php echo \esc_attr( $style ); ?>" placeholder="normal, italic, oblique -12deg 0deg">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Display', 'functionalities' ); ?></label>
								<select class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][display]">
									<option value="swap" <?php selected( $display, 'swap' ); ?>>swap</option>
									<option value="auto" <?php selected( $display, 'auto' ); ?>>auto</option>
									<option value="block" <?php selected( $display, 'block' ); ?>>block</option>
									<option value="fallback" <?php selected( $display, 'fallback' ); ?>>fallback</option>
									<option value="optional" <?php selected( $display, 'optional' ); ?>>optional</option>
								</select>
							</div>
						</div>
						<div class="fc-font-form__row">
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Static Weight', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][weight]" value="<?php echo \esc_attr( $weight ); ?>" placeholder="400, 700...">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Variable Weight Range', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][weight_range]" value="<?php echo \esc_attr( $weight_range ); ?>" placeholder="100 900">
							</div>
						</div>
						<div class="fc-font-form__row">
							<div class="fc-font-form__checkbox">
								<input type="checkbox" id="fc-var-<?php echo (int) $i; ?>" name="functionalities_fonts[items][<?php echo (int) $i; ?>][is_variable]" value="1" <?php checked( $is_variable ); ?>>
								<label for="fc-var-<?php echo (int) $i; ?>"><?php esc_html_e( 'Variable font', 'functionalities' ); ?></label>
							</div>
							<div class="fc-font-form__checkbox">
								<input type="checkbox" id="fc-pre-<?php echo (int) $i; ?>" name="functionalities_fonts[items][<?php echo (int) $i; ?>][preload]" value="1" <?php checked( $preload ); ?>>
								<label for="fc-pre-<?php echo (int) $i; ?>"><?php esc_html_e( 'Preload this font', 'functionalities' ); ?></label>
							</div>
						</div>
						<div class="fc-font-form__group" style="margin-bottom: 16px;">
							<label class="fc-font-form__label"><?php esc_html_e( 'WOFF2 URL (required)', 'functionalities' ); ?></label>
							<div class="fc-font-form__file-row">
								<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][<?php echo (int) $i; ?>][woff2_url]" value="<?php echo \esc_url( $woff2 ); ?>" placeholder="https://...">
								<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff2"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
							</div>
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'WOFF URL (fallback)', 'functionalities' ); ?></label>
							<div class="fc-font-form__file-row">
								<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][<?php echo (int) $i; ?>][woff_url]" value="<?php echo \esc_url( $woff ); ?>" placeholder="https://...">
								<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
							</div>
						</div>
						<div class="fc-font-form__actions">
							<button type="button" class="fc-font-form__btn fc-font-form__btn--done"><?php esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-font-form__btn fc-font-form__btn--cancel"><?php esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
				<?php
					$i++;
				endforeach;
				?>
			</div>
		</div>

		<!-- Template for new fonts -->
		<template id="fc-font-template">
			<div class="fc-font-card is-editing" data-index="__INDEX__">
				<div class="fc-font-card__header">
					<div>
						<h4 class="fc-font-card__title"><?php esc_html_e( 'New Font', 'functionalities' ); ?></h4>
						<div class="fc-font-card__meta">
							<span class="fc-font-card__badge">normal</span>
						</div>
					</div>
					<div class="fc-font-card__actions">
						<button type="button" class="fc-font-card__btn fc-font-card__btn--edit"><?php esc_html_e( 'Edit', 'functionalities' ); ?></button>
						<button type="button" class="fc-font-card__btn fc-font-card__btn--delete"><?php esc_html_e( 'Delete', 'functionalities' ); ?></button>
					</div>
				</div>
				<div class="fc-font-card__content">
					<div class="fc-font-form__row">
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Font Family', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][family]" value="" placeholder="Inter, Roboto...">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Style', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][style]" value="normal" placeholder="normal, italic, oblique -12deg 0deg">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Display', 'functionalities' ); ?></label>
							<select class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][display]">
								<option value="swap">swap</option>
								<option value="auto">auto</option>
								<option value="block">block</option>
								<option value="fallback">fallback</option>
								<option value="optional">optional</option>
							</select>
						</div>
					</div>
					<div class="fc-font-form__row">
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Static Weight', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][weight]" value="" placeholder="400, 700...">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Variable Weight Range', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][weight_range]" value="" placeholder="100 900">
						</div>
					</div>
					<div class="fc-font-form__row">
						<div class="fc-font-form__checkbox">
							<input type="checkbox" id="fc-var-__INDEX__" name="functionalities_fonts[items][__INDEX__][is_variable]" value="1">
							<label for="fc-var-__INDEX__"><?php esc_html_e( 'Variable font', 'functionalities' ); ?></label>
						</div>
						<div class="fc-font-form__checkbox">
							<input type="checkbox" id="fc-pre-__INDEX__" name="functionalities_fonts[items][__INDEX__][preload]" value="1">
							<label for="fc-pre-__INDEX__"><?php esc_html_e( 'Preload this font', 'functionalities' ); ?></label>
						</div>
					</div>
					<div class="fc-font-form__group" style="margin-bottom: 16px;">
						<label class="fc-font-form__label"><?php esc_html_e( 'WOFF2 URL (required)', 'functionalities' ); ?></label>
						<div class="fc-font-form__file-row">
							<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][__INDEX__][woff2_url]" value="" placeholder="https://...">
							<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff2"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-form__group">
						<label class="fc-font-form__label"><?php esc_html_e( 'WOFF URL (fallback)', 'functionalities' ); ?></label>
						<div class="fc-font-form__file-row">
							<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][__INDEX__][woff_url]" value="" placeholder="https://...">
							<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-form__actions">
						<button type="button" class="fc-font-form__btn fc-font-form__btn--done"><?php esc_html_e( 'Done', 'functionalities' ); ?></button>
						<button type="button" class="fc-font-form__btn fc-font-form__btn--cancel"><?php esc_html_e( 'Cancel', 'functionalities' ); ?></button>
					</div>
				</div>
			</div>
		</template>

		<script>
		(function() {
			var container = document.getElementById('fc-fonts');
			var grid = document.getElementById('fc-fonts-grid');
			var addBtn = document.getElementById('fc-add-font');
			var template = document.getElementById('fc-font-template');
			var emptyState = document.getElementById('fc-fonts-empty');
			var countEl = document.getElementById('fc-fonts-count');
			var nextIndex = <?php echo (int) $i; ?>;
			var totalItems = <?php echo (int) $total_items; ?>;

			if (!container || !grid) return;

			// Update title from input
			function updateCardTitle(card) {
				var familyInput = card.querySelector('input[name*="[family]"]');
				var titleEl = card.querySelector('.fc-font-card__title');
				var styleSelect = card.querySelector('select[name*="[style]"]');
				var varCheck = card.querySelector('input[name*="[is_variable]"]');
				var preCheck = card.querySelector('input[name*="[preload]"]');
				var metaEl = card.querySelector('.fc-font-card__meta');

				if (familyInput && titleEl) {
					titleEl.textContent = familyInput.value || '<?php echo esc_js( __( 'Untitled Font', 'functionalities' ) ); ?>';
				}

				// Update badges
				if (metaEl && styleSelect) {
					var badges = '<span class="fc-font-card__badge">' + styleSelect.value + '</span>';
					if (varCheck && varCheck.checked) {
						badges += '<span class="fc-font-card__badge fc-font-card__badge--variable"><?php echo esc_js( __( 'Variable', 'functionalities' ) ); ?></span>';
					}
					if (preCheck && preCheck.checked) {
						badges += '<span class="fc-font-card__badge fc-font-card__badge--preload"><?php echo esc_js( __( 'Preload', 'functionalities' ) ); ?></span>';
					}
					metaEl.innerHTML = badges;
				}
			}

			// Update count
			function updateCount() {
				var cards = grid.querySelectorAll('.fc-font-card');
				totalItems = cards.length;
				if (countEl) {
					var text = totalItems === 1 ? '<?php echo esc_js( __( '1 font', 'functionalities' ) ); ?>' : totalItems + ' <?php echo esc_js( __( 'fonts', 'functionalities' ) ); ?>';
					countEl.textContent = text;
				}
				// Toggle empty state
				if (emptyState) {
					emptyState.style.display = totalItems === 0 ? 'block' : 'none';
				}
			}

			// Event delegation
			container.addEventListener('click', function(e) {
				var card = e.target.closest('.fc-font-card');

				// Edit button
				if (e.target.closest('.fc-font-card__btn--edit')) {
					e.preventDefault();
					if (card) card.classList.add('is-editing');
					return;
				}

				// Done button
				if (e.target.closest('.fc-font-form__btn--done')) {
					e.preventDefault();
					if (card) {
						updateCardTitle(card);
						card.classList.remove('is-editing');
					}
					return;
				}

				// Cancel button
				if (e.target.closest('.fc-font-form__btn--cancel')) {
					e.preventDefault();
					if (card) {
						var familyInput = card.querySelector('input[name*="[family]"]');
						var woff2Input = card.querySelector('input[name*="[woff2_url]"]');
						// Remove if new and empty
						if (familyInput && !familyInput.value && woff2Input && !woff2Input.value) {
							card.remove();
							updateCount();
						} else {
							card.classList.remove('is-editing');
						}
					}
					return;
				}

				// Delete button
				if (e.target.closest('.fc-font-card__btn--delete')) {
					e.preventDefault();
					if (card && confirm('<?php echo esc_js( __( 'Delete this font?', 'functionalities' ) ); ?>')) {
						// Clear all inputs
						var inputs = card.querySelectorAll('input, select');
						inputs.forEach(function(input) {
							input.value = '';
							input.name = '';
						});
						card.remove();
						updateCount();
					}
					return;
				}

				// Upload button
				if (e.target.closest('.fc-upload-font')) {
					e.preventDefault();
					var btn = e.target.closest('.fc-upload-font');
					var format = btn.dataset.format;
					var urlInput = btn.parentElement.querySelector('.fc-font-url');

					var mediaUploader = wp.media({
						title: '<?php echo esc_js( __( 'Select or Upload Font File', 'functionalities' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Use this file', 'functionalities' ) ); ?>' },
						multiple: false
					});

					mediaUploader.on('select', function() {
						var attachment = mediaUploader.state().get('selection').first().toJSON();
						if (urlInput) {
							urlInput.value = attachment.url;
						}
					});

					mediaUploader.open();
					return;
				}
			});

			// Live updates on input
			container.addEventListener('input', function(e) {
				if (e.target.matches('input, select')) {
					var card = e.target.closest('.fc-font-card');
					if (card) updateCardTitle(card);
				}
			});

			container.addEventListener('change', function(e) {
				if (e.target.matches('input[type="checkbox"], select')) {
					var card = e.target.closest('.fc-font-card');
					if (card) updateCardTitle(card);
				}
			});

			// Add new font
			if (addBtn && template) {
				addBtn.addEventListener('click', function() {
					// Hide empty state
					if (emptyState) emptyState.style.display = 'none';

					var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
					grid.insertAdjacentHTML('beforeend', html);
					nextIndex++;
					updateCount();

					var newCard = grid.lastElementChild;
					if (newCard) {
						newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
						var familyInput = newCard.querySelector('input[name*="[family]"]');
						if (familyInput) familyInput.focus();
					}
				});
			}
		})();
		</script>

		<p class="description" style="margin-top: 16px;">
			<?php esc_html_e( 'Add multiple fonts and upload WOFF2/WOFF files directly. Each font generates a separate @font-face rule. Enable "Preload" for critical fonts to improve performance.', 'functionalities' ); ?>
		</p>
		<?php
	}

	/**
	 * Render typography assignment fields for the Fonts module.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function field_fonts_assignments() : void {
		$o     = self::get_fonts_options();
		$items = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : array();
		$assign_enabled = ! empty( $o['assign_enabled'] );
		$body_font      = $o['body_font'] ?? '';
		$heading_font   = $o['heading_font'] ?? '';
		$per_heading     = ! empty( $o['per_heading'] );
		$heading_fonts  = isset( $o['heading_fonts'] ) && is_array( $o['heading_fonts'] ) ? $o['heading_fonts'] : array();

		// Collect available font families.
		$families = array();
		foreach ( $items as $item ) {
			$family = trim( (string) ( $item['family'] ?? '' ) );
			if ( $family !== '' && ! empty( $item['woff2_url'] ) ) {
				$families[] = $family;
			}
		}
		$families = array_unique( $families );
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="functionalities_fonts[assign_enabled]" value="1" <?php checked( $assign_enabled ); ?> id="fc-assign-toggle">
				<?php \esc_html_e( 'Assign fonts to body text and headings', 'functionalities' ); ?>
			</label>
			<p class="description"><?php \esc_html_e( 'When enabled, outputs CSS that sets font-family on body and heading elements at highest priority.', 'functionalities' ); ?></p>
		</fieldset>

		<div id="fc-assign-fields" style="margin-top: 16px; <?php echo $assign_enabled ? '' : 'display:none;'; ?>">
			<?php if ( empty( $families ) ) : ?>
				<p class="description" style="color: #d63638;"><?php \esc_html_e( 'Add at least one font family above before assigning fonts.', 'functionalities' ); ?></p>
			<?php else : ?>
			<table class="form-table" role="presentation" style="margin-top: 0;">
				<tr>
					<th scope="row"><label for="fc-body-font"><?php \esc_html_e( 'Body / Content Font', 'functionalities' ); ?></label></th>
					<td>
						<select name="functionalities_fonts[body_font]" id="fc-body-font">
							<option value=""><?php \esc_html_e( '— None —', 'functionalities' ); ?></option>
							<?php foreach ( $families as $f ) : ?>
								<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $body_font, $f ); ?>><?php echo \esc_html( $f ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Applied to body, p, li, td, input, textarea, select, button.', 'functionalities' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fc-heading-font"><?php \esc_html_e( 'Headings Font', 'functionalities' ); ?></label></th>
					<td>
						<select name="functionalities_fonts[heading_font]" id="fc-heading-font">
							<option value=""><?php \esc_html_e( '— None —', 'functionalities' ); ?></option>
							<?php foreach ( $families as $f ) : ?>
								<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $heading_font, $f ); ?>><?php echo \esc_html( $f ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Applied to h1–h6. Override individual levels below.', 'functionalities' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Per-Heading Override', 'functionalities' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="functionalities_fonts[per_heading]" value="1" <?php checked( $per_heading ); ?> id="fc-per-heading-toggle">
							<?php \esc_html_e( 'Set a different font for each heading level', 'functionalities' ); ?>
						</label>
						<div id="fc-per-heading-fields" style="margin-top: 12px; <?php echo $per_heading ? '' : 'display:none;'; ?>">
							<?php for ( $level = 1; $level <= 6; $level++ ) :
								$val = $heading_fonts[ 'h' . $level ] ?? '';
								?>
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
									<label style="width: 30px; font-weight: 600; font-size: 13px;">H<?php echo (int) $level; ?></label>
									<select name="functionalities_fonts[heading_fonts][h<?php echo (int) $level; ?>]" style="min-width: 200px;">
										<option value=""><?php \esc_html_e( '— Use default heading font —', 'functionalities' ); ?></option>
										<?php foreach ( $families as $f ) : ?>
											<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $val, $f ); ?>><?php echo \esc_html( $f ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endfor; ?>
						</div>
					</td>
				</tr>
			</table>
			<?php endif; ?>
		</div>

		<script>
		(function() {
			var toggle = document.getElementById('fc-assign-toggle');
			var fields = document.getElementById('fc-assign-fields');
			if (toggle && fields) {
				toggle.addEventListener('change', function() {
					fields.style.display = this.checked ? '' : 'none';
				});
			}
			var perToggle = document.getElementById('fc-per-heading-toggle');
			var perFields = document.getElementById('fc-per-heading-fields');
			if (perToggle && perFields) {
				perToggle.addEventListener('change', function() {
					perFields.style.display = this.checked ? '' : 'none';
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render section description for Meta & Copyright.
	 *
	 * @return void
	 */
	public static function section_meta() : void {
		$detected = \Functionalities\Features\Meta::detect_seo_plugin();
		echo '<p>' . \esc_html__( 'Add copyright metadata, Dublin Core (DCMI) tags, and per-post licensing options. Works standalone or integrates with major SEO plugins.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'meta' );

		// Render documentation accordions.
		echo '<div class="functionalities-module-docs">';

		if ( ! empty( $docs['features'] ) ) {
			$list = '<ul>';
			foreach ( $docs['features'] as $feature ) {
				$list .= '<li>' . \esc_html( $feature ) . '</li>';
			}
			$list .= '</ul>';
			Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
		}

		// Schema.org Support (dynamic based on detected plugin).
		$plugin_names = array(
			'rank-math'     => 'Rank Math',
			'yoast'         => 'Yoast SEO',
			'seo-framework' => 'The SEO Framework',
			'seopress'      => 'SEOPress',
			'aioseo'        => 'All in One SEO',
		);

		if ( $detected !== 'none' ) {
			$schema_content = '<p><strong>✓ ' . \esc_html__( 'Detected:', 'functionalities' ) . '</strong> ' . \esc_html( $plugin_names[ $detected ] ?? $detected ) . '</p>';
			$schema_content .= '<p>' . \esc_html__( 'Copyright data will be added to your SEO plugin\'s existing schema output.', 'functionalities' ) . '</p>';
		} else {
			$schema_content = '<p><strong>✓ ' . \esc_html__( 'Standalone Mode', 'functionalities' ) . '</strong></p>';
			$schema_content .= '<p>' . \esc_html__( 'No SEO plugin detected. Complete Article schema with copyright will be output independently.', 'functionalities' ) . '</p>';
		}
		Admin_UI::render_docs_section( \__( 'Schema.org Support', 'functionalities' ), $schema_content, 'developer', true );

		// Compatible plugins.
		$plugins_html = '<ul style="columns:2">';
		$plugins_html .= '<li>Rank Math</li>';
		$plugins_html .= '<li>Yoast SEO</li>';
		$plugins_html .= '<li>The SEO Framework</li>';
		$plugins_html .= '<li>SEOPress</li>';
		$plugins_html .= '<li>All in One SEO</li>';
		$plugins_html .= '<li><em>' . \esc_html__( 'or Standalone', 'functionalities' ) . '</em></li>';
		$plugins_html .= '</ul>';
		Admin_UI::render_docs_section( \__( 'Compatible SEO Plugins', 'functionalities' ), $plugins_html, 'usage' );

		if ( ! empty( $docs['hooks'] ) ) {
			$hooks_html = '<dl class="functionalities-hooks-list">';
			foreach ( $docs['hooks'] as $hook ) {
				$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
				$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
			}
			$hooks_html .= '</dl>';
			Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
		}

		echo '</div>';
	}

	/**
	 * Render section description for content regression detection.
	 *
	 * @return void
	 */
	public static function section_content_regression() : void {
		echo '<p>' . \esc_html__( 'Detect structural regressions when posts are updated. This module compares each post against its own historical baseline.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'content-regression' );

		// Render documentation accordions.
		echo '<div class="functionalities-module-docs">';

		if ( ! empty( $docs['features'] ) ) {
			$list = '<ul>';
			foreach ( $docs['features'] as $feature ) {
				$list .= '<li>' . \esc_html( $feature ) . '</li>';
			}
			$list .= '</ul>';
			Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
		}

		if ( ! empty( $docs['usage'] ) ) {
			Admin_UI::render_docs_section( \__( 'Philosophy', 'functionalities' ), '<p>' . \esc_html( $docs['usage'] ) . '</p>', 'usage' );
		}

		if ( ! empty( $docs['hooks'] ) ) {
			$hooks_html = '<dl class="functionalities-hooks-list">';
			foreach ( $docs['hooks'] as $hook ) {
				$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
				$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
			}
			$hooks_html .= '</dl>';
			Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
		}

		echo '</div>';
	}

	/**
	 * Assumption detection section callback.
	 *
	 * @return void
	 */
	public static function section_assumption_detection() : void {
		echo '<p>' . \esc_html__( 'Detects when technical assumptions stop being true. This module notices changes, not problems.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'assumption-detection' );

		// Render documentation accordions.
		if ( ! empty( $docs['features'] ) ) {
			$list = '<ul>';
			foreach ( $docs['features'] as $feature ) {
				$list .= '<li>' . \esc_html( $feature ) . '</li>';
			}
			$list .= '</ul>';
			Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
		}

		if ( ! empty( $docs['usage'] ) ) {
			Admin_UI::render_docs_section( \__( 'How to Use', 'functionalities' ), '<p>' . \esc_html( $docs['usage'] ) . '</p>', 'usage' );
		}

		if ( ! empty( $docs['hooks'] ) ) {
			$hooks_html = '<dl class="functionalities-hooks-list">';
			foreach ( $docs['hooks'] as $hook ) {
				$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
				$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
			}
			$hooks_html .= '</dl>';
			Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
		}
	}

	/**
	 * Detected assumptions field callback.
	 *
	 * @return void
	 */
	public static function field_detected_assumptions() : void {
		$assumptions = \Functionalities\Features\Assumption_Detection::get_detected_assumptions();
		$ignored = \Functionalities\Features\Assumption_Detection::get_ignored_assumptions();

		// Filter out expired ignored items and already-ignored assumptions.
		$active_warnings = array();
		foreach ( $assumptions as $assumption ) {
			$hash = self::generate_warning_hash( $assumption );
			// Skip if ignored and not expired.
			if ( isset( $ignored[ $hash ] ) && $ignored[ $hash ]['expires'] > time() ) {
				continue;
			}
			$assumption['_hash'] = $hash;
			$active_warnings[] = $assumption;
		}

		if ( empty( $active_warnings ) ) {
			echo '<div class="functionalities-no-assumptions" style="display:flex;align-items:center;gap:10px;padding:15px;background:#edfaef;border-left:4px solid #00a32a;border-radius:4px;">';
			echo '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:24px;width:24px;height:24px;"></span>';
			echo '<span>' . \esc_html__( 'No assumption changes detected. All monitored items are consistent.', 'functionalities' ) . '</span>';
			echo '</div>';
			return;
		}

		echo '<div class="functionalities-assumptions-list" style="max-height:400px;overflow-y:auto;">';

		foreach ( $active_warnings as $assumption ) {
			$hash = $assumption['_hash'];
			$warning_type = $assumption['type'] ?? 'unknown';
			$type_class = 'warning';
			$icon = 'dashicons-warning';

			echo '<div class="functionalities-assumption-item" data-hash="' . \esc_attr( $hash ) . '" style="background:#fff8e5;border-left:4px solid #dba617;padding:12px;margin-bottom:10px;border-radius:4px;">';
			echo '<div class="functionalities-assumption-header" style="display:flex;align-items:flex-start;gap:10px;">';
			echo '<span class="dashicons ' . \esc_attr( $icon ) . '" style="color:#dba617;flex-shrink:0;margin-top:2px;"></span>';
			echo '<div class="functionalities-assumption-content" style="flex:1;">';
			echo '<p class="functionalities-assumption-message" style="margin:0;font-weight:500;">' . \esc_html( $assumption['message'] ?? '' ) . '</p>';

			// Show location (where) if available.
			if ( ! empty( $assumption['location'] ) ) {
				echo '<p class="functionalities-assumption-location" style="margin:6px 0 0;font-size:12px;color:#1e1e1e;">';
				echo '<strong>' . \esc_html__( 'Where:', 'functionalities' ) . '</strong> ';
				echo wp_kses( $assumption['location'], array( 'code' => array(), 'strong' => array() ) );
				echo '</p>';
			}

			// Show reason (why) if available.
			if ( ! empty( $assumption['reason'] ) ) {
				echo '<p class="functionalities-assumption-reason" style="margin:6px 0 0;font-size:12px;color:#646970;font-style:italic;">';
				echo '<strong style="font-style:normal;">' . \esc_html__( 'Why it matters:', 'functionalities' ) . '</strong> ';
				echo \esc_html( $assumption['reason'] );
				echo '</p>';
			}

			// Show type badge.
			echo '<span style="display:inline-block;background:#f0f0f1;padding:2px 8px;border-radius:3px;font-size:11px;margin-top:8px;color:#50575e;">' . \esc_html( str_replace( '_', ' ', ucfirst( $warning_type ) ) ) . '</span>';

			if ( ! empty( $assumption['detected'] ) ) {
				$time_ago = \human_time_diff( $assumption['detected'], \time() );
				/* translators: %s: human-readable time difference */
				echo '<span style="display:inline-block;font-size:11px;margin-left:10px;color:#646970;">' . \sprintf( \esc_html__( 'Detected %s ago', 'functionalities' ), \esc_html( $time_ago ) ) . '</span>';
			}

			echo '</div></div>';

			// Actions.
			echo '<div class="functionalities-assumption-actions" style="margin-top:10px;padding-top:10px;border-top:1px solid #e0e0e0;display:flex;gap:8px;">';
			echo '<button type="button" class="button button-small functionalities-assumption-acknowledge" data-hash="' . \esc_attr( $hash ) . '">';
			echo \esc_html__( 'Dismiss', 'functionalities' );
			echo '</button>';
			echo '<button type="button" class="button button-small functionalities-assumption-snooze" data-hash="' . \esc_attr( $hash ) . '">';
			echo \esc_html__( 'Snooze 7 days', 'functionalities' );
			echo '</button>';
			echo '<button type="button" class="button button-small functionalities-assumption-ignore" data-hash="' . \esc_attr( $hash ) . '">';
			echo \esc_html__( 'Ignore permanently', 'functionalities' );
			echo '</button>';
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';

		// Inline script for AJAX actions.
		$nonce = \wp_create_nonce( 'functionalities_assumptions' );
		?>
		<script>
		jQuery(function($) {
			var nonce = '<?php echo \esc_js( $nonce ); ?>';

			$('.functionalities-assumption-acknowledge').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.functionalities-assumption-item');
				var hash = $btn.data('hash');

				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'functionalities_acknowledge_assumption',
					hash: hash,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						$item.fadeOut(300, function() {
							$(this).remove();
							checkEmptyList();
						});
					} else {
						alert(response.data?.message || 'Action failed.');
						$btn.prop('disabled', false);
					}
				}).fail(function() {
					alert('Request failed.');
					$btn.prop('disabled', false);
				});
			});

			$('.functionalities-assumption-snooze').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.functionalities-assumption-item');
				var hash = $btn.data('hash');

				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'functionalities_snooze_assumption',
					hash: hash,
					days: 7,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						$item.fadeOut(300, function() {
							$(this).remove();
							checkEmptyList();
						});
					} else {
						alert(response.data?.message || 'Action failed.');
						$btn.prop('disabled', false);
					}
				}).fail(function() {
					alert('Request failed.');
					$btn.prop('disabled', false);
				});
			});

			$('.functionalities-assumption-ignore').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.functionalities-assumption-item');
				var hash = $btn.data('hash');

				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'functionalities_ignore_assumption',
					hash: hash,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						$item.fadeOut(300, function() {
							$(this).remove();
							checkEmptyList();
						});
					} else {
						alert(response.data?.message || 'Action failed.');
						$btn.prop('disabled', false);
					}
				}).fail(function() {
					alert('Request failed.');
					$btn.prop('disabled', false);
				});
			});

			function checkEmptyList() {
				if ($('.functionalities-assumption-item').length === 0) {
					$('.functionalities-assumptions-list').html(
						'<div class="functionalities-no-assumptions" style="display:flex;align-items:center;gap:10px;padding:15px;background:#edfaef;border-left:4px solid #00a32a;border-radius:4px;">' +
						'<span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:24px;width:24px;height:24px;"></span>' +
						'<span><?php echo \esc_js( \__( 'No assumption changes detected. All monitored items are consistent.', 'functionalities' ) ); ?></span>' +
						'</div>'
					);
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * Generate a warning hash using Assumption_Detection class method.
	 *
	 * @param array $warning Warning data.
	 * @return string Hash.
	 */
	protected static function generate_warning_hash( array $warning ) : string {
		return \Functionalities\Features\Assumption_Detection::get_warning_hash( $warning );
	}

	/**
	 * Render Task Manager module page.
	 *
	 * @param array $module Module configuration.
	 * @return void
	 */
	private static function render_module_task_manager( array $module ) : void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
		$current_project = isset( $_GET['project'] ) ? \sanitize_key( $_GET['project'] ) : '';
		$projects        = \Functionalities\Features\Task_Manager::get_projects();
		$project_data    = null;

		if ( $current_project && isset( $projects[ $current_project ] ) ) {
			$project_data = $projects[ $current_project ];
		}

		$nonce    = \wp_create_nonce( 'functionalities_task_manager' );
		$tm_opts  = (array) \get_option( 'functionalities_task_manager', array( 'enabled' => false ) );

		// Handle enable/disable toggle.
		if ( isset( $_POST['functionalities_task_manager_toggle'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'functionalities_task_manager_toggle' ) ) {
			$tm_opts['enabled'] = ! empty( $_POST['enabled'] );
			\update_option( 'functionalities_task_manager', $tm_opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Settings saved.', 'functionalities' ) . '</p></div>';
		}
		?>
		<div class="wrap functionalities-module functionalities-task-manager">
			<h1>
				<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
				<?php echo \esc_html( $module['title'] ); ?>
			</h1>

			<nav class="functionalities-breadcrumb">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>">
					<?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?>
				</a>
				<span class="separator">›</span>
				<?php if ( $project_data ) : ?>
					<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities&module=task-manager' ) ); ?>">
						<?php echo \esc_html( $module['title'] ); ?>
					</a>
					<span class="separator">›</span>
					<span class="current"><?php echo \esc_html( $project_data['name'] ); ?></span>
				<?php else : ?>
					<span class="current"><?php echo \esc_html( $module['title'] ); ?></span>
				<?php endif; ?>
			</nav>

			<form method="post">
				<?php \wp_nonce_field( 'functionalities_task_manager_toggle' ); ?>
				<input type="hidden" name="functionalities_task_manager_toggle" value="1" />
				<label class="tm-enable-toggle">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $tm_opts['enabled'] ) ); ?> onchange="this.form.submit()" />
					<strong><?php echo \esc_html__( 'Enable Task Manager', 'functionalities' ); ?></strong>
					<span><?php echo \esc_html__( 'File-based project task management', 'functionalities' ); ?></span>
				</label>
			</form>

			<?php if ( ! $project_data ) : ?>
				<?php self::render_task_manager_overview( $projects, $nonce ); ?>
			<?php else : ?>
				<?php self::render_task_manager_project( $project_data, $nonce ); ?>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Render Task Manager overview (project list).
	 *
	 * @param array  $projects List of projects.
	 * @param string $nonce    Security nonce.
	 * @return void
	 */
	private static function render_task_manager_overview( array $projects, string $nonce ) : void {
		?>
		<div class="tm-feature-info">
			<h2>
				<span class="dashicons dashicons-info-outline"></span>
				<?php \esc_html_e( 'About Task Manager', 'functionalities' ); ?>
			</h2>
			<p><?php \esc_html_e( 'A lightweight, file-based task management system with zero frontend footprint. Tasks are stored as JSON files in your wp-content directory.', 'functionalities' ); ?></p>
			<div class="tm-feature-grid">
				<div class="tm-feature-item">
					<span class="dashicons dashicons-yes"></span>
					<div>
						<strong><?php \esc_html_e( 'Check/Uncheck Tasks', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Click the checkbox to mark tasks complete or pending.', 'functionalities' ); ?></p>
					</div>
				</div>
				<div class="tm-feature-item">
					<span class="dashicons dashicons-tag"></span>
					<div>
						<strong><?php \esc_html_e( 'Tags with #hashtags', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Add #tag to tasks for categorization. Example: "Review code #urgent #frontend"', 'functionalities' ); ?></p>
					</div>
				</div>
				<div class="tm-feature-item">
					<span class="dashicons dashicons-flag"></span>
					<div>
						<strong><?php \esc_html_e( 'Priority Levels (!1, !2, !3)', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Add !1 (high), !2 (medium), or !3 (low) priority. Example: "Fix bug !1"', 'functionalities' ); ?></p>
					</div>
				</div>
				<div class="tm-feature-item">
					<span class="dashicons dashicons-edit"></span>
					<div>
						<strong><?php \esc_html_e( 'Notes for Each Task', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Add detailed notes and context to any task.', 'functionalities' ); ?></p>
					</div>
				</div>
				<div class="tm-feature-item">
					<span class="dashicons dashicons-download"></span>
					<div>
						<strong><?php \esc_html_e( 'Export & Import', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Export projects as JSON for backup or sharing. Import projects from JSON.', 'functionalities' ); ?></p>
					</div>
				</div>
				<div class="tm-feature-item">
					<span class="dashicons dashicons-dashboard"></span>
					<div>
						<strong><?php \esc_html_e( 'Dashboard Widget', 'functionalities' ); ?></strong>
						<p><?php \esc_html_e( 'Show any project as a dashboard widget for quick access.', 'functionalities' ); ?></p>
					</div>
				</div>
			</div>
			<p class="tm-storage-note">
				<span class="dashicons dashicons-portfolio"></span>
				<?php
				printf(
					/* translators: %s: directory path */
					\esc_html__( 'Tasks are stored in: %s', 'functionalities' ),
					'<code>' . \esc_html( WP_CONTENT_DIR . '/functionalities/tasks/' ) . '</code>'
				);
				?>
			</p>
		</div>

		<div class="tm-new-project">
			<label>
				<strong><?php \esc_html_e( 'New Project', 'functionalities' ); ?></strong>
				<input type="text" id="new-project-name" placeholder="<?php \esc_attr_e( 'Enter project name...', 'functionalities' ); ?>">
			</label>
			<button type="button" id="create-project-btn" class="button button-primary">
				<?php \esc_html_e( 'Create Project', 'functionalities' ); ?>
			</button>
			<button type="button" id="import-project-btn" class="button">
				<?php \esc_html_e( 'Import JSON', 'functionalities' ); ?>
			</button>
		</div>

		<?php if ( empty( $projects ) ) : ?>
			<div class="tm-empty-state">
				<span class="dashicons dashicons-welcome-add-page"></span>
				<h3><?php \esc_html_e( 'No Projects Yet', 'functionalities' ); ?></h3>
				<p><?php \esc_html_e( 'Create your first project to start managing tasks.', 'functionalities' ); ?></p>
			</div>
		<?php else : ?>
			<div class="tm-projects-grid">
				<?php foreach ( $projects as $slug => $project ) :
					$stats = \Functionalities\Features\Task_Manager::get_stats( $project );
					?>
					<div class="tm-project-card" data-project="<?php echo \esc_attr( $slug ); ?>">
						<h3>
							<span class="dashicons dashicons-portfolio"></span>
							<?php echo \esc_html( $project['name'] ); ?>
						</h3>
						<div class="tm-project-stats">
							<?php
							printf(
								/* translators: 1: completed count, 2: total count */
								\esc_html__( '%1$d of %2$d tasks completed', 'functionalities' ),
								(int) $stats['completed'],
								(int) $stats['total']
							);
							?>
						</div>
						<div class="tm-progress-bar">
							<div class="tm-progress-fill" style="width: <?php echo \esc_attr( $stats['percent'] ); ?>%;"></div>
						</div>
						<div class="tm-project-actions">
							<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities&module=task-manager&project=' . $slug ) ); ?>" class="button button-primary">
								<?php \esc_html_e( 'Open', 'functionalities' ); ?>
							</a>
							<button type="button" class="button export-project-btn" data-project="<?php echo \esc_attr( $slug ); ?>">
								<?php \esc_html_e( 'Export', 'functionalities' ); ?>
							</button>
							<button type="button" class="button delete-project-btn" data-project="<?php echo \esc_attr( $slug ); ?>" data-name="<?php echo \esc_attr( $project['name'] ); ?>">
								<?php \esc_html_e( 'Delete', 'functionalities' ); ?>
							</button>
						</div>
						<?php if ( ! empty( $project['show_widget'] ) ) : ?>
							<div class="tm-widget-badge">
								<span class="dashicons dashicons-dashboard"></span>
								<?php \esc_html_e( 'Shown on Dashboard', 'functionalities' ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Import Modal -->
		<div class="tm-modal-overlay" id="import-modal">
			<div class="tm-modal">
				<h3><?php \esc_html_e( 'Import Project', 'functionalities' ); ?></h3>
				<p><?php \esc_html_e( 'Paste the JSON content exported from another project:', 'functionalities' ); ?></p>
				<textarea class="tm-import-area" id="import-json-content" placeholder='{"name": "My Project", "tasks": [...]}'></textarea>
				<div class="tm-modal-actions">
					<button type="button" class="button" id="cancel-import-btn"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
					<button type="button" class="button button-primary" id="confirm-import-btn"><?php \esc_html_e( 'Import', 'functionalities' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Export Modal -->
		<div class="tm-modal-overlay" id="export-modal">
			<div class="tm-modal">
				<h3><?php \esc_html_e( 'Export Project', 'functionalities' ); ?></h3>
				<p><?php \esc_html_e( 'Copy this JSON to save or share your project:', 'functionalities' ); ?></p>
				<textarea class="tm-import-area" id="export-json-content" readonly></textarea>
				<div class="tm-modal-actions">
					<button type="button" class="button" id="close-export-btn"><?php \esc_html_e( 'Close', 'functionalities' ); ?></button>
					<button type="button" class="button button-primary" id="copy-export-btn"><?php \esc_html_e( 'Copy to Clipboard', 'functionalities' ); ?></button>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo \esc_js( $nonce ); ?>';
			var ajaxUrl = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';

			// Create project
			$('#create-project-btn').on('click', function() {
				var name = $('#new-project-name').val().trim();
				if (!name) {
					alert('<?php echo \esc_js( \__( 'Please enter a project name.', 'functionalities' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo \esc_js( \__( 'Creating...', 'functionalities' ) ); ?>');

				$.post(ajaxUrl, {
					action: 'functionalities_task_create_project',
					nonce: nonce,
					name: name
				}, function(response) {
					if (response.success) {
						window.location.href = '<?php echo \esc_js( \admin_url( 'admin.php?page=functionalities&module=task-manager&project=' ) ); ?>' + response.data.project.slug;
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to create project.', 'functionalities' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Create Project', 'functionalities' ) ); ?>');
					}
				}).fail(function() {
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
					$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Create Project', 'functionalities' ) ); ?>');
				});
			});

			// Delete project
			$('.delete-project-btn').on('click', function() {
				var project = $(this).data('project');
				var name = $(this).data('name');
				if (!confirm('<?php echo \esc_js( \__( 'Are you sure you want to delete the project:', 'functionalities' ) ); ?> "' + name + '"?')) {
					return;
				}

				$.post(ajaxUrl, {
					action: 'functionalities_task_delete_project',
					nonce: nonce,
					project: project
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to delete project.', 'functionalities' ) ); ?>');
					}
				});
			});

			// Export project
			$('.export-project-btn').on('click', function() {
				var project = $(this).data('project');
				$.post(ajaxUrl, {
					action: 'functionalities_task_export',
					nonce: nonce,
					project: project
				}, function(response) {
					if (response.success) {
						$('#export-json-content').val(response.data.json);
						$('#export-modal').addClass('active');
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to export project.', 'functionalities' ) ); ?>');
					}
				});
			});

			$('#close-export-btn').on('click', function() {
				$('#export-modal').removeClass('active');
			});

			$('#copy-export-btn').on('click', function() {
				$('#export-json-content').select();
				document.execCommand('copy');
				$(this).text('<?php echo \esc_js( \__( 'Copied!', 'functionalities' ) ); ?>');
				setTimeout(function() {
					$('#copy-export-btn').text('<?php echo \esc_js( \__( 'Copy to Clipboard', 'functionalities' ) ); ?>');
				}, 2000);
			});

			// Import project
			$('#import-project-btn').on('click', function() {
				$('#import-json-content').val('');
				$('#import-modal').addClass('active');
			});

			$('#cancel-import-btn').on('click', function() {
				$('#import-modal').removeClass('active');
			});

			$('#confirm-import-btn').on('click', function() {
				var json = $('#import-json-content').val().trim();
				if (!json) {
					alert('<?php echo \esc_js( \__( 'Please paste JSON content.', 'functionalities' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo \esc_js( \__( 'Importing...', 'functionalities' ) ); ?>');

				$.post(ajaxUrl, {
					action: 'functionalities_task_import',
					nonce: nonce,
					json: json
				}, function(response) {
					if (response.success) {
						window.location.href = '<?php echo \esc_js( \admin_url( 'admin.php?page=functionalities&module=task-manager&project=' ) ); ?>' + response.data.project.slug;
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to import project.', 'functionalities' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Import', 'functionalities' ) ); ?>');
					}
				}).fail(function() {
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
					$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Import', 'functionalities' ) ); ?>');
				});
			});

			// Close modals on overlay click
			$('.tm-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$(this).removeClass('active');
				}
			});

			// Enter key to create project
			$('#new-project-name').on('keypress', function(e) {
				if (e.which === 13) {
					$('#create-project-btn').click();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Task Manager project view.
	 *
	 * @param array  $project Project data.
	 * @param string $nonce   Security nonce.
	 * @return void
	 */
	private static function render_task_manager_project( array $project, string $nonce ) : void {
		$stats = \Functionalities\Features\Task_Manager::get_stats( $project );
		?>
		<div class="tm-project-header">
			<div>
				<h2>
					<?php echo \esc_html( $project['name'] ); ?>
					<span class="tm-task-count">
						(<?php printf( '%d/%d', (int) $stats['completed'], (int) $stats['total'] ); ?>)
					</span>
				</h2>
				<div class="tm-progress-bar tm-header-progress">
					<div class="tm-progress-fill" id="project-progress" style="width: <?php echo \esc_attr( $stats['percent'] ); ?>%;"></div>
				</div>
			</div>
			<div class="tm-toolbar">
				<div class="tm-view-toggle">
					<button type="button" class="tm-view-btn active" data-view="list" title="<?php \esc_attr_e( 'List View', 'functionalities' ); ?>">
						<span class="dashicons dashicons-list-view"></span>
					</button>
					<button type="button" class="tm-view-btn" data-view="columns" title="<?php \esc_attr_e( 'Column View', 'functionalities' ); ?>">
						<span class="dashicons dashicons-columns"></span>
					</button>
				</div>
				<label class="tm-widget-label">
					<input type="checkbox" id="show-widget-toggle" <?php checked( ! empty( $project['show_widget'] ) ); ?>>
					<?php \esc_html_e( 'Show on Dashboard', 'functionalities' ); ?>
				</label>
				<button type="button" class="button" id="import-drafts-btn">
					<?php \esc_html_e( 'Import Drafts', 'functionalities' ); ?>
				</button>
				<button type="button" class="button" id="export-this-project-btn">
					<?php \esc_html_e( 'Export', 'functionalities' ); ?>
				</button>
			</div>
		</div>

		<div class="tm-task-list">
			<div class="tm-filters">
				<div class="tm-search-wrap">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="task-search" placeholder="<?php \esc_attr_e( 'Search tasks...', 'functionalities' ); ?>">
				</div>
				<select id="filter-priority">
					<option value="all"><?php \esc_html_e( 'All Priorities', 'functionalities' ); ?></option>
					<option value="1"><?php \esc_html_e( 'High (!1)', 'functionalities' ); ?></option>
					<option value="2"><?php \esc_html_e( 'Medium (!2)', 'functionalities' ); ?></option>
					<option value="3"><?php \esc_html_e( 'Low (!3)', 'functionalities' ); ?></option>
				</select>
				<select id="filter-status">
					<option value="all"><?php \esc_html_e( 'All Status', 'functionalities' ); ?></option>
					<option value="pending"><?php \esc_html_e( 'Pending', 'functionalities' ); ?></option>
					<option value="completed"><?php \esc_html_e( 'Completed', 'functionalities' ); ?></option>
				</select>
				<button type="button" class="button tm-clear-completed" id="clear-completed-btn">
					<span class="dashicons dashicons-dismiss"></span>
					<?php \esc_html_e( 'Clear Completed', 'functionalities' ); ?>
				</button>
			</div>

			<div class="tm-add-task">
				<div class="tm-add-task-input-wrap">
					<input type="text" id="new-task-text" placeholder="<?php \esc_attr_e( 'Add a new task... (use @post to link, #tag for tags, !1/!2/!3 for priority)', 'functionalities' ); ?>">
					<div id="post-search-dropdown" class="post-search-dropdown"></div>
				</div>
				<div class="tm-add-task-hint">
					<?php \esc_html_e( 'Examples: "Review @my-post-title !1" or "Update documentation #docs !3" — Type @ to search posts', 'functionalities' ); ?>
				</div>
				<textarea id="new-task-notes" placeholder="<?php \esc_attr_e( 'Optional notes...', 'functionalities' ); ?>"></textarea>
				<button type="button" id="add-task-btn" class="button button-primary">
					<?php \esc_html_e( 'Add Task', 'functionalities' ); ?>
				</button>
			</div>

			<div id="tasks-container">
				<?php if ( empty( $project['tasks'] ) ) : ?>
					<div class="tm-empty-state">
						<p><?php \esc_html_e( 'No tasks yet. Add your first task above.', 'functionalities' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $project['tasks'] as $task ) :
						$completed_class = ! empty( $task['completed'] ) ? ' completed' : '';
						?>
						<div class="task-item<?php echo \esc_attr( $completed_class ); ?>" data-task-id="<?php echo \esc_attr( $task['id'] ); ?>">
							<div class="task-drag-handle" title="<?php \esc_attr_e( 'Drag to reorder', 'functionalities' ); ?>">
								<span class="dashicons dashicons-menu"></span>
							</div>
							<input type="checkbox" class="task-checkbox" <?php checked( ! empty( $task['completed'] ) ); ?>>
							<div class="task-content">
								<p class="task-text"><?php echo \esc_html( $task['text'] ); ?></p>
								<div class="task-meta">
									<?php if ( ! empty( $task['priority'] ) ) : ?>
										<span class="task-priority p<?php echo \esc_attr( $task['priority'] ); ?>">
											!<?php echo \esc_html( $task['priority'] ); ?>
										</span>
									<?php endif; ?>
									<?php if ( ! empty( $task['tags'] ) ) :
										foreach ( $task['tags'] as $tag ) : ?>
											<span class="task-tag">#<?php echo \esc_html( $tag ); ?></span>
										<?php endforeach;
									endif; ?>
								</div>
								<?php if ( ! empty( $task['notes'] ) ) : ?>
									<div class="task-notes"><?php echo \esc_html( $task['notes'] ); ?></div>
								<?php endif; ?>
							</div>
							<div class="task-actions">
								<button type="button" class="button edit-task-btn" title="<?php \esc_attr_e( 'Edit', 'functionalities' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button type="button" class="button delete-task-btn" title="<?php \esc_attr_e( 'Delete', 'functionalities' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Columns View (for priority-based display) -->
		<div id="tasks-columns-view" class="tm-columns-view">
			<div class="tm-column p1">
				<div class="tm-column-header">
					<span style="color: #d63638;">!1</span> <?php \esc_html_e( 'High', 'functionalities' ); ?>
					<span class="tm-column-count" id="count-p1">0</span>
				</div>
				<div class="tm-column-tasks" data-priority="1"></div>
			</div>
			<div class="tm-column p2">
				<div class="tm-column-header">
					<span style="color: #dba617;">!2</span> <?php \esc_html_e( 'Medium', 'functionalities' ); ?>
					<span class="tm-column-count" id="count-p2">0</span>
				</div>
				<div class="tm-column-tasks" data-priority="2"></div>
			</div>
			<div class="tm-column p3">
				<div class="tm-column-header">
					<span style="color: #2271b1;">!3</span> <?php \esc_html_e( 'Low', 'functionalities' ); ?>
					<span class="tm-column-count" id="count-p3">0</span>
				</div>
				<div class="tm-column-tasks" data-priority="3"></div>
			</div>
			<div class="tm-column p0">
				<div class="tm-column-header">
					<?php \esc_html_e( 'No Priority', 'functionalities' ); ?>
					<span class="tm-column-count" id="count-p0">0</span>
				</div>
				<div class="tm-column-tasks" data-priority="0"></div>
			</div>
		</div>

		<!-- Edit Task Modal -->
		<div class="tm-modal-overlay" id="edit-task-modal">
			<div class="tm-modal">
				<h3><?php \esc_html_e( 'Edit Task', 'functionalities' ); ?></h3>
				<input type="hidden" id="edit-task-id">
				<div class="tm-modal-field">
					<label for="edit-task-text"><?php \esc_html_e( 'Task', 'functionalities' ); ?></label>
					<input type="text" id="edit-task-text">
				</div>
				<div class="tm-modal-field">
					<label for="edit-task-notes"><?php \esc_html_e( 'Notes', 'functionalities' ); ?></label>
					<textarea id="edit-task-notes"></textarea>
				</div>
				<div class="tm-modal-field">
					<label for="edit-task-priority"><?php \esc_html_e( 'Priority', 'functionalities' ); ?></label>
					<select id="edit-task-priority">
						<option value="0"><?php \esc_html_e( 'No priority', 'functionalities' ); ?></option>
						<option value="1"><?php \esc_html_e( '!1 - High', 'functionalities' ); ?></option>
						<option value="2"><?php \esc_html_e( '!2 - Medium', 'functionalities' ); ?></option>
						<option value="3"><?php \esc_html_e( '!3 - Low', 'functionalities' ); ?></option>
					</select>
				</div>
				<div class="tm-modal-field">
					<label for="edit-task-tags"><?php \esc_html_e( 'Tags', 'functionalities' ); ?></label>
					<input type="text" id="edit-task-tags" placeholder="<?php \esc_attr_e( 'Comma-separated: urgent, frontend, bug', 'functionalities' ); ?>">
				</div>
				<div class="tm-modal-actions">
					<button type="button" class="button" id="cancel-edit-btn"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
					<button type="button" class="button button-primary" id="save-edit-btn"><?php \esc_html_e( 'Save', 'functionalities' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Export Modal -->
		<div class="tm-modal-overlay" id="export-modal">
			<div class="tm-modal">
				<h3><?php \esc_html_e( 'Export Project', 'functionalities' ); ?></h3>
				<p><?php \esc_html_e( 'Copy this JSON to save or share your project:', 'functionalities' ); ?></p>
				<textarea class="tm-import-area" id="export-json-content" readonly></textarea>
				<div class="tm-modal-actions">
					<button type="button" class="button" id="close-export-btn"><?php \esc_html_e( 'Close', 'functionalities' ); ?></button>
					<button type="button" class="button button-primary" id="copy-export-btn"><?php \esc_html_e( 'Copy to Clipboard', 'functionalities' ); ?></button>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo \esc_js( $nonce ); ?>';
			var ajaxUrl = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';
			var projectSlug = '<?php echo \esc_js( $project['slug'] ); ?>';
			var totalTasks = <?php echo (int) count( $project['tasks'] ); ?>;
			var completedTasks = <?php echo (int) $stats['completed']; ?>;

			// Focus new task input
			$('#new-task-text').focus();

			// Filtering logic
			function applyFilters() {
				var searchTerm = $('#task-search').val().toLowerCase();
				var priorityFilter = $('#filter-priority').val();
				var statusFilter = $('#filter-status').val();

				$('.task-item').each(function() {
					var $item = $(this);
					var text = $item.find('.task-text').text().toLowerCase();
					var notes = $item.find('.task-notes').text().toLowerCase();
					var tags = '';
					$item.find('.task-tag').each(function() {
						tags += $(this).text().toLowerCase() + ' ';
					});
					
					var priority = '0';
					var priorityClasses = $item.find('.task-priority').attr('class');
					if (priorityClasses) {
						var match = priorityClasses.match(/p(\d)/);
						if (match) priority = match[1];
					}

					var isCompleted = $item.hasClass('completed');

					var matchesSearch = text.indexOf(searchTerm) !== -1 || notes.indexOf(searchTerm) !== -1 || tags.indexOf(searchTerm) !== -1;
					var matchesPriority = priorityFilter === 'all' || priority === priorityFilter;
					var matchesStatus = statusFilter === 'all' || 
						(statusFilter === 'completed' && isCompleted) || 
						(statusFilter === 'pending' && !isCompleted);

					if (matchesSearch && matchesPriority && matchesStatus) {
						$item.show();
					} else {
						$item.hide();
					}
				});
			}

			$('#task-search').on('input', applyFilters);
			$('#filter-priority, #filter-status').on('change', applyFilters);

			// Tag click filtering
			$(document).on('click', '.task-tag', function(e) {
				e.preventDefault();
				var tag = $(this).text().replace('#', '').trim();
				$('#task-search').val(tag).trigger('input');
			});

			// Clear completed
			$('#clear-completed-btn').on('click', function() {
				var completedIds = [];
				$('.task-item.completed').each(function() {
					completedIds.push($(this).data('task-id'));
				});

				if (completedIds.length === 0) {
					alert('<?php echo \esc_js( \__( 'No completed tasks to clear.', 'functionalities' ) ); ?>');
					return;
				}

				if (!confirm('<?php echo \esc_js( \__( 'Are you sure you want to delete all completed tasks?', 'functionalities' ) ); ?>')) {
					return;
				}

				var deletedCount = 0;
				var totalToDelete = completedIds.length;

				completedIds.forEach(function(id) {
					$.post(ajaxUrl, {
						action: 'functionalities_task_delete',
						nonce: nonce,
						project: projectSlug,
						task_id: id
					}, function(response) {
						if (response.success) {
							deletedCount++;
							if (deletedCount === totalToDelete) {
								location.reload();
							}
						}
					});
				});
			});

			// Drag and drop reordering
			if ($.fn.sortable) {
				$('#tasks-container').sortable({
					items: '.task-item',
					axis: 'y',
					handle: '.task-drag-handle',
					placeholder: 'ui-state-highlight',
					update: function() {
						var taskIds = [];
						$('.task-item').each(function() {
							taskIds.push($(this).data('task-id'));
						});

						$.post(ajaxUrl, {
							action: 'functionalities_task_reorder',
							nonce: nonce,
							project: projectSlug,
							task_ids: taskIds
						});
					}
				});
			}

			function updateProgress() {
				var percent = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;
				$('#project-progress').css('width', percent + '%');
			}

			// Add task
			$('#add-task-btn').on('click', function() {
				var text = $('#new-task-text').val().trim();
				var notes = $('#new-task-notes').val().trim();
				if (!text) {
					alert('<?php echo \esc_js( \__( 'Please enter task text.', 'functionalities' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true);

				$.post(ajaxUrl, {
					action: 'functionalities_task_add',
					nonce: nonce,
					project: projectSlug,
					text: text,
					notes: notes
				}, function(response) {
					if (response.success) {
						$('#new-task-text').val('');
						$('#new-task-notes').val('');
						totalTasks++;
						updateProgress();
						// Reload to show new task (simple approach)
						location.reload();
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to add task.', 'functionalities' ) ); ?>');
					}
					$btn.prop('disabled', false);
				}).fail(function() {
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
					$btn.prop('disabled', false);
				});
			});

			// Keyboard shortcuts for adding task
			$('#new-task-text, #new-task-notes').on('keydown', function(e) {
				if ((e.ctrlKey || e.metaKey) && e.which === 13) {
					$('#add-task-btn').click();
				}
			});

			// Enter key to add task (simple enter on title)
			$('#new-task-text').on('keypress', function(e) {
				if (e.which === 13 && !e.ctrlKey && !e.metaKey) {
					$('#add-task-btn').click();
				}
			});

			// Toggle task
			$(document).on('change', '.task-checkbox', function() {
				var $checkbox = $(this);
				var $item = $checkbox.closest('.task-item');
				var taskId = $item.data('task-id');

				$.post(ajaxUrl, {
					action: 'functionalities_task_toggle',
					nonce: nonce,
					project: projectSlug,
					task_id: taskId
				}, function(response) {
					if (response.success) {
						if (response.data.completed) {
							$item.addClass('completed');
							completedTasks++;
						} else {
							$item.removeClass('completed');
							completedTasks--;
						}
						updateProgress();
					} else {
						$checkbox.prop('checked', !$checkbox.prop('checked'));
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to update task.', 'functionalities' ) ); ?>');
					}
				}).fail(function() {
					$checkbox.prop('checked', !$checkbox.prop('checked'));
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
				});
			});

			// Delete task
			$(document).on('click', '.delete-task-btn', function() {
				if (!confirm('<?php echo \esc_js( \__( 'Delete this task?', 'functionalities' ) ); ?>')) {
					return;
				}

				var $item = $(this).closest('.task-item');
				var taskId = $item.data('task-id');
				var wasCompleted = $item.hasClass('completed');

				$.post(ajaxUrl, {
					action: 'functionalities_task_delete',
					nonce: nonce,
					project: projectSlug,
					task_id: taskId
				}, function(response) {
					if (response.success) {
						$item.fadeOut(300, function() {
							$(this).remove();
							totalTasks--;
							if (wasCompleted) completedTasks--;
							updateProgress();
							if ($('.task-item').length === 0) {
								$('#tasks-container').html('<div class="tm-empty-state"><p><?php echo \esc_js( \__( 'No tasks yet. Add your first task above.', 'functionalities' ) ); ?></p></div>');
							}
						});
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to delete task.', 'functionalities' ) ); ?>');
					}
				});
			});

			// Edit task - open modal
			$(document).on('click', '.edit-task-btn', function() {
				var $item = $(this).closest('.task-item');
				var taskId = $item.data('task-id');
				var text = $item.find('.task-text').text();
				var notes = $item.find('.task-notes').text() || '';
				var priority = 0;
				var $priority = $item.find('.task-priority');
				if ($priority.length) {
					priority = parseInt($priority.text().replace('!', ''), 10);
				}
				var tags = [];
				$item.find('.task-tag').each(function() {
					tags.push($(this).text().replace('#', ''));
				});

				$('#edit-task-id').val(taskId);
				$('#edit-task-text').val(text);
				$('#edit-task-notes').val(notes);
				$('#edit-task-priority').val(priority);
				$('#edit-task-tags').val(tags.join(', '));
				$('#edit-task-modal').addClass('active');
				$('#edit-task-text').focus();
			});

			$('#edit-task-text, #edit-task-notes, #edit-task-tags, #edit-task-priority').on('keydown', function(e) {
				if ((e.ctrlKey || e.metaKey) && e.which === 13) {
					$('#save-edit-btn').click();
				}
			});

			$('#cancel-edit-btn').on('click', function() {
				$('#edit-task-modal').removeClass('active');
			});

			$('#save-edit-btn').on('click', function() {
				var taskId = $('#edit-task-id').val();
				var text = $('#edit-task-text').val().trim();
				var notes = $('#edit-task-notes').val().trim();
				var priority = parseInt($('#edit-task-priority').val(), 10);
				var tags = $('#edit-task-tags').val().split(',').map(function(t) { return t.trim(); }).filter(function(t) { return t; });

				if (!text) {
					alert('<?php echo \esc_js( \__( 'Task text is required.', 'functionalities' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true);

				$.post(ajaxUrl, {
					action: 'functionalities_task_update',
					nonce: nonce,
					project: projectSlug,
					task_id: taskId,
					text: text,
					notes: notes,
					priority: priority,
					tags: tags
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to update task.', 'functionalities' ) ); ?>');
						$btn.prop('disabled', false);
					}
				}).fail(function() {
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
					$btn.prop('disabled', false);
				});
			});

			// Show widget toggle
			$('#show-widget-toggle').on('change', function() {
				var showWidget = $(this).prop('checked');
				$.post(ajaxUrl, {
					action: 'functionalities_task_update_widget_setting',
					nonce: nonce,
					project: projectSlug,
					show_widget: showWidget ? 'true' : 'false'
				}, function(response) {
					if (!response.success) {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to update setting.', 'functionalities' ) ); ?>');
						$('#show-widget-toggle').prop('checked', !showWidget);
					}
				});
			});

			// Import Drafts
			$('#import-drafts-btn').on('click', function() {
				if (!confirm('<?php echo \esc_js( \__( 'Import all draft posts as tasks? This will scan for all drafts and add them to this project.', 'functionalities' ) ); ?>')) {
					return;
				}
				
				var $btn = $(this);
				var originalText = $btn.html();
				$btn.prop('disabled', true).text('<?php echo \esc_js( \__( 'Importing...', 'functionalities' ) ); ?>');

				$.post(ajaxUrl, {
					action: 'functionalities_task_import_drafts',
					nonce: nonce,
					project: projectSlug
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to import drafts.', 'functionalities' ) ); ?>');
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});

			// Export this project
			$('#export-this-project-btn').on('click', function() {
				$.post(ajaxUrl, {
					action: 'functionalities_task_export',
					nonce: nonce,
					project: projectSlug
				}, function(response) {
					if (response.success) {
						$('#export-json-content').val(response.data.json);
						$('#export-modal').addClass('active');
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to export project.', 'functionalities' ) ); ?>');
					}
				});
			});

			$('#close-export-btn').on('click', function() {
				$('#export-modal').removeClass('active');
			});

			$('#copy-export-btn').on('click', function() {
				$('#export-json-content').select();
				document.execCommand('copy');
				$(this).text('<?php echo \esc_js( \__( 'Copied!', 'functionalities' ) ); ?>');
				setTimeout(function() {
					$('#copy-export-btn').text('<?php echo \esc_js( \__( 'Copy to Clipboard', 'functionalities' ) ); ?>');
				}, 2000);
			});

			// Close modals on overlay click
			$('.tm-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$(this).removeClass('active');
				}
			});

			// ========================================
			// View Mode Toggle (List vs Columns)
			// ========================================
			var currentViewMode = 'list';

			$('.tm-view-btn').on('click', function() {
				var viewMode = $(this).data('view');
				if (viewMode === currentViewMode) return;

				currentViewMode = viewMode;
				$('.tm-view-btn').removeClass('active');
				$(this).addClass('active');

				if (viewMode === 'columns') {
					$('.tm-task-list').hide();
					$('#tasks-columns-view').addClass('active');
					populateColumnsView();
				} else {
					$('#tasks-columns-view').removeClass('active');
					$('.tm-task-list').show();
				}
			});

			function populateColumnsView() {
				// Clear all columns
				$('.tm-column-tasks').empty();

				// Counters for each priority
				var counts = { p0: 0, p1: 0, p2: 0, p3: 0 };

				// Get all tasks from the list view and clone them to columns
				$('#tasks-container .task-item').each(function() {
					var $task = $(this);
					var priorityClasses = $task.find('.task-priority').attr('class') || '';
					var priority = '0';
					var match = priorityClasses.match(/p(\d)/);
					if (match) priority = match[1];

					// Clone the task
					var $clone = $task.clone();
					$clone.show(); // Show in case filtered

					// Add to appropriate column
					$('.tm-column-tasks[data-priority="' + priority + '"]').append($clone);
					counts['p' + priority]++;
				});

				// Update counts
				$('#count-p0').text(counts.p0);
				$('#count-p1').text(counts.p1);
				$('#count-p2').text(counts.p2);
				$('#count-p3').text(counts.p3);

				// Add empty states
				$('.tm-column-tasks').each(function() {
					if ($(this).children().length === 0) {
						$(this).html('<div class="tm-column-empty"><?php echo \esc_js( \__( 'No tasks', 'functionalities' ) ); ?></div>');
					}
				});
			}

			// ========================================
			// Post Search (@mention) Autocomplete
			// ========================================
			var $taskInput = $('#new-task-text');
			var $dropdown = $('#post-search-dropdown');
			var searchTimeout = null;
			var isSearching = false;
			var atPosition = -1;

			$taskInput.on('input', function() {
				var value = $(this).val();
				var cursorPos = this.selectionStart;

				// Find the @ position before cursor
				var textBeforeCursor = value.substring(0, cursorPos);
				var atIndex = textBeforeCursor.lastIndexOf('@');

				if (atIndex !== -1) {
					// Check if @ is at start or preceded by space
					if (atIndex === 0 || textBeforeCursor[atIndex - 1] === ' ') {
						var searchTerm = textBeforeCursor.substring(atIndex + 1);
						
						// Check if no space after the search term (still typing)
						if (searchTerm.indexOf(' ') === -1 && searchTerm.length >= 2) {
							atPosition = atIndex;
							clearTimeout(searchTimeout);
							searchTimeout = setTimeout(function() {
								searchPosts(searchTerm);
							}, 300);
							return;
						}
					}
				}

				// Hide dropdown if no valid @ pattern
				hideDropdown();
			});

			$taskInput.on('keydown', function(e) {
				if (!$dropdown.hasClass('active')) return;

				var $items = $dropdown.find('.post-search-item');
				var $selected = $items.filter('.selected');

				if (e.which === 40) { // Down arrow
					e.preventDefault();
					if ($selected.length === 0) {
						$items.first().addClass('selected');
					} else {
						$selected.removeClass('selected');
						var $next = $selected.next('.post-search-item');
						if ($next.length) {
							$next.addClass('selected');
						} else {
							$items.first().addClass('selected');
						}
					}
				} else if (e.which === 38) { // Up arrow
					e.preventDefault();
					if ($selected.length === 0) {
						$items.last().addClass('selected');
					} else {
						$selected.removeClass('selected');
						var $prev = $selected.prev('.post-search-item');
						if ($prev.length) {
							$prev.addClass('selected');
						} else {
							$items.last().addClass('selected');
						}
					}
				} else if (e.which === 13 || e.which === 9) { // Enter or Tab
					if ($selected.length) {
						e.preventDefault();
						e.stopPropagation();
						selectPost($selected);
					}
				} else if (e.which === 27) { // Escape
					hideDropdown();
				}
			});

			function searchPosts(term) {
				if (isSearching) return;
				isSearching = true;

				$dropdown.html('<div class="post-search-loading"><?php echo \esc_js( \__( 'Searching...', 'functionalities' ) ); ?></div>');
				$dropdown.addClass('active');

				$.post(ajaxUrl, {
					action: 'functionalities_task_search_posts',
					nonce: nonce,
					search: term
				}, function(response) {
					isSearching = false;
					if (response.success && response.data.posts.length > 0) {
						var html = '';
						response.data.posts.forEach(function(post) {
							html += '<div class="post-search-item" data-id="' + post.id + '" data-title="' + escapeHtml(post.title) + '" data-edit-url="' + post.edit_url + '">';
							html += '<span class="post-title">' + escapeHtml(post.title) + '</span>';
							html += '<span class="post-status ' + post.status + '">' + post.status + '</span>';
							html += '</div>';
						});
						$dropdown.html(html);
					} else {
						$dropdown.html('<div class="post-search-empty"><?php echo \esc_js( \__( 'No posts found', 'functionalities' ) ); ?></div>');
					}
				}).fail(function() {
					isSearching = false;
					hideDropdown();
				});
			}

			function selectPost($item) {
				var title = $item.data('title');
				var value = $taskInput.val();
				var cursorPos = $taskInput[0].selectionStart;

				// Replace @searchterm with the post title
				var beforeAt = value.substring(0, atPosition);
				var afterCursor = value.substring(cursorPos);

				var newValue = beforeAt + '@' + title + ' ' + afterCursor.trimStart();
				$taskInput.val(newValue);

				// Set cursor position after the inserted title
				var newCursorPos = atPosition + title.length + 2;
				$taskInput[0].setSelectionRange(newCursorPos, newCursorPos);
				$taskInput.focus();

				hideDropdown();
			}

			function hideDropdown() {
				$dropdown.removeClass('active').empty();
				atPosition = -1;
			}

			function escapeHtml(str) {
				return $('<div>').text(str).html();
			}

			// Click to select post
			$(document).on('click', '.post-search-item', function() {
				selectPost($(this));
			});

			// Hide dropdown when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.tm-add-task-input-wrap').length) {
					hideDropdown();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Redirect Manager module page.
	 *
	 * @param array $module Module configuration.
	 * @return void
	 */
	private static function render_module_redirect_manager( array $module ) : void {
		$redirects = \Functionalities\Features\Redirect_Manager::get_redirects();
		$stats     = \Functionalities\Features\Redirect_Manager::get_stats();
		$nonce     = \wp_create_nonce( 'functionalities_redirect_manager' );
		$rm_opts   = (array) \get_option( 'functionalities_redirect_manager', array( 'enabled' => false ) );

		// Handle enable/disable toggle.
		if ( isset( $_POST['functionalities_redirect_manager_toggle'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'functionalities_redirect_manager_toggle' ) ) {
			$rm_opts['enabled'] = ! empty( $_POST['enabled'] );
			\update_option( 'functionalities_redirect_manager', $rm_opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Settings saved.', 'functionalities' ) . '</p></div>';
		}
		?>
		<div class="wrap functionalities-module functionalities-redirect-manager">
			<h1>
				<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
				<?php echo \esc_html( $module['title'] ); ?>
			</h1>

			<nav class="functionalities-breadcrumb">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>">
					<?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?>
				</a>
				<span class="separator">›</span>
				<span class="current"><?php echo \esc_html( $module['title'] ); ?></span>
			</nav>

			<form method="post" style="margin:15px 0;">
				<?php \wp_nonce_field( 'functionalities_redirect_manager_toggle' ); ?>
				<input type="hidden" name="functionalities_redirect_manager_toggle" value="1" />
				<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $rm_opts['enabled'] ) ); ?> onchange="this.form.submit()" />
					<strong><?php echo \esc_html__( 'Enable Redirect Manager', 'functionalities' ); ?></strong>
					<span style="color:#646970;font-size:13px;"><?php echo \esc_html__( 'Create and manage URL redirects', 'functionalities' ); ?></span>
				</label>
			</form>

			<div class="feature-info" style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin:20px 0;border-radius:4px;">
				<h2 style="margin-top:0;"><?php \esc_html_e( 'URL Redirects', 'functionalities' ); ?></h2>
				<p><?php \esc_html_e( 'Manage 301 (permanent) and 302 (temporary) redirects. Redirects are stored in a JSON file with zero database overhead.', 'functionalities' ); ?></p>
				<div style="display:flex;gap:20px;margin-top:15px;">
					<div style="background:#f0f6fc;padding:10px 15px;border-radius:4px;text-align:center;">
						<strong style="font-size:24px;color:#2271b1;"><?php echo (int) $stats['total']; ?></strong>
						<div style="font-size:12px;color:#646970;"><?php \esc_html_e( 'Total', 'functionalities' ); ?></div>
					</div>
					<div style="background:#f0fdf4;padding:10px 15px;border-radius:4px;text-align:center;">
						<strong style="font-size:24px;color:#16a34a;"><?php echo (int) $stats['enabled']; ?></strong>
						<div style="font-size:12px;color:#646970;"><?php \esc_html_e( 'Active', 'functionalities' ); ?></div>
					</div>
					<div style="background:#fef3c7;padding:10px 15px;border-radius:4px;text-align:center;">
						<strong style="font-size:24px;color:#d97706;"><?php echo (int) $stats['hits']; ?></strong>
						<div style="font-size:12px;color:#646970;"><?php \esc_html_e( 'Total Hits', 'functionalities' ); ?></div>
					</div>
				</div>
			</div>

			<div style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin-bottom:20px;border-radius:4px;">
				<h3 style="margin-top:0;"><?php \esc_html_e( 'Add New Redirect', 'functionalities' ); ?></h3>
				<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
					<label style="flex:1;min-width:200px;">
						<span style="display:block;font-weight:600;margin-bottom:5px;"><?php \esc_html_e( 'From URL', 'functionalities' ); ?></span>
						<input type="text" id="redirect-from" placeholder="/old-page" style="width:100%;">
					</label>
					<label style="flex:1;min-width:200px;">
						<span style="display:block;font-weight:600;margin-bottom:5px;"><?php \esc_html_e( 'To URL', 'functionalities' ); ?></span>
						<input type="text" id="redirect-to" placeholder="https://example.com/new-page" style="width:100%;">
					</label>
					<label style="width:100px;">
						<span style="display:block;font-weight:600;margin-bottom:5px;"><?php \esc_html_e( 'Type', 'functionalities' ); ?></span>
						<select id="redirect-type" style="width:100%;">
							<option value="301">301</option>
							<option value="302">302</option>
							<option value="307">307</option>
						</select>
					</label>
					<button type="button" id="add-redirect-btn" class="button button-primary"><?php \esc_html_e( 'Add Redirect', 'functionalities' ); ?></button>
				</div>
				<p class="description" style="margin-top:10px;"><?php \esc_html_e( 'Use * at the end for wildcard matching (e.g., /old-section/*)', 'functionalities' ); ?></p>
			</div>

			<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
				<div style="padding:15px;border-bottom:1px solid #f0f0f1;display:flex;justify-content:flex-end;">
					<div style="position:relative;">
						<span class="dashicons dashicons-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#646970;"></span>
						<input type="text" id="redirect-search" placeholder="<?php \esc_attr_e( 'Search redirects...', 'functionalities' ); ?>" style="padding-left:35px;width:250px;">
					</div>
				</div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:30px;"><?php \esc_html_e( 'On', 'functionalities' ); ?></th>
							<th><?php \esc_html_e( 'From', 'functionalities' ); ?></th>
							<th><?php \esc_html_e( 'To', 'functionalities' ); ?></th>
							<th style="width:60px;"><?php \esc_html_e( 'Type', 'functionalities' ); ?></th>
							<th style="width:60px;"><?php \esc_html_e( 'Hits', 'functionalities' ); ?></th>
							<th style="width:100px;"><?php \esc_html_e( 'Actions', 'functionalities' ); ?></th>
						</tr>
					</thead>
					<tbody id="redirects-list">
						<?php if ( empty( $redirects ) ) : ?>
							<tr class="no-items"><td colspan="6" style="text-align:center;padding:20px;color:#646970;"><?php \esc_html_e( 'No redirects yet. Add one above.', 'functionalities' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $redirects as $r ) : ?>
								<tr data-id="<?php echo \esc_attr( $r['id'] ); ?>">
									<td><input type="checkbox" class="toggle-redirect" <?php checked( ! empty( $r['enabled'] ) ); ?>></td>
									<td><code><?php echo \esc_html( $r['from'] ); ?></code></td>
									<td style="word-break:break-all;"><?php echo \esc_html( $r['to'] ); ?></td>
									<td><?php echo \esc_html( $r['type'] ); ?></td>
									<td><?php echo \esc_html( $r['hits'] ?? 0 ); ?></td>
									<td>
										<button type="button" class="button button-small delete-redirect"><?php \esc_html_e( 'Delete', 'functionalities' ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div style="margin-top:20px;display:flex;gap:10px;">
				<button type="button" id="export-redirects-btn" class="button"><?php \esc_html_e( 'Export JSON', 'functionalities' ); ?></button>
				<button type="button" id="import-redirects-btn" class="button"><?php \esc_html_e( 'Import JSON', 'functionalities' ); ?></button>
			</div>

			<div class="modal-overlay" id="import-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center;">
				<div style="background:#fff;padding:20px;border-radius:4px;max-width:500px;width:90%;">
					<h3 style="margin-top:0;"><?php \esc_html_e( 'Import Redirects', 'functionalities' ); ?></h3>
					<textarea id="import-json" style="width:100%;height:200px;font-family:monospace;" placeholder='[{"from": "/old", "to": "/new", "type": 301}]'></textarea>
					<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
						<button type="button" class="button" id="cancel-import"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						<button type="button" class="button button-primary" id="confirm-import"><?php \esc_html_e( 'Import', 'functionalities' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo \esc_js( $nonce ); ?>';
			var ajaxUrl = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';

			// Search functionality.
			$('#redirect-search').on('input', function() {
				var term = $(this).val().toLowerCase();
				$('#redirects-list tr:not(.no-items)').each(function() {
					var from = $(this).find('td:eq(1)').text().toLowerCase();
					var to = $(this).find('td:eq(2)').text().toLowerCase();
					if (from.indexOf(term) !== -1 || to.indexOf(term) !== -1) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			});

			$('#add-redirect-btn').on('click', function() {
				var from = $('#redirect-from').val().trim();
				var to = $('#redirect-to').val().trim();
				var type = $('#redirect-type').val();
				if (!from || !to) { alert('<?php echo \esc_js( \__( 'Both URLs are required.', 'functionalities' ) ); ?>'); return; }
				$.post(ajaxUrl, { action: 'functionalities_redirect_add', nonce: nonce, from: from, to: to, type: type }, function(r) {
					if (r.success) { location.reload(); } else { alert(r.data?.message || 'Error'); }
				});
			});

			$(document).on('change', '.toggle-redirect', function() {
				var id = $(this).closest('tr').data('id');
				$.post(ajaxUrl, { action: 'functionalities_redirect_toggle', nonce: nonce, id: id });
			});

			$(document).on('click', '.delete-redirect', function() {
				if (!confirm('<?php echo \esc_js( \__( 'Delete this redirect?', 'functionalities' ) ); ?>')) return;
				var $row = $(this).closest('tr');
				$.post(ajaxUrl, { action: 'functionalities_redirect_delete', nonce: nonce, id: $row.data('id') }, function(r) {
					if (r.success) { $row.fadeOut(function() { $(this).remove(); }); }
				});
			});

			$('#export-redirects-btn').on('click', function() {
				$.post(ajaxUrl, { action: 'functionalities_redirect_export', nonce: nonce }, function(r) {
					if (r.success) {
						var blob = new Blob([r.data.json], {type: 'application/json'});
						var a = document.createElement('a');
						a.href = URL.createObjectURL(blob);
						a.download = 'redirects.json';
						a.click();
					}
				});
			});

			$('#import-redirects-btn').on('click', function() { $('#import-modal').css('display','flex'); });
			$('#cancel-import').on('click', function() { $('#import-modal').hide(); });
			$('#confirm-import').on('click', function() {
				var json = $('#import-json').val();
				$.post(ajaxUrl, { action: 'functionalities_redirect_import', nonce: nonce, json: json }, function(r) {
					if (r.success) { location.reload(); } else { alert(r.data?.message || 'Error'); }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the SVG Icons custom page.
	 *
	 * @param array $module Module data.
	 * @return void
	 */
	private static function render_module_svg_icons( array $module ) : void {
		$opts  = self::get_svg_icons_options();
		$icons = isset( $opts['icons'] ) && is_array( $opts['icons'] ) ? $opts['icons'] : array();
		$nonce = \wp_create_nonce( 'functionalities_svg_icons' );

		// Handle enable/disable toggle.
		if ( isset( $_POST['functionalities_svg_icons_toggle'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'functionalities_svg_icons_toggle' ) ) {
			$opts['enabled'] = ! empty( $_POST['enabled'] );
			\update_option( 'functionalities_svg_icons', $opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Settings saved.', 'functionalities' ) . '</p></div>';
		}
		?>
		<div class="wrap functionalities-module func-svg-icons-admin">
			<h1>
				<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
				<?php echo \esc_html( $module['title'] ); ?>
			</h1>

			<nav class="functionalities-breadcrumb">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>">
					<?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?>
				</a>
				<span class="separator">›</span>
				<span class="current"><?php echo \esc_html( $module['title'] ); ?></span>
			</nav>

			<!-- Enable/Disable Toggle -->
			<form method="post" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;margin-bottom:20px;">
				<?php \wp_nonce_field( 'functionalities_svg_icons_toggle' ); ?>
				<input type="hidden" name="functionalities_svg_icons_toggle" value="1" />
				<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $opts['enabled'] ) ); ?> onchange="this.form.submit()" />
					<strong><?php echo \esc_html__( 'Enable SVG Icons', 'functionalities' ); ?></strong>
					<span style="color:#646970;font-size:13px;"><?php echo \esc_html__( 'Allow inserting custom SVG icons in the block editor', 'functionalities' ); ?></span>
				</label>
			</form>

			<!-- Documentation -->
			<div class="functionalities-module-docs" style="margin-bottom:20px;padding-top:0;border-top:0;">
				<?php
				$docs = Module_Docs::get( 'svg-icons' );
				if ( ! empty( $docs['features'] ) ) {
					$list = '<ul>';
					foreach ( $docs['features'] as $feature ) {
						$list .= '<li>' . \esc_html( $feature ) . '</li>';
					}
					$list .= '</ul>';
					Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
				}
				if ( ! empty( $docs['usage'] ) ) {
					Admin_UI::render_docs_section( \__( 'How to Use', 'functionalities' ), '<p>' . \esc_html( $docs['usage'] ) . '</p>', 'usage' );
				}
				if ( ! empty( $docs['hooks'] ) ) {
					$hooks_html = '<dl class="functionalities-hooks-list">';
					foreach ( $docs['hooks'] as $hook ) {
						$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
						$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
					}
					$hooks_html .= '</dl>';
					Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
				}
				?>
			</div>

			<!-- Add Icon Form -->
			<div class="func-svg-add-form">
				<h3><?php echo \esc_html__( 'Add New Icon', 'functionalities' ); ?></h3>
				<div class="func-svg-form-row">
					<label for="icon-slug"><?php echo \esc_html__( 'Slug (unique identifier)', 'functionalities' ); ?></label>
					<input type="text" id="icon-slug" class="regular-text" placeholder="my-icon" pattern="[a-z0-9-]+" title="<?php echo \esc_attr__( 'Lowercase letters, numbers, and hyphens only', 'functionalities' ); ?>" />
					<p class="description"><?php echo \esc_html__( 'Lowercase letters, numbers, and hyphens only. This will be used to reference the icon.', 'functionalities' ); ?></p>
				</div>
				<div class="func-svg-form-row">
					<label for="icon-name"><?php echo \esc_html__( 'Display Name', 'functionalities' ); ?></label>
					<input type="text" id="icon-name" class="regular-text" placeholder="My Icon" />
				</div>
				<div class="func-svg-form-row">
					<label for="icon-svg"><?php echo \esc_html__( 'SVG Code', 'functionalities' ); ?></label>
					<textarea id="icon-svg" class="large-text code" rows="6" placeholder="<svg viewBox=&quot;0 0 24 24&quot;>...</svg>"></textarea>
					<p class="description"><?php echo \esc_html__( 'Paste your SVG code. It will be sanitized to remove any potentially harmful content.', 'functionalities' ); ?></p>
				</div>
				<div class="func-svg-form-row">
					<div id="icon-preview" class="func-svg-preview-area" style="display:none;">
						<span><?php echo \esc_html__( 'Preview:', 'functionalities' ); ?></span>
						<div class="func-svg-preview-box" id="preview-box"></div>
					</div>
				</div>
				<div class="func-svg-form-row">
					<button type="button" id="save-icon-btn" class="button button-primary"><?php echo \esc_html__( 'Save Icon', 'functionalities' ); ?></button>
					<span id="save-status" style="margin-left:10px;"></span>
				</div>
			</div>

			<!-- Icons List -->
			<h2><?php echo \esc_html__( 'Your Icons', 'functionalities' ); ?> <span style="color:#646970;font-weight:normal;">(<?php echo count( $icons ); ?>)</span></h2>

			<?php if ( empty( $icons ) ) : ?>
				<div class="func-svg-empty">
					<span class="dashicons dashicons-flag"></span>
					<p><?php echo \esc_html__( 'No icons yet. Add your first icon above.', 'functionalities' ); ?></p>
				</div>
			<?php else : ?>
				<div class="func-svg-icons-grid" id="icons-list">
					<?php foreach ( $icons as $slug => $icon ) : ?>
						<div class="func-svg-icon-card" data-slug="<?php echo \esc_attr( $slug ); ?>">
							<div class="func-svg-icon-preview">
								<?php
								echo \wp_kses( $icon['svg'], self::get_svg_kses_allowed() );
								?>
							</div>
							<div class="func-svg-icon-info">
								<p class="func-svg-icon-name"><?php echo \esc_html( $icon['name'] ); ?></p>
								<p class="func-svg-icon-slug"><?php echo \esc_html( $slug ); ?></p>
							</div>
							<div class="func-svg-icon-actions">
								<button type="button" class="button button-small copy-shortcode" data-slug="<?php echo \esc_attr( $slug ); ?>" title="<?php echo \esc_attr__( 'Copy shortcode', 'functionalities' ); ?>">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
								<button type="button" class="button button-small delete-icon" data-slug="<?php echo \esc_attr( $slug ); ?>" title="<?php echo \esc_attr__( 'Delete', 'functionalities' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.func-svg-icons-admin .func-svg-add-form { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
		.func-svg-icons-admin .func-svg-add-form h3 { margin-top: 0; margin-bottom: 16px; }
		.func-svg-icons-admin .func-svg-form-row { margin-bottom: 16px; }
		.func-svg-icons-admin .func-svg-form-row:last-child { margin-bottom: 0; }
		.func-svg-icons-admin .func-svg-form-row label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
		.func-svg-icons-admin .func-svg-form-row textarea { min-height: 120px; font-family: monospace; font-size: 12px; }
		.func-svg-icons-admin .func-svg-preview-area { display: flex; align-items: center; gap: 16px; padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
		.func-svg-icons-admin .func-svg-preview-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; border-radius: 4px; }
		.func-svg-icons-admin .func-svg-preview-box svg { max-width: 32px; max-height: 32px; }
		/* Icons Grid */
		.func-svg-icons-admin .func-svg-icons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-top: 16px; }
		.func-svg-icons-admin .func-svg-icon-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px; text-align: center; transition: all 0.2s ease; }
		.func-svg-icons-admin .func-svg-icon-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transform: translateY(-2px); }
		.func-svg-icons-admin .func-svg-icon-preview { width: 64px; height: 64px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; border-radius: 8px; }
		.func-svg-icons-admin .func-svg-icon-preview svg { width: 40px; height: 40px; fill: currentColor; color: #1d2327; }
		.func-svg-icons-admin .func-svg-icon-info { margin-bottom: 12px; }
		.func-svg-icons-admin .func-svg-icon-name { font-weight: 600; font-size: 14px; color: #1d2327; margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.func-svg-icons-admin .func-svg-icon-slug { font-size: 11px; color: #646970; font-family: monospace; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.func-svg-icons-admin .func-svg-icon-actions { display: flex; justify-content: center; gap: 8px; }
		.func-svg-icons-admin .func-svg-icon-actions .button .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; }
		.func-svg-icons-admin .func-svg-empty { text-align: center; padding: 40px 20px; background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 4px; color: #646970; }
		.func-svg-icons-admin .func-svg-empty .dashicons { font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; margin-bottom: 12px; display: block; }
		</style>

		<script>
		jQuery(function($) {
			var ajaxUrl = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = '<?php echo \esc_js( $nonce ); ?>';

			// Preview SVG on input.
			$('#icon-svg').on('input', function() {
				var svg = $(this).val();
				if (svg.indexOf('<svg') !== -1) {
					$('#preview-box').html(svg);
					$('#icon-preview').show();
				} else {
					$('#icon-preview').hide();
				}
			});

			// Save icon.
			$('#save-icon-btn').on('click', function() {
				var $btn = $(this);
				var slug = $('#icon-slug').val().toLowerCase().replace(/[^a-z0-9-]/g, '');
				var name = $('#icon-name').val() || slug;
				var svg = $('#icon-svg').val();

				if (!slug) {
					$('#save-status').text('<?php echo \esc_js( \__( 'Slug is required.', 'functionalities' ) ); ?>').css('color', '#d63638');
					return;
				}
				if (!svg || svg.indexOf('<svg') === -1) {
					$('#save-status').text('<?php echo \esc_js( \__( 'Valid SVG code is required.', 'functionalities' ) ); ?>').css('color', '#d63638');
					return;
				}

				$btn.prop('disabled', true);
				$('#save-status').text('<?php echo \esc_js( \__( 'Saving...', 'functionalities' ) ); ?>').css('color', '#646970');

				$.post(ajaxUrl, {
					action: 'functionalities_svg_icon_save',
					nonce: nonce,
					slug: slug,
					name: name,
					svg: svg
				}, function(response) {
					$btn.prop('disabled', false);
					if (response.success) {
						$('#save-status').text('<?php echo \esc_js( \__( 'Icon saved!', 'functionalities' ) ); ?>').css('color', '#00a32a');
						// Clear form.
						$('#icon-slug').val('');
						$('#icon-name').val('');
						$('#icon-svg').val('');
						$('#icon-preview').hide();
						// Reload page to show new icon.
						setTimeout(function() { location.reload(); }, 500);
					} else {
						$('#save-status').text(response.data?.message || '<?php echo \esc_js( \__( 'Error saving icon.', 'functionalities' ) ); ?>').css('color', '#d63638');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					$('#save-status').text('<?php echo \esc_js( \__( 'Network error.', 'functionalities' ) ); ?>').css('color', '#d63638');
				});
			});

			// Delete icon.
			$(document).on('click', '.delete-icon', function() {
				var slug = $(this).data('slug');
				if (!confirm('<?php echo \esc_js( \__( 'Delete this icon?', 'functionalities' ) ); ?>')) {
					return;
				}
				$.post(ajaxUrl, {
					action: 'functionalities_svg_icon_delete',
					nonce: nonce,
					slug: slug
				}, function(response) {
					if (response.success) {
						$('.func-svg-icon-card[data-slug="' + slug + '"]').fadeOut(function() { $(this).remove(); });
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Error deleting icon.', 'functionalities' ) ); ?>');
					}
				});
			});

			// Copy shortcode.
			$(document).on('click', '.copy-shortcode', function() {
				var slug = $(this).data('slug');
				var shortcode = '[func_icon name="' + slug + '"]';
				var $btn = $(this);
				var $icon = $btn.find('.dashicons');
				
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(shortcode).then(function() {
						$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
						$btn.css('color', '#00a32a');
						setTimeout(function() {
							$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
							$btn.css('color', '');
						}, 2000);
					});
				} else {
					// Fallback for older browsers.
					var textArea = document.createElement("textarea");
					textArea.value = shortcode;
					document.body.appendChild(textArea);
					textArea.select();
					document.execCommand("copy");
					document.body.removeChild(textArea);
					$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
					$btn.css('color', '#00a32a');
					setTimeout(function() {
						$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
						$btn.css('color', '');
					}, 2000);
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Get allowed HTML for wp_kses SVG output.
	 *
	 * Mirrors the allowed elements and attributes from SVG_Icons::sanitize_svg()
	 * to ensure output escaping preserves all valid SVG content.
	 *
	 * @since 1.0.1
	 *
	 * @return array Allowed HTML array for wp_kses.
	 */
	private static function get_svg_kses_allowed(): array {
		$common_attrs = array(
			'id'               => true,
			'class'            => true,
			'style'            => true,
			'fill'             => true,
			'fill-opacity'     => true,
			'fill-rule'        => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-dasharray' => true,
			'stroke-dashoffset' => true,
			'stroke-opacity'   => true,
			'opacity'          => true,
			'transform'        => true,
			'clip-path'        => true,
			'clip-rule'        => true,
			'mask'             => true,
		);

		return array(
			'svg'            => array_merge( $common_attrs, array(
				'xmlns'              => true,
				'xmlns:xlink'        => true,
				'viewbox'            => true,
				'width'              => true,
				'height'             => true,
				'aria-hidden'        => true,
				'role'               => true,
				'focusable'          => true,
				'preserveaspectratio' => true,
				'version'            => true,
				'xml:space'          => true,
				'enable-background'  => true,
			) ),
			'g'              => $common_attrs,
			'path'           => array_merge( $common_attrs, array( 'd' => true ) ),
			'circle'         => array_merge( $common_attrs, array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			) ),
			'ellipse'        => array_merge( $common_attrs, array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			) ),
			'rect'           => array_merge( $common_attrs, array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			) ),
			'line'           => array_merge( $common_attrs, array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			) ),
			'polyline'       => array_merge( $common_attrs, array( 'points' => true ) ),
			'polygon'        => array_merge( $common_attrs, array( 'points' => true ) ),
			'defs'           => array( 'id' => true ),
			'clippath'       => array( 'id' => true ),
			'mask'           => array( 'id' => true ),
			'use'            => array(
				'xlink:href' => true,
				'href'       => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
			),
			'symbol'         => array(
				'id'                 => true,
				'viewbox'            => true,
				'preserveaspectratio' => true,
			),
			'title'          => array(),
			'desc'           => array(),
			'lineargradient' => array(
				'id'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
				'spreadmethod'      => true,
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
			),
			'radialgradient' => array(
				'id'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
				'spreadmethod'      => true,
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'fx'                => true,
				'fy'                => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
				'style'        => true,
			),
		);
	}

	// -------------------------------------------------------------------------
	// PWA Field Renderers
	// -------------------------------------------------------------------------

	/**
	 * Render a media upload field.
	 *
	 * @param string $name  Input name attribute.
	 * @param string $value Current URL value.
	 * @param string $desc  Description text.
	 * @return void
	 */
	private static function render_media_field( string $name, string $value, string $desc = '' ) : void {
		$id = 'func-media-' . \sanitize_key( str_replace( array( '[', ']' ), '-', $name ) );
		echo '<div class="func-media-field">';
		echo '<input type="text" id="' . \esc_attr( $id ) . '" name="' . \esc_attr( $name ) . '" value="' . \esc_attr( $value ) . '" class="regular-text">';
		echo ' <button type="button" class="button func-upload-btn" data-target="#' . \esc_attr( $id ) . '">' . \esc_html__( 'Upload', 'functionalities' ) . '</button>';
		if ( $value ) {
			echo '<div style="margin-top:8px"><img src="' . \esc_url( $value ) . '" style="max-width:80px;max-height:80px;border-radius:4px;border:1px solid #ddd"></div>';
		}
		if ( $desc ) {
			echo '<p class="description">' . \esc_html( $desc ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render snippets repeater field for a given location.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location  Location key: header, body_open, or footer.
	 * @param string $hook_name WordPress hook name for description.
	 * @return void
	 */
	public static function field_snippets_repeater( string $location, string $hook_name ) : void {
		$o     = self::get_snippets_options();
		$items = ! empty( $o[ $location ] ) && \is_array( $o[ $location ] ) ? $o[ $location ] : array();
		$cid   = 'func-snippets-' . $location;

		echo '<div id="' . \esc_attr( $cid ) . '">';
		$i = 0;
		foreach ( $items as $item ) {
			self::render_snippet_row( $location, $i, $item );
			$i++;
		}
		echo '</div>';

		echo '<button type="button" class="button func-snippets-add" data-location="' . \esc_attr( $location ) . '" data-container="' . \esc_attr( $cid ) . '">';
		echo \esc_html__( '+ Add Snippet', 'functionalities' ) . '</button>';
		echo '<p class="description">' . \sprintf(
			/* translators: %s: WordPress hook name */
			\esc_html__( 'Output in %s. Each snippet can be individually toggled.', 'functionalities' ),
			'<code>' . \esc_html( $hook_name ) . '</code>'
		) . '</p>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline JS for repeater template, encoded via wp_json_encode.
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			var loc = ' . \wp_json_encode( $location ) . ';
			var cid = ' . \wp_json_encode( $cid ) . ';
			var tpl = ' . \wp_json_encode( self::get_snippet_row_html( $location, '__INDEX__' ) ) . ';
			var $c = $("#"+cid);

			$(".func-snippets-add[data-location=\""+loc+"\"]").on("click",function(){
				var html = tpl.replace(/__INDEX__/g, idx);
				$c.append(html);
				var $row = $c.children().last();
				$row.addClass("is-open is-enabled");
				var ta = $row.find("textarea")[0];
				if(ta && typeof window.funcFsWrapTextarea==="function"){
					window.funcFsWrapTextarea(ta);
				}
				if(ta) ta.focus();
				idx++;
			});

			$c.on("click",".func-snippet-remove",function(){
				var $row = $(this).closest(".func-snippet-row");
				$row.slideUp(150, function(){ $row.remove(); });
			});

			$c.on("click",".func-snippet-expand",function(){
				$(this).closest(".func-snippet-row").toggleClass("is-open");
			});

			$c.on("change",".func-snippet-toggle input",function(){
				$(this).closest(".func-snippet-row").toggleClass("is-enabled", this.checked);
			});
		});
		</script>';
	}

	/**
	 * Render a single snippet repeater row.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location Location key.
	 * @param int    $index    Row index.
	 * @param array  $data     Snippet data (label, code, enabled).
	 * @return void
	 */
	private static function render_snippet_row( string $location, int $index, array $data ) : void {
		$label   = $data['label'] ?? '';
		$code    = $data['code'] ?? '';
		$enabled = ! empty( $data['enabled'] );
		$prefix  = 'functionalities_snippets[' . $location . '][' . $index . ']';
		$has_code = '' !== $code;

		echo '<div class="func-snippet-row' . ( $enabled ? ' is-enabled' : '' ) . '">';

		// Header bar — always visible.
		echo '<div class="func-snippet-row-header">';
		echo '<label class="func-snippet-toggle"><input type="checkbox" name="' . \esc_attr( $prefix ) . '[enabled]" value="1" ' . \checked( $enabled, true, false ) . '></label>';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[label]" value="' . \esc_attr( $label ) . '" placeholder="' . \esc_attr__( 'Label this snippet…', 'functionalities' ) . '" class="func-snippet-label">';
		if ( $has_code ) {
			echo '<span class="func-snippet-badge">' . \esc_html( self::snippet_type_badge( $code ) ) . '</span>';
		}
		echo '<button type="button" class="func-snippet-expand" title="' . \esc_attr__( 'Expand / Collapse', 'functionalities' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<button type="button" class="func-snippet-remove" title="' . \esc_attr__( 'Remove snippet', 'functionalities' ) . '"><span class="dashicons dashicons-trash"></span></button>';
		echo '</div>';

		// Body — collapsible.
		echo '<div class="func-snippet-body">';
		echo '<textarea name="' . \esc_attr( $prefix ) . '[code]" rows="6" cols="60" class="large-text code" placeholder="' . \esc_attr__( 'Paste your <script>, <style>, <meta>, or <link> tag here…', 'functionalities' ) . '">' . \esc_textarea( $code ) . '</textarea>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Detect snippet type from code for badge display.
	 *
	 * @since 1.4.0
	 *
	 * @param string $code Snippet code.
	 * @return string Badge label.
	 */
	private static function snippet_type_badge( string $code ) : string {
		$code_lower = strtolower( ltrim( $code ) );
		if ( 0 === strpos( $code_lower, '<style' ) ) {
			return 'CSS';
		}
		if ( 0 === strpos( $code_lower, '<script' ) ) {
			return 'JS';
		}
		if ( 0 === strpos( $code_lower, '<link' ) ) {
			return 'Link';
		}
		if ( 0 === strpos( $code_lower, '<meta' ) ) {
			return 'Meta';
		}
		if ( 0 === strpos( $code_lower, '<noscript' ) ) {
			return 'NoScript';
		}
		return 'HTML';
	}

	/**
	 * Get snippet row HTML template for JavaScript.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location Location key.
	 * @param string $index    Placeholder index string.
	 * @return string HTML template with placeholder index.
	 */
	private static function get_snippet_row_html( string $location, string $index ) : string {
		$sentinel = 89714;
		ob_start();
		self::render_snippet_row( $location, $sentinel, array( 'enabled' => true ) );
		$html = ob_get_clean();
		return str_replace( (string) $sentinel, $index, $html );
	}

	/**
	 * Render PWA shortcuts repeater field.
	 *
	 * @return void
	 */
	public static function field_pwa_shortcuts() : void {
		$o = self::get_pwa_options();
		$shortcuts = ! empty( $o['shortcuts'] ) ? $o['shortcuts'] : array();
		echo '<div id="func-pwa-shortcuts">';
		$i = 0;
		foreach ( $shortcuts as $sc ) {
			self::render_shortcut_row( $i, $sc );
			$i++;
		}
		echo '</div>';
		echo '<button type="button" class="button" id="func-pwa-add-shortcut">' . \esc_html__( '+ Add Shortcut', 'functionalities' ) . '</button>';
		echo '<p class="description">' . \esc_html__( 'Maximum 4 shortcuts. Each needs a name and URL.', 'functionalities' ) . '</p>';
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			$("#func-pwa-add-shortcut").on("click",function(){
				if(idx>=4) return;
				var html = ' . \wp_json_encode( self::get_shortcut_row_html( '__INDEX__' ) ) . ';
				html = html.replace(/__INDEX__/g, idx);
				$("#func-pwa-shortcuts").append(html);
				idx++;
			});
			$(document).on("click",".func-pwa-remove-shortcut",function(){
				$(this).closest(".func-pwa-shortcut-row").remove();
			});
		});
		</script>';
	}

	/**
	 * Render a single shortcut row.
	 *
	 * @param int   $index Row index.
	 * @param array $data  Shortcut data.
	 * @return void
	 */
	private static function render_shortcut_row( int $index, array $data ) : void {
		$name = \esc_attr( $data['name'] ?? '' );
		$url  = \esc_attr( $data['url'] ?? '' );
		$desc = \esc_attr( $data['description'] ?? '' );
		$icon = \esc_attr( $data['icon'] ?? '' );
		$prefix = 'functionalities_pwa[shortcuts][' . $index . ']';
		echo '<div class="func-pwa-shortcut-row" style="padding:10px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;background:#fafafa">';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[name]" value="' . \esc_attr( $name ) . '" placeholder="' . \esc_attr__( 'Name', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[url]" value="' . \esc_attr( $url ) . '" placeholder="' . \esc_attr__( 'URL (e.g. /blog/)', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[description]" value="' . \esc_attr( $desc ) . '" placeholder="' . \esc_attr__( 'Description', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[icon]" value="' . \esc_attr( $icon ) . '" placeholder="' . \esc_attr__( 'Icon URL (96x96 PNG)', 'functionalities' ) . '" class="regular-text">';
		echo '</div>';
		echo '<button type="button" class="button button-link-delete func-pwa-remove-shortcut" style="margin-top:6px">' . \esc_html__( 'Remove', 'functionalities' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Get shortcut row HTML template for JS.
	 *
	 * @param string $index Placeholder index.
	 * @return string HTML template.
	 */
	private static function get_shortcut_row_html( string $index ) : string {
		ob_start();
		self::render_shortcut_row( (int) $index, array() );
		$html = ob_get_clean();
		return str_replace( (string) (int) $index, $index, $html );
	}

	/**
	 * Render PWA screenshots repeater field.
	 *
	 * @return void
	 */
	public static function field_pwa_screenshots() : void {
		$o = self::get_pwa_options();
		$screenshots = ! empty( $o['screenshots'] ) ? $o['screenshots'] : array();
		echo '<div id="func-pwa-screenshots">';
		$i = 0;
		foreach ( $screenshots as $ss ) {
			self::render_screenshot_row( $i, $ss );
			$i++;
		}
		echo '</div>';
		echo '<button type="button" class="button" id="func-pwa-add-screenshot">' . \esc_html__( '+ Add Screenshot', 'functionalities' ) . '</button>';
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			$("#func-pwa-add-screenshot").on("click",function(){
				var html = ' . \wp_json_encode( self::get_screenshot_row_html( '__INDEX__' ) ) . ';
				html = html.replace(/__INDEX__/g, idx);
				$("#func-pwa-screenshots").append(html);
				idx++;
			});
			$(document).on("click",".func-pwa-remove-screenshot",function(){
				$(this).closest(".func-pwa-screenshot-row").remove();
			});
		});
		</script>';
	}

	/**
	 * Render a single screenshot row.
	 *
	 * @param int   $index Row index.
	 * @param array $data  Screenshot data.
	 * @return void
	 */
	private static function render_screenshot_row( int $index, array $data ) : void {
		$src   = \esc_attr( $data['src'] ?? '' );
		$sizes = \esc_attr( $data['sizes'] ?? '' );
		$label = \esc_attr( $data['label'] ?? '' );
		$form  = $data['form_factor'] ?? 'wide';
		$prefix = 'functionalities_pwa[screenshots][' . $index . ']';
		echo '<div class="func-pwa-screenshot-row" style="padding:10px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;background:#fafafa">';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[src]" value="' . \esc_attr( $src ) . '" placeholder="' . \esc_attr__( 'Image URL', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[sizes]" value="' . \esc_attr( $sizes ) . '" placeholder="' . \esc_attr__( '1280x720', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[label]" value="' . \esc_attr( $label ) . '" placeholder="' . \esc_attr__( 'Label', 'functionalities' ) . '" class="regular-text">';
		echo '<select name="' . \esc_attr( $prefix ) . '[form_factor]">';
		echo '<option value="wide"' . \selected( $form, 'wide', false ) . '>' . \esc_html__( 'Wide (desktop)', 'functionalities' ) . '</option>';
		echo '<option value="narrow"' . \selected( $form, 'narrow', false ) . '>' . \esc_html__( 'Narrow (mobile)', 'functionalities' ) . '</option>';
		echo '</select>';
		echo '</div>';
		echo '<button type="button" class="button button-link-delete func-pwa-remove-screenshot" style="margin-top:6px">' . \esc_html__( 'Remove', 'functionalities' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Get screenshot row HTML template for JS.
	 *
	 * @param string $index Placeholder index.
	 * @return string HTML template.
	 */
	private static function get_screenshot_row_html( string $index ) : string {
		ob_start();
		self::render_screenshot_row( (int) $index, array() );
		$html = ob_get_clean();
		return str_replace( (string) (int) $index, $index, $html );
	}
}
