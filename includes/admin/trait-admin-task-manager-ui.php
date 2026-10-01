<?php
/**
 * Admin Task Manager UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Task_Manager_UI {

	/**
	 * Render Task Manager module page.
	 *
	 * @param array $module Module configuration.
	 * @return void
	 */
	public static function render_module_task_manager( array $module ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
		$current_project = isset( $_GET['project'] ) ? \sanitize_key( $_GET['project'] ) : '';
		$projects        = \Functionalities\Features\Task_Manager::get_projects();
		$project_data    = null;

		if ( $current_project && isset( $projects[ $current_project ] ) ) {
			$project_data = $projects[ $current_project ];
		}

		$nonce   = \wp_create_nonce( 'functionalities_task_manager' );
		$tm_opts = (array) \get_option( 'functionalities_task_manager', array( 'enabled' => false ) );

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
			<?php $task_storage_errors = \Functionalities\Features\Task_Manager::get_storage_errors(); ?>
			<?php if ( $task_storage_errors ) : ?>
				<div class="notice notice-error"><p>
				<?php
					echo \esc_html(
						sprintf(
							/* translators: %s: comma-separated storage error codes. */
							\__( 'Task storage error(s): %s. Existing JSON files were preserved.', 'functionalities' ),
							implode( ', ', $task_storage_errors )
						)
					);
				?>
				</p></div>
			<?php endif; ?>

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
	private static function render_task_manager_overview( array $projects, string $nonce ): void {
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
					\esc_html__( 'Tasks are stored in a private folder under %s that is not reachable over the web.', 'functionalities' ),
					'<code>' . \esc_html( str_replace( ABSPATH, '', \Functionalities\Storage\Data_Directory::base() ) ) . '/</code>'
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
				<?php
				foreach ( $projects as $slug => $project ) :
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
						<?php if ( ! empty( $project['show_widget'] ) ) : ?>
							<div class="tm-widget-badge">
								<span class="dashicons dashicons-dashboard"></span>
								<?php \esc_html_e( 'Shown on Dashboard', 'functionalities' ); ?>
							</div>
						<?php endif; ?>
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
	private static function render_task_manager_project( array $project, string $nonce ): void {
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
					<?php
					foreach ( $project['tasks'] as $task ) :
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
									<?php
									if ( ! empty( $task['tags'] ) ) :
										foreach ( $task['tags'] as $tag ) :
											?>
											<span class="task-tag">#<?php echo \esc_html( $tag ); ?></span>
											<?php
										endforeach;
									endif;
									?>
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
				var wasCompleted = $item.hasClass('completed');
				var desired = $checkbox.prop('checked');
				var $copies = $('.task-item').filter(function() { return String($(this).data('task-id')) === String(taskId); });
				$copies.find('.task-checkbox').prop('disabled', true);

				$.post(ajaxUrl, {
					action: 'functionalities_task_toggle',
					nonce: nonce,
					project: projectSlug,
					task_id: taskId,
					completed: desired ? '1' : '0'
				}, function(response) {
					if (response.success) {
						var completed = !!response.data.completed;
						$copies.toggleClass('completed', completed).find('.task-checkbox').prop('checked', completed);
						completedTasks += Number(completed) - Number(wasCompleted);
						updateProgress();
					} else {
						$copies.find('.task-checkbox').prop('checked', wasCompleted);
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Failed to update task.', 'functionalities' ) ); ?>');
					}
				}).fail(function() {
					$copies.find('.task-checkbox').prop('checked', wasCompleted);
					alert('<?php echo \esc_js( \__( 'Request failed.', 'functionalities' ) ); ?>');
				}).always(function() { $copies.find('.task-checkbox').prop('disabled', false); });
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
					tags: tags.join(',')
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
}
