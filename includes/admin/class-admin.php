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
					'remove_heading_block_class' => false,
					'remove_list_block_class'    => false,
					'remove_image_block_class'   => false,
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
			\__( 'Remove wp-block-heading on headings', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_heading' ],
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_list_block_class',
			\__( 'Remove wp-block-list on lists', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_list' ],
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_image_block_class',
			\__( 'Remove .wp-block-image', 'functionalities' ),
			[ __CLASS__, 'field_bc_remove_image' ],
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
				echo '<p>' . \esc_html__( 'Limit link suggestions to selected post types in the editor search UI.', 'functionalities' ) . '</p>';
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
				echo '<p>' . \esc_html__( 'Add service codes to header/footer. For Google Analytics 4, provide just the Measurement ID (e.g., G-XXXXXXX).', 'functionalities' ) . '</p>';
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
				echo '<p>' . \esc_html__( 'Add microdata (itemscope/itemtype) to key areas and article content.', 'functionalities' ) . '</p>';
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
				echo '<p>' . \esc_html__( 'Define reusable UI components as CSS class + rules. These will be enqueued site-wide.', 'functionalities' ) . '</p>';
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
				echo '<p>' . \esc_html__( 'Enable selective cleanups and performance tweaks.', 'functionalities' ) . '</p>';
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
				echo '<p>' . \esc_html__( 'Register custom font families (WOFF2 recommended). Variable fonts supported via weight ranges.', 'functionalities' ) . '</p>';
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
			function(){ echo '<p>' . \esc_html__( 'Replace Font Awesome elements with SVG <use> icons and optionally remove FA assets. Provide an SVG sprite and mappings as needed.', 'functionalities' ) . '</p>'; },
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
		echo '<p>' . \esc_html__( 'Control how external and internal links are handled.', 'functionalities' ) . '</p>';
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
		echo '<input type="text" class="regular-text code" name="functionalities_link_management[json_preset_url]" value="' . \esc_attr( $val ) . '" placeholder="' . \esc_attr( FUNCTIONALITIES_DIR . 'exception-urls.json' ) . '" />';
		echo '<p class="description">' . \esc_html__( 'Optional: Path to JSON file containing exception URLs. Format: {"urls": ["https://example.com"]}', 'functionalities' ) . '</p>';
		echo '<p class="description">' . \esc_html__( 'Filter available: functionalities_json_preset_path', 'functionalities' ) . '</p>';
	}

	/**
	 * Render enable developer filters field.
	 *
	 * @return void
	 */
	public static function field_enable_developer_filters() : void {
		$opts    = self::get_link_management_options();
		$checked = ! empty( $opts['enable_developer_filters'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="functionalities_link_management[enable_developer_filters]" value="1" ' . $checked . '> ';
		echo \esc_html__( 'Enable developer filters for exception customization', 'functionalities' ) . '</label>';
		echo '<p class="description">' . \esc_html__( 'Available filters: functionalities_exception_domains, functionalities_exception_urls, gtnf_exception_domains (legacy), gtnf_exception_urls (legacy)', 'functionalities' ) . '</p>';
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
		echo '<p>' . \esc_html__( 'Strip block classes from frontend output.', 'functionalities' ) . '</p>';
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
			'remove_heading_block_class' => ! empty( $input['remove_heading_block_class'] ),
			'remove_list_block_class'    => ! empty( $input['remove_list_block_class'] ),
			'remove_image_block_class'   => ! empty( $input['remove_image_block_class'] ),
		];
	}
	public static function get_block_cleanup_options() : array {
		$defaults = [
			'remove_heading_block_class' => false,
			'remove_list_block_class'    => false,
			'remove_image_block_class'   => false,
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
		echo '<style>
			.fc-accordions{border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
			.fc-acc{border-top:1px solid #e5e7eb}
			.fc-acc:first-child{border-top:0}
			.fc-acc__hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;background:#f9fafb;cursor:pointer}
			.fc-acc__title{font-weight:600;margin:0}
			.fc-acc__body{display:none;padding:1rem;background:#fff}
			.fc-acc.is-open .fc-acc__body{display:block}
			.fc-fields .field{margin-bottom:.5rem}
			.fc-fields .field label{display:block;font-weight:600;margin-bottom:.25rem}
		</style>';
		echo '<div class="fc-accordions" id="fc-accordions">';
		$i = 0;
		foreach ( $items as $item ) {
			$name = \esc_attr( $item['name'] ?? '' );
			$class = \esc_attr( $item['class'] ?? '' );
			$css = \esc_textarea( $item['css'] ?? '' );
			echo '<div class="fc-acc">';
			echo '<div class="fc-acc__hdr"><h3 class="fc-acc__title">' . ( $name !== '' ? $name : \esc_html__( 'Component', 'functionalities' ) ) . '</h3><span class="dashicons dashicons-arrow-down-alt2"></span></div>';
			echo '<div class="fc-acc__body">';
			echo '<div class="fc-fields">';
			echo '<div class="field"><label>' . \esc_html__( 'Name', 'functionalities' ) . '</label><input type="text" name="functionalities_components[items]['.$i.'][name]" value="'.$name.'" class="regular-text" /></div>';
			echo '<div class="field"><label>' . \esc_html__( 'Class', 'functionalities' ) . '</label><input type="text" name="functionalities_components[items]['.$i.'][class]" value="'.$class.'" class="regular-text code" placeholder=".c-card or .btn.primary" /></div>';
			echo '<div class="field"><label>' . \esc_html__( 'CSS Rules', 'functionalities' ) . '</label><textarea name="functionalities_components[items]['.$i.'][css]" rows="5" cols="50" class="large-text code">'.$css.'</textarea></div>';
			echo '</div>'; // fields
			echo '</div>'; // body
			echo '</div>'; // acc
			$i++;
		}
		// one empty accordion for new entry
		echo '<div class="fc-acc is-open">';
		echo '<div class="fc-acc__hdr"><h3 class="fc-acc__title">' . \esc_html__( 'New Component', 'functionalities' ) . '</h3><span class="dashicons dashicons-arrow-down-alt2"></span></div>';
		echo '<div class="fc-acc__body">';
		echo '<div class="fc-fields">';
		echo '<div class="field"><label>' . \esc_html__( 'Name', 'functionalities' ) . '</label><input type="text" name="functionalities_components[items]['.$i.'][name]" value="" class="regular-text" /></div>';
		echo '<div class="field"><label>' . \esc_html__( 'Class', 'functionalities' ) . '</label><input type="text" name="functionalities_components[items]['.$i.'][class]" value="" class="regular-text code" placeholder=".c-custom" /></div>';
		echo '<div class="field"><label>' . \esc_html__( 'CSS Rules', 'functionalities' ) . '</label><textarea name="functionalities_components[items]['.$i.'][css]" rows="5" cols="50" class="large-text code"></textarea></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<script>(function(){const root=document.getElementById("fc-accordions");if(!root)return;root.addEventListener("click",function(e){const hdr=e.target.closest(".fc-acc__hdr");if(!hdr)return;const acc=hdr.parentElement;acc.classList.toggle("is-open");});})();</script>';
		echo '<p class="description">' . \esc_html__( 'Click a panel to expand. Fill the last “New Component” panel to add more; a fresh one will appear after saving.', 'functionalities' ) . '</p>';
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
}
