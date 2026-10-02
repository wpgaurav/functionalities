<?php
/**
 * Native settings and workspaces for utility modules.
 *
 * @package Functionalities\Admin
 */
namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep utility forms permission-checked and usable without JavaScript. */
trait Admin_Utilities_UI {
	/** Register module-specific settings and action routes. */
	public static function register_utility_settings(): void {
		foreach ( array( 'content_tools', 'link_health', 'site_activity' ) as $key ) {
			$option = 'functionalities_' . $key;
			\register_setting(
				$option,
				$option,
				array(
					'sanitize_callback' => array( __CLASS__, 'sanitize_' . $key ),
					'default'           => array( 'enabled' => false ),
				)
			);
			\add_settings_section(
				$option . '_section',
				'',
				static function () use ( $key ) {
					if ( 'content_tools' === $key ) {
						Admin_UI::render_module_docs( Module_Docs::get( 'content-tools' ) );
					}
				},
				$option
			);
			\add_settings_field(
				'enabled',
				\__( 'Enable module', 'functionalities' ),
				static function () use ( $option ) {
					$value = (array) \get_option( $option, array() );
					echo '<label><input type="checkbox" name="' . \esc_attr( $option ) . '[enabled]" value="1" ' . \checked( ! empty( $value['enabled'] ), true, false ) . '> ' . \esc_html__( 'Enable this module', 'functionalities' ) . '</label>';
				},
				$option,
				$option . '_section'
			);
		}
		\add_settings_field(
			'weekly_scan',
			\__( 'Weekly scan', 'functionalities' ),
			static function () {
				$value = self::get_link_health_options();
				echo '<label><input type="checkbox" name="functionalities_link_health[weekly_scan]" value="1" ' . \checked( $value['weekly_scan'], true, false ) . '> ' . \esc_html__( 'Scan weekly in small background batches', 'functionalities' ) . '</label>';
			},
			'functionalities_link_health',
			'functionalities_link_health_section'
		);
		\add_settings_field(
			'coverage',
			\__( 'Supported content', 'functionalities' ),
			static function () {
				echo '<p>' . \esc_html__( 'Duplicate posts or pages into a new draft from the content list. Blocks, taxonomies, featured image, and page template are copied. Comments, revisions, and builder metadata are excluded unless explicitly supported by an integration.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_tools',
			'functionalities_content_tools_section'
		);
	}

	/** Register action handling before the module's enabled state is consulted. */
	public static function init_utility_actions(): void {
		Link_Health_Controller::init();
		\add_action( 'admin_post_functionalities_link_health', array( __CLASS__, 'handle_link_health_action' ) );
		\add_action( 'admin_post_functionalities_activity_clear', array( __CLASS__, 'handle_activity_clear' ) );
	}

	/** All state-changing actions require administrator access and a nonce. */
	private static function utility_authorize(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'Administrator access is required.', 'functionalities' ), '', array( 'response' => 403 ) );
		}
	}

	/** Return to the module after a successful form action. */
	private static function utility_redirect( string $module ): void {
		\wp_safe_redirect( \admin_url( 'admin.php?page=functionalities&module=' . $module ) );
		exit;
	}

	public static function handle_link_health_action(): void {
		self::utility_authorize();
		\check_admin_referer( 'functionalities_link_health' );
		$operation = \sanitize_key( \wp_unslash( $_POST['operation'] ?? '' ) );
		$post_id   = \absint( $_POST['post_id'] ?? 0 );
		$url       = Link_Health_Controller::request_url();
		$run       = isset( $_POST['run'] ) && is_string( $_POST['run'] ) ? \sanitize_text_field( \wp_unslash( $_POST['run'] ) ) : '';
		if ( in_array( $operation, array( 'resume', 'stop' ), true ) && '' === $run ) {
			\wp_die( \esc_html__( 'Refresh the scan status before continuing.', 'functionalities' ), '', array( 'response' => 400 ) );
		}
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			\wp_die( \esc_html__( 'Enable Link Health before running an action.', 'functionalities' ), '', array( 'response' => 403 ) );
		}
		switch ( $operation ) {
			case 'start':
				$result = \Functionalities\Features\Link_Health::start_scan();
				break;
			case 'resume':
				$result = 'stopped' === ( \Functionalities\Features\Link_Health::state()['status'] ?? '' ) ? \Functionalities\Features\Link_Health::resume_scan( $run ) : \Functionalities\Features\Link_Health::run_batch( $run );
				break;
			case 'stop':
				$result = \Functionalities\Features\Link_Health::stop_scan( $run );
				break;
			case 'ignore':
				$result = \Functionalities\Features\Link_Health::set_ignored( $post_id, $url, true );
				break;
			case 'recheck':
				$result = \Functionalities\Features\Link_Health::recheck( $post_id, $url );
				break;
			case 'export':
				self::export_link_health();
				return;
			default:
				$result = new \WP_Error( 'invalid_action', \__( 'Unknown action.', 'functionalities' ) );
		}
		if ( \is_wp_error( $result ) ) {
			\wp_die(
				\esc_html( $result->get_error_message() ),
				'',
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}
		self::utility_redirect( 'link-health' );
	}

	public static function handle_activity_clear(): void {
		self::utility_authorize();
		\check_admin_referer( 'functionalities_activity_clear' );
		if ( empty( $_POST['confirm_clear'] ) || ! \Functionalities\Features\Site_Activity::clear() ) {
			\wp_die( \esc_html__( 'The activity log could not be cleared. Check private storage.', 'functionalities' ) );
		}
		self::utility_redirect( 'site-activity' );
	}

	/** Escape spreadsheet formulas in all textual CSV columns. */
	public static function csv_safe( string $value ): string {
		return preg_match( '/^[\s]*[=+@\-]/u', $value ) ? "'" . $value : $value;
	}

	/** Stream a complete paginated report rather than loading it into memory. */
	private static function export_link_health(): void {
		\nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="functionalities-link-health.csv"' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream a generated CSV download.
		$stream = fopen( 'php://output', 'w' );
		fputcsv( $stream, array( 'Post ID', 'Title', 'URL', 'Status', 'HTTP', 'Checked UTC', 'Stale', 'Complete', 'Truncated' ), ',', '"', '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller already verifies the administrator and export nonce.
		$filters = \Functionalities\Features\Link_Health_Report::filters( \wp_unslash( $_POST ) );
		foreach ( \Functionalities\Features\Link_Health_Report::export_rows( $filters ) as $row ) {
			fputcsv( $stream, array( $row['post_id'], self::csv_safe( $row['title'] ), self::csv_safe( $row['url'] ), $row['status'], $row['code'], $row['checked'] ? gmdate( 'c', $row['checked'] ) : '', (int) $row['stale'], (int) $row['complete'], (int) $row['truncated'] ), ',', '"', '' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Complete the CSV response stream.
		fclose( $stream );
		exit;
	}

	/** Render a nonce-protected form for one Link Health operation. */
	private static function link_health_button( string $operation, string $label, int $post_id = 0, string $url = '' ): void {
		?>
		<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="functionalities-utility-action" data-link-action="<?php echo \esc_attr( $operation ); ?>">
			<?php \wp_nonce_field( 'functionalities_link_health' ); ?>
			<input type="hidden" name="action" value="functionalities_link_health">
			<input type="hidden" name="operation" value="<?php echo \esc_attr( $operation ); ?>">
			<input type="hidden" name="post_id" value="<?php echo \esc_attr( $post_id ); ?>">
			<input type="hidden" name="url" value="<?php echo \esc_attr( $url ); ?>">
			<?php
			if ( in_array( $operation, array( 'resume', 'stop' ), true ) ) {
				echo '<input type="hidden" name="run" value="' . \esc_attr( \Functionalities\Features\Link_Health::state()['run'] ?? '' ) . '">';
			}
			if ( 'export' === $operation ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selected report filters.
				foreach ( \Functionalities\Features\Link_Health_Report::filters( \wp_unslash( $_GET ) ) as $key => $value ) {
					echo '<input type="hidden" name="' . \esc_attr( $key ) . '" value="' . \esc_attr( $value ) . '">';
				}
			}
			?>
			<button class="button" type="submit"><?php echo \esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/** Render shared native settings, with no nested forms. */
	private static function utility_header( array $module, string $key ): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'Administrator access is required.', 'functionalities' ) );
		}
		?>
		<div class="wrap functionalities-module functionalities-utilities">
		<?php Admin_UI::render_header( $module['title'], $module['description'], str_replace( '_', '-', $key ) ); ?>
		<?php
	}

	/** Render settings inside the shared grid without nesting action forms. */
	private static function utility_settings( string $key ): void {
		?>
		<form action="options.php" method="post">
			<?php
			\settings_fields( 'functionalities_' . $key );
			\do_settings_sections( 'functionalities_' . $key );
			\submit_button();
			?>
		</form>
		<?php
	}

	/** Keep the header outside the settings/report grid. */
	private static function utility_workspace( array $module, string $key, callable $render ): void {
		self::utility_header( $module, $key );
		Admin_UI::render_settings_layout(
			static function () use ( $key, $render ) {
				self::utility_settings( $key );
				Admin_UI::render_module_docs( Module_Docs::get( str_replace( '_', '-', $key ) ) );
				$render();
			}
		);
		echo '</div>';
	}

	/** Render scan status and results without issuing any URL requests. */
	public static function render_module_link_health( array $module ): void {
		self::utility_header( $module, 'link_health' );
		Admin_UI::render_settings_layout(
			static function () {
				self::utility_settings( 'link_health' );
				Admin_UI::render_module_docs( Module_Docs::get( 'link-health' ) );
				self::render_link_health_workspace();
			}
		);
		if ( \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only results pagination.
			$page = max( 1, \absint( $_GET['report_page'] ?? 1 ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
			$filters = \Functionalities\Features\Link_Health_Report::filters( \wp_unslash( $_GET ) );
			self::render_link_health_filters( $filters );
			echo '<div data-link-edit-notice class="notice notice-success inline" role="status" hidden></div>';
			echo '<div data-link-report class="functionalities-link-report">';
			self::render_link_health_report( $page, $filters );
			echo '</div>';
			self::render_link_health_editor();
		}
		echo '</div>';
	}

	/** Render report controls and status alongside their own settings. */
	private static function render_link_health_workspace(): void {
		$enabled = \Functionalities\Core\Module_Registry::is_enabled( 'link-health' );
		?>
		<p><?php \esc_html_e( 'Checks links stored in public posts and pages. Password-protected content, dynamic blocks, shortcodes, and navigation are excluded. A maximum of 1,000 unique links is checked per post; truncated reports are marked. Scans never change your content.', 'functionalities' ); ?></p>
		<?php if ( ! $enabled ) : ?>
			<p><?php \esc_html_e( 'Enable Link Health to view reports and start a scan.', 'functionalities' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<?php
		$progress = Link_Health_Controller::progress();
		?>
		<section class="functionalities-link-progress" data-link-health data-progress="<?php echo \esc_attr( \wp_json_encode( $progress ) ); ?>" aria-label="<?php echo \esc_attr__( 'Scan progress', 'functionalities' ); ?>">
		<h2><?php \esc_html_e( 'Scan progress', 'functionalities' ); ?></h2>
		<p class="functionalities-link-state"><span class="spinner" data-link-spinner aria-hidden="true"></span><strong data-link-label role="status" aria-live="polite"><?php echo \esc_html( $progress['label'] ); ?></strong></p>
		<progress data-link-meter max="100" value="<?php echo \esc_attr( $progress['percent'] ); ?>" aria-label="<?php echo \esc_attr__( 'Posts checked', 'functionalities' ); ?>"></progress>
		<p data-link-summary><?php echo \esc_html( $progress['summary'] ); ?></p>
		<p data-link-error role="alert" hidden></p>
		<div class="functionalities-utility-actions">
		<?php
		foreach ( array(
			'start'  => \__( 'Start new scan', 'functionalities' ),
			'resume' => \__( 'Resume scan', 'functionalities' ),
			'stop'   => \__( 'Stop scan', 'functionalities' ),
		) as $operation => $label ) {
			$active = in_array( $progress['status'], array( 'running', 'stopping' ), true );
			$show   = 'start' === $operation ? ! $active && 'stopped' !== $progress['status'] : ( 'stop' === $operation ? $active : 'stopped' === $progress['status'] || 'running' === $progress['status'] );
			echo '<span data-link-control="' . \esc_attr( $operation ) . '"' . ( $show ? '' : ' hidden' ) . '>';
			self::link_health_button( $operation, $label );
			echo '</span>';
		}
		self::link_health_button( 'export', \__( 'Export CSV', 'functionalities' ) );
		?>
		</div>
		<p class="description"><?php \esc_html_e( 'Keep this page open for continuous checking and live results. Scans also continue on your site’s background schedule when you leave.', 'functionalities' ); ?></p>
		<noscript><p><?php \esc_html_e( 'Enable JavaScript for live progress, or use Resume scan to process one batch at a time.', 'functionalities' ); ?></p></noscript>
		</section>
		<?php
	}

	/** Render one global page of 50 link results, independently of the scan controls. */
	public static function render_link_health_report( int $page = 1, array $filters = array() ): void {
		$filters = \Functionalities\Features\Link_Health_Report::filters( $filters );
		$report  = \Functionalities\Features\Link_Health::report_page( $page, $filters );
		$page    = $report['page'];
		?>
		<h2><?php \esc_html_e( 'Link results', 'functionalities' ); ?></h2>
		<p data-link-page="<?php echo \esc_attr( $page ); ?>"><?php echo \esc_html( sprintf( /* translators: 1: first visible result, 2: last visible result, 3: total results. */ \__( '%1$d–%2$d of %3$d results · 50 per page', 'functionalities' ), $report['total'] ? ( $page - 1 ) * 50 + 1 : 0, min( $page * 50, $report['total'] ), $report['total'] ) ); ?></p>
		<div class="functionalities-utility-table"><table class="widefat striped">
		<thead><tr><th><?php \esc_html_e( 'Source', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Link', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Result', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Last checked (UTC)', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Actions', 'functionalities' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $report['rows'] ) :
			?>
			<tr><td colspan="5"><?php echo '' !== implode( '', $filters ) ? \esc_html__( 'No links match these filters.', 'functionalities' ) : \esc_html__( 'No link results on this page. Start a scan or check another results page.', 'functionalities' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $report['rows'] as $row ) : ?>
		<tr>
			<td><a href="<?php echo \esc_url( \get_edit_post_link( $row['post_id'] ) ); ?>"><?php echo \esc_html( $row['title'] ); ?></a>
			<?php
			if ( $row['stale'] ) :
				?>
				<p><?php \esc_html_e( 'Content changed. Run a new scan.', 'functionalities' ); ?></p><?php endif; ?>
			<?php
			if ( ! $row['complete'] ) :
				?>
				<p><?php \esc_html_e( 'Partial scan', 'functionalities' ); ?></p><?php endif; ?>
			<?php
			if ( $row['truncated'] ) :
				?>
				<p><?php \esc_html_e( 'First 1,000 unique links only', 'functionalities' ); ?></p><?php endif; ?></td>
			<td class="functionalities-utility-url"><?php echo \esc_html( $row['url'] ); ?>
			<?php
			if ( $row['chain'] ) :
				?>
				<p><?php echo \esc_html( implode( ' → ', $row['chain'] ) ); ?></p><?php endif; ?></td>
			<td><?php echo \esc_html( self::link_status_label( \Functionalities\Features\Link_Health_Report::status( $row ) ) ); ?> <?php echo \esc_html( $row['code'] ?: '' ); ?></td>
			<td><?php echo \esc_html( $row['checked'] ? gmdate( 'Y-m-d H:i:s', $row['checked'] ) : '—' ); ?></td>
			<td>
			<?php
			self::link_health_button( 'recheck', \__( 'Recheck', 'functionalities' ), $row['post_id'], $row['url'] );
			if ( 'ignored' !== $row['status'] ) {
				self::link_health_button( 'ignore', \__( 'Ignore', 'functionalities' ), $row['post_id'], $row['url'] ); }
			?>
			<?php if ( \current_user_can( 'edit_post', $row['post_id'] ) ) : ?>
				<button type="button" class="button" data-link-edit="replace" data-post-id="<?php echo \esc_attr( $row['post_id'] ); ?>" data-url="<?php echo \esc_attr( $row['url'] ); ?>" data-source="<?php echo \esc_attr( $row['title'] ); ?>"><?php \esc_html_e( 'Replace URL', 'functionalities' ); ?></button>
				<button type="button" class="button" data-link-edit="unlink" data-post-id="<?php echo \esc_attr( $row['post_id'] ); ?>" data-url="<?php echo \esc_attr( $row['url'] ); ?>" data-source="<?php echo \esc_attr( $row['title'] ); ?>"><?php \esc_html_e( 'Unlink', 'functionalities' ); ?></button>
			<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
		self::utility_pagination(
			'link-health',
			'report_page',
			$page,
			$report['pages'],
			\add_query_arg(
				array_filter(
					$filters,
					static function ( $value ) {
						return '' !== $value;
					}
				),
				\admin_url( 'admin.php' )
			)
		);
		?>
		<?php
	}

	private static function link_status_label( string $status ): string {
		$labels = array(
			'ok'         => \__( 'OK', 'functionalities' ),
			'broken'     => \__( 'Broken', 'functionalities' ),
			'redirected' => \__( 'Redirected', 'functionalities' ),
			'unknown'    => \__( 'Inconclusive', 'functionalities' ),
			'unsafe'     => \__( 'Unsafe URL', 'functionalities' ),
			'ignored'    => \__( 'Ignored', 'functionalities' ),
			'unchecked'  => \__( 'Not checked', 'functionalities' ),
		);
		return $labels[ $status ] ?? $labels['unknown'];
	}

	/** Keep filter inputs outside the live table so refreshes cannot reset a draft selection. */
	private static function render_link_health_filters( array $filters ): void {
		?>
		<form method="get" action="<?php echo \esc_url( \admin_url( 'admin.php' ) ); ?>" class="functionalities-link-filters" data-link-filters data-filters="<?php echo \esc_attr( \wp_json_encode( $filters ) ); ?>">
			<input type="hidden" name="page" value="functionalities"><input type="hidden" name="module" value="link-health"><input type="hidden" name="report_page" value="1">
			<label><?php \esc_html_e( 'Result', 'functionalities' ); ?><select name="link_status"><option value=""><?php \esc_html_e( 'All results', 'functionalities' ); ?></option>
			<?php foreach ( array( 'broken', 'redirected', 'unknown', 'unsafe', 'ignored', 'unchecked', 'ok' ) as $status ) : ?>
				<option value="<?php echo \esc_attr( $status ); ?>" <?php \selected( $filters['link_status'], $status ); ?>><?php echo \esc_html( self::link_status_label( $status ) ); ?></option>
			<?php endforeach; ?></select></label>
			<label><?php \esc_html_e( 'Source type', 'functionalities' ); ?><select name="source_type"><option value=""><?php \esc_html_e( 'Posts and pages', 'functionalities' ); ?></option><option value="post" <?php \selected( $filters['source_type'], 'post' ); ?>><?php \esc_html_e( 'Posts', 'functionalities' ); ?></option><option value="page" <?php \selected( $filters['source_type'], 'page' ); ?>><?php \esc_html_e( 'Pages', 'functionalities' ); ?></option></select></label>
			<label class="functionalities-link-search"><?php \esc_html_e( 'URL or source title', 'functionalities' ); ?><input type="search" name="link_search" maxlength="200" value="<?php echo \esc_attr( $filters['link_search'] ); ?>"></label>
			<button class="button" type="submit"><?php \esc_html_e( 'Filter results', 'functionalities' ); ?></button>
			<a class="button" href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities&module=link-health' ) ); ?>"><?php \esc_html_e( 'Reset', 'functionalities' ); ?></a>
		</form>
		<?php
	}

	private static function render_link_health_editor(): void {
		?>
		<dialog class="functionalities-link-editor" data-link-editor aria-labelledby="functionalities-link-editor-title">
			<h2 id="functionalities-link-editor-title"><?php \esc_html_e( 'Edit source link', 'functionalities' ); ?></h2>
			<p><strong data-edit-source></strong></p><p class="description"><?php \esc_html_e( 'This action updates matching links in this source post only. Text and media are preserved.', 'functionalities' ); ?></p>
			<p><code data-edit-url></code></p>
			<form data-link-edit-form>
				<label><?php \esc_html_e( 'Action', 'functionalities' ); ?><select data-edit-mode><option value="replace"><?php \esc_html_e( 'Replace URL', 'functionalities' ); ?></option><option value="unlink"><?php \esc_html_e( 'Unlink', 'functionalities' ); ?></option></select></label>
				<label data-edit-destination><?php \esc_html_e( 'New URL', 'functionalities' ); ?><input type="url" data-edit-new-url maxlength="2048" placeholder="https://example.com/new-page/"></label>
				<p data-edit-feedback role="status"></p><p data-edit-preview hidden></p>
				<div class="functionalities-utility-actions"><button type="submit" class="button" data-edit-review><?php \esc_html_e( 'Preview change', 'functionalities' ); ?></button><button type="button" class="button button-primary" data-edit-apply disabled><?php \esc_html_e( 'Apply change', 'functionalities' ); ?></button><button type="button" class="button" data-edit-cancel><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button></div>
			</form>
		</dialog>
		<?php
	}

	/** Search and filter a bounded history in memory, then paginate. */
	public static function render_module_site_activity( array $module ): void {
		self::utility_workspace( $module, 'site_activity', array( __CLASS__, 'render_site_activity_workspace' ) );
	}

	/** Render report controls and status alongside their own settings. */
	private static function render_site_activity_workspace(): void {
		?>
		<p><?php \esc_html_e( 'Tracks module settings, plugin/theme changes, and post/page status changes. Stores changed field names, never their values. Keeps up to 1,000 events for 30 days. Routine visitor requests are not logged.', 'functionalities' ); ?></p>
		<?php if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'site-activity' ) ) : ?>
			<p><?php \esc_html_e( 'Enable Site Activity to view and record events.', 'functionalities' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only report filters.
		$search = \sanitize_text_field( \wp_unslash( $_GET['activity_search'] ?? '' ) );
		$event  = \sanitize_key( \wp_unslash( $_GET['activity_event'] ?? '' ) );
		$actor  = isset( $_GET['activity_actor'] ) && '' !== $_GET['activity_actor'] ? \absint( $_GET['activity_actor'] ) : null;
		$page   = max( 1, \absint( $_GET['activity_page'] ?? 1 ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$entries = \Functionalities\Features\Site_Activity::entries();
		$events  = array_unique( array_column( $entries, 'event' ) );
		$entries = array_filter(
			array_reverse( $entries ),
			static function ( $row ) use ( $search, $event, $actor ) {
				return ( '' === $event || $row['event'] === $event ) && ( null === $actor || (int) $row['actor'] === $actor ) && ( '' === $search || false !== stripos(
					$row['target'] . ' ' . implode(
						' ',
						array_map(
							static function ( $value ) {
								return is_array( $value ) ? implode( ' ', $value ) : $value;
							},
							$row['details']
						)
					),
					$search
				) );
			}
		);
		$pages   = (int) ceil( count( $entries ) / 50 );
		?>
		<?php
		if ( \Functionalities\Features\Site_Activity::storage_error() ) :
			?>
			<div class="notice notice-error"><p><?php \esc_html_e( 'The activity log could not be read. Check private storage.', 'functionalities' ); ?></p></div><?php endif; ?>
		<form method="get" action="<?php echo \esc_url( \admin_url( 'admin.php' ) ); ?>" class="functionalities-utility-actions">
			<input type="hidden" name="page" value="functionalities"><input type="hidden" name="module" value="site-activity">
			<label><span><?php \esc_html_e( 'Search target or fields', 'functionalities' ); ?></span> <input type="search" name="activity_search" value="<?php echo \esc_attr( $search ); ?>"></label>
			<label><span><?php \esc_html_e( 'Event', 'functionalities' ); ?></span> <select name="activity_event" aria-label="<?php \esc_attr_e( 'Event', 'functionalities' ); ?>"><option value=""><?php \esc_html_e( 'All events', 'functionalities' ); ?></option>
			<?php
			foreach ( $events as $type ) :
				?>
				<option value="<?php echo \esc_attr( $type ); ?>" <?php \selected( $event, $type ); ?>><?php echo \esc_html( self::activity_event_label( $type ) ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php \esc_html_e( 'Actor ID', 'functionalities' ); ?></span> <input type="number" min="0" name="activity_actor" value="<?php echo \esc_attr( null === $actor ? '' : $actor ); ?>"></label>
			<button class="button" type="submit"><?php \esc_html_e( 'Filter', 'functionalities' ); ?></button>
		</form>
		<div class="functionalities-utility-table"><table class="widefat striped"><thead><tr><th><?php \esc_html_e( 'Time (UTC)', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Actor', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Event', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Target', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Details', 'functionalities' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $entries ) :
			?>
			<tr><td colspan="5"><?php \esc_html_e( 'No activity matches these filters.', 'functionalities' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( array_slice( $entries, ( $page - 1 ) * 50, 50 ) as $row ) :
			$user = $row['actor'] ? \get_userdata( $row['actor'] ) : false;
			?>
		<tr><td><?php echo \esc_html( gmdate( 'Y-m-d H:i:s', $row['time'] ) ); ?></td><td><?php echo \esc_html( $user ? $user->display_name . ' (#' . $row['actor'] . ')' : \__( 'System or anonymized user', 'functionalities' ) ); ?></td><td><?php echo \esc_html( self::activity_event_label( $row['event'] ) ); ?></td><td><?php echo \esc_html( $row['target'] ); ?></td><td><?php echo \esc_html( isset( $row['details']['fields'] ) ? implode( ', ', $row['details']['fields'] ) : implode( ' → ', $row['details'] ) ); ?></td></tr>
		<?php endforeach; ?></tbody></table></div>
		<?php self::utility_pagination( 'site-activity', 'activity_page', $page, $pages ); ?>
		<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
			<?php \wp_nonce_field( 'functionalities_activity_clear' ); ?>
			<input type="hidden" name="action" value="functionalities_activity_clear">
			<p><label><input type="checkbox" name="confirm_clear" value="1" required> <?php \esc_html_e( 'Delete the stored activity history', 'functionalities' ); ?></label></p>
			<button class="button" type="submit"><?php \esc_html_e( 'Clear activity log', 'functionalities' ); ?></button>
		</form>
		<?php
	}

	private static function activity_event_label( string $event ): string {
		$labels = array(
			'settings_changed'   => \__( 'Module settings changed', 'functionalities' ),
			'plugin_activated'   => \__( 'Plugin activated', 'functionalities' ),
			'plugin_deactivated' => \__( 'Plugin deactivated', 'functionalities' ),
			'theme_switched'     => \__( 'Theme switched', 'functionalities' ),
			'plugin_updated'     => \__( 'Plugin updated', 'functionalities' ),
			'theme_updated'      => \__( 'Theme updated', 'functionalities' ),
			'status_changed'     => \__( 'Publishing status changed', 'functionalities' ),
		);
		return $labels[ $event ] ?? $event;
	}

	/** Preserve report filters when moving between pages. */
	private static function utility_pagination( string $module, string $key, int $page, int $pages, string $base = '' ): void {
		?>
		<p><?php echo \esc_html( sprintf( /* translators: 1: current page number, 2: total pages */ \__( 'Page %1$d of %2$d', 'functionalities' ), $page, max( 1, $pages ) ) ); ?>
		<?php
		foreach ( array(
			$page - 1 => \__( 'Previous', 'functionalities' ),
			$page + 1 => \__( 'Next', 'functionalities' ),
		) as $number => $label ) :
			?>
			<?php
			if ( $number >= 1 && $number <= $pages ) :
				?>
				<a class="button" href="
				<?php
				echo \esc_url(
					\add_query_arg(
						array(
							'page'      => 'functionalities',
							'module'    => $module,
							$key        => $number,
							'link_page' => null,
						),
						'' !== $base ? $base : false
					)
				);
				?>
				"><?php echo \esc_html( $label ); ?></a><?php endif; ?>
		<?php endforeach; ?></p>
		<?php
	}
}
