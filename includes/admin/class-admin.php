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

/**
 * Admin class for managing plugin settings and UI.
 */
class Admin {

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
	}

	/**
	 * AJAX handler for database update tool.
	 *
	 * @return void
	 */
	public static function ajax_update_database() : void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_db_update' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
		}

		// Get URL from request.
		$url = isset( $_POST['url'] ) ? \sanitize_text_field( $_POST['url'] ) : '';

		// Call the update method.
		$result = \Functionalities\Features\Link_Management::update_links_in_database( $url );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for creating JSON file in theme directory.
	 *
	 * @return void
	 */
	public static function ajax_create_json_file() : void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_create_json' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
		}

		// Get content from request.
		$content = isset( $_POST['content'] ) ? \wp_unslash( $_POST['content'] ) : '';

		// Validate JSON.
		$decoded = json_decode( $content, true );
		if ( null === $decoded ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid JSON format.', 'functionalities' ) ) );
		}

		// Validate structure.
		if ( ! isset( $decoded['urls'] ) || ! is_array( $decoded['urls'] ) ) {
			\wp_send_json_error( array( 'message' => \__( 'JSON must contain a "urls" array.', 'functionalities' ) ) );
		}

		// Get theme directory.
		$theme_dir = \get_stylesheet_directory();
		$file_path = $theme_dir . '/exception-urls.json';

		// Check if theme directory is writable.
		if ( ! is_writable( $theme_dir ) ) {
			\wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: directory path */
						\__( 'Theme directory is not writable: %s', 'functionalities' ),
						$theme_dir
					),
				)
			);
		}

		// Format JSON nicely.
		$formatted_content = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		// Write file.
		$result = file_put_contents( $file_path, $formatted_content );

		if ( false === $result ) {
			\wp_send_json_error( array( 'message' => \__( 'Failed to write file.', 'functionalities' ) ) );
		}

		\wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: file path */
					\__( 'JSON file created successfully: %s', 'functionalities' ),
					$file_path
				),
				'path'    => $file_path,
			)
		);
	}

	/**
	 * AJAX handler for running assumption detection.
	 *
	 * @return void
	 */
	public static function ajax_run_detection() : void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'functionalities_run_detection' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
		}

		// Check capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
		}

		// Run detection.
		$warnings = \Functionalities\Features\Assumption_Detection::force_run_detection();

		\wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of warnings */
					\__( 'Detection complete. Found %d issue(s).', 'functionalities' ),
					count( $warnings )
				),
				'count'   => count( $warnings ),
			)
		);
	}

	/**
	 * Define available modules.
	 *
	 * @return void
	 */
	private static function define_modules() : void {
		self::$modules = array(
			'link-management' => array(
				'title'       => \__( 'Link Management', 'functionalities' ),
				'description' => \__( 'Control how external and internal links are handled.', 'functionalities' ),
				'icon'        => 'dashicons-admin-links',
			),
			'block-cleanup'   => array(
				'title'       => \__( 'Block Cleanup', 'functionalities' ),
				'description' => \__( 'Strip block classes from frontend output.', 'functionalities' ),
				'icon'        => 'dashicons-block-default',
			),
			'editor-links'    => array(
				'title'       => \__( 'Editor Link Suggestions', 'functionalities' ),
				'description' => \__( 'Limit link suggestions to selected post types.', 'functionalities' ),
				'icon'        => 'dashicons-editor-unlink',
			),
			'misc'            => array(
				'title'       => \__( 'Miscellaneous', 'functionalities' ),
				'description' => \__( 'Bloat control and performance tweaks.', 'functionalities' ),
				'icon'        => 'dashicons-admin-tools',
			),
			'snippets'        => array(
				'title'       => \__( 'Header & Footer', 'functionalities' ),
				'description' => \__( 'Add GA4, custom header and footer code.', 'functionalities' ),
				'icon'        => 'dashicons-editor-code',
			),
			'schema'          => array(
				'title'       => \__( 'Schema Settings', 'functionalities' ),
				'description' => \__( 'Add microdata to key areas and content.', 'functionalities' ),
				'icon'        => 'dashicons-networking',
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
			'icons'           => array(
				'title'       => \__( 'Icons', 'functionalities' ),
				'description' => \__( 'Replace Font Awesome with SVG icons.', 'functionalities' ),
				'icon'        => 'dashicons-star-filled',
			),
			'meta'            => array(
				'title'       => \__( 'Meta & Copyright', 'functionalities' ),
				'description' => \__( 'Copyright, Dublin Core, licensing, and SEO plugin integration.', 'functionalities' ),
				'icon'        => 'dashicons-shield',
			),
			'updates'         => array(
				'title'       => \__( 'GitHub Updates', 'functionalities' ),
				'description' => \__( 'Receive plugin updates directly from GitHub releases.', 'functionalities' ),
				'icon'        => 'dashicons-update',
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
		);
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function register_menu() : void {
		\add_menu_page(
			\__( 'Functionalities', 'functionalities' ),
			\__( 'Functionalities', 'functionalities' ),
			'manage_options',
			'functionalities',
			array( __CLASS__, 'render_main_page' ),
			'dashicons-admin-generic',
			65
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) : void {
		if ( 'toplevel_page_functionalities' !== $hook ) {
			return;
		}

		\wp_enqueue_style(
			'functionalities-admin',
			FUNCTIONALITIES_URL . 'assets/css/admin.css',
			array(),
			FUNCTIONALITIES_VERSION
		);

		\wp_enqueue_script(
			'functionalities-admin',
			FUNCTIONALITIES_URL . 'assets/js/admin.js',
			array( 'jquery' ),
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

		// Get current module from URL parameter.
		$current_module = isset( $_GET['module'] ) ? \sanitize_key( $_GET['module'] ) : '';

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
	private static function render_dashboard() : void {
		?>
		<div class="wrap functionalities-dashboard">
			<h1><?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?></h1>
			<p class="description">
				<?php echo \esc_html__( 'Modular site-specific plugin to organize common features with simple toggles.', 'functionalities' ); ?>
			</p>

			<div class="functionalities-modules-grid">
				<?php foreach ( self::$modules as $slug => $module ) : ?>
					<div class="functionalities-module-card">
						<div class="module-card-header">
							<span class="dashicons <?php echo \esc_attr( $module['icon'] ); ?>"></span>
							<h2><?php echo \esc_html( $module['title'] ); ?></h2>
						</div>
						<p class="module-description"><?php echo \esc_html( $module['description'] ); ?></p>
						<a href="<?php echo \esc_url( self::get_module_url( $slug ) ); ?>" class="button button-primary">
							<?php echo \esc_html__( 'Configure', 'functionalities' ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="functionalities-help-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
				<h2 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-editor-help" style="font-size: 24px; width: 24px; height: 24px;"></span>
					<?php echo \esc_html__( 'Help & Support', 'functionalities' ); ?>
				</h2>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php echo \esc_html__( 'Need help with Functionalities? Check out these resources:', 'functionalities' ); ?>
				</p>
				<div style="display: flex; flex-wrap: wrap; gap: 10px;">
					<a href="https://functionalities.dev/docs" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-book" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Documentation', 'functionalities' ); ?>
					</a>
					<a href="https://functionalities.dev/faq" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-sos" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'FAQ', 'functionalities' ); ?>
					</a>
					<a href="https://github.com/wpgaurav/functionalities/issues" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-flag" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Report Issues', 'functionalities' ); ?>
					</a>
					<a href="https://gauravtiwari.org/contact/" target="_blank" rel="noopener" class="button" style="display: inline-flex; align-items: center; gap: 5px;">
						<span class="dashicons dashicons-email" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php echo \esc_html__( 'Contact Developer', 'functionalities' ); ?>
					</a>
				</div>
				<p style="color: #646970; margin-top: 15px; margin-bottom: 0; font-size: 12px;">
					<?php
					printf(
						/* translators: %s: Plugin version number */
						\esc_html__( 'Functionalities v%s | Visit %s for more information.', 'functionalities' ),
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

		// GT Nofollow Manager features.
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_paragraph_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_quote_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_table_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_separator_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_group_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_columns_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_button_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_cover_block_class]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_media_text_block_class]" value="1" ' . $checked . '> ';
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
					'enable_header' => false,
					'header_code'   => '',
					'enable_footer' => false,
					'footer_code'   => '',
					'enable_ga4'    => false,
					'ga4_id'        => '',
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
				echo '<li>' . \esc_html__( 'Custom header code for meta tags, scripts, styles, and tracking codes', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Custom footer code for chat widgets, tracking pixels, and deferred scripts', 'functionalities' ) . '</li>';
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
			'enable_ga4',
			\__( 'Enable Google Analytics 4', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$checked = ! empty( $o['enable_ga4'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enable_ga4]" value="1" ' . $checked . '> ' . \esc_html__( 'Insert GA4 gtag in head', 'functionalities' ) . '</label>';
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
			'enable_header',
			\__( 'Enable custom header code', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$checked = ! empty( $o['enable_header'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enable_header]" value="1" ' . $checked . '> ' . \esc_html__( 'Output below in wp_head', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'header_code',
			\__( 'Header code', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$val = isset( $o['header_code'] ) ? (string) $o['header_code'] : '';
				echo '<textarea name="functionalities_snippets[header_code]" rows="6" cols="60" class="large-text code">' . \esc_textarea( $val ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Allowed: script, style, link, meta, noscript.', 'functionalities' ) . '</p>';
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
					'enable_site_schema'  => true,
					'site_itemtype'       => 'WebPage',
					'enable_header_part'  => true,
					'enable_footer_part'  => true,
					'enable_article'      => true,
					'article_itemtype'    => 'Article',
					'add_headline'        => true,
					'add_dates'           => true,
					'add_author'          => true,
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
			'enable_site_schema',
			\__( 'Enable site schema (html tag)', 'functionalities' ),
			function() {
				$o = self::get_schema_options();
				$checked = ! empty( $o['enable_site_schema'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_site_schema]" value="1" ' . $checked . '> ' . \esc_html__( 'Add itemscope/itemtype to <html>', 'functionalities' ) . '</label>';
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
					echo '<option value="' . esc_attr( $opt ) . '" ' . $sel . '>' . esc_html( $opt ) . '</option>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[enable_header_part]" value="1" ' . $checked . '> ' . \esc_html__( 'Output a microdata hasPart for header', 'functionalities' ) . '</label>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[enable_footer_part]" value="1" ' . $checked . '> ' . \esc_html__( 'Output a microdata hasPart for footer', 'functionalities' ) . '</label>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[enable_article]" value="1" ' . $checked . '> ' . \esc_html__( 'Wrap content with Article microdata on singular', 'functionalities' ) . '</label>';
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
					echo '<option value="' . esc_attr( $opt ) . '" ' . $sel . '>' . esc_html( $opt ) . '</option>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[add_headline]" value="1" ' . $checked . '> ' . \esc_html__( 'Add itemprop="headline"', 'functionalities' ) . '</label>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[add_dates]" value="1" ' . $checked . '> ' . \esc_html__( 'Add itemprop dates to time tags', 'functionalities' ) . '</label>';
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
				echo '<label><input type="checkbox" name="functionalities_schema[add_author]" value="1" ' . $checked . '> ' . \esc_html__( 'Add itemprop="author" where possible', 'functionalities' ) . '</label>';
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
					'enabled' => true,
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
				echo '<label><input type="checkbox" name="functionalities_components[enabled]" value="1" ' . $checked . '> ' . \esc_html__( 'Output components CSS on frontend', 'functionalities' ) . '</label>';
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
		\add_settings_field(
			'enable_footer',
			\__( 'Enable custom footer code', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$checked = ! empty( $o['enable_footer'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enable_footer]" value="1" ' . $checked . '> ' . \esc_html__( 'Output below in wp_footer', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'footer_code',
			\__( 'Footer code', 'functionalities' ),
			function() {
				$o = self::get_snippets_options();
				$val = isset( $o['footer_code'] ) ? (string) $o['footer_code'] : '';
				echo '<textarea name="functionalities_snippets[footer_code]" rows="6" cols="60" class="large-text code">' . \esc_textarea( $val ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Allowed: script, style, link, meta, noscript.', 'functionalities' ) . '</p>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);

		// Miscellaneous (bloat control)
		\register_setting(
			'functionalities_misc',
			'functionalities_misc',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_misc' ],
				'default' => [
					'disable_block_widgets'            => false,
					'load_separate_core_block_assets'  => false,
					'disable_emojis'                   => false,
					'disable_embeds'                   => false,
					'remove_rest_api_links_head'       => false,
					'remove_rsd_wlw_shortlink'         => false,
					'remove_generator_meta'            => false,
					'disable_xmlrpc'                   => false,
					'disable_feeds'                    => false,
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

		self::add_misc_field( 'disable_block_widgets', \__( 'Disable block-based widget editor (use classic widgets)', 'functionalities' ) );
		self::add_misc_field( 'load_separate_core_block_assets', \__( 'Load core block styles separately (per-block CSS)', 'functionalities' ) );
		self::add_misc_field( 'disable_emojis', \__( 'Disable emojis scripts/styles', 'functionalities' ) );
		self::add_misc_field( 'disable_embeds', \__( 'Disable oEmbed scripts and endpoints', 'functionalities' ) );
		self::add_misc_field( 'remove_rest_api_links_head', \__( 'Remove REST API and oEmbed discovery links from <head>', 'functionalities' ) );
		self::add_misc_field( 'remove_rsd_wlw_shortlink', \__( 'Remove RSD, WLWManifest, and shortlink tags', 'functionalities' ) );
		self::add_misc_field( 'remove_generator_meta', \__( 'Remove WordPress version meta (generator)', 'functionalities' ) );
		self::add_misc_field( 'disable_xmlrpc', \__( 'Disable XML-RPC', 'functionalities' ) );
		self::add_misc_field( 'disable_feeds', \__( 'Disable RSS/Atom feeds (redirect to homepage)', 'functionalities' ) );
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
				echo '<label><input type="checkbox" name="functionalities_fonts[enabled]" value="1" ' . $checked . '> ' . \esc_html__( 'Output @font-face CSS across site (front and admin)', 'functionalities' ) . '</label>';
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

		// Icons settings
		\register_setting(
			'functionalities_icons',
			'functionalities_icons',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_icons' ],
				'default' => [
					'remove_fontawesome_assets' => false,
					'convert_fa_to_svg'        => false,
					'svg_sprite_url'           => '',
					'mappings'                 => '',
				],
			]
		);
		\add_settings_section(
			'functionalities_icons_section',
			\__( 'Icon Replacement', 'functionalities' ),
			function() {
				echo '<p>' . \esc_html__( 'Optimize icon delivery by replacing Font Awesome with lightweight SVG sprites.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Remove Font Awesome CSS and JavaScript files to reduce page weight', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Convert Font Awesome icon markup to SVG sprite references', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Works with fa, fas, far, and fab icon prefixes', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Significantly improves performance while maintaining icon compatibility', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Setup Required', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'You need an SVG sprite file containing your icons. Point the sprite URL to your file and the module will convert &lt;i class="fa fa-icon"&gt; to &lt;svg&gt;&lt;use href="sprite.svg#fa-icon"&gt;&lt;/svg&gt;.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_icons_remove_fa_enabled</code> — ' . \esc_html__( 'control asset removal', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_icons_convert_enabled</code> — ' . \esc_html__( 'control conversion', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_icons_sprite_url</code> — ' . \esc_html__( 'modify sprite URL', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_icons_fa_handles</code> — ' . \esc_html__( 'add/remove FA handles', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_icons'
		);
		\add_settings_field(
			'convert_fa_to_svg',
			\__( 'Enable FA → SVG replacement', 'functionalities' ),
			function(){ $o=self::get_icons_options(); $c=!empty($o['convert_fa_to_svg'])?'checked':''; echo '<label><input type="checkbox" name="functionalities_icons[convert_fa_to_svg]" value="1" '.$c.'> ' . \esc_html__( 'Convert <i class="fa ..."> to <svg><use> where possible', 'functionalities' ) . '</label>'; },
			'functionalities_icons',
			'functionalities_icons_section'
		);
		\add_settings_field(
			'remove_fontawesome_assets',
			\__( 'Remove Font Awesome assets', 'functionalities' ),
			function(){ $o=self::get_icons_options(); $c=!empty($o['remove_fontawesome_assets'])?'checked':''; echo '<label><input type="checkbox" name="functionalities_icons[remove_fontawesome_assets]" value="1" '.$c.'> ' . \esc_html__( 'Dequeue common FA styles/scripts', 'functionalities' ) . '</label>'; },
			'functionalities_icons',
			'functionalities_icons_section'
		);
		\add_settings_field(
			'svg_sprite_url',
			\__( 'SVG sprite URL', 'functionalities' ),
			function(){ $o=self::get_icons_options(); $v=isset($o['svg_sprite_url'])?(string)$o['svg_sprite_url']:''; echo '<input type="url" class="regular-text" name="functionalities_icons[svg_sprite_url]" value="'.\esc_attr($v).'" placeholder="/path/to/sprite.svg" />'; },
			'functionalities_icons',
			'functionalities_icons_section'
		);
		\add_settings_field(
			'mappings',
			\__( 'Class → Symbol mappings', 'functionalities' ),
			function(){ $o=self::get_icons_options(); $v=isset($o['mappings'])?(string)$o['mappings']:''; echo '<textarea name="functionalities_icons[mappings]" rows="6" cols="60" class="large-text code">'.\esc_textarea($v).'</textarea><p class="description">'.\esc_html__('Format: fa-user = user; fa-bars = bars','functionalities').'</p>'; },
			'functionalities_icons',
			'functionalities_icons_section'
		);

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
				echo '<label><input type="checkbox" name="functionalities_meta[enabled]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_meta[enable_copyright_meta]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_meta[enable_dublin_core]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_meta[enable_license_metabox]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_meta[enable_schema_integration]" value="1" ' . $checked . '> ';
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
					echo '<option value="' . \esc_attr( $key ) . '" ' . $sel . '>' . \esc_html( $label ) . '</option>';
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
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_meta[post_types][]" value="' . \esc_attr( $name ) . '" ' . $is_checked . '> ' . \esc_html( $label ) . '</label>';
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
					echo '<option value="' . \esc_attr( $key ) . '" ' . $sel . '>' . \esc_html( $label ) . '</option>';
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

		// GitHub Updates settings.
		\register_setting(
			'functionalities_updates',
			'functionalities_updates',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_updates' ),
				'default'           => array(
					'enabled'        => false,
					'github_owner'   => 'wpgaurav',
					'github_repo'    => 'functionalities',
					'access_token'   => '',
					'cache_duration' => 21600,
				),
			)
		);

		\add_settings_section(
			'functionalities_updates_section',
			\__( 'GitHub Updates Settings', 'functionalities' ),
			array( __CLASS__, 'section_updates' ),
			'functionalities_updates'
		);

		\add_settings_field(
			'updates_enabled',
			\__( 'Enable GitHub Updates', 'functionalities' ),
			function() {
				$o = self::get_updates_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_updates[enabled]" value="1" ' . $checked . '> ';
				echo \esc_html__( 'Check for plugin updates from GitHub releases', 'functionalities' ) . '</label>';
			},
			'functionalities_updates',
			'functionalities_updates_section'
		);

		\add_settings_field(
			'github_owner',
			\__( 'GitHub Owner/Organization', 'functionalities' ),
			function() {
				$o = self::get_updates_options();
				$val = isset( $o['github_owner'] ) ? (string) $o['github_owner'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_updates[github_owner]" value="' . \esc_attr( $val ) . '" placeholder="wpgaurav" />';
				echo '<p class="description">' . \esc_html__( 'The GitHub username or organization that owns the repository.', 'functionalities' ) . '</p>';
			},
			'functionalities_updates',
			'functionalities_updates_section'
		);

		\add_settings_field(
			'github_repo',
			\__( 'GitHub Repository Name', 'functionalities' ),
			function() {
				$o = self::get_updates_options();
				$val = isset( $o['github_repo'] ) ? (string) $o['github_repo'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_updates[github_repo]" value="' . \esc_attr( $val ) . '" placeholder="functionalities" />';
				echo '<p class="description">' . \esc_html__( 'The name of the GitHub repository.', 'functionalities' ) . '</p>';
			},
			'functionalities_updates',
			'functionalities_updates_section'
		);

		\add_settings_field(
			'access_token',
			\__( 'GitHub Access Token (Optional)', 'functionalities' ),
			function() {
				$o = self::get_updates_options();
				$val = isset( $o['access_token'] ) ? (string) $o['access_token'] : '';
				$masked = ! empty( $val ) ? str_repeat( '•', 20 ) . substr( $val, -4 ) : '';
				echo '<input type="password" class="regular-text" name="functionalities_updates[access_token]" value="" placeholder="' . \esc_attr( $masked ?: 'ghp_xxxxxxxxxxxx' ) . '" autocomplete="new-password" />';
				echo '<p class="description">' . \esc_html__( 'Required for private repositories. Leave empty for public repos. Token needs "repo" scope.', 'functionalities' ) . '</p>';
				if ( ! empty( $val ) ) {
					echo '<p class="description" style="color:#059669">✓ ' . \esc_html__( 'Token is saved. Leave empty to keep current token, or enter new one to replace.', 'functionalities' ) . '</p>';
				}
			},
			'functionalities_updates',
			'functionalities_updates_section'
		);

		\add_settings_field(
			'cache_duration',
			\__( 'Update Check Interval', 'functionalities' ),
			function() {
				$o = self::get_updates_options();
				$val = isset( $o['cache_duration'] ) ? (int) $o['cache_duration'] : 21600;
				$options = array(
					3600   => \__( '1 hour', 'functionalities' ),
					10800  => \__( '3 hours', 'functionalities' ),
					21600  => \__( '6 hours (recommended)', 'functionalities' ),
					43200  => \__( '12 hours', 'functionalities' ),
					86400  => \__( '24 hours', 'functionalities' ),
				);
				echo '<select name="functionalities_updates[cache_duration]">';
				foreach ( $options as $seconds => $label ) {
					$sel = selected( $val, $seconds, false );
					echo '<option value="' . \esc_attr( $seconds ) . '" ' . $sel . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'How often to check GitHub for new releases. More frequent checks may hit API rate limits.', 'functionalities' ) . '</p>';
			},
			'functionalities_updates',
			'functionalities_updates_section'
		);

		\add_settings_field(
			'update_status',
			\__( 'Current Status', 'functionalities' ),
			array( __CLASS__, 'field_update_status' ),
			'functionalities_updates',
			'functionalities_updates_section'
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[enabled]" value="1" ' . $checked . '> ';
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
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_content_regression[post_types][]" value="' . \esc_attr( $name ) . '" ' . $is_checked . '> ' . \esc_html( $label ) . '</label>';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[link_drop_enabled]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_nofollow_links]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[word_count_enabled]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_shortcodes]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[heading_enabled]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_missing_h1]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_multiple_h1]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_skipped_levels]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_content_regression[show_post_column]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[enabled]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_schema_collision]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_analytics_dupe]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_font_redundancy]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_inline_css_growth]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_jquery_conflicts]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_meta_duplication]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_rest_exposure]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_lazy_load_conflict]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_mixed_content]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_missing_security_headers]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_debug_exposure]" value="1" ' . $checked . '> ';
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
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_cron_issues]" value="1" ' . $checked . '> ';
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
	}

	public static function field_nofollow_external() : void {
		$opts = self::get_link_management_options();
		$checked = ! empty( $opts['nofollow_external'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[nofollow_external]" value="1" ' . $checked . '> ' . \esc_html__( 'Enable rel="nofollow" for all external links', 'functionalities' ) . '</label>';
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
		echo '<label><input type="checkbox" name="functionalities_link_management[open_external_new_tab]" value="1" ' . $checked . '> ';
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
		echo '<label><input type="checkbox" name="functionalities_link_management[open_internal_new_tab]" value="1" ' . $checked . '> ';
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
						nonce: '<?php echo \wp_create_nonce( 'functionalities_create_json' ); ?>'
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
			<input type="checkbox" name="functionalities_link_management[enable_developer_filters]" value="1" <?php echo $checked; ?>>
			<?php echo \esc_html__( 'Enable developer filters for exception customization', 'functionalities' ); ?>
		</label>
		<p class="description">
			<?php echo \esc_html__( 'Available filters: functionalities_exception_domains, functionalities_exception_urls, gtnf_exception_domains (legacy), gtnf_exception_urls (legacy)', 'functionalities' ); ?>
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

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
					<?php echo \esc_html__( 'Legacy GT Nofollow Manager Compatibility', 'functionalities' ); ?>
				</summary>
				<div style="margin-top: 10px;">
					<p class="description"><?php echo \esc_html__( 'If migrating from GT Nofollow Manager, your existing filter code will still work:', 'functionalities' ); ?></p>
					<pre class="functionalities-code-block" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><code>&lt;?php
/**
 * Legacy GT Nofollow Manager filters (still supported).
 * These work identically to the new filters above.
 */

// Legacy domain filter
add_filter( 'gtnf_exception_domains', function( $domains ) {
    $domains[] = 'legacy-trusted-site.com';
    return $domains;
} );

// Legacy URL filter
add_filter( 'gtnf_exception_urls', function( $urls ) {
    $urls[] = 'https://legacy-partner.com/page';
    return $urls;
} );</code></pre>
					<button type="button" class="button button-small functionalities-copy-btn" data-target="legacy-filters"><?php echo \esc_html__( 'Copy Code', 'functionalities' ); ?></button>
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
							nonce: '<?php echo \wp_create_nonce( 'functionalities_db_update' ); ?>'
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

	/**
	 * Sanitize link management settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_link_management( $input ) : array {
		$out = array(
			'nofollow_external'             => ! empty( $input['nofollow_external'] ),
			'exceptions'                    => '',
			'open_external_new_tab'         => ! empty( $input['open_external_new_tab'] ),
			'open_internal_new_tab'         => ! empty( $input['open_internal_new_tab'] ),
			'internal_new_tab_exceptions'   => '',
			'json_preset_url'               => '',
			'enable_developer_filters'      => ! empty( $input['enable_developer_filters'] ),
		);

		if ( isset( $input['exceptions'] ) ) {
			$raw = (string) $input['exceptions'];
			$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
			$clean = [];
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( $line === '' ) { continue; }
				$clean[] = \sanitize_text_field( $line );
			}
			$out['exceptions'] = implode( "\n", $clean );
		}

		if ( isset( $input['internal_new_tab_exceptions'] ) ) {
			$raw   = (string) $input['internal_new_tab_exceptions'];
			$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
			$clean = array();
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( $line === '' ) {
					continue;
				}
				$clean[] = \sanitize_text_field( $line );
			}
			$out['internal_new_tab_exceptions'] = implode( "\n", $clean );
		}

		// Sanitize JSON preset URL.
		if ( isset( $input['json_preset_url'] ) ) {
			$out['json_preset_url'] = \sanitize_text_field( (string) $input['json_preset_url'] );
		}

		return $out;
	}


	/**
	 * Get link management options with defaults.
	 *
	 * @return array Link management options.
	 */
	public static function get_link_management_options() : array {
		$defaults = array(
			'nofollow_external'             => false,
			'exceptions'                    => '',
			'open_external_new_tab'         => false,
			'open_internal_new_tab'         => false,
			'internal_new_tab_exceptions'   => '',
			'json_preset_url'               => '',
			'enable_developer_filters'      => false,
		);
		$opts = (array) \get_option( 'functionalities_link_management', $defaults );
		return array_merge( $defaults, $opts );
	}

	// Block Cleanup: fields & helpers
	public static function field_bc_remove_heading() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_heading_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_heading_block_class]" value="1" ' . $checked . '> ' . \esc_html__( 'Remove class "wp-block-heading" from headings (h1–h6)', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_list() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_list_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_list_block_class]" value="1" ' . $checked . '> ' . \esc_html__( 'Remove class "wp-block-list" from ul/ol', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_image() : void {
		$opts = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_image_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_image_block_class]" value="1" ' . $checked . '> ' . \esc_html__( 'Remove class ".wp-block-image" from frontend', 'functionalities' ) . '</label>';
	}
	public static function sanitize_block_cleanup( $input ) : array {
		return [
			'remove_heading_block_class'    => ! empty( $input['remove_heading_block_class'] ),
			'remove_list_block_class'       => ! empty( $input['remove_list_block_class'] ),
			'remove_image_block_class'      => ! empty( $input['remove_image_block_class'] ),
			'remove_paragraph_block_class'  => ! empty( $input['remove_paragraph_block_class'] ),
			'remove_quote_block_class'      => ! empty( $input['remove_quote_block_class'] ),
			'remove_table_block_class'      => ! empty( $input['remove_table_block_class'] ),
			'remove_separator_block_class'  => ! empty( $input['remove_separator_block_class'] ),
			'remove_group_block_class'      => ! empty( $input['remove_group_block_class'] ),
			'remove_columns_block_class'    => ! empty( $input['remove_columns_block_class'] ),
			'remove_button_block_class'     => ! empty( $input['remove_button_block_class'] ),
			'remove_cover_block_class'      => ! empty( $input['remove_cover_block_class'] ),
			'remove_media_text_block_class' => ! empty( $input['remove_media_text_block_class'] ),
			'custom_classes_to_remove'      => isset( $input['custom_classes_to_remove'] )
				? \sanitize_textarea_field( $input['custom_classes_to_remove'] )
				: '',
		];
	}
	public static function get_block_cleanup_options() : array {
		$defaults = [
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
		];
		$opts = (array) \get_option( 'functionalities_block_cleanup', $defaults );
		return array_merge( $defaults, $opts );
	}

	// Editor Links: fields & helpers
	public static function field_el_enable() : void {
		$opts = self::get_editor_links_options();
		$checked = ! empty( $opts['enable_limit'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_editor_links[enable_limit]" value="1" ' . $checked . '> ' . \esc_html__( 'Limit editor link suggestions to selected post types', 'functionalities' ) . '</label>';
	}
	public static function field_el_post_types() : void {
		$opts = self::get_editor_links_options();
		$selected = isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : [];
		$pts = get_post_types( [ 'public' => true ], 'objects' );
		echo '<fieldset>';
		foreach ( $pts as $name => $obj ) {
			$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
			$label = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
			echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_editor_links[post_types][]" value="' . esc_attr( $name ) . '" ' . $is_checked . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
	}
	public static function sanitize_editor_links( $input ) : array {
		$out = [ 'enable_limit' => ! empty( $input['enable_limit'] ), 'post_types' => [] ];
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = sanitize_key( (string) $pt );
				if ( post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
			$out['post_types'] = array_values( array_unique( $out['post_types'] ) );
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = self::default_editor_link_post_types();
		}
		return $out;
	}
	public static function default_editor_link_post_types() : array {
		$pts = get_post_types( [ 'public' => true ], 'objects' );
		$defaults = [];
		foreach ( $pts as $name => $obj ) {
			$is_cpt = ! ( $obj->_builtin ?? false );
			if ( $is_cpt ) { $defaults[] = $name; }
		}
		return $defaults;
	}
	public static function get_editor_links_options() : array {
		$defaults = [ 'enable_limit' => false, 'post_types' => self::default_editor_link_post_types() ];
		$opts = (array) \get_option( 'functionalities_editor_links', $defaults );
		return array_merge( $defaults, $opts );
	}


	public static function sanitize_snippets( $input ) : array {
		$out = [
			'enable_header' => ! empty( $input['enable_header'] ),
			'header_code'   => '',
			'enable_footer' => ! empty( $input['enable_footer'] ),
			'footer_code'   => '',
			'enable_ga4'    => ! empty( $input['enable_ga4'] ),
			'ga4_id'        => '',
		];

		$ga4 = isset( $input['ga4_id'] ) ? (string) $input['ga4_id'] : '';
		$ga4 = strtoupper( trim( $ga4 ) );
		if ( preg_match( '/^G-[A-Z0-9]{4,}$/', $ga4 ) ) {
			$out['ga4_id'] = $ga4;
		}

		$allowed_tags = [
			'script'   => [ 'src' => true, 'type' => true, 'async' => true, 'defer' => true, 'crossorigin' => true, 'integrity' => true, 'data-*' => true ],
			'style'    => [ 'type' => true, 'media' => true ],
			'link'     => [ 'rel' => true, 'href' => true, 'as' => true, 'crossorigin' => true, 'media' => true, 'type' => true ],
			'meta'     => [ 'name' => true, 'content' => true, 'property' => true, 'http-equiv' => true ],
			'noscript' => [],
			// Common wrappers if pasted
			'div'      => [ 'id' => true, 'class' => true, 'style' => true ],
			'span'     => [ 'id' => true, 'class' => true, 'style' => true ],
		];

		$raw_header = isset( $input['header_code'] ) ? (string) $input['header_code'] : '';
		$raw_footer = isset( $input['footer_code'] ) ? (string) $input['footer_code'] : '';

		if ( \current_user_can( 'unfiltered_html' ) ) {
			// Store as-is for trusted admins.
			$out['header_code'] = $raw_header;
			$out['footer_code'] = $raw_footer;
		} else {
			$out['header_code'] = \wp_kses( $raw_header, $allowed_tags );
			$out['footer_code'] = \wp_kses( $raw_footer, $allowed_tags );
		}
		return $out;
	}

	public static function get_snippets_options() : array {
		$defaults = [
			'enable_header' => false,
			'header_code'   => '',
			'enable_footer' => false,
			'footer_code'   => '',
			'enable_ga4'    => false,
			'ga4_id'        => '',
		];
		$opts = (array) \get_option( 'functionalities_snippets', $defaults );
		return array_merge( $defaults, $opts );
	}


	public static function sanitize_schema( $input ) : array {
		return [
			'enable_site_schema' => ! empty( $input['enable_site_schema'] ),
			'site_itemtype'      => preg_replace( '/[^A-Za-z]/', '', (string) ( $input['site_itemtype'] ?? 'WebPage' ) ),
			'enable_header_part' => ! empty( $input['enable_header_part'] ),
			'enable_footer_part' => ! empty( $input['enable_footer_part'] ),
			'enable_article'     => ! empty( $input['enable_article'] ),
			'article_itemtype'   => preg_replace( '/[^A-Za-z]/', '', (string) ( $input['article_itemtype'] ?? 'Article' ) ),
			'add_headline'       => ! empty( $input['add_headline'] ),
			'add_dates'          => ! empty( $input['add_dates'] ),
			'add_author'         => ! empty( $input['add_author'] ),
		];
	}
	public static function get_schema_options() : array {
		$defaults = [
			'enable_site_schema'  => true,
			'site_itemtype'       => 'WebPage',
			'enable_header_part'  => true,
			'enable_footer_part'  => true,
			'enable_article'      => true,
			'article_itemtype'    => 'Article',
			'add_headline'        => true,
			'add_dates'           => true,
			'add_author'          => true,
		];
		$opts = (array) \get_option( 'functionalities_schema', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function sanitize_components( $input ) : array {
		$out = [ 'enabled' => ! empty( $input['enabled'] ), 'items' => [] ];
		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $item ) {
				$class = isset( $item['class'] ) ? trim( (string) $item['class'] ) : '';
				$css   = isset( $item['css'] ) ? trim( (string) $item['css'] ) : '';
				$name  = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
				if ( $class === '' || $css === '' ) { continue; }
				$out['items'][] = [
					'name'  => \sanitize_text_field( $name ),
					'class' => preg_replace( '/[^A-Za-z0-9_\-\.\s]/', '', $class ),
					'css'   => $css,
				];
			}
		}
		return $out;
	}
	public static function get_components_options() : array {
		$defaults = [ 'enabled' => true, 'items' => self::default_components() ];
		$opts = (array) \get_option( 'functionalities_components', $defaults );
		if ( empty( $opts['items'] ) ) { $opts['items'] = self::default_components(); }
		return array_merge( $defaults, $opts );
	}
	public static function field_components_items() : void {
		$o = self::get_components_options();
		$items = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : [];
		$per_page = 24;
		$total_items = count( $items );
		$total_pages = max( 1, ceil( $total_items / $per_page ) );
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
					<?php printf( \esc_html__( '%d components', 'functionalities' ), $total_items ); ?>
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
					$page  = floor( $i / $per_page ) + 1;
					$is_visible = ( $page === 1 ) ? 'is-visible' : '';
				?>
				<div class="fc-card <?php echo $is_visible; ?>" data-index="<?php echo $i; ?>" data-page="<?php echo $page; ?>">
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
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo $i; ?>][name]" value="<?php echo \esc_attr( $name ); ?>" placeholder="<?php \esc_attr_e( 'Component Name', 'functionalities' ); ?>">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Selector', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo $i; ?>][class]" value="<?php echo \esc_attr( $class ); ?>" placeholder=".my-component">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Rules', 'functionalities' ); ?></label>
							<textarea class="fc-form__input fc-form__textarea" name="functionalities_components[items][<?php echo $i; ?>][css]" placeholder="background: #fff; padding: 1rem;"><?php echo \esc_textarea( $css ); ?></textarea>
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
				<button type="button" class="fc-pagination__btn <?php echo ( $p === 1 ) ? 'is-active' : ''; ?>" data-page="<?php echo $p; ?>">
					<?php echo $p; ?>
				</button>
				<?php endfor; ?>
				<button type="button" class="fc-pagination__btn" data-action="next" <?php echo ( 1 === $total_pages ) ? 'disabled' : ''; ?>>
					<?php \esc_html_e( 'Next', 'functionalities' ); ?> &rarr;
				</button>
				<span class="fc-pagination__info">
					<?php printf( \esc_html__( 'Page %1$d of %2$d', 'functionalities' ), 1, $total_pages ); ?>
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
			var perPage = <?php echo $per_page; ?>;
			var totalItems = <?php echo $total_items; ?>;
			var nextIndex = <?php echo $i; ?>;

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
	public static function default_components() : array {
		return [
			[ 'name' => 'Card', 'class' => '.c-card', 'css' => 'background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06);padding:1rem;' ],
			[ 'name' => 'Button', 'class' => '.c-btn', 'css' => 'display:inline-block;padding:.625rem 1rem;border-radius:.5rem;background:#0a7cff;color:#fff;text-decoration:none;font-weight:600;transition:background .2s;cursor:pointer;' ],
			[ 'name' => 'Button (ghost)', 'class' => '.c-btn--ghost', 'css' => 'background:transparent;border:1px solid currentColor;color:#0a7cff;' ],
			[ 'name' => 'Accordion', 'class' => '.c-accordion', 'css' => 'border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;' ],
			[ 'name' => 'Accordion Item', 'class' => '.c-accordion__item', 'css' => 'border-top:1px solid #e5e7eb;padding:.75rem 1rem;' ],
			[ 'name' => 'Badge', 'class' => '.c-badge', 'css' => 'display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:.75rem;font-weight:600;' ],
			[ 'name' => 'Chip', 'class' => '.c-chip', 'css' => 'display:inline-flex;align-items:center;gap:.5rem;padding:.25rem .5rem;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;' ],
			[ 'name' => 'Alert', 'class' => '.c-alert', 'css' => 'padding:.75rem 1rem;border-radius:.5rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;' ],
			[ 'name' => 'Avatar', 'class' => '.c-avatar', 'css' => 'display:inline-block;width:3rem;height:3rem;border-radius:999px;object-fit:cover;' ],
			[ 'name' => 'Grid', 'class' => '.c-grid', 'css' => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;' ],
			[ 'name' => 'Marquee', 'class' => '.c-marquee', 'css' => 'display:block;white-space:nowrap;overflow:hidden;animation:marquee 20s linear infinite;' ],
			[ 'name' => 'Tabs', 'class' => '.c-tabs', 'css' => 'display:flex;gap:.5rem;border-bottom:1px solid #e5e7eb;' ],
			[ 'name' => 'Card Media', 'class' => '.c-card__media', 'css' => 'display:block;width:100%;height:auto;border-top-left-radius:12px;border-top-right-radius:12px;' ],
		];
	}

	// Misc helpers
	protected static function add_misc_field( string $key, string $label ) : void {
		\add_settings_field(
			$key,
			$label,
			function() use ( $key, $label ) {
				$opts = self::get_misc_options();
				$checked = ! empty( $opts[ $key ] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_misc[' . esc_attr( $key ) . ']" value="1" ' . $checked . '> ' . esc_html( $label ) . '</label>';
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);
	}
	public static function sanitize_misc( $input ) : array {
		$keys = [
			'disable_block_widgets',
			'load_separate_core_block_assets',
			'disable_emojis',
			'disable_embeds',
			'remove_rest_api_links_head',
			'remove_rsd_wlw_shortlink',
			'remove_generator_meta',
			'disable_xmlrpc',
			'disable_feeds',
			'disable_dashicons_for_guests',
			'disable_heartbeat',
			'disable_admin_bar_front',
			'remove_jquery_migrate',
			'enable_prism_admin',
			'enable_textarea_fullscreen',
		];
		$out = [];
		foreach ( $keys as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] );
		}
		return $out;
	}
	public static function get_misc_options() : array {
		$defaults = [
			'disable_block_widgets'            => false,
			'load_separate_core_block_assets'  => false,
			'disable_emojis'                   => false,
			'disable_embeds'                   => false,
			'remove_rest_api_links_head'       => false,
			'remove_rsd_wlw_shortlink'         => false,
			'remove_generator_meta'            => false,
			'disable_xmlrpc'                   => false,
			'disable_feeds'                    => false,
			'disable_dashicons_for_guests'     => false,
			'disable_heartbeat'                => false,
			'disable_admin_bar_front'          => false,
			'remove_jquery_migrate'            => false,
			'enable_prism_admin'               => false,
			'enable_textarea_fullscreen'       => false,
		];
		$opts = (array) \get_option( 'functionalities_misc', $defaults );
		return array_merge( $defaults, $opts );
	}

	// Fonts helpers
	public static function get_fonts_options() : array {
		$defaults = [ 'enabled' => false, 'items' => [] ];
		$opts = (array) \get_option( 'functionalities_fonts', $defaults );
		return array_merge( $defaults, $opts );
	}
	public static function sanitize_fonts( $input ) : array {
		$out = [ 'enabled' => ! empty( $input['enabled'] ), 'items' => [] ];
		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $it ) {
				$family = isset( $it['family'] ) ? trim( (string) $it['family'] ) : '';
				$style  = isset( $it['style'] ) ? trim( (string) $it['style'] ) : 'normal';
				$display= isset( $it['display'] ) ? trim( (string) $it['display'] ) : 'swap';
				$weight = isset( $it['weight'] ) ? trim( (string) $it['weight'] ) : '';
				$weight_range = isset( $it['weight_range'] ) ? trim( (string) $it['weight_range'] ) : '';
				$is_variable = ! empty( $it['is_variable'] );
				$woff2 = isset( $it['woff2_url'] ) ? trim( (string) $it['woff2_url'] ) : '';
				$woff  = isset( $it['woff_url'] ) ? trim( (string) $it['woff_url'] ) : '';
				if ( $family === '' || $woff2 === '' ) { continue; }
				$out['items'][] = [
					'family' => \sanitize_text_field( $family ),
					'style'  => in_array( $style, [ 'normal', 'italic' ], true ) ? $style : 'normal',
					'display'=> in_array( $display, [ 'auto', 'block', 'swap', 'fallback', 'optional' ], true ) ? $display : 'swap',
					'weight' => preg_replace( '/[^0-9]/', '', $weight ),
					'weight_range' => preg_replace( '/[^0-9\s]/', '', $weight_range ),
					'is_variable' => (bool) $is_variable,
					'woff2_url' => \esc_url_raw( $woff2 ),
					'woff_url'  => \esc_url_raw( $woff ),
				];
			}
		}
		return $out;
	}
	public static function field_fonts_items() : void {
		$o = self::get_fonts_options();
		$items = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : [];
		echo '<div id="ff-items">';
		$i = 0;
		foreach ( $items as $it ) {
			$family = \esc_attr( $it['family'] ?? '' );
			$style  = \esc_attr( $it['style'] ?? 'normal' );
			$display= \esc_attr( $it['display'] ?? 'swap' );
			$weight = \esc_attr( $it['weight'] ?? '' );
			$weight_range = \esc_attr( $it['weight_range'] ?? '' );
			$isv = ! empty( $it['is_variable'] ) ? 'checked' : '';
			$woff2 = \esc_attr( $it['woff2_url'] ?? '' );
			$woff  = \esc_attr( $it['woff_url'] ?? '' );
			echo '<fieldset style="border:1px solid #e5e7eb;padding:10px;margin:8px 0;border-radius:6px">';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Family', 'functionalities' ) . '</label><input class="regular-text" type="text" name="functionalities_fonts[items]['.$i.'][family]" value="'.$family.'" />';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Style', 'functionalities' ) . '</label><select name="functionalities_fonts[items]['.$i.'][style]"><option value="normal" ' . selected( $style, 'normal', false ) . '>normal</option><option value="italic" ' . selected( $style, 'italic', false ) . '>italic</option></select>';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Display', 'functionalities' ) . '</label><select name="functionalities_fonts[items]['.$i.'][display]"><option ' . selected( $display, 'swap', false ) . '>swap</option><option ' . selected( $display, 'auto', false ) . '>auto</option><option ' . selected( $display, 'block', false ) . '>block</option><option ' . selected( $display, 'fallback', false ) . '>fallback</option><option ' . selected( $display, 'optional', false ) . '>optional</option></select>';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Static weight', 'functionalities' ) . '</label><input class="small-text" type="text" name="functionalities_fonts[items]['.$i.'][weight]" value="'.$weight.'" />';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Variable weight range', 'functionalities' ) . '</label><input class="small-text" type="text" name="functionalities_fonts[items]['.$i.'][weight_range]" value="'.$weight_range.'" />';
			echo '<label style="display:block;margin:.25rem 0"><input type="checkbox" name="functionalities_fonts[items]['.$i.'][is_variable]" value="1" ' . $isv . ' /> ' . \esc_html__( 'Variable font', 'functionalities' ) . '</label>';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'WOFF2 URL', 'functionalities' ) . '</label><input class="regular-text code" type="url" name="functionalities_fonts[items]['.$i.'][woff2_url]" value="'.$woff2.'" />';
			echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'WOFF URL (fallback)', 'functionalities' ) . '</label><input class="regular-text code" type="url" name="functionalities_fonts[items]['.$i.'][woff_url]" value="'.$woff.'" />';
			echo '</fieldset>';
			$i++;
		}
		// new row
		echo '<fieldset style="border:1px dashed #e5e7eb;padding:10px;margin:8px 0;border-radius:6px">';
		echo '<legend>' . \esc_html__( 'Add new font', 'functionalities' ) . '</legend>';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Family', 'functionalities' ) . '</label><input class="regular-text" type="text" name="functionalities_fonts[items]['.$i.'][family]" />';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Style', 'functionalities' ) . '</label><select name="functionalities_fonts[items]['.$i.'][style]"><option value="normal">normal</option><option value="italic">italic</option></select>';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Display', 'functionalities' ) . '</label><select name="functionalities_fonts[items]['.$i.'][display]"><option>swap</option><option>auto</option><option>block</option><option>fallback</option><option>optional</option></select>';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Static weight', 'functionalities' ) . '</label><input class="small-text" type="text" name="functionalities_fonts[items]['.$i.'][weight]" />';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'Variable weight range', 'functionalities' ) . '</label><input class="small-text" type="text" name="functionalities_fonts[items]['.$i.'][weight_range]" />';
		echo '<label style="display:block;margin:.25rem 0"><input type="checkbox" name="functionalities_fonts[items]['.$i.'][is_variable]" value="1" /> ' . \esc_html__( 'Variable font', 'functionalities' ) . '</label>';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'WOFF2 URL', 'functionalities' ) . '</label><input class="regular-text code" type="url" name="functionalities_fonts[items]['.$i.'][woff2_url]" />';
		echo '<label style="display:block;margin:.25rem 0;font-weight:600">' . \esc_html__( 'WOFF URL (fallback)', 'functionalities' ) . '</label><input class="regular-text code" type="url" name="functionalities_fonts[items]['.$i.'][woff_url]" />';
		echo '</fieldset>';
		echo '</div>';
	}

	// Icons helpers
	public static function get_icons_options() : array {
		$defaults = [ 'enable_fa_replacement' => false, 'remove_fa_assets' => true, 'sprite_url' => '', 'mappings' => '' ];
		$opts = (array) \get_option( 'functionalities_icons', $defaults );
		return array_merge( $defaults, $opts );
	}
	public static function sanitize_icons( $input ) : array {
		return [
			'enable_fa_replacement' => ! empty( $input['enable_fa_replacement'] ),
			'remove_fa_assets'      => ! empty( $input['remove_fa_assets'] ),
			'sprite_url'            => isset( $input['sprite_url'] ) ? \esc_url_raw( (string) $input['sprite_url'] ) : '',
			'mappings'              => isset( $input['mappings'] ) ? \sanitize_textarea_field( (string) $input['mappings'] ) : '',
		];
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
	 * Sanitize Meta settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_meta( $input ) : array {
		$valid_licenses = array(
			'all-rights-reserved',
			'cc-by',
			'cc-by-sa',
			'cc-by-nc',
			'cc-by-nc-sa',
			'cc-by-nd',
			'cc-by-nc-nd',
			'cc0',
		);

		$valid_holder_types = array( 'author', 'site', 'custom' );

		$out = array(
			'enabled'                   => ! empty( $input['enabled'] ),
			'enable_copyright_meta'     => ! empty( $input['enable_copyright_meta'] ),
			'enable_dublin_core'        => ! empty( $input['enable_dublin_core'] ),
			'enable_license_metabox'    => ! empty( $input['enable_license_metabox'] ),
			'enable_schema_integration' => ! empty( $input['enable_schema_integration'] ),
			'default_license'           => 'all-rights-reserved',
			'default_license_url'       => '',
			'post_types'                => array( 'post' ),
			'copyright_holder_type'     => 'author',
			'custom_copyright_holder'   => '',
			'dc_language'               => '',
		);

		// Validate default license.
		if ( isset( $input['default_license'] ) ) {
			$license = \sanitize_key( (string) $input['default_license'] );
			if ( in_array( $license, $valid_licenses, true ) ) {
				$out['default_license'] = $license;
			}
		}

		// Sanitize license URL.
		if ( isset( $input['default_license_url'] ) ) {
			$out['default_license_url'] = \esc_url_raw( (string) $input['default_license_url'] );
		}

		// Validate post types.
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$out['post_types'] = array();
			foreach ( $input['post_types'] as $pt ) {
				$pt = \sanitize_key( (string) $pt );
				if ( \post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
			if ( empty( $out['post_types'] ) ) {
				$out['post_types'] = array( 'post' );
			}
		}

		// Validate copyright holder type.
		if ( isset( $input['copyright_holder_type'] ) ) {
			$type = \sanitize_key( (string) $input['copyright_holder_type'] );
			if ( in_array( $type, $valid_holder_types, true ) ) {
				$out['copyright_holder_type'] = $type;
			}
		}

		// Sanitize custom copyright holder.
		if ( isset( $input['custom_copyright_holder'] ) ) {
			$out['custom_copyright_holder'] = \sanitize_text_field( (string) $input['custom_copyright_holder'] );
		}

		// Sanitize DC language.
		if ( isset( $input['dc_language'] ) ) {
			$out['dc_language'] = \sanitize_text_field( (string) $input['dc_language'] );
		}

		return $out;
	}

	/**
	 * Get Meta options with defaults.
	 *
	 * @return array Meta options.
	 */
	public static function get_meta_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_meta', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Render section description for GitHub Updates.
	 *
	 * @return void
	 */
	public static function section_updates() : void {
		echo '<p>' . \esc_html__( 'Receive plugin updates directly from GitHub releases. Configure your repository details below to enable automatic update checks.', 'functionalities' ) . '</p>';

		// Get module docs.
		$docs = Module_Docs::get( 'updates' );

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

		// How it works.
		$how_html = '<ol>';
		$how_html .= '<li>' . \esc_html__( 'Create releases on GitHub with version tags (e.g., v0.5.0 or 0.5.0)', 'functionalities' ) . '</li>';
		$how_html .= '<li>' . \esc_html__( 'WordPress will check GitHub for new releases based on your interval setting', 'functionalities' ) . '</li>';
		$how_html .= '<li>' . \esc_html__( 'When a new version is found, update notification appears in your dashboard', 'functionalities' ) . '</li>';
		$how_html .= '<li>' . \esc_html__( 'Click "Update Now" to install the new version with one click', 'functionalities' ) . '</li>';
		$how_html .= '</ol>';
		Admin_UI::render_docs_section( \__( 'How It Works', 'functionalities' ), $how_html, 'usage' );

		// Release requirements.
		$req_html = '<ul>';
		$req_html .= '<li>' . \esc_html__( 'Tag format: v1.0.0, 1.0.0, or any semver format', 'functionalities' ) . '</li>';
		$req_html .= '<li>' . \esc_html__( 'Attach a .zip file to the release (recommended) OR use auto-generated zipball', 'functionalities' ) . '</li>';
		$req_html .= '<li>' . \esc_html__( 'The zip should contain the plugin files in a folder matching the plugin directory name', 'functionalities' ) . '</li>';
		$req_html .= '</ul>';
		Admin_UI::render_docs_section( \__( 'Release Requirements', 'functionalities' ), $req_html, 'developer' );

		if ( ! empty( $docs['usage'] ) ) {
			Admin_UI::render_docs_section( \__( 'Usage Note', 'functionalities' ), '<p>' . \esc_html( $docs['usage'] ) . '</p>', 'caution' );
		}

		echo '</div>';
	}

	/**
	 * Render update status field.
	 *
	 * @return void
	 */
	public static function field_update_status() : void {
		$options = self::get_updates_options();

		if ( empty( $options['enabled'] ) || empty( $options['github_owner'] ) || empty( $options['github_repo'] ) ) {
			echo '<p style="color:#6b7280">' . \esc_html__( 'Configure settings above and save to check for updates.', 'functionalities' ) . '</p>';
			return;
		}

		// Get current version.
		$current_version = FUNCTIONALITIES_VERSION;

		// Try to get cached release info.
		$cache_key = 'functionalities_github_update_' . md5( \plugin_basename( FUNCTIONALITIES_FILE ) );
		$release   = \get_transient( $cache_key );

		echo '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px">';
		echo '<p style="margin:0 0 8px"><strong>' . \esc_html__( 'Current Version:', 'functionalities' ) . '</strong> ' . \esc_html( $current_version ) . '</p>';
		echo '<p style="margin:0 0 8px"><strong>' . \esc_html__( 'Repository:', 'functionalities' ) . '</strong> ';
		echo '<a href="https://github.com/' . \esc_attr( $options['github_owner'] ) . '/' . \esc_attr( $options['github_repo'] ) . '" target="_blank">';
		echo \esc_html( $options['github_owner'] . '/' . $options['github_repo'] );
		echo '</a></p>';

		if ( $release && is_object( $release ) ) {
			echo '<p style="margin:0 0 8px"><strong>' . \esc_html__( 'Latest Release:', 'functionalities' ) . '</strong> ';
			if ( version_compare( $current_version, $release->version, '<' ) ) {
				echo '<span style="color:#dc2626">' . \esc_html( $release->version ) . ' (' . \esc_html__( 'Update available!', 'functionalities' ) . ')</span>';
			} else {
				echo '<span style="color:#059669">' . \esc_html( $release->version ) . ' (' . \esc_html__( 'Up to date', 'functionalities' ) . ')</span>';
			}
			echo '</p>';
		} else {
			echo '<p style="margin:0;color:#6b7280">' . \esc_html__( 'No cached release data. Use "Check for updates" link on the Plugins page.', 'functionalities' ) . '</p>';
		}

		echo '</div>';

		// Add manual check link.
		$check_url = \wp_nonce_url(
			\admin_url( 'plugins.php?functionalities_check_update=1' ),
			'functionalities_check_update'
		);
		echo '<p style="margin-top:8px"><a href="' . \esc_url( $check_url ) . '" class="button">' . \esc_html__( 'Check Now', 'functionalities' ) . '</a></p>';
	}

	/**
	 * Sanitize GitHub Updates settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_updates( $input ) : array {
		$current = self::get_updates_options();

		$out = array(
			'enabled'        => ! empty( $input['enabled'] ),
			'github_owner'   => 'wpgaurav',
			'github_repo'    => 'functionalities',
			'access_token'   => $current['access_token'], // Preserve existing token by default.
			'cache_duration' => 21600,
		);

		// Sanitize owner (alphanumeric, hyphens, underscores).
		if ( isset( $input['github_owner'] ) ) {
			$out['github_owner'] = preg_replace( '/[^a-zA-Z0-9\-_]/', '', (string) $input['github_owner'] );
		}

		// Sanitize repo name.
		if ( isset( $input['github_repo'] ) ) {
			$out['github_repo'] = preg_replace( '/[^a-zA-Z0-9\-_\.]/', '', (string) $input['github_repo'] );
		}

		// Only update token if a new one was provided.
		if ( isset( $input['access_token'] ) && ! empty( trim( $input['access_token'] ) ) ) {
			$out['access_token'] = \sanitize_text_field( $input['access_token'] );
		}

		// Validate cache duration.
		$valid_durations = array( 3600, 10800, 21600, 43200, 86400 );
		if ( isset( $input['cache_duration'] ) ) {
			$duration = (int) $input['cache_duration'];
			if ( in_array( $duration, $valid_durations, true ) ) {
				$out['cache_duration'] = $duration;
			}
		}

		// Clear update cache when settings change.
		$cache_key = 'functionalities_github_update_' . md5( \plugin_basename( FUNCTIONALITIES_FILE ) );
		\delete_transient( $cache_key );

		return $out;
	}

	/**
	 * Get GitHub Updates options with defaults.
	 *
	 * @return array Updates options.
	 */
	public static function get_updates_options() : array {
		$defaults = array(
			'enabled'        => false,
			'github_owner'   => 'wpgaurav',
			'github_repo'    => 'functionalities',
			'access_token'   => '',
			'cache_duration' => 21600,
		);
		$opts = (array) \get_option( 'functionalities_updates', $defaults );
		return array_merge( $defaults, $opts );
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
	 * Sanitize content regression settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_content_regression( $input ) : array {
		$out = array(
			'enabled'                     => ! empty( $input['enabled'] ),
			'post_types'                  => array( 'post', 'page' ),
			'link_drop_enabled'           => ! empty( $input['link_drop_enabled'] ),
			'link_drop_percent'           => 30,
			'link_drop_absolute'          => 3,
			'exclude_nofollow_links'      => ! empty( $input['exclude_nofollow_links'] ),
			'word_count_enabled'          => ! empty( $input['word_count_enabled'] ),
			'word_count_drop_percent'     => 35,
			'word_count_min_age_days'     => 30,
			'word_count_compare_average'  => ! empty( $input['word_count_compare_average'] ),
			'exclude_shortcodes'          => ! empty( $input['exclude_shortcodes'] ),
			'heading_enabled'             => ! empty( $input['heading_enabled'] ),
			'detect_missing_h1'           => ! empty( $input['detect_missing_h1'] ),
			'detect_multiple_h1'          => ! empty( $input['detect_multiple_h1'] ),
			'detect_skipped_levels'       => ! empty( $input['detect_skipped_levels'] ),
			'snapshot_rolling_count'      => 5,
			'show_post_column'            => ! empty( $input['show_post_column'] ),
		);

		// Sanitize post types.
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$out['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		}

		// Sanitize numeric thresholds.
		if ( isset( $input['link_drop_percent'] ) ) {
			$val = (int) $input['link_drop_percent'];
			$out['link_drop_percent'] = max( 1, min( 100, $val ) );
		}

		if ( isset( $input['link_drop_absolute'] ) ) {
			$val = (int) $input['link_drop_absolute'];
			$out['link_drop_absolute'] = max( 1, min( 100, $val ) );
		}

		if ( isset( $input['word_count_drop_percent'] ) ) {
			$val = (int) $input['word_count_drop_percent'];
			$out['word_count_drop_percent'] = max( 1, min( 100, $val ) );
		}

		if ( isset( $input['word_count_min_age_days'] ) ) {
			$val = (int) $input['word_count_min_age_days'];
			$out['word_count_min_age_days'] = max( 0, min( 365, $val ) );
		}

		if ( isset( $input['snapshot_rolling_count'] ) ) {
			$val = (int) $input['snapshot_rolling_count'];
			$out['snapshot_rolling_count'] = max( 1, min( 20, $val ) );
		}

		return $out;
	}

	/**
	 * Get content regression detection options with defaults.
	 *
	 * @return array Content regression options.
	 */
	public static function get_content_regression_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_content_regression', $defaults );
		return array_merge( $defaults, $opts );
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
		$assumptions = \Functionalities\Features\Assumption_Detection::get_detected();
		$ignored = \Functionalities\Features\Assumption_Detection::get_ignored();

		if ( empty( $assumptions ) ) {
			echo '<div class="functionalities-no-assumptions">';
			echo '<span class="dashicons dashicons-yes-alt"></span>';
			echo '<span>' . \esc_html__( 'No assumption changes detected. All monitored items are consistent.', 'functionalities' ) . '</span>';
			echo '</div>';
			return;
		}

		echo '<div class="functionalities-assumptions-list">';

		foreach ( $assumptions as $key => $assumption ) {
			// Skip ignored.
			if ( isset( $ignored[ $key ] ) ) {
				continue;
			}

			$type_class = ( $assumption['severity'] ?? 'notice' ) === 'warning' ? 'warning' : 'notice';
			$icon = $type_class === 'warning' ? 'dashicons-warning' : 'dashicons-info';

			echo '<div class="functionalities-assumption-item" data-key="' . \esc_attr( $key ) . '">';
			echo '<div class="functionalities-assumption-header">';
			echo '<span class="functionalities-assumption-icon ' . \esc_attr( $type_class ) . ' dashicons ' . \esc_attr( $icon ) . '"></span>';
			echo '<div class="functionalities-assumption-content">';
			echo '<p class="functionalities-assumption-message">' . \esc_html( $assumption['message'] ?? '' ) . '</p>';

			if ( ! empty( $assumption['details'] ) ) {
				echo '<p class="functionalities-assumption-details">' . \esc_html( $assumption['details'] ) . '</p>';
			}

			if ( ! empty( $assumption['detected'] ) ) {
				$time_ago = \human_time_diff( $assumption['detected'], \time() );
				/* translators: %s: human-readable time difference */
				echo '<p class="functionalities-assumption-meta">' . \sprintf( \esc_html__( 'Detected %s ago', 'functionalities' ), $time_ago ) . '</p>';
			}

			echo '</div></div>';

			// Actions.
			echo '<div class="functionalities-assumption-actions">';
			echo '<button type="button" class="button button-small functionalities-assumption-acknowledge" data-key="' . \esc_attr( $key ) . '">';
			echo \esc_html__( 'Acknowledge', 'functionalities' );
			echo '</button>';
			echo '<button type="button" class="button button-small functionalities-assumption-ignore" data-key="' . \esc_attr( $key ) . '">';
			echo \esc_html__( 'Ignore', 'functionalities' );
			echo '</button>';
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';

		// Inline script for AJAX actions.
		?>
		<script>
		jQuery(function($) {
			$('.functionalities-assumption-acknowledge, .functionalities-assumption-ignore').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.functionalities-assumption-item');
				var key = $btn.data('key');
				var action = $btn.hasClass('functionalities-assumption-ignore') ? 'ignore' : 'acknowledge';

				$.post(ajaxurl, {
					action: 'functionalities_assumption_action',
					key: key,
					assumption_action: action,
					nonce: '<?php echo \esc_js( \wp_create_nonce( 'functionalities_assumption_action' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$item.fadeOut(300, function() {
							$(this).remove();
							if ($('.functionalities-assumption-item').length === 0) {
								$('.functionalities-assumptions-list').html(
									'<div class="functionalities-no-assumptions">' +
									'<span class="dashicons dashicons-yes-alt"></span>' +
									'<span><?php echo \esc_js( \__( 'No assumption changes detected. All monitored items are consistent.', 'functionalities' ) ); ?></span>' +
									'</div>'
								);
							}
						});
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Sanitize assumption detection settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_assumption_detection( $input ) : array {
		return array(
			'enabled'                       => ! empty( $input['enabled'] ),
			'detect_schema_collision'       => ! empty( $input['detect_schema_collision'] ),
			'detect_analytics_dupe'         => ! empty( $input['detect_analytics_dupe'] ),
			'detect_font_redundancy'        => ! empty( $input['detect_font_redundancy'] ),
			'detect_inline_css_growth'      => ! empty( $input['detect_inline_css_growth'] ),
			'inline_css_threshold_kb'       => isset( $input['inline_css_threshold_kb'] )
				? max( 10, min( 500, (int) $input['inline_css_threshold_kb'] ) )
				: 50,
			'detect_jquery_conflicts'       => ! empty( $input['detect_jquery_conflicts'] ),
			'detect_meta_duplication'       => ! empty( $input['detect_meta_duplication'] ),
			'detect_rest_exposure'          => ! empty( $input['detect_rest_exposure'] ),
			'detect_lazy_load_conflict'     => ! empty( $input['detect_lazy_load_conflict'] ),
			'detect_mixed_content'          => ! empty( $input['detect_mixed_content'] ),
			'detect_missing_security_headers' => ! empty( $input['detect_missing_security_headers'] ),
			'detect_debug_exposure'         => ! empty( $input['detect_debug_exposure'] ),
			'detect_cron_issues'            => ! empty( $input['detect_cron_issues'] ),
		);
	}

	/**
	 * Get assumption detection options with defaults.
	 *
	 * @return array Assumption detection options.
	 */
	public static function get_assumption_detection_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_assumption_detection', $defaults );
		return array_merge( $defaults, $opts );
	}
}
