<?php
/**
 * Progressive integrations for WordPress 7 platform APIs.
 *
 * @package Functionalities\Core
 */

namespace Functionalities\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connect existing Functionalities features to WordPress 7 APIs.
 *
 * Every hook is feature-detected so the plugin keeps a graceful fallback on
 * older supported WordPress versions. The integration itself has no frontend
 * assets.
 */
class WordPress_7_Integration {

	const OPTION_NAME = 'functionalities_wordpress_7';

	/**
	 * Register platform integration hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		\add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_ability_category' ) );
		\add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		\add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		\add_filter( 'block_bindings_supported_attributes_functionalities/svg-icon-block', array( __CLASS__, 'register_svg_binding_attributes' ) );

		if ( \is_admin() ) {
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			\add_action( 'functionalities_admin_dashboard_tools', array( __CLASS__, 'render_settings_panel' ), 5 );
		}
	}

	/**
	 * Register the Functionalities ability category.
	 *
	 * @return void
	 */
	public static function register_ability_category(): void {
		if ( ! \function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		\wp_register_ability_category(
			'functionalities',
			array(
				'label'       => \__( 'Functionalities', 'functionalities' ),
				'description' => \__( 'Site diagnostics, content checks, redirects, tasks, and module controls.', 'functionalities' ),
			)
		);
	}

	/**
	 * Register machine-readable operations with the Abilities API.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! \function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			'get-module-status',
			\__( 'Get module status', 'functionalities' ),
			\__( 'Returns enabled state and metadata for every Functionalities module.', 'functionalities' ),
			null,
			array( __CLASS__, 'ability_get_module_status' ),
			array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'object' ),
			),
			true
		);

		self::register_ability(
			'run-diagnostics',
			\__( 'Run privacy-safe diagnostics', 'functionalities' ),
			\__( 'Returns software, module, storage, and rewrite health without site URLs, users, task content, redirects, or secrets.', 'functionalities' ),
			null,
			array( __CLASS__, 'ability_run_diagnostics' ),
			array( 'type' => 'object' ),
			true
		);

		self::register_ability(
			'scan-assumptions',
			\__( 'Scan site assumptions', 'functionalities' ),
			\__( 'Runs the configured Assumption Detection checks and returns the current findings.', 'functionalities' ),
			null,
			array( __CLASS__, 'ability_scan_assumptions' ),
			array( 'type' => 'array' ),
			false,
			false
		);

		self::register_ability(
			'check-content-integrity',
			\__( 'Check content integrity', 'functionalities' ),
			\__( 'Checks one post against its Content Integrity baseline.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
			array( __CLASS__, 'ability_check_content_integrity' ),
			array( 'type' => 'array' ),
			true
		);

		self::register_ability(
			'preview-redirect-import',
			\__( 'Preview redirect import', 'functionalities' ),
			\__( 'Validates CSV redirect data and reports duplicates, loops, chains, and invalid rows without saving.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'csv' ),
				'properties' => array(
					'csv' => array(
						'type'      => 'string',
						'maxLength' => 1000000,
					),
				),
			),
			array( __CLASS__, 'ability_preview_redirect_import' ),
			array( 'type' => 'object' ),
			true
		);

		self::register_ability(
			'create-redirect',
			\__( 'Create redirect', 'functionalities' ),
			\__( 'Creates a validated redirect in the Redirect Manager.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'from_url', 'to_url' ),
				'properties' => array(
					'from_url' => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 2000,
					),
					'to_url'   => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 2000,
					),
					'type'     => array(
						'type'    => 'integer',
						'enum'    => array( 301, 302, 307, 308 ),
						'default' => 301,
					),
				),
			),
			array( __CLASS__, 'ability_create_redirect' ),
			array( 'type' => 'object' ),
			false,
			false
		);

		self::register_ability(
			'create-task',
			\__( 'Create task', 'functionalities' ),
			\__( 'Adds a task to an existing Task Manager project.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'project', 'text' ),
				'properties' => array(
					'project' => array(
						'type'      => 'string',
						'pattern'   => '^[a-zA-Z0-9_-]+$',
						'maxLength' => 190,
					),
					'text'    => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 1000,
					),
					'notes'   => array(
						'type'      => 'string',
						'maxLength' => 10000,
						'default'   => '',
					),
				),
			),
			array( __CLASS__, 'ability_create_task' ),
			array( 'type' => 'object' ),
			false,
			false
		);

		self::register_ability(
			'toggle-module',
			\__( 'Toggle module', 'functionalities' ),
			\__( 'Enables or disables one Functionalities module while preserving its settings.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'module', 'enabled' ),
				'properties' => array(
					'module'  => array(
						'type' => 'string',
					),
					'enabled' => array(
						'type' => 'boolean',
					),
				),
			),
			array( __CLASS__, 'ability_toggle_module' ),
			array( 'type' => 'object' ),
			false,
			true
		);

		self::register_ability(
			'explain-finding',
			\__( 'Explain a finding with AI', 'functionalities' ),
			\__( 'Uses the WordPress AI Client to explain one Assumption Detection or Content Integrity finding. This requires explicit opt-in and a configured provider.', 'functionalities' ),
			array(
				'type'       => 'object',
				'required'   => array( 'context', 'finding' ),
				'properties' => array(
					'context' => array(
						'type' => 'string',
						'enum' => array( 'assumption', 'content-integrity' ),
					),
					'finding' => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 12000,
					),
				),
			),
			array( __CLASS__, 'ability_explain_finding' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'explanation' => array( 'type' => 'string' ),
				),
			),
			false,
			false
		);
	}

	/**
	 * Register one normalized ability.
	 *
	 * @param string     $name        Ability name without namespace.
	 * @param string     $label       Human-readable label.
	 * @param string     $description Human-readable description.
	 * @param array|null $input       Input schema or null.
	 * @param callable   $callback    Execution callback.
	 * @param array      $output      Output schema.
	 * @param bool       $is_readonly Whether execution is read-only.
	 * @param bool       $idempotent  Whether repeated execution is idempotent.
	 * @return void
	 */
	private static function register_ability( string $name, string $label, string $description, ?array $input, callable $callback, array $output, bool $is_readonly, bool $idempotent = true ): void {
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'functionalities',
			'execute_callback'    => $callback,
			'permission_callback' => array( __CLASS__, 'ability_permission' ),
			'output_schema'       => $output,
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => $is_readonly,
					'destructive' => false,
					'idempotent'  => $idempotent,
				),
			),
		);
		if ( null !== $input ) {
			$args['input_schema'] = $input;
		}
		\wp_register_ability( 'functionalities/' . $name, $args );
	}

	/**
	 * Check ability permissions.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function ability_permission( $input = null ): bool {
		if ( is_array( $input ) && isset( $input['post_id'] ) ) {
			return \current_user_can( 'edit_post', (int) $input['post_id'] );
		}
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Return module status.
	 *
	 * @return array
	 */
	public static function ability_get_module_status(): array {
		$status = array();
		foreach ( Module_Registry::get_definitions() as $slug => $definition ) {
			$status[ $slug ] = array(
				'title'       => $definition['title'],
				'description' => $definition['description'],
				'enabled'     => Module_Registry::is_enabled( $slug ),
			);
		}
		return $status;
	}

	/**
	 * Return a privacy-safe diagnostic summary.
	 *
	 * @return array
	 */
	public static function ability_run_diagnostics(): array {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '';
		return array(
			'plugin_version'    => FUNCTIONALITIES_VERSION,
			'wordpress_version' => \get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'multisite'         => \is_multisite(),
			'modules'           => self::ability_get_module_status(),
			'storage'           => array(
				'content_directory_writable' => $content_dir ? \wp_is_writable( $content_dir ) : false,
				'data_directory_writable'    => $content_dir ? ( is_dir( $content_dir . '/functionalities' ) ? \wp_is_writable( $content_dir . '/functionalities' ) : \wp_is_writable( $content_dir ) ) : false,
			),
			'rewrite'           => array(
				'permalink_structure' => '' !== (string) \get_option( 'permalink_structure', '' ),
				'rules_available'     => is_array( \get_option( 'rewrite_rules', array() ) ),
			),
		);
	}

	/**
	 * Execute Assumption Detection.
	 *
	 * @return array|\WP_Error
	 */
	public static function ability_scan_assumptions() {
		if ( ! Module_Registry::is_enabled( 'assumption-detection' ) ) {
			return new \WP_Error( 'functionalities_module_disabled', \__( 'Assumption Detection is disabled.', 'functionalities' ) );
		}
		Module_Registry::boot_module( 'assumption-detection' );
		return \Functionalities\Features\Assumption_Detection::force_run_detection();
	}

	/**
	 * Check a post for regressions.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_check_content_integrity( array $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( ! $post_id || ! \get_post( $post_id ) ) {
			return new \WP_Error( 'functionalities_invalid_post', \__( 'The post could not be found.', 'functionalities' ) );
		}
		if ( ! Module_Registry::is_enabled( 'content-regression' ) ) {
			return new \WP_Error( 'functionalities_module_disabled', \__( 'Content Integrity is disabled.', 'functionalities' ) );
		}
		Module_Registry::boot_module( 'content-regression' );
		return \Functionalities\Features\Content_Regression::detect_regressions( $post_id );
	}

	/**
	 * Preview CSV redirect data.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_preview_redirect_import( array $input ) {
		if ( ! Module_Registry::is_enabled( 'redirect-manager' ) ) {
			return new \WP_Error( 'functionalities_module_disabled', \__( 'Redirect Manager is disabled.', 'functionalities' ) );
		}
		Module_Registry::boot_module( 'redirect-manager' );
		$parsed = \Functionalities\Features\Redirect_Manager::parse_csv( (string) $input['csv'] );
		if ( ! empty( $parsed['errors'] ) ) {
			return array(
				'success'  => false,
				'rows'     => array(),
				'errors'   => $parsed['errors'],
				'warnings' => array(),
				'count'    => 0,
			);
		}
		return \Functionalities\Features\Redirect_Manager::prepare_import( $parsed['rows'] );
	}

	/**
	 * Create a redirect.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_create_redirect( array $input ) {
		if ( ! Module_Registry::is_enabled( 'redirect-manager' ) ) {
			return new \WP_Error( 'functionalities_module_disabled', \__( 'Redirect Manager is disabled.', 'functionalities' ) );
		}
		Module_Registry::boot_module( 'redirect-manager' );
		$result = \Functionalities\Features\Redirect_Manager::add_redirect(
			(string) $input['from_url'],
			(string) $input['to_url'],
			isset( $input['type'] ) ? (int) $input['type'] : 301
		);
		return is_array( $result ) ? $result : new \WP_Error( 'functionalities_redirect_failed', \__( 'The redirect could not be created.', 'functionalities' ) );
	}

	/**
	 * Create a task.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_create_task( array $input ) {
		if ( ! Module_Registry::is_enabled( 'task-manager' ) ) {
			return new \WP_Error( 'functionalities_module_disabled', \__( 'Task Manager is disabled.', 'functionalities' ) );
		}
		Module_Registry::boot_module( 'task-manager' );
		$result = \Functionalities\Features\Task_Manager::add_task(
			\sanitize_key( (string) $input['project'] ),
			\sanitize_text_field( (string) $input['text'] ),
			isset( $input['notes'] ) ? \sanitize_textarea_field( (string) $input['notes'] ) : ''
		);
		return is_array( $result ) ? $result : new \WP_Error( 'functionalities_task_failed', \__( 'The task could not be created.', 'functionalities' ) );
	}

	/**
	 * Enable or disable a module.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_toggle_module( array $input ) {
		$slug        = \sanitize_key( (string) $input['module'] );
		$definitions = Module_Registry::get_definitions();
		if ( ! isset( $definitions[ $slug ] ) ) {
			return new \WP_Error( 'functionalities_unknown_module', \__( 'Unknown module.', 'functionalities' ) );
		}
		$options            = (array) \get_option( $definitions[ $slug ]['option'], array() );
		$options['enabled'] = ! empty( $input['enabled'] );
		\update_option( $definitions[ $slug ]['option'], $options );
		return array(
			'module'  => $slug,
			'enabled' => (bool) $options['enabled'],
		);
	}

	/**
	 * Explain one finding through the WordPress AI Client.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function ability_explain_finding( array $input ) {
		$options = self::get_options();
		if ( empty( $options['ai_explanations'] ) ) {
			return new \WP_Error( 'functionalities_ai_disabled', \__( 'AI explanations are disabled. Enable them in Functionalities settings first.', 'functionalities' ) );
		}
		if ( ! \function_exists( 'wp_ai_client_prompt' ) || ( \function_exists( 'wp_supports_ai' ) && ! \wp_supports_ai() ) ) {
			return new \WP_Error( 'functionalities_ai_unavailable', \__( 'The WordPress AI Client is not available in this environment.', 'functionalities' ) );
		}

		$context = 'assumption' === $input['context'] ? 'a site assumption warning' : 'a content integrity warning';
		$prompt  = sprintf(
			"Explain %s from a WordPress administration screen. Be concise and practical. Cover: what it means, likely causes, what to verify first, and a safe remediation. Do not invent site facts. Finding:\n\n%s",
			$context,
			(string) $input['finding']
		);
		$builder = \wp_ai_client_prompt( $prompt );
		if ( ! $builder->is_supported_for_text_generation() ) {
			return new \WP_Error( 'functionalities_ai_not_configured', \__( 'No configured AI provider supports text generation.', 'functionalities' ) );
		}
		$result = $builder->generate_text();
		if ( \is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'explanation' => (string) $result );
	}

	/**
	 * Register admin data and settings routes used by DataViews/DataForm.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		\register_rest_route(
			'functionalities/v1',
			'/wp7/admin-data',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
				'callback'            => array( __CLASS__, 'rest_admin_data' ),
			)
		);
		\register_rest_route(
			'functionalities/v1',
			'/wp7/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'callback'            => array( __CLASS__, 'rest_get_settings' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'callback'            => array( __CLASS__, 'rest_update_settings' ),
					'args'                => array(
						'ai_explanations' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * REST permission callback.
	 *
	 * @return bool
	 */
	public static function rest_permission(): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Return normalized redirect and task data for the modern admin views.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_admin_data(): \WP_REST_Response {
		$redirects       = array();
		$not_found       = array();
		$tasks           = array();
		$projects        = array();
		$project_options = array();

		if ( Module_Registry::is_enabled( 'redirect-manager' ) ) {
			Module_Registry::boot_module( 'redirect-manager' );
			$redirects = array_values( \Functionalities\Features\Redirect_Manager::get_redirects() );
			$not_found = array_values( \Functionalities\Features\Redirect_Manager::get_404_log() );
		}

		if ( Module_Registry::is_enabled( 'task-manager' ) ) {
			Module_Registry::boot_module( 'task-manager' );
			$projects = \Functionalities\Features\Task_Manager::get_projects();
			foreach ( $projects as $slug => $project ) {
				$project_options[] = array(
					'slug' => $slug,
					'name' => isset( $project['name'] ) ? \sanitize_text_field( (string) $project['name'] ) : $slug,
				);
				foreach ( (array) ( $project['tasks'] ?? array() ) as $task ) {
					$task['project']      = $slug;
					$task['project_name'] = isset( $project['name'] ) ? $project['name'] : $slug;
					$tasks[]              = $task;
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'redirects' => $redirects,
				'notFound'  => $not_found,
				'tasks'     => $tasks,
				'projects'  => $project_options,
			)
		);
	}

	/**
	 * Return WordPress 7 integration settings.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( self::get_options() );
	}

	/**
	 * Update WordPress 7 integration settings.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function rest_update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$options                    = self::get_options();
		$options['ai_explanations'] = (bool) $request->get_param( 'ai_explanations' );
		\update_option( self::OPTION_NAME, $options );
		return new \WP_REST_Response( $options );
	}

	/**
	 * Add custom content attributes to the block bindings UI.
	 *
	 * @param string[] $attributes Supported attributes.
	 * @return string[]
	 */
	public static function register_svg_binding_attributes( array $attributes ): array {
		return array_values( array_unique( array_merge( $attributes, array( 'iconSlug', 'coreIcon', 'label' ) ) ) );
	}

	/**
	 * Load WordPress 7 admin enhancements.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( \version_compare( \get_bloginfo( 'version' ), '7.0', '<' ) || ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_functionalities = false !== strpos( $hook_suffix, 'functionalities' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$module = isset( $_GET['module'] ) ? \sanitize_key( \wp_unslash( $_GET['module'] ) ) : '';
		$config = array(
			'isFunctionalities' => $is_functionalities,
			'page'              => $page,
			'module'            => $module,
			'adminDataPath'     => '/functionalities/v1/wp7/admin-data',
			'settingsPath'      => '/functionalities/v1/wp7/settings',
			'abilitiesPath'     => '/wp-abilities/v1/abilities/',
			'urls'              => array(
				'dashboard'   => \admin_url( 'admin.php?page=functionalities' ),
				'redirects'   => \admin_url( 'admin.php?page=functionalities&module=redirect-manager' ),
				'tasks'       => \admin_url( 'admin.php?page=functionalities-task-manager' ),
				'svgIcons'    => \admin_url( 'admin.php?page=functionalities-svg-icons' ),
				'assumptions' => \admin_url( 'admin.php?page=functionalities&module=assumption-detection' ),
			),
			'i18n'              => array(
				'modernTools' => \__( 'WordPress 7 workspace', 'functionalities' ),
				'loading'     => \__( 'Loading WordPress 7 workspace…', 'functionalities' ),
				'loadError'   => \__( 'The WordPress 7 workspace could not be loaded. The classic interface remains available below.', 'functionalities' ),
				'saved'       => \__( 'Saved.', 'functionalities' ),
			),
		);

		\wp_enqueue_script(
			'functionalities-wp7-commands',
			FUNCTIONALITIES_URL . 'assets/js/wp7-commands.js',
			array( 'wp-api-fetch', 'wp-commands', 'wp-data', 'wp-dom-ready', 'wp-i18n' ),
			FUNCTIONALITIES_VERSION,
			true
		);
		\wp_localize_script( 'functionalities-wp7-commands', 'functionalitiesWp7', $config );

		$workspace_modules = array( 'redirect-manager', 'task-manager', 'assumption-detection', 'content-regression' );
		if ( ! $is_functionalities || ( ! in_array( $module, $workspace_modules, true ) && 'functionalities-task-manager' !== $page ) ) {
			return;
		}

		$asset_file              = FUNCTIONALITIES_DIR . 'assets/js/wp7-admin.asset.php';
		$asset                   = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-api-fetch', 'wp-commands', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
				'version'      => FUNCTIONALITIES_VERSION,
			);
		$asset['dependencies'][] = 'functionalities-wp7-commands';
		$asset['dependencies']   = array_values( array_unique( $asset['dependencies'] ) );
		\wp_enqueue_script(
			'functionalities-wp7-admin',
			FUNCTIONALITIES_URL . 'assets/js/wp7-admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		\wp_enqueue_style(
			'functionalities-wp7-dataviews',
			FUNCTIONALITIES_URL . 'assets/js/wp7-dataviews.css',
			array( 'wp-components' ),
			$asset['version']
		);
		\wp_style_add_data( 'functionalities-wp7-dataviews', 'rtl', 'replace' );
		\wp_enqueue_style(
			'functionalities-wp7-admin',
			FUNCTIONALITIES_URL . 'assets/js/wp7-admin.css',
			array( 'functionalities-wp7-dataviews' ),
			$asset['version']
		);
	}

	/**
	 * Render the dashboard integration settings mount point and fallback.
	 *
	 * @return void
	 */
	public static function render_settings_panel(): void {
		$options = self::get_options();
		?>
		<section class="functionalities-tools functionalities-wp7-settings" data-functionalities-wp7-settings>
			<h2><?php \esc_html_e( 'WordPress 7 Integration', 'functionalities' ); ?></h2>
			<p><?php \esc_html_e( 'Abilities, command-palette actions, modern data workspaces, Core Icon interoperability, and optional AI explanations.', 'functionalities' ); ?></p>
			<label>
				<input type="checkbox" data-functionalities-ai-toggle <?php \checked( ! empty( $options['ai_explanations'] ) ); ?>>
				<?php \esc_html_e( 'Allow AI explanations for individual findings', 'functionalities' ); ?>
			</label>
			<p class="description"><?php \esc_html_e( 'Off by default. When enabled, only the finding you explicitly ask to explain is sent through the configured WordPress AI provider.', 'functionalities' ); ?></p>
			<p data-functionalities-wp7-status aria-live="polite"></p>
		</section>
		<?php
	}

	/**
	 * Return integration options with defaults.
	 *
	 * @return array
	 */
	private static function get_options(): array {
		return \wp_parse_args(
			(array) \get_option( self::OPTION_NAME, array() ),
			array(
				'ai_explanations' => false,
			)
		);
	}
}
