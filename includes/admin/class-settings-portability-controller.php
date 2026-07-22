<?php
/**
 * Settings portability and diagnostics controller.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export, preview, import, and diagnose plugin configuration.
 */
class Settings_Portability_Controller {

	const SCHEMA_VERSION = 1;
	const NONCE_ACTION   = 'functionalities_settings_portability';

	/**
	 * Register controller hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		\add_action( 'functionalities_admin_dashboard_tools', array( __CLASS__, 'render_panel' ) );
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		\add_action( 'wp_ajax_functionalities_settings_export', array( __CLASS__, 'ajax_export' ) );
		\add_action( 'wp_ajax_functionalities_settings_preview', array( __CLASS__, 'ajax_preview' ) );
		\add_action( 'wp_ajax_functionalities_settings_import', array( __CLASS__, 'ajax_import' ) );
		\add_action( 'wp_ajax_functionalities_diagnostics', array( __CLASS__, 'ajax_diagnostics' ) );
	}

	/**
	 * Load the dashboard tools script.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'functionalities' ) ) {
			return;
		}

		\wp_enqueue_script(
			'functionalities-admin-tools',
			FUNCTIONALITIES_URL . 'assets/js/admin-tools.js',
			array(),
			FUNCTIONALITIES_VERSION,
			true
		);
		\wp_localize_script(
			'functionalities-admin-tools',
			'functionalitiesTools',
			array(
				'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'nonce'   => \wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Render settings portability controls on the dashboard.
	 *
	 * @return void
	 */
	public static function render_panel(): void {
		$modules = \Functionalities\Core\Module_Registry::get_definitions();
		?>
		<section class="functionalities-tools" data-functionalities-tools>
			<h2><?php \esc_html_e( 'Settings Portability & Diagnostics', 'functionalities' ); ?></h2>
			<p><?php \esc_html_e( 'Move validated module settings between sites or download a privacy-conscious support report.', 'functionalities' ); ?></p>
			<details>
				<summary><?php \esc_html_e( 'Choose modules', 'functionalities' ); ?></summary>
				<div class="functionalities-tools__modules">
					<?php foreach ( $modules as $slug => $definition ) : ?>
						<label><input type="checkbox" data-tools-module value="<?php echo \esc_attr( $slug ); ?>" checked> <?php echo \esc_html( $definition['title'] ); ?></label>
					<?php endforeach; ?>
				</div>
			</details>
			<label><input type="checkbox" data-tools-include-code> <?php \esc_html_e( 'Include custom snippets and component code', 'functionalities' ); ?></label>
			<div class="functionalities-tools__actions">
				<button type="button" class="button" data-tools-export><?php \esc_html_e( 'Export Settings', 'functionalities' ); ?></button>
				<label class="button"><?php \esc_html_e( 'Choose Import File', 'functionalities' ); ?><input type="file" accept="application/json,.json" data-tools-file hidden></label>
				<button type="button" class="button" data-tools-preview disabled><?php \esc_html_e( 'Preview Import', 'functionalities' ); ?></button>
				<button type="button" class="button button-primary" data-tools-apply disabled><?php \esc_html_e( 'Apply Import', 'functionalities' ); ?></button>
				<button type="button" class="button" data-tools-diagnostics><?php \esc_html_e( 'Download Diagnostics', 'functionalities' ); ?></button>
			</div>
			<pre class="functionalities-tools__result" data-tools-result hidden></pre>
		</section>
		<?php
	}

	/**
	 * Build a versioned export document.
	 *
	 * @param array $selected     Selected module slugs.
	 * @param bool  $include_code Include custom code fields.
	 * @return array
	 */
	public static function build_export( array $selected, bool $include_code = false ): array {
		$definitions = \Functionalities\Core\Module_Registry::get_definitions();
		$settings    = array();
		$redacted    = array();

		foreach ( array_unique( $selected ) as $slug ) {
			if ( ! isset( $definitions[ $slug ] ) ) {
				continue;
			}

			$value = (array) \get_option( $definitions[ $slug ]['option'], array() );
			if ( ! $include_code ) {
				list( $value, $removed ) = self::redact_custom_code( $slug, $value );
				if ( $removed ) {
					$redacted[ $slug ] = $removed;
				}
			}
			$settings[ $slug ] = $value;
		}

		return array(
			'schema'        => self::SCHEMA_VERSION,
			'plugin'        => 'dynamic-functionalities',
			'pluginVersion' => FUNCTIONALITIES_VERSION,
			'exportedAt'    => gmdate( 'c' ),
			'settings'      => $settings,
			'redacted'      => $redacted,
		);
	}

	/**
	 * Validate and compare an import document without changing options.
	 *
	 * @param array $document Import document.
	 * @param bool  $include_code Permit custom code fields.
	 * @return array
	 */
	public static function preview_import( array $document, bool $include_code = false ): array {
		if ( ! isset( $document['schema'] ) ) {
			$document['schema'] = 1;
		}
		if ( self::SCHEMA_VERSION !== (int) $document['schema'] ) {
			return array(
				'success' => false,
				'error'   => 'unsupported_schema',
			);
		}
		if ( 'dynamic-functionalities' !== ( $document['plugin'] ?? '' ) || ! isset( $document['settings'] ) || ! is_array( $document['settings'] ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_document',
			);
		}

		$definitions = \Functionalities\Core\Module_Registry::get_definitions();
		$validated   = array();
		$changes     = array();
		$skipped     = array();

		foreach ( $document['settings'] as $slug => $incoming ) {
			if ( ! isset( $definitions[ $slug ] ) || ! is_array( $incoming ) ) {
				$skipped[ $slug ] = 'unknown_or_invalid_module';
				continue;
			}
			if ( ! $include_code ) {
				list( $incoming, $removed ) = self::redact_custom_code( $slug, $incoming );
				if ( $removed ) {
					$skipped[ $slug ] = $removed;
				}
			}

			$current            = (array) \get_option( $definitions[ $slug ]['option'], array() );
			$incoming           = self::validate_module( $slug, $incoming, $include_code );
			$incoming           = array_merge( $current, $incoming );
			$validated[ $slug ] = $incoming;
			$changed_fields     = array();
			foreach ( array_unique( array_merge( array_keys( $incoming ), array_keys( $current ) ) ) as $field ) {
				if ( ! array_key_exists( $field, $incoming ) || ! array_key_exists( $field, $current ) || $incoming[ $field ] !== $current[ $field ] ) {
					$changed_fields[] = $field;
				}
			}
			$changes[ $slug ] = array(
				'status'  => empty( $current ) ? 'add' : ( $current === $incoming ? 'unchanged' : 'change' ),
				'changed' => $changed_fields,
			);
		}

		return array(
			'success'   => true,
			'validated' => $validated,
			'changes'   => $changes,
			'skipped'   => $skipped,
		);
	}

	/**
	 * Handle settings export.
	 *
	 * @return void
	 */
	public static function ajax_export(): void {
		self::verify_request();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by verify_request().
		$modules = isset( $_POST['modules'] ) ? array_map( 'sanitize_key', (array) \wp_unslash( $_POST['modules'] ) ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by verify_request().
		$data = self::build_export( $modules, ! empty( $_POST['include_code'] ) );
		\wp_send_json_success(
			array(
				'filename' => 'functionalities-settings-' . gmdate( 'Y-m-d' ) . '.json',
				'content'  => \wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			)
		);
	}

	/**
	 * Handle import preview.
	 *
	 * @return void
	 */
	public static function ajax_preview(): void {
		self::verify_request();
		$document = self::decode_request_document();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by verify_request().
		$preview = self::preview_import( $document, ! empty( $_POST['include_code'] ) );
		if ( ! $preview['success'] ) {
			\wp_send_json_error( array( 'message' => $preview['error'] ) );
		}
		unset( $preview['validated'] );
		\wp_send_json_success( $preview );
	}

	/**
	 * Apply a fully validated import.
	 *
	 * @return void
	 */
	public static function ajax_import(): void {
		self::verify_request();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by verify_request().
		$preview = self::preview_import( self::decode_request_document(), ! empty( $_POST['include_code'] ) );
		if ( ! $preview['success'] ) {
			\wp_send_json_error( array( 'message' => $preview['error'] ) );
		}

		$definitions = \Functionalities\Core\Module_Registry::get_definitions();
		$updated     = array();
		$originals   = array();
		foreach ( $preview['validated'] as $slug => $value ) {
			$option = $definitions[ $slug ]['option'];
			$old    = (array) \get_option( $option, array() );
			if ( $old === $value ) {
				continue;
			}
			$originals[ $option ] = $old;
			if ( ! \update_option( $option, $value ) ) {
				foreach ( $originals as $rollback_option => $rollback_value ) {
					\update_option( $rollback_option, $rollback_value );
				}
				\wp_send_json_error( array( 'message' => \__( 'Import failed and previous settings were restored.', 'functionalities' ) ) );
			}
			$updated[] = $slug;
		}
		\wp_send_json_success(
			array(
				'updated' => $updated,
				'skipped' => $preview['skipped'],
			)
		);
	}

	/**
	 * Download privacy-conscious environment diagnostics.
	 *
	 * @return void
	 */
	public static function ajax_diagnostics(): void {
		self::verify_request();
		$enabled = array();
		foreach ( \Functionalities\Core\Module_Registry::get_definitions() as $slug => $definition ) {
			if ( \Functionalities\Core\Module_Registry::is_enabled( $slug ) ) {
				$enabled[] = $slug;
			}
		}
		$upload = \wp_upload_dir();
		$data   = array(
			'schema'           => 1,
			'generatedAt'      => gmdate( 'c' ),
			'pluginVersion'    => FUNCTIONALITIES_VERSION,
			'wordpressVersion' => isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '',
			'phpVersion'       => PHP_VERSION,
			'enabledModules'   => $enabled,
			'paths'            => array(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Diagnostics is a read-only native-path check.
				'uploadsWritable' => empty( $upload['error'] ) && is_writable( $upload['basedir'] ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Diagnostics is a read-only native-path check.
				'dataWritable'    => is_dir( WP_CONTENT_DIR . '/functionalities' ) ? is_writable( WP_CONTENT_DIR . '/functionalities' ) : is_writable( WP_CONTENT_DIR ),
			),
			'rewriteRules'     => is_array( \get_option( 'rewrite_rules', array() ) ) && ! empty( \get_option( 'rewrite_rules', array() ) ) ? 'present' : 'missing',
		);
		\wp_send_json_success(
			array(
				'filename' => 'functionalities-diagnostics-' . gmdate( 'Y-m-d' ) . '.json',
				'content'  => \wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			)
		);
	}

	/**
	 * Verify capability and nonce.
	 *
	 * @return void
	 */
	private static function verify_request(): void {
		\check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ), 403 );
		}
	}

	/**
	 * Decode the posted JSON document.
	 *
	 * @return array
	 */
	private static function decode_request_document(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by caller; JSON is validated after decoding.
		$json = isset( $_POST['document'] ) ? \wp_unslash( $_POST['document'] ) : '';
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid JSON document.', 'functionalities' ) ) );
		}
		return $data;
	}

	/**
	 * Remove custom code fields unless explicitly requested.
	 *
	 * @param string $slug  Module slug.
	 * @param array  $value Module settings.
	 * @return array
	 */
	private static function redact_custom_code( string $slug, array $value ): array {
		$fields  = array();
		$removed = array();
		if ( 'snippets' === $slug ) {
			$fields = array_diff( array_keys( $value ), array( 'enabled' ) );
		} elseif ( 'components' === $slug || 'svg-icons' === $slug ) {
			$fields = array( 'items' );
			if ( 'svg-icons' === $slug ) {
				$fields = array( 'icons' );
			}
		}
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $value ) ) {
				unset( $value[ $field ] );
				$removed[] = $field;
			}
		}
		return array( $value, $removed );
	}

	/**
	 * Validate settings through the owning module sanitizer when available.
	 *
	 * @param string $slug         Module slug.
	 * @param array  $value        Candidate settings.
	 * @param bool   $include_code Whether custom code was explicitly selected.
	 * @return array
	 */
	private static function validate_module( string $slug, array $value, bool $include_code ): array {
		$sanitizers = array(
			'link-management'      => 'sanitize_link_management',
			'block-cleanup'        => 'sanitize_block_cleanup',
			'editor-links'         => 'sanitize_editor_links',
			'snippets'             => 'sanitize_snippets',
			'schema'               => 'sanitize_schema',
			'components'           => 'sanitize_components',
			'misc'                 => 'sanitize_misc',
			'fonts'                => 'sanitize_fonts',
			'login-security'       => 'sanitize_login_security',
			'meta'                 => 'sanitize_meta',
			'content-regression'   => 'sanitize_content_regression',
			'assumption-detection' => 'sanitize_assumption_detection',
			'pwa'                  => 'sanitize_pwa',
		);
		$input_keys = array_keys( $value );
		$callback   = isset( $sanitizers[ $slug ] ) ? array( '\Functionalities\Admin\Module_Controller', $sanitizers[ $slug ] ) : null;
		if ( $callback && is_callable( $callback ) ) {
			$clean = (array) call_user_func( $callback, $value );
			return array_intersect_key( $clean, array_flip( $input_keys ) );
		}

		if ( 'redirect-manager' === $slug ) {
			$clean = array(
				'enabled'                => ! empty( $value['enabled'] ),
				'monitor_404'            => ! empty( $value['monitor_404'] ),
				'monitor_cap'            => max( 25, min( 2000, (int) ( $value['monitor_cap'] ?? 500 ) ) ),
				'monitor_retention_days' => max( 1, min( 365, (int) ( $value['monitor_retention_days'] ?? 30 ) ) ),
				'monitor_exclusions'     => \sanitize_textarea_field( $value['monitor_exclusions'] ?? '' ),
				'monitor_ignored_paths'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $value['monitor_ignored_paths'] ?? array() ) ) ) ),
			);
			return array_intersect_key( $clean, array_flip( $input_keys ) );
		}

		if ( 'svg-icons' === $slug ) {
			$clean = array( 'enabled' => ! empty( $value['enabled'] ) );
			if ( $include_code && isset( $value['icons'] ) && is_array( $value['icons'] ) ) {
				$clean['icons'] = array();
				foreach ( $value['icons'] as $icon_slug => $icon ) {
					$icon_slug = \sanitize_key( $icon_slug );
					if ( $icon_slug && is_array( $icon ) && isset( $icon['svg'] ) ) {
						$clean['icons'][ $icon_slug ] = array(
							'name' => \sanitize_text_field( $icon['name'] ?? $icon_slug ),
							'svg'  => \Functionalities\Features\SVG_Icons::sanitize_svg( (string) $icon['svg'] ),
						);
					}
				}
			}
			return array_intersect_key( $clean, array_flip( $input_keys ) );
		}

		if ( 'task-manager' === $slug ) {
			return array_intersect_key( array( 'enabled' => ! empty( $value['enabled'] ) ), array_flip( $input_keys ) );
		}

		return (array) self::sanitize_value( $value, $include_code );
	}

	/**
	 * Recursively normalize imported scalar values.
	 *
	 * @param mixed $value         Imported value.
	 * @param bool  $preserve_code Preserve explicitly opted-in code strings.
	 * @return mixed
	 */
	private static function sanitize_value( $value, bool $preserve_code = false ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ is_int( $key ) ? $key : \sanitize_key( $key ) ] = self::sanitize_value( $item, $preserve_code );
			}
			return $clean;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return $preserve_code ? (string) $value : \sanitize_textarea_field( (string) $value );
	}
}
