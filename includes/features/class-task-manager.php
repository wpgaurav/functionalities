<?php
/**
 * Task Manager - File-based project task management.
 *
 * @package Functionalities\Features
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Task_Manager class.
 *
 * Provides a file-based task management system using JSON files.
 * Tasks are stored in /wp-content/functionalities/tasks/ folder.
 * Each project has its own JSON file.
 */
class Task_Manager {

	/**
	 * Tasks directory path.
	 *
	 * @var string
	 */
	private static $tasks_dir = '';

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init() : void {
		self::$tasks_dir = WP_CONTENT_DIR . '/functionalities/tasks/';

		// Only run in admin - no frontend footprint.
		if ( ! \is_admin() ) {
			return;
		}

		// AJAX handlers.
		\add_action( 'wp_ajax_functionalities_task_create_project', array( __CLASS__, 'ajax_create_project' ) );
		\add_action( 'wp_ajax_functionalities_task_delete_project', array( __CLASS__, 'ajax_delete_project' ) );
		\add_action( 'wp_ajax_functionalities_task_add', array( __CLASS__, 'ajax_add_task' ) );
		\add_action( 'wp_ajax_functionalities_task_update', array( __CLASS__, 'ajax_update_task' ) );
		\add_action( 'wp_ajax_functionalities_task_delete', array( __CLASS__, 'ajax_delete_task' ) );
		\add_action( 'wp_ajax_functionalities_task_toggle', array( __CLASS__, 'ajax_toggle_task' ) );
		\add_action( 'wp_ajax_functionalities_task_reorder', array( __CLASS__, 'ajax_reorder_tasks' ) );
		\add_action( 'wp_ajax_functionalities_task_import', array( __CLASS__, 'ajax_import_project' ) );
		\add_action( 'wp_ajax_functionalities_task_export', array( __CLASS__, 'ajax_export_project' ) );
		\add_action( 'wp_ajax_functionalities_task_update_widget_setting', array( __CLASS__, 'ajax_update_widget_setting' ) );

		// Dashboard widgets.
		\add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widgets' ) );
	}

	/**
	 * Get the tasks directory, creating it if needed.
	 *
	 * @return string|false Directory path or false on failure.
	 */
	public static function get_tasks_dir() {
		if ( ! file_exists( self::$tasks_dir ) ) {
			if ( ! wp_mkdir_p( self::$tasks_dir ) ) {
				return false;
			}
			// Add index.php for security.
			$index_file = self::$tasks_dir . 'index.php';
			if ( ! file_exists( $index_file ) ) {
				$result = file_put_contents( $index_file, '<?php // Silence is golden.' );
				if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
					error_log( 'Functionalities: Failed to create index.php in tasks directory.' );
				}
			}
			// Add .htaccess for Apache servers.
			$htaccess_file = self::$tasks_dir . '.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all" );
			}
		}
		return self::$tasks_dir;
	}

	/**
	 * Validate slug format for security.
	 *
	 * @param string $slug The slug to validate.
	 * @return bool True if valid.
	 */
	private static function is_valid_slug( string $slug ) : bool {
		// Only allow alphanumeric, hyphens, and underscores.
		return (bool) preg_match( '/^[a-z0-9_-]+$/i', $slug );
	}

	/**
	 * Get all projects.
	 *
	 * @return array List of project data.
	 */
	public static function get_projects() : array {
		$dir = self::get_tasks_dir();
		if ( ! $dir || ! is_dir( $dir ) ) {
			return array();
		}

		$projects = array();
		$files    = glob( $dir . '*.json' );

		if ( ! $files ) {
			return array();
		}

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			$data    = json_decode( $content, true );

			if ( $data && isset( $data['name'] ) ) {
				$slug              = basename( $file, '.json' );
				$data['slug']      = $slug;
				$data['file_path'] = $file;
				$projects[ $slug ] = $data;
			}
		}

		// Sort by name.
		uasort( $projects, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		});

		return $projects;
	}

	/**
	 * Get a single project by slug.
	 *
	 * @param string $slug Project slug.
	 * @return array|null Project data or null if not found.
	 */
	public static function get_project( string $slug ) : ?array {
		$dir = self::get_tasks_dir();
		if ( ! $dir ) {
			return null;
		}

		// Validate slug format.
		if ( ! self::is_valid_slug( $slug ) ) {
			return null;
		}

		$safe_slug = sanitize_file_name( $slug );
		$file      = $dir . $safe_slug . '.json';

		// Verify file is within tasks directory (prevent path traversal).
		$real_path = realpath( $file );
		$real_dir  = realpath( $dir );
		if ( false === $real_path || false === $real_dir || 0 !== strpos( $real_path, $real_dir ) ) {
			// File doesn't exist or is outside tasks directory.
			if ( ! file_exists( $file ) ) {
				return null;
			}
		}

		$content = file_get_contents( $file );
		if ( false === $content ) {
			return null;
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		if ( $data && isset( $data['name'] ) ) {
			$data['slug']      = $safe_slug;
			$data['file_path'] = $file;
			return $data;
		}

		return null;
	}

	/**
	 * Save a project.
	 *
	 * @param string $slug Project slug.
	 * @param array  $data Project data.
	 * @return bool True on success.
	 */
	public static function save_project( string $slug, array $data ) : bool {
		$dir = self::get_tasks_dir();
		if ( ! $dir ) {
			return false;
		}

		$file = $dir . sanitize_file_name( $slug ) . '.json';

		// Update modified timestamp.
		$data['modified'] = current_time( 'mysql' );

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return false !== file_put_contents( $file, $json );
	}

	/**
	 * Create a new project.
	 *
	 * @param string $name Project name.
	 * @return array|false Project data or false on failure.
	 */
	public static function create_project( string $name ) {
		$slug = sanitize_title( $name );

		// Ensure unique slug.
		$original_slug = $slug;
		$counter       = 1;
		while ( self::get_project( $slug ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		$data = array(
			'name'          => $name,
			'created'       => current_time( 'mysql' ),
			'modified'      => current_time( 'mysql' ),
			'show_widget'   => false,
			'tasks'         => array(),
		);

		if ( self::save_project( $slug, $data ) ) {
			$data['slug'] = $slug;
			return $data;
		}

		return false;
	}

	/**
	 * Delete a project.
	 *
	 * @param string $slug Project slug.
	 * @return bool True on success.
	 */
	public static function delete_project( string $slug ) : bool {
		$project = self::get_project( $slug );
		if ( ! $project ) {
			return false;
		}

		\wp_delete_file( $project['file_path'] );
		return ! file_exists( $project['file_path'] );
	}

	/**
	 * Generate a unique task ID.
	 *
	 * @return string Unique ID.
	 */
	private static function generate_task_id() : string {
		return 'task_' . substr( md5( uniqid( '', true ) ), 0, 12 );
	}

	/**
	 * Extract tags from task text.
	 *
	 * @param string $text Task text.
	 * @return array Array with 'text' (cleaned) and 'tags'.
	 */
	public static function extract_tags( string $text ) : array {
		$tags = array();
		preg_match_all( '/#([a-zA-Z0-9_-]+)/', $text, $matches );

		if ( ! empty( $matches[1] ) ) {
			$tags = array_unique( $matches[1] );
		}

		return array(
			'text' => trim( preg_replace( '/#[a-zA-Z0-9_-]+/', '', $text ) ),
			'tags' => $tags,
		);
	}

	/**
	 * Parse priority from text (e.g., !1, !2, !3).
	 *
	 * @param string $text Task text.
	 * @return array Array with 'text' (cleaned) and 'priority'.
	 */
	public static function extract_priority( string $text ) : array {
		$priority = 0;
		if ( preg_match( '/!([1-3])/', $text, $match ) ) {
			$priority = (int) $match[1];
			$text     = trim( preg_replace( '/![1-3]/', '', $text ) );
		}

		return array(
			'text'     => $text,
			'priority' => $priority,
		);
	}

	/**
	 * Add a task to a project.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $text         Task text (may include tags and priority).
	 * @param string $notes        Optional notes.
	 * @return array|false Task data or false on failure.
	 */
	public static function add_task( string $project_slug, string $text, string $notes = '' ) {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			return false;
		}

		// Extract tags and priority from text.
		$tag_data      = self::extract_tags( $text );
		$priority_data = self::extract_priority( $tag_data['text'] );

		$task = array(
			'id'        => self::generate_task_id(),
			'text'      => $priority_data['text'],
			'completed' => false,
			'tags'      => $tag_data['tags'],
			'priority'  => $priority_data['priority'],
			'notes'     => $notes,
			'created'   => current_time( 'mysql' ),
		);

		$project['tasks'][] = $task;

		if ( self::save_project( $project_slug, $project ) ) {
			return $task;
		}

		return false;
	}

	/**
	 * Update a task.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $task_id      Task ID.
	 * @param array  $updates      Updates to apply.
	 * @return array|false Updated task or false on failure.
	 */
	public static function update_task( string $project_slug, string $task_id, array $updates ) {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			return false;
		}

		foreach ( $project['tasks'] as &$task ) {
			if ( $task['id'] === $task_id ) {
				// Handle text updates (may contain new tags/priority).
				if ( isset( $updates['text'] ) ) {
					$tag_data      = self::extract_tags( $updates['text'] );
					$priority_data = self::extract_priority( $tag_data['text'] );

					$task['text'] = $priority_data['text'];

					// Merge new tags.
					if ( ! empty( $tag_data['tags'] ) ) {
						$task['tags'] = array_unique( array_merge( $task['tags'] ?? array(), $tag_data['tags'] ) );
					}

					// Update priority if specified.
					if ( $priority_data['priority'] > 0 ) {
						$task['priority'] = $priority_data['priority'];
					}
				}

				// Direct updates.
				if ( isset( $updates['completed'] ) ) {
					$task['completed'] = (bool) $updates['completed'];
				}
				if ( isset( $updates['notes'] ) ) {
					$task['notes'] = $updates['notes'];
				}
				if ( isset( $updates['tags'] ) ) {
					$task['tags'] = (array) $updates['tags'];
				}
				if ( isset( $updates['priority'] ) ) {
					$task['priority'] = (int) $updates['priority'];
				}

				if ( self::save_project( $project_slug, $project ) ) {
					return $task;
				}
				break;
			}
		}

		return false;
	}

	/**
	 * Delete a task.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $task_id      Task ID.
	 * @return bool True on success.
	 */
	public static function delete_task( string $project_slug, string $task_id ) : bool {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			return false;
		}

		$project['tasks'] = array_filter( $project['tasks'], function( $task ) use ( $task_id ) {
			return $task['id'] !== $task_id;
		});

		// Re-index array.
		$project['tasks'] = array_values( $project['tasks'] );

		return self::save_project( $project_slug, $project );
	}

	/**
	 * Toggle task completion.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $task_id      Task ID.
	 * @return bool|null New completion state or null on failure.
	 */
	public static function toggle_task( string $project_slug, string $task_id ) {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			return null;
		}

		$new_state = null;
		foreach ( $project['tasks'] as &$task ) {
			if ( $task['id'] === $task_id ) {
				$task['completed'] = ! $task['completed'];
				$new_state         = $task['completed'];
				break;
			}
		}

		if ( null !== $new_state && self::save_project( $project_slug, $project ) ) {
			return $new_state;
		}

		return null;
	}

	/**
	 * Reorder tasks.
	 *
	 * @param string $project_slug Project slug.
	 * @param array  $task_ids     Ordered task IDs.
	 * @return bool True on success.
	 */
	public static function reorder_tasks( string $project_slug, array $task_ids ) : bool {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			return false;
		}

		// Build lookup.
		$tasks_by_id = array();
		foreach ( $project['tasks'] as $task ) {
			$tasks_by_id[ $task['id'] ] = $task;
		}

		// Reorder.
		$new_tasks = array();
		foreach ( $task_ids as $id ) {
			if ( isset( $tasks_by_id[ $id ] ) ) {
				$new_tasks[] = $tasks_by_id[ $id ];
			}
		}

		// Add any tasks not in the ID list (safety).
		foreach ( $tasks_by_id as $id => $task ) {
			if ( ! in_array( $id, $task_ids, true ) ) {
				$new_tasks[] = $task;
			}
		}

		$project['tasks'] = $new_tasks;

		return self::save_project( $project_slug, $project );
	}

	/**
	 * Get task statistics for a project.
	 *
	 * @param array $project Project data.
	 * @return array Statistics.
	 */
	public static function get_stats( array $project ) : array {
		$total     = count( $project['tasks'] ?? array() );
		$completed = 0;

		foreach ( $project['tasks'] ?? array() as $task ) {
			if ( ! empty( $task['completed'] ) ) {
				$completed++;
			}
		}

		return array(
			'total'     => $total,
			'completed' => $completed,
			'pending'   => $total - $completed,
			'percent'   => $total > 0 ? round( ( $completed / $total ) * 100 ) : 0,
		);
	}

	/**
	 * Register dashboard widgets for projects that have show_widget enabled.
	 *
	 * @return void
	 */
	public static function register_dashboard_widgets() : void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$projects = self::get_projects();

		foreach ( $projects as $slug => $project ) {
			if ( ! empty( $project['show_widget'] ) ) {
				\wp_add_dashboard_widget(
					'functionalities_tasks_' . $slug,
					sprintf(
						/* translators: %s: Project name */
						\__( 'Tasks: %s', 'functionalities' ),
						\esc_html( $project['name'] )
					),
					function() use ( $slug ) {
						self::render_dashboard_widget( $slug );
					}
				);
			}
		}
	}

	/**
	 * Render dashboard widget content.
	 *
	 * @param string $project_slug Project slug.
	 * @return void
	 */
	public static function render_dashboard_widget( string $project_slug ) : void {
		$project = self::get_project( $project_slug );
		if ( ! $project ) {
			echo '<p>' . \esc_html__( 'Project not found.', 'functionalities' ) . '</p>';
			return;
		}

		$stats = self::get_stats( $project );
		?>
		<div class="functionalities-task-widget">
			<div class="task-progress" style="margin-bottom: 10px;">
				<div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; color: #646970;">
					<span><?php printf( '%d/%d tasks', (int) $stats['completed'], (int) $stats['total'] ); ?></span>
					<span><?php echo (int) $stats['percent']; ?>%</span>
				</div>
				<div style="background: #dcdcde; border-radius: 3px; height: 8px; overflow: hidden;">
					<div style="background: #2271b1; height: 100%; width: <?php echo (int) $stats['percent']; ?>%; transition: width 0.3s;"></div>
				</div>
			</div>
			<ul style="margin: 0; padding: 0; list-style: none; max-height: 200px; overflow-y: auto;">
				<?php
				$pending_tasks = array_filter( $project['tasks'], function( $task ) {
					return empty( $task['completed'] );
				});
				$pending_tasks = array_slice( $pending_tasks, 0, 5 ); // Show top 5.

				if ( empty( $pending_tasks ) ) :
					?>
					<li style="color: #646970; padding: 5px 0;"><?php \esc_html_e( 'All tasks completed!', 'functionalities' ); ?></li>
				<?php else :
					foreach ( $pending_tasks as $task ) :
						$priority_class = '';
						if ( ! empty( $task['priority'] ) ) {
							$priority_class = ' priority-' . $task['priority'];
						}
						?>
						<li style="padding: 5px 0; border-bottom: 1px solid #f0f0f1; display: flex; align-items: flex-start; gap: 8px;">
							<?php if ( ! empty( $task['priority'] ) ) : ?>
								<span style="color: <?php echo $task['priority'] === 1 ? '#d63638' : ( $task['priority'] === 2 ? '#dba617' : '#2271b1' ); ?>; font-weight: bold; flex-shrink: 0;">!</span>
							<?php endif; ?>
							<span style="flex: 1;">
								<?php echo \esc_html( $task['text'] ); ?>
								<?php if ( ! empty( $task['tags'] ) ) : ?>
									<span style="color: #2271b1; font-size: 11px;">
										<?php echo \esc_html( '#' . implode( ' #', $task['tags'] ) ); ?>
									</span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach;
				endif;
				?>
			</ul>
			<p style="margin: 10px 0 0; text-align: right;">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities&module=task-manager&project=' . $project_slug ) ); ?>">
					<?php \esc_html_e( 'Manage Tasks →', 'functionalities' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	// AJAX Handlers.

	/**
	 * Verify AJAX request.
	 *
	 * @return bool True if valid.
	 */
	private static function verify_ajax() : bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce doesn't need sanitization.
		$nonce = isset( $_POST['nonce'] ) ? \wp_unslash( $_POST['nonce'] ) : '';
		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'functionalities_task_manager' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Security check failed.', 'functionalities' ) ) );
			return false;
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * AJAX: Create project.
	 */
	public static function ajax_create_project() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$name = isset( $_POST['name'] ) ? \sanitize_text_field( \wp_unslash( $_POST['name'] ) ) : '';
		if ( empty( $name ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project name is required.', 'functionalities' ) ) );
			return;
		}

		$project = self::create_project( $name );
		if ( $project ) {
			\wp_send_json_success( array(
				'message' => \__( 'Project created.', 'functionalities' ),
				'project' => $project,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to create project.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Delete project.
	 */
	public static function ajax_delete_project() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$slug = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		if ( empty( $slug ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project slug is required.', 'functionalities' ) ) );
			return;
		}

		if ( self::delete_project( $slug ) ) {
			\wp_send_json_success( array( 'message' => \__( 'Project deleted.', 'functionalities' ) ) );
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to delete project.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Add task.
	 */
	public static function ajax_add_task() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$project = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$text    = isset( $_POST['text'] ) ? \sanitize_text_field( \wp_unslash( $_POST['text'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$notes   = isset( $_POST['notes'] ) ? \sanitize_textarea_field( \wp_unslash( $_POST['notes'] ) ) : '';

		if ( empty( $project ) || empty( $text ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project and task text are required.', 'functionalities' ) ) );
			return;
		}

		$task = self::add_task( $project, $text, $notes );
		if ( $task ) {
			\wp_send_json_success( array(
				'message' => \__( 'Task added.', 'functionalities' ),
				'task'    => $task,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to add task.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Update task.
	 */
	public static function ajax_update_task() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$project  = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$task_id  = isset( $_POST['task_id'] ) ? \sanitize_key( $_POST['task_id'] ) : '';
		$updates  = array();

		if ( isset( $_POST['text'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['text'] = \sanitize_text_field( \wp_unslash( $_POST['text'] ) );
		}
		if ( isset( $_POST['notes'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['notes'] = \sanitize_textarea_field( \wp_unslash( $_POST['notes'] ) );
		}
		if ( isset( $_POST['priority'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
			$updates['priority'] = (int) $_POST['priority'];
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce verified in verify_ajax(). Tags are sanitized with array_map.
		if ( isset( $_POST['tags'] ) ) {
			$tags = is_array( $_POST['tags'] ) ? \wp_unslash( $_POST['tags'] ) : explode( ',', \wp_unslash( $_POST['tags'] ) );
			$updates['tags'] = array_map( 'sanitize_key', $tags );
		}
		// phpcs:enable

		if ( empty( $project ) || empty( $task_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project and task ID are required.', 'functionalities' ) ) );
			return;
		}

		$task = self::update_task( $project, $task_id, $updates );
		if ( $task ) {
			\wp_send_json_success( array(
				'message' => \__( 'Task updated.', 'functionalities' ),
				'task'    => $task,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to update task.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Delete task.
	 */
	public static function ajax_delete_task() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$project = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$task_id = isset( $_POST['task_id'] ) ? \sanitize_key( $_POST['task_id'] ) : '';

		if ( empty( $project ) || empty( $task_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project and task ID are required.', 'functionalities' ) ) );
			return;
		}

		if ( self::delete_task( $project, $task_id ) ) {
			\wp_send_json_success( array( 'message' => \__( 'Task deleted.', 'functionalities' ) ) );
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to delete task.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Toggle task.
	 */
	public static function ajax_toggle_task() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$project = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$task_id = isset( $_POST['task_id'] ) ? \sanitize_key( $_POST['task_id'] ) : '';

		if ( empty( $project ) || empty( $task_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project and task ID are required.', 'functionalities' ) ) );
			return;
		}

		$new_state = self::toggle_task( $project, $task_id );
		if ( null !== $new_state ) {
			\wp_send_json_success( array(
				'message'   => \__( 'Task updated.', 'functionalities' ),
				'completed' => $new_state,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to toggle task.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Reorder tasks.
	 */
	public static function ajax_reorder_tasks() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$project  = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce verified. IDs are sanitized with array_map.
		$task_ids = isset( $_POST['task_ids'] ) ? (array) \wp_unslash( $_POST['task_ids'] ) : array();
		$task_ids = array_map( 'sanitize_key', $task_ids );
		// phpcs:enable

		if ( empty( $project ) || empty( $task_ids ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project and task IDs are required.', 'functionalities' ) ) );
			return;
		}

		if ( self::reorder_tasks( $project, $task_ids ) ) {
			\wp_send_json_success( array( 'message' => \__( 'Tasks reordered.', 'functionalities' ) ) );
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to reorder tasks.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Import project.
	 */
	public static function ajax_import_project() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified. JSON needs raw parsing.
		$json = isset( $_POST['json'] ) ? \wp_unslash( $_POST['json'] ) : '';
		if ( empty( $json ) ) {
			\wp_send_json_error( array( 'message' => \__( 'JSON data is required.', 'functionalities' ) ) );
			return;
		}

		$data = json_decode( $json, true );
		if ( ! $data || ! isset( $data['name'] ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid JSON format. Must contain "name" field.', 'functionalities' ) ) );
			return;
		}

		// Create project with imported data.
		$slug = sanitize_title( $data['name'] );
		$original_slug = $slug;
		$counter = 1;
		while ( self::get_project( $slug ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		// Ensure required fields.
		$data['created']    = $data['created'] ?? current_time( 'mysql' );
		$data['modified']   = current_time( 'mysql' );
		$data['show_widget'] = $data['show_widget'] ?? false;
		$data['tasks']      = $data['tasks'] ?? array();

		// Re-generate task IDs to avoid conflicts.
		foreach ( $data['tasks'] as &$task ) {
			$task['id'] = self::generate_task_id();
		}

		if ( self::save_project( $slug, $data ) ) {
			$data['slug'] = $slug;
			\wp_send_json_success( array(
				'message' => \__( 'Project imported.', 'functionalities' ),
				'project' => $data,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to import project.', 'functionalities' ) ) );
		}
	}

	/**
	 * AJAX: Export project.
	 */
	public static function ajax_export_project() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$slug = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		if ( empty( $slug ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project slug is required.', 'functionalities' ) ) );
			return;
		}

		$project = self::get_project( $slug );
		if ( ! $project ) {
			\wp_send_json_error( array( 'message' => \__( 'Project not found.', 'functionalities' ) ) );
			return;
		}

		// Remove internal fields.
		unset( $project['slug'], $project['file_path'] );

		\wp_send_json_success( array(
			'json' => wp_json_encode( $project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		));
	}

	/**
	 * AJAX: Update widget setting.
	 */
	public static function ajax_update_widget_setting() : void {
		if ( ! self::verify_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$slug        = isset( $_POST['project'] ) ? \sanitize_key( $_POST['project'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax().
		$show_widget = isset( $_POST['show_widget'] ) && $_POST['show_widget'] === 'true';

		if ( empty( $slug ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Project slug is required.', 'functionalities' ) ) );
			return;
		}

		$project = self::get_project( $slug );
		if ( ! $project ) {
			\wp_send_json_error( array( 'message' => \__( 'Project not found.', 'functionalities' ) ) );
			return;
		}

		$project['show_widget'] = $show_widget;

		if ( self::save_project( $slug, $project ) ) {
			\wp_send_json_success( array(
				'message'     => \__( 'Widget setting updated.', 'functionalities' ),
				'show_widget' => $show_widget,
			));
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to update setting.', 'functionalities' ) ) );
		}
	}
}
