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
			\add_settings_section( $option . '_section', '', '__return_false', $option );
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
		$url       = \sanitize_text_field( \wp_unslash( $_POST['url'] ?? '' ) );
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			\wp_die( \esc_html__( 'Enable Link Health before running an action.', 'functionalities' ), '', array( 'response' => 403 ) );
		}
		switch ( $operation ) {
			case 'start':
				$result = \Functionalities\Features\Link_Health::start_scan();
				break;
			case 'resume':
				$result = \Functionalities\Features\Link_Health::run_batch();
				break;
			case 'stop':
				$result = \Functionalities\Features\Link_Health::stop_scan();
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
		$page = 1;
		do {
			$report = \Functionalities\Features\Link_Health::report_page( $page );
			foreach ( $report['rows'] as $row ) {
				fputcsv( $stream, array( $row['post_id'], self::csv_safe( $row['title'] ), self::csv_safe( $row['url'] ), $row['status'], $row['code'], $row['checked'] ? gmdate( 'c', $row['checked'] ) : '', (int) $row['stale'], (int) $row['complete'], (int) $row['truncated'] ), ',', '"', '' );
			}
			++$page;
		} while ( $page <= $report['pages'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Complete the CSV response stream.
		fclose( $stream );
		exit;
	}

	/** Render a nonce-protected form for one Link Health operation. */
	private static function link_health_button( string $operation, string $label, int $post_id = 0, string $url = '' ): void {
		?>
		<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="functionalities-utility-action">
			<?php \wp_nonce_field( 'functionalities_link_health' ); ?>
			<input type="hidden" name="action" value="functionalities_link_health">
			<input type="hidden" name="operation" value="<?php echo \esc_attr( $operation ); ?>">
			<input type="hidden" name="post_id" value="<?php echo \esc_attr( $post_id ); ?>">
			<input type="hidden" name="url" value="<?php echo \esc_attr( $url ); ?>">
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
		<p><a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>"><?php \esc_html_e( 'Functionalities', 'functionalities' ); ?></a></p>
		<form action="options.php" method="post">
			<?php
			\settings_fields( 'functionalities_' . $key );
			\do_settings_sections( 'functionalities_' . $key );
			\submit_button();
			?>
		</form>
		<?php
	}

	/** Render scan status and results without issuing any URL requests. */
	public static function render_module_link_health( array $module ): void {
		self::utility_header( $module, 'link_health' );
		$enabled = \Functionalities\Core\Module_Registry::is_enabled( 'link-health' );
		?>
		<p><?php \esc_html_e( 'Checks links stored in public posts and pages. Password-protected content, dynamic blocks, shortcodes, and navigation are excluded. A maximum of 1,000 unique links is checked per post; truncated reports are marked. Scans never change your content.', 'functionalities' ); ?></p>
		<?php if ( ! $enabled ) : ?>
			<p><?php \esc_html_e( 'Enable Link Health to view reports and start a scan.', 'functionalities' ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<?php
		$state = \Functionalities\Features\Link_Health::state();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page   = max( 1, \absint( $_GET['report_page'] ?? 1 ) );
		$report = \Functionalities\Features\Link_Health::report_page( $page );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination within the source post.
		$link_page  = max( 1, \absint( $_GET['link_page'] ?? 1 ) );
		$link_pages = (int) ceil( count( $report['rows'] ) / 50 );
		?>
		<h2><?php \esc_html_e( 'Scan progress', 'functionalities' ); ?></h2>
		<p role="status"><?php echo \esc_html( sprintf( /* translators: 1: scan state, 2: completed post count, 3: processed link count. */ \__( '%1$s: %2$d posts completed, %3$d link checks processed.', 'functionalities' ), self::scan_state_label( $state['status'] ?? 'idle' ), $state['posts'] ?? 0, $state['urls'] ?? 0 ) ); ?></p>
		<?php if ( ! empty( $state['error'] ) ) : ?>
			<div class="notice notice-error"><p><?php \esc_html_e( 'The scan encountered an error. Check private storage and resume the scan.', 'functionalities' ); ?></p></div>
		<?php endif; ?>
		<div class="functionalities-utility-actions">
		<?php
		if ( 'running' === ( $state['status'] ?? '' ) ) {
			self::link_health_button( 'resume', \__( 'Resume one batch', 'functionalities' ) );
			self::link_health_button( 'stop', \__( 'Stop scan', 'functionalities' ) );
		} else {
			self::link_health_button( 'start', \__( 'Start new scan', 'functionalities' ) );
		}
		self::link_health_button( 'export', \__( 'Export CSV', 'functionalities' ) );
		?>
		</div>
		<p><?php \esc_html_e( 'Background batches run when WordPress cron is triggered. You can resume manually when cron is delayed. Authentication errors, rate limits, and timeouts are inconclusive.', 'functionalities' ); ?></p>
		<h2><?php \esc_html_e( 'Link results', 'functionalities' ); ?></h2>
		<div class="functionalities-utility-table"><table class="widefat striped">
		<thead><tr><th><?php \esc_html_e( 'Source', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Link', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Result', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Last checked (UTC)', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Actions', 'functionalities' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $report['rows'] ) :
			?>
			<tr><td colspan="5"><?php \esc_html_e( 'No link results on this page. Start a scan or check another results page.', 'functionalities' ); ?></td></tr><?php endif; ?>
		<?php foreach ( array_slice( $report['rows'], ( $link_page - 1 ) * 50, 50 ) as $row ) : ?>
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
			<td><?php echo \esc_html( self::link_status_label( $row['status'] ) ); ?> <?php echo \esc_html( $row['code'] ?: '' ); ?></td>
			<td><?php echo \esc_html( $row['checked'] ? gmdate( 'Y-m-d H:i:s', $row['checked'] ) : '—' ); ?></td>
			<td>
			<?php
			self::link_health_button( 'recheck', \__( 'Recheck', 'functionalities' ), $row['post_id'], $row['url'] );
			if ( 'ignored' !== $row['status'] ) {
				self::link_health_button( 'ignore', \__( 'Ignore', 'functionalities' ), $row['post_id'], $row['url'] ); }
			?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<p><?php \esc_html_e( 'Source posts', 'functionalities' ); ?></p>
		<?php self::utility_pagination( 'link-health', 'report_page', $page, $report['pages'] ); ?>
		<p><?php \esc_html_e( 'Links in this source post', 'functionalities' ); ?></p>
		<?php self::utility_pagination( 'link-health', 'link_page', $link_page, $link_pages ); ?>
		</div>
		<?php
	}

	private static function scan_state_label( string $state ): string {
		$labels = array(
			'idle'      => \__( 'Not started', 'functionalities' ),
			'running'   => \__( 'Running', 'functionalities' ),
			'completed' => \__( 'Completed', 'functionalities' ),
			'stopped'   => \__( 'Stopped', 'functionalities' ),
			'error'     => \__( 'Storage error', 'functionalities' ),
		);
		return $labels[ $state ] ?? $labels['idle'];
	}
	private static function link_status_label( string $status ): string {
		$labels = array(
			'ok'         => \__( 'OK', 'functionalities' ),
			'broken'     => \__( 'Broken', 'functionalities' ),
			'redirected' => \__( 'Redirected', 'functionalities' ),
			'unknown'    => \__( 'Inconclusive', 'functionalities' ),
			'unsafe'     => \__( 'Unsafe URL', 'functionalities' ),
			'ignored'    => \__( 'Ignored', 'functionalities' ),
		);
		return $labels[ $status ] ?? $labels['unknown'];
	}

	/** Search and filter a bounded history in memory, then paginate. */
	public static function render_module_site_activity( array $module ): void {
		self::utility_header( $module, 'site_activity' );
		?>
		<p><?php \esc_html_e( 'Tracks module settings, plugin/theme changes, and post/page status changes. Stores changed field names, never their values. Keeps up to 1,000 events for 30 days. Routine visitor requests are not logged.', 'functionalities' ); ?></p>
		<?php if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'site-activity' ) ) : ?>
			<p><?php \esc_html_e( 'Enable Site Activity to view and record events.', 'functionalities' ); ?></p></div>
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
		</form></div>
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
	private static function utility_pagination( string $module, string $key, int $page, int $pages ): void {
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
							'link_page' => 'report_page' === $key ? 1 : ( 'link_page' === $key ? $number : null ),
						)
					)
				);
				?>
				"><?php echo \esc_html( $label ); ?></a><?php endif; ?>
		<?php endforeach; ?></p>
		<?php
	}
}
