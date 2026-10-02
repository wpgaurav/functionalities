<?php
/**
 * Admin Redirect Manager UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Redirect_Manager_UI {

	/**
	 * Render Redirect Manager module page.
	 *
	 * @param array $module Module configuration.
	 * @return void
	 */
	public static function render_module_redirect_manager( array $module ): void {
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
		if ( isset( $_POST['functionalities_redirect_monitor_settings'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'functionalities_redirect_monitor_settings' ) ) {
			$rm_opts['monitor_404']            = ! empty( $_POST['monitor_404'] );
			$rm_opts['monitor_cap']            = max( 25, min( 2000, (int) ( $_POST['monitor_cap'] ?? 500 ) ) );
			$rm_opts['monitor_retention_days'] = max( 1, min( 365, (int) ( $_POST['monitor_retention_days'] ?? 30 ) ) );
			$rm_opts['monitor_exclusions']     = \sanitize_textarea_field( \wp_unslash( $_POST['monitor_exclusions'] ?? '' ) );
			\update_option( 'functionalities_redirect_manager', $rm_opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( '404 monitor settings saved.', 'functionalities' ) . '</p></div>';
		}
		$not_found = \Functionalities\Features\Redirect_Manager::get_404_log();
		?>
		<div class="wrap functionalities-module functionalities-redirect-manager">
			<?php Admin_UI::render_header( $module['title'], $module['description'], 'redirect-manager' ); ?>


			<form method="post" style="margin:15px 0;">
				<?php \wp_nonce_field( 'functionalities_redirect_manager_toggle' ); ?>
				<input type="hidden" name="functionalities_redirect_manager_toggle" value="1" />
				<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $rm_opts['enabled'] ) ); ?> onchange="this.form.submit()" />
					<strong><?php echo \esc_html__( 'Enable Redirect Manager', 'functionalities' ); ?></strong>
					<span style="color:#646970;font-size:13px;"><?php echo \esc_html__( 'Create and manage URL redirects', 'functionalities' ); ?></span>
				</label>
			</form>

			<?php if ( \Functionalities\Features\Redirect_Manager::get_storage_error() ) : ?>
				<div class="notice notice-error"><p>
				<?php
					echo \esc_html(
						sprintf(
							/* translators: %s: storage error code. */
							\__( 'Redirect storage error: %s. The existing file was not overwritten.', 'functionalities' ),
							\Functionalities\Features\Redirect_Manager::get_storage_error()
						)
					);
				?>
				</p></div>
			<?php endif; ?>

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
							<option value="308">308</option>
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
						<input type="text" id="redirect-search" aria-label="<?php \esc_attr_e( 'Search redirects', 'functionalities' ); ?>" placeholder="<?php \esc_attr_e( 'Search redirects...', 'functionalities' ); ?>" style="padding-left:35px;width:250px;">
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

			<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
				<button type="button" id="export-redirects-btn" class="button"><?php \esc_html_e( 'Export JSON', 'functionalities' ); ?></button>
				<button type="button" id="export-redirects-csv-btn" class="button"><?php \esc_html_e( 'Export CSV', 'functionalities' ); ?></button>
				<button type="button" id="import-redirects-btn" class="button"><?php \esc_html_e( 'Import JSON', 'functionalities' ); ?></button>
				<button type="button" id="import-redirects-csv-btn" class="button"><?php \esc_html_e( 'Import CSV', 'functionalities' ); ?></button>
			</div>

			<div style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin-top:20px;border-radius:4px;">
				<h2 style="margin-top:0;"><?php \esc_html_e( 'Bounded 404 Monitor', 'functionalities' ); ?></h2>
				<form method="post">
					<?php \wp_nonce_field( 'functionalities_redirect_monitor_settings' ); ?>
					<input type="hidden" name="functionalities_redirect_monitor_settings" value="1">
					<p><label><input type="checkbox" name="monitor_404" value="1" <?php checked( ! empty( $rm_opts['monitor_404'] ) ); ?>> <?php \esc_html_e( 'Log privacy-conscious 404 aggregates (opt-in)', 'functionalities' ); ?></label></p>
					<p><label><?php \esc_html_e( 'Maximum rows', 'functionalities' ); ?> <input type="number" name="monitor_cap" min="25" max="2000" value="<?php echo \esc_attr( $rm_opts['monitor_cap'] ?? 500 ); ?>"></label> &nbsp; <label><?php \esc_html_e( 'Retention days', 'functionalities' ); ?> <input type="number" name="monitor_retention_days" min="1" max="365" value="<?php echo \esc_attr( $rm_opts['monitor_retention_days'] ?? 30 ); ?>"></label></p>
					<p><label><?php \esc_html_e( 'Excluded path prefixes, one per line', 'functionalities' ); ?><br><textarea name="monitor_exclusions" rows="3" class="large-text"><?php echo \esc_textarea( $rm_opts['monitor_exclusions'] ?? '' ); ?></textarea></label></p>
					<?php \submit_button( \__( 'Save Monitor Settings', 'functionalities' ), 'secondary', 'submit', false ); ?>
					<button type="button" id="purge-404-btn" class="button"><?php \esc_html_e( 'Purge Log', 'functionalities' ); ?></button>
				</form>
				<table class="widefat striped" style="margin-top:15px;">
					<thead><tr><th><?php \esc_html_e( 'Path', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Count', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Last seen', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Referrer origin', 'functionalities' ); ?></th><th><?php \esc_html_e( 'Actions', 'functionalities' ); ?></th></tr></thead>
					<tbody id="redirect-404-list">
					<?php
					if ( empty( $not_found ) ) :
						?>
						<tr class="no-items"><td colspan="5"><?php \esc_html_e( 'No retained 404 entries.', 'functionalities' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $not_found as $item ) : ?>
						<tr data-path="<?php echo \esc_attr( $item['path'] ); ?>"><td><code><?php echo \esc_html( $item['path'] ); ?></code></td><td><?php echo (int) $item['count']; ?></td><td><?php echo \esc_html( \human_time_diff( (int) $item['last_seen'], time() ) ); ?> <?php \esc_html_e( 'ago', 'functionalities' ); ?></td><td><?php echo \esc_html( $item['referrer_origin'] ?? '' ); ?></td><td><button type="button" class="button button-small create-from-404"><?php \esc_html_e( 'Create redirect', 'functionalities' ); ?></button> <button type="button" class="button button-small ignore-404"><?php \esc_html_e( 'Ignore', 'functionalities' ); ?></button></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="modal-overlay" id="import-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center;">
				<div style="background:#fff;padding:20px;border-radius:4px;max-width:500px;width:90%;">
					<h3 style="margin-top:0;"><?php \esc_html_e( 'Import Redirects', 'functionalities' ); ?></h3>
					<input type="hidden" id="import-format" value="json">
					<textarea id="import-json" style="width:100%;height:200px;font-family:monospace;" placeholder='[{"from": "/old", "to": "/new", "type": 301}]'></textarea>
					<pre id="import-preview" style="max-height:180px;overflow:auto;" hidden></pre>
					<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
						<button type="button" class="button" id="cancel-import"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						<button type="button" class="button" id="preview-import"><?php \esc_html_e( 'Dry-run Preview', 'functionalities' ); ?></button>
						<button type="button" class="button button-primary" id="confirm-import" disabled><?php \esc_html_e( 'Import', 'functionalities' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<?php
	}
}
