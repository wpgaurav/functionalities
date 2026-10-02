<?php
/**
 * Module settings controller for Functionalities plugin.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load traits.
require_once __DIR__ . '/trait-admin-utilities-ui.php';
require_once __DIR__ . '/trait-admin-ajax.php';
require_once __DIR__ . '/trait-admin-options.php';
require_once __DIR__ . '/trait-admin-sanitizers.php';
require_once __DIR__ . '/trait-admin-settings.php';
require_once __DIR__ . '/trait-admin-components-ui.php';
require_once __DIR__ . '/trait-admin-fonts-ui.php';
require_once __DIR__ . '/trait-admin-task-manager-ui.php';
require_once __DIR__ . '/trait-admin-redirect-manager-ui.php';
require_once __DIR__ . '/trait-admin-svg-icons-ui.php';
require_once __DIR__ . '/trait-admin-snippets-ui.php';
require_once __DIR__ . '/trait-admin-pwa-ui.php';

/**
 * Controller for module settings and legacy-compatible admin UI.
 *
 * This class uses traits to organize its methods:
 * - Admin_Ajax: AJAX handlers
 * - Admin_Options: Options getters
 * - Admin_Sanitizers: Sanitizer methods
 */
class Module_Controller {

	use Admin_Utilities_UI;
	use Admin_Ajax;
	use Admin_Options;
	use Admin_Sanitizers;
	use Admin_Settings;
	use Admin_Components_UI;
	use Admin_Fonts_UI;
	use Admin_Task_Manager_UI;
	use Admin_Redirect_Manager_UI;
	use Admin_SVG_Icons_UI;
	use Admin_Snippets_UI;
	use Admin_PWA_UI;

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
	public static function init(): void {
		self::define_modules();
		self::init_utility_actions();
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
	private static function define_modules(): void {
		self::$modules = \Functionalities\Core\Module_Registry::get_admin_modules();
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		$parent_slug = 'functionalities';
		\add_menu_page(
			\__( 'Functionalities', 'functionalities' ),
			\__( 'Functionalities', 'functionalities' ),
			'manage_options',
			$parent_slug,
			array( __CLASS__, 'render_main_page' ),
			FUNCTIONALITIES_URL . 'assets/brand/functionalities.svg',
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
	public static function enqueue_admin_assets( $hook ): void {
		\wp_enqueue_style( 'functionalities-admin-brand', FUNCTIONALITIES_URL . 'assets/css/admin-brand.css', array(), self::admin_asset_version( 'assets/css/admin-brand.css' ) );
		if ( strpos( $hook, 'functionalities' ) === false ) {
			return;
		}

		$deps = array( 'jquery' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection doesn't require nonce.
		$page = isset( $_GET['page'] ) ? \sanitize_key( $_GET['page'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Module detection doesn't require nonce.
		$module = isset( $_GET['module'] ) ? \sanitize_key( $_GET['module'] ) : '';
		if ( '' === $module && 0 === strpos( $page, 'functionalities-' ) ) {
			$module = substr( $page, strlen( 'functionalities-' ) );
		}

		if ( 'task-manager' === $module || 'functionalities-task-manager' === $page ) {
			$deps[] = 'jquery-ui-sortable';
		}
		if ( 'redirect-manager' === $module || 'functionalities-redirect-manager' === $page ) {
			\wp_enqueue_script(
				'functionalities-admin-redirects',
				FUNCTIONALITIES_URL . 'assets/js/admin-redirects.js',
				array( 'jquery' ),
				FUNCTIONALITIES_VERSION,
				true
			);
			\wp_localize_script(
				'functionalities-admin-redirects',
				'functionalitiesRedirectAdmin',
				array(
					'ajaxUrl'      => \admin_url( 'admin-ajax.php' ),
					'nonce'        => \wp_create_nonce( 'functionalities_redirect_manager' ),
					'bothRequired' => \__( 'Both URLs are required.', 'functionalities' ),
					'deletePrompt' => \__( 'Delete this redirect?', 'functionalities' ),
					'purgePrompt'  => \__( 'Purge the retained 404 log?', 'functionalities' ),
				)
			);
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

		\wp_enqueue_style( 'functionalities-admin-icons', FUNCTIONALITIES_URL . 'assets/css/admin-icons.css', array( 'functionalities-admin' ), self::admin_asset_version( 'assets/css/admin-icons.css' ) );
		\wp_enqueue_style( 'functionalities-admin-polish', FUNCTIONALITIES_URL . 'assets/css/admin-polish.css', array( 'functionalities-admin-icons' ), self::admin_asset_version( 'assets/css/admin-polish.css' ) );
		\wp_enqueue_script( 'functionalities-admin-polish', FUNCTIONALITIES_URL . 'assets/js/admin-polish.js', array(), self::admin_asset_version( 'assets/js/admin-polish.js' ), true );

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
				'ajaxUrl'           => \admin_url( 'admin-ajax.php' ),
				'runDetectionNonce' => \wp_create_nonce( 'functionalities_run_detection' ),
				'runningText'       => \__( 'Running...', 'functionalities' ),
				'runDetectionText'  => \__( 'Run Detection Now', 'functionalities' ),
			)
		);
	}

	/** Give edited backend assets a fresh cache key while a release is being tested. */
	private static function admin_asset_version( string $relative ): string {
		$path = FUNCTIONALITIES_DIR . $relative;
		return FUNCTIONALITIES_VERSION . '-' . ( is_file( $path ) ? (string) filemtime( $path ) : '0' );
	}

	/**
	 * Render main admin page with module navigation.
	 *
	 * @return void
	 */
	public static function render_main_page(): void {
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
	private static function is_module_enabled( string $slug ): bool {
		return \Functionalities\Core\Module_Registry::is_enabled( $slug );
	}

	private static function render_dashboard(): void {
		?>
		<div class="wrap functionalities-dashboard">
			<?php Admin_UI::render_header( \__( 'Dynamic Functionalities', 'functionalities' ), \__( 'Tools for your content, workflow, performance, and security. Enable only the modules you need.', 'functionalities' ) ); ?>

			<div class="functionalities-modules-grid">
				<?php
				foreach ( self::$modules as $slug => $module ) :
					$is_active = self::is_module_enabled( $slug );
					?>
					<div class="functionalities-module-card">
						<div class="module-card-header">
							<?php echo Admin_Icons::module( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted local icon markup. ?>
							<h2><?php echo \esc_html( $module['title'] ); ?></h2>
						</div>
						<p class="module-description"><?php echo \esc_html( $module['description'] ); ?></p>
						<div class="functionalities-card-actions">
							<a href="<?php echo \esc_url( self::get_module_url( $slug ) ); ?>" class="button button-primary">
								<?php echo \esc_html__( 'Configure', 'functionalities' ); ?>
							</a>
							<?php if ( $is_active ) : ?>
								<span class="functionalities-status functionalities-status--active"><span class="functionalities-icon functionalities-icon--check" aria-hidden="true"></span><?php echo \esc_html__( 'Active', 'functionalities' ); ?></span>
							<?php else : ?>
								<span class="functionalities-status"><?php echo \esc_html__( 'Inactive', 'functionalities' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php \do_action( 'functionalities_admin_dashboard_tools' ); ?>

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

			<style>
				.functionalities-help-section .functionalities-help-section__buttons{display:flex;flex-wrap:wrap;gap:10px;}
				.functionalities-help-section a.functionalities-help-btn.button{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;color:#2271b1;border:1px solid #2271b1;border-radius:4px;font-weight:500;text-decoration:none;line-height:1.4;box-shadow:none;}
				.functionalities-help-section a.functionalities-help-btn.button:hover,
				.functionalities-help-section a.functionalities-help-btn.button:focus{background:#f0f6fc;color:#0a4b78;border-color:#0a4b78;box-shadow:none;}
				.functionalities-help-section a.functionalities-help-btn.button:focus{outline:2px solid #2271b1;outline-offset:1px;}
				.functionalities-help-section a.functionalities-help-btn.button .dashicons{font-size:16px;width:16px;height:16px;line-height:1;color:inherit;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;}
			</style>
			<div class="functionalities-help-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
				<h2 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-editor-help" style="font-size: 24px; width: 24px; height: 24px;"></span>
					<?php echo \esc_html__( 'Help & Support', 'functionalities' ); ?>
				</h2>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php echo \esc_html__( 'Need help with Dynamic Functionalities? Check out these resources:', 'functionalities' ); ?>
				</p>
				<div class="functionalities-help-section__buttons">
					<a href="https://gauravtiwari.org/portal/course/functionalities-training/lessons" target="_blank" rel="noopener" class="button functionalities-help-btn">
						<span class="dashicons dashicons-book"></span>
						<?php echo \esc_html__( 'Documentation', 'functionalities' ); ?>
					</a>
					<a href="https://wordpress.org/support/plugin/functionalities/" target="_blank" rel="noopener" class="button functionalities-help-btn">
						<span class="dashicons dashicons-sos"></span>
						<?php echo \esc_html__( 'Support', 'functionalities' ); ?>
					</a>
					<a href="https://github.com/wpgaurav/functionalities/issues" target="_blank" rel="noopener" class="button functionalities-help-btn">
						<span class="dashicons dashicons-flag"></span>
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
	private static function render_module( $module_slug ): void {
		$module = self::$modules[ $module_slug ];

		// Handle custom page modules.
		if ( ! empty( $module['custom_page'] ) && ! empty( $module['controller'] ) && is_callable( array( $module['controller'], 'render' ) ) ) {
			call_user_func( array( $module['controller'], 'render' ), $module );
			return;
		}

		?>
		<div class="wrap functionalities-module">
			<?php Admin_UI::render_header( $module['title'], $module['description'], $module_slug ); ?>

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
	private static function get_module_url( $module_slug ): string {
		return \admin_url( 'admin.php?page=functionalities&module=' . \rawurlencode( $module_slug ) );
	}

	public static function field_nofollow_external(): void {
		$opts    = self::get_link_management_options();
		$checked = ! empty( $opts['nofollow_external'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[nofollow_external]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Enable rel="nofollow" for all external links', 'functionalities' ) . '</label>';
	}

	/**
	 * Render exceptions field.
	 *
	 * @return void
	 */
	public static function field_exceptions(): void {
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
	public static function section_link_management(): void {
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
	public static function field_open_external_new_tab(): void {
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
	public static function field_open_internal_new_tab(): void {
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
	public static function field_internal_new_tab_exceptions(): void {
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
	public static function field_json_preset_url(): void {
		$opts = self::get_link_management_options();
		$val  = isset( $opts['json_preset_url'] ) ? (string) $opts['json_preset_url'] : '';

		// Check for theme exception-urls.json.
		$theme_json_path    = \get_stylesheet_directory() . '/exception-urls.json';
		$theme_json_exists  = file_exists( $theme_json_path );
		$parent_json_path   = \get_template_directory() . '/exception-urls.json';
		$parent_json_exists = \get_stylesheet_directory() !== \get_template_directory() && file_exists( $parent_json_path );
		?>
		<div class="functionalities-json-picker">
			<div class="functionalities-json-picker-input">
				<input type="text" id="functionalities_json_preset_url" class="regular-text code" name="functionalities_link_management[json_preset_url]" aria-label="' . \esc_attr__( 'JSON preset path or URL', 'functionalities' ) . '" value="<?php echo \esc_attr( $val ); ?>" placeholder="<?php echo \esc_attr( FUNCTIONALITIES_DIR . 'exception-urls.json' ); ?>" />
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
				<?php
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_json_preset_path</code> — ' . \esc_html__( 'change where the preset file is read from', 'functionalities' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both segments escaped above; the code tag is literal.
				?>
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
	public static function field_enable_developer_filters(): void {
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
	public static function field_database_update_tool(): void {
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

					var totalUpdated = 0;
					var totalScanned = 0;
					var nonce = '<?php echo esc_attr( \wp_create_nonce( 'functionalities_db_update' ) ); ?>';

					function finish(html, cls) {
						$result.html('<div class="notice notice-' + cls + '"><p>' + html + '</p></div>');
						$btn.prop('disabled', false).text('<?php echo \esc_js( \__( 'Update Database', 'functionalities' ) ); ?>');
					}

					// Walk the whole table with an ID cursor. Each batch reports
					// progress so a large site shows movement instead of stalling.
					function runBatch(after) {
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'functionalities_update_database',
								url: url,
								after: after,
								nonce: nonce
							},
							success: function(response) {
								if (!response.success) {
									finish(response.data.message, 'error');
									return;
								}

								totalUpdated += parseInt(response.data.count, 10) || 0;
								totalScanned += parseInt(response.data.processed, 10) || 0;

								if (response.data.has_more) {
									$result.html('<div class="notice notice-info"><p>' + response.data.message + ' (' + totalUpdated + '/' + totalScanned + ')</p></div>');
									runBatch(parseInt(response.data.last_id, 10) || 0);
									return;
								}

								finish('<?php echo \esc_js( \__( 'Done.', 'functionalities' ) ); ?> ' + totalUpdated + ' / ' + totalScanned, 'success');
							},
							error: function() {
								finish('<?php echo \esc_js( \__( 'An error occurred.', 'functionalities' ) ); ?>', 'error');
							}
						});
					}

					runBatch(0);
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
	public static function section_block_cleanup(): void {
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
		$classes_html  = '<ul style="font-family:monospace;font-size:12px">';
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
	public static function field_bc_remove_heading(): void {
		$opts    = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_heading_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_heading_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class "wp-block-heading" from headings (h1–h6)', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_list(): void {
		$opts    = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_list_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_list_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class "wp-block-list" from ul/ol', 'functionalities' ) . '</label>';
	}
	public static function field_bc_remove_image(): void {
		$opts    = self::get_block_cleanup_options();
		$checked = ! empty( $opts['remove_image_block_class'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_image_block_class]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Remove class ".wp-block-image" from frontend', 'functionalities' ) . '</label>';
	}

	// Editor Links: fields & helpers
	public static function field_el_enable(): void {
		$opts    = self::get_editor_links_options();
		$checked = ! empty( $opts['enable_limit'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_editor_links[enable_limit]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Limit editor link suggestions to selected post types', 'functionalities' ) . '</label>';
	}
	public static function field_el_post_types(): void {
		$opts     = self::get_editor_links_options();
		$selected = isset( $opts['post_types'] ) && is_array( $opts['post_types'] ) ? $opts['post_types'] : array();
		$pts      = get_post_types( array( 'public' => true ), 'objects' );
		echo '<fieldset>';
		foreach ( $pts as $name => $obj ) {
			$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
			$label      = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
			echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_editor_links[post_types][]" value="' . esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
	}

	protected static function add_misc_field( string $key, string $label ): void {
		\add_settings_field(
			$key,
			$label,
			function () use ( $key, $label ) {
				$opts    = self::get_misc_options();
				$checked = ! empty( $opts[ $key ] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_misc[' . esc_attr( $key ) . ']" value="1" ' . esc_attr( $checked ) . '> ' . esc_html( $label ) . '</label>';
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);
	}

	/**
	 * Render section description for Meta & Copyright.
	 *
	 * @return void
	 */
	public static function section_meta(): void {
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
			$schema_content  = '<p><strong>✓ ' . \esc_html__( 'Detected:', 'functionalities' ) . '</strong> ' . \esc_html( $plugin_names[ $detected ] ?? $detected ) . '</p>';
			$schema_content .= '<p>' . \esc_html__( 'Copyright data will be added to your SEO plugin\'s existing schema output.', 'functionalities' ) . '</p>';
		} else {
			$schema_content  = '<p><strong>✓ ' . \esc_html__( 'Standalone Mode', 'functionalities' ) . '</strong></p>';
			$schema_content .= '<p>' . \esc_html__( 'No SEO plugin detected. Complete Article schema with copyright will be output independently.', 'functionalities' ) . '</p>';
		}
		Admin_UI::render_docs_section( \__( 'Schema.org Support', 'functionalities' ), $schema_content, 'developer', true );

		// Compatible plugins.
		$plugins_html  = '<ul style="columns:2">';
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
	public static function section_content_regression(): void {
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
	public static function section_assumption_detection(): void {
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
	public static function field_detected_assumptions(): void {
		$assumptions = \Functionalities\Features\Assumption_Detection::get_detected_assumptions();
		$ignored     = \Functionalities\Features\Assumption_Detection::get_ignored_assumptions();

		// Filter out expired ignored items and already-ignored assumptions.
		$active_warnings = array();
		foreach ( $assumptions as $assumption ) {
			$hash = self::generate_warning_hash( $assumption );
			// Skip if ignored and not expired.
			if ( isset( $ignored[ $hash ] ) && $ignored[ $hash ]['expires'] > time() ) {
				continue;
			}
			$assumption['_hash'] = $hash;
			$active_warnings[]   = $assumption;
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
			$hash         = $assumption['_hash'];
			$warning_type = $assumption['type'] ?? 'unknown';
			$type_class   = 'warning';
			$icon         = 'dashicons-warning';

			echo '<div class="functionalities-assumption-item" data-hash="' . \esc_attr( $hash ) . '" style="background:#fff8e5;border-left:4px solid #dba617;padding:12px;margin-bottom:10px;border-radius:4px;">';
			echo '<div class="functionalities-assumption-header" style="display:flex;align-items:flex-start;gap:10px;">';
			echo '<span class="dashicons ' . \esc_attr( $icon ) . '" style="color:#dba617;flex-shrink:0;margin-top:2px;"></span>';
			echo '<div class="functionalities-assumption-content" style="flex:1;">';
			echo '<p class="functionalities-assumption-message" style="margin:0;font-weight:500;">' . \esc_html( $assumption['message'] ?? '' ) . '</p>';

			// Show location (where) if available.
			if ( ! empty( $assumption['location'] ) ) {
				echo '<p class="functionalities-assumption-location" style="margin:6px 0 0;font-size:12px;color:#1e1e1e;">';
				echo '<strong>' . \esc_html__( 'Where:', 'functionalities' ) . '</strong> ';
				echo wp_kses(
					$assumption['location'],
					array(
						'code'   => array(),
						'strong' => array(),
					)
				);
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
	protected static function generate_warning_hash( array $warning ): string {
		return \Functionalities\Features\Assumption_Detection::get_warning_hash( $warning );
	}

	/**
	 * Render a media upload field.
	 *
	 * @param string $name  Input name attribute.
	 * @param string $value Current URL value.
	 * @param string $desc  Description text.
	 * @return void
	 */
	private static function render_media_field( string $name, string $value, string $desc = '' ): void {
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
}
