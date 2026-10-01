<?php
/**
 * Storage migration, task mutation, and durable redirect delivery regressions.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;
use Functionalities\Storage\Atomic_JSON_Store as Store;
use Functionalities\Storage\Data_Directory as Directory;
use Functionalities\Features\Task_Manager as Tasks;
use Functionalities\Features\Redirect_Manager as Redirects;

final class StorageWorkflowTest extends TestCase {
	private $base;

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/functionalities-content-test' );
		}
		require_once dirname( __DIR__ ) . '/includes/storage/class-atomic-json-store.php';
		require_once dirname( __DIR__ ) . '/includes/storage/class-data-directory.php';
		require_once dirname( __DIR__ ) . '/includes/features/class-task-manager.php';
		require_once dirname( __DIR__ ) . '/includes/features/class-redirect-manager.php';
	}

	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/functionalities-workflow-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->base, 0700 );
		$GLOBALS['functionalities_test_filter_values']['functionalities_data_base_dir'] = $this->base;
		$GLOBALS['functionalities_test_filter_values']['functionalities_redirect_buffer_threshold'] = 100000;
		$GLOBALS['functionalities_test_options'] = array( Directory::KEY_OPTION => 'testprivatekey12345' );
		$GLOBALS['functionalities_test_caps']['manage_options'] = true;
		$GLOBALS['functionalities_test_blog_id'] = 1;
		$GLOBALS['functionalities_test_is_admin'] = true;
		Directory::flush();
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->base );
		$GLOBALS['functionalities_test_filter_values'] = array();
		$GLOBALS['functionalities_test_options'] = array();
		$GLOBALS['functionalities_test_blog_id'] = 1;
		$GLOBALS['functionalities_test_is_admin'] = false;
		$_POST = array();
		Directory::flush();
	}

	private function remove_directory( string $directory ): void {
		foreach ( scandir( $directory ) ?: array() as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $directory . '/' . $name;
			is_dir( $path ) ? $this->remove_directory( $path ) : unlink( $path );
		}
		rmdir( $directory );
	}

	private function seed_redirect(): array {
		$row = array( 'id' => 'r_test', 'from' => '/old', 'to' => '/new', 'type' => 301, 'enabled' => true, 'hits' => 0 );
		$this->assertTrue( Redirects::save_redirects( array( $row ) ) );
		return $row;
	}

	private function seed_project(): array {
		$project = array( 'name' => 'Test', 'tasks' => array( array( 'id' => 'task_test', 'text' => 'Task', 'tags' => array( 'one' ), 'completed' => false, 'priority' => 0 ) ) );
		$this->assertTrue( Tasks::save_project( 'test', $project ) );
		return $project;
	}

	public function test_new_projects_are_guarded_and_immediately_accept_tasks(): void {
		$project = Tasks::create_project( 'QA project' );
		$this->assertIsArray( $project );
		$this->assertSame( 'qa-project', $project['slug'] );
		$stored = Tasks::get_project( $project['slug'] );
		$this->assertIsArray( $stored );
		$this->assertStringEndsWith( '.json.php', $stored['file_path'] );
		$this->assertStringStartsWith( Store::PHP_GUARD, file_get_contents( $stored['file_path'] ) );
		$this->assertFileDoesNotExist( dirname( $stored['file_path'] ) . '/qa-project.json' );
		$task = Tasks::add_task( $project['slug'], 'New task' );
		$this->assertIsArray( $task );
		$this->assertCount( 1, Tasks::get_project( $project['slug'] )['tasks'] );
	}

	public function test_legacy_and_private_files_migrate_to_guarded_php_without_data_loss(): void {
		$data = array( 'version' => '1.0', 'redirects' => array( array( 'from' => '/old', 'to' => '/new' ) ) );
		file_put_contents( $this->base . '/redirects.json', json_encode( $data ) );
		mkdir( $this->base . '/tasks' );
		file_put_contents( $this->base . '/tasks/project.json', json_encode( array( 'name' => 'Project', 'tasks' => array() ) ) );
		$path = Directory::file( 'redirects.json' );
		$this->assertStringEndsWith( '.json.php', $path );
		$this->assertSame( $data, Store::read( $path )['data'] );
		$this->assertStringStartsWith( Store::PHP_GUARD, file_get_contents( $path ) );
		$this->assertFileDoesNotExist( $this->base . '/redirects.json' );
		$this->assertFileDoesNotExist( $this->base . '/tasks/project.json' );
		$this->assertSame( 'Project', Tasks::get_project( 'project' )['name'] );
		$this->assertSame( '', shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $path ) ) ?? '' );
	}

	public function test_partial_conflicting_migration_blocks_writes_and_retries(): void {
		$private = $this->base . '/testprivatekey12345';
		mkdir( $private );
		file_put_contents( $this->base . '/redirects.json', '{"redirects":[]}' );
		file_put_contents( $this->base . '/404-log.json', '{"items":[]}' );
		Store::write( $private . '/404-log.json.php', array( 'items' => array( '/other' => array( 'count' => 2 ) ) ) );
		$this->assertSame( '', Directory::path() );
		$this->assertContains( 'migration_conflict', Directory::get_errors() );
		$this->assertFalse( Tasks::save_project( 'new', array( 'name' => 'New', 'tasks' => array() ) ) );
		$this->assertFileExists( $this->base . '/404-log.json' );
		$this->assertFileExists( $private . '/redirects.json.php' );
		unlink( $private . '/404-log.json.php' );
		$this->assertSame( $private, Directory::path() );
		$this->assertSame( array(), Directory::get_errors() );
		$this->assertFileDoesNotExist( $this->base . '/404-log.json' );
	}

	public function test_previous_private_raw_files_are_guarded_and_custom_probe_mapping_is_unverified(): void {
		$private = $this->base . '/testprivatekey12345';
		mkdir( $private );
		mkdir( $private . '/tasks' );
		file_put_contents( $private . '/redirects.json', '{"redirects":[]}' );
		file_put_contents( $private . '/tasks/project.json', '{"name":"Project","tasks":[]}' );
		$this->assertSame( $private, Directory::path() );
		$this->assertFileDoesNotExist( $private . '/redirects.json' );
		$this->assertFileDoesNotExist( $private . '/tasks/project.json' );
		$this->assertSame( 'Project', Tasks::get_project( 'project' )['name'] );
		$this->assertSame( '', Directory::probe_url() );
		$this->assertSame( array(), Directory::get_errors() );
		$this->assertStringStartsWith( Store::PHP_GUARD, file_get_contents( $private . '/privacy-probe.json.php' ) );
	}

	public function test_project_import_rejects_scalar_members_and_blank_names(): void {
		foreach ( array( '{"name":"Test","tasks":[7]}', '{"name":"","tasks":[]}' ) as $json ) {
			$_POST = array( 'nonce' => 'test-nonce', 'json' => $json );
			try {
				Tasks::ajax_import_project();
				$this->fail( 'Import must return a validation error.' );
			} catch ( Functionalities_Test_Response $response ) {
				$this->assertFalse( $response->success );
			}
		}
		$this->assertSame( array(), Tasks::get_projects() );
	}

	public function test_tags_clear_reorder_duplicates_reject_and_completion_is_idempotent(): void {
		$this->seed_project();
		$_POST = array( 'nonce' => 'test-nonce', 'project' => 'test', 'task_id' => 'task_test', 'text' => 'Task', 'tags' => '' );
		try {
			Tasks::ajax_update_task();
		} catch ( Functionalities_Test_Response $response ) {
			$this->assertTrue( $response->success );
		}
		$this->assertSame( array(), Tasks::get_project( 'test' )['tasks'][0]['tags'] );
		$this->assertFalse( Tasks::reorder_tasks( 'test', array( 'task_test', 'task_test' ) ) );
		$this->assertCount( 1, Tasks::get_project( 'test' )['tasks'] );
		$this->assertTrue( Tasks::toggle_task( 'test', 'task_test', true ) );
		$this->assertTrue( Tasks::toggle_task( 'test', 'task_test', true ) );
	}

	public function test_locked_project_deletion_waits_for_update_then_remains_deleted(): void {
		$this->seed_project();
		$file = Tasks::get_project( 'test' )['file_path'];
		$marker = $this->base . '/worker-ready';
		$worker = $this->worker( 'update', array( $file, $marker ) );
		for ( $i = 0; $i < 2000 && ! file_exists( $marker ); ++$i ) {
			usleep( 1000 );
			clearstatcache( true, $marker );
		}
		$this->assertFileExists( $marker );
		$this->assertTrue( Tasks::delete_project( 'test' ) );
		$this->assertSame( 0, proc_close( $worker ) );
		clearstatcache( true, $file );
		$this->assertFileDoesNotExist( $file );
	}

	private function worker( string $operation, array $args ) {
		$command = array_merge( array( PHP_BINARY, __DIR__ . '/fixtures/storage-worker.php', $operation, $this->base ), $args );
		$worker = proc_open( implode( ' ', array_map( 'escapeshellarg', $command ) ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		foreach ( $pipes as $pipe ) {
			fclose( $pipe );
		}
		return $worker;
	}

	public function test_concurrent_redirect_events_and_flushes_retain_every_count(): void {
		$this->seed_redirect();
		$workers = array();
		for ( $i = 0; $i < 4; ++$i ) {
			$workers[] = $this->worker( 'events', array( '30' ) );
		}
		foreach ( $workers as $worker ) {
			$this->assertSame( 0, proc_close( $worker ) );
		}
		Redirects::flush_buffer();
		$this->assertSame( 120, Redirects::get_redirects()[0]['hits'] );
		$this->assertSame( 120, Redirects::get_404_log()[0]['count'] );
		$this->assertSame( array(), Redirects::get_buffered_hits() );
	}

	public function test_failed_bucket_delivery_remains_pending_and_replay_is_idempotent(): void {
		$row = $this->seed_redirect();
		$batch = array( 'hits' => array( 'r_test' => 3 ), 'not_found' => array( '/missing' => array( 'count' => 2, 'last_seen' => time() ) ) );
		$buffer_file = Directory::file( 'redirect-buffer.json' );
		Store::write( $buffer_file, array( 'pending' => array( 'batch-test' => $batch ) ) );
		$log_file = Directory::file( '404-log.json' );
		file_put_contents( $log_file, 'broken' );
		Redirects::flush_buffer();
		$this->assertSame( 3, Redirects::get_redirects()[0]['hits'] );
		$remaining = Store::read( $buffer_file )['data']['pending']['batch-test'];
		$this->assertArrayNotHasKey( 'hits', $remaining );
		$this->assertSame( $batch['not_found'], $remaining['not_found'] );
		// Model a stopped process after target write but before acknowledgement.
		Store::write( $buffer_file, array( 'pending' => array( 'batch-test' => $batch ) ) );
		Store::delete( $log_file );
		Redirects::flush_buffer();
		$this->assertSame( 3, Redirects::get_redirects()[0]['hits'] );
		$this->assertSame( 2, Redirects::get_404_log()[0]['count'] );
		$this->assertEmpty( Store::read( $buffer_file )['data']['pending'] );
	}

	public function test_storage_caches_follow_blog_switches(): void {
		$this->seed_project();
		$this->seed_redirect();
		$GLOBALS['functionalities_test_blog_id'] = 2;
		$GLOBALS['functionalities_test_options'][ Directory::KEY_OPTION ] = 'secondprivatekey123';
		$this->assertSame( array(), Tasks::get_projects() );
		$this->assertSame( array(), Redirects::get_redirects() );
		$this->assertTrue( Redirects::save_redirects( array( array( 'id' => 'second', 'from' => '/second', 'to' => '/new', 'enabled' => true ) ) ) );
		$GLOBALS['functionalities_test_blog_id'] = 1;
		$GLOBALS['functionalities_test_options'][ Directory::KEY_OPTION ] = 'testprivatekey12345';
		$this->assertSame( '/old', Redirects::get_redirects()[0]['from'] );
		$this->assertSame( 'Test', Tasks::get_project( 'test' )['name'] );
	}

	public function test_same_version_storage_upgrade_runs_with_modules_disabled_and_retries_failure(): void {
		require_once dirname( __DIR__ ) . '/includes/core/class-upgrader.php';
		$GLOBALS['functionalities_test_options']['functionalities_version'] = '1.6.2';
		$legacy = $this->base . '/redirects.json';
		file_put_contents( $legacy, 'broken' );
		\Functionalities\Core\Upgrader::maybe_upgrade();
		$this->assertSame( '1.6.2', get_option( 'functionalities_version' ) );
		$this->assertFileExists( $legacy );
		$this->assertContains( 'invalid_json', Directory::get_errors() );
		file_put_contents( $legacy, '{"redirects":[]}' );
		\Functionalities\Core\Upgrader::maybe_upgrade();
		$this->assertSame( FUNCTIONALITIES_VERSION, get_option( 'functionalities_version' ) );
		$this->assertFileDoesNotExist( $legacy );
		$this->assertFileExists( Directory::file( 'redirects.json' ) );
	}

	public function test_legacy_buffer_is_retained_until_durably_queued_and_delivered_once(): void {
		$this->seed_redirect();
		$legacy = array( 'hits' => array( 'r_test' => 5 ) );
		$GLOBALS['functionalities_test_options'][ Redirects::BUFFER_OPTION ] = $legacy;
		$file = Directory::file( 'redirect-buffer.json' );
		file_put_contents( $file, 'broken' );
		Redirects::flush_buffer();
		$this->assertSame( $legacy, get_option( Redirects::BUFFER_OPTION ) );
		$this->assertSame( 0, Redirects::get_redirects()[0]['hits'] );
		Store::delete( $file );
		Redirects::flush_buffer();
		$this->assertFalse( get_option( Redirects::BUFFER_OPTION ) );
		$this->assertSame( 5, Redirects::get_redirects()[0]['hits'] );
		Redirects::flush_buffer();
		$this->assertSame( 5, Redirects::get_redirects()[0]['hits'] );
	}

	public function test_redirect_edits_validate_cycles_and_state_updates_are_idempotent(): void {
		$this->seed_redirect();
		$this->assertFalse( Redirects::add_redirect( '/new', '/old' ) );
		$this->assertFalse( Redirects::update_redirect( 'r_test', array( 'to' => '/old' ) ) );
		$this->assertSame( '/new', Redirects::get_redirects()[0]['to'] );
		$this->assertIsArray( Redirects::update_redirect( 'r_test', array( 'to' => 'https://other.example/old' ) ) );
		$this->assertFalse( Redirects::toggle_redirect( 'r_test', false ) );
		$this->assertFalse( Redirects::toggle_redirect( 'r_test', false ) );
	}

	private function buffer_missing( string $path ): void {
		$method = new ReflectionMethod( Redirects::class, 'buffer_event' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( null, 'not_found', $path );
	}

	public function test_purged_and_ignored_404s_do_not_return_from_pending_events(): void {
		$this->seed_redirect();
		$this->buffer_missing( '/missing' );
		$_POST = array( 'nonce' => 'test-nonce' );
		try {
			Redirects::ajax_purge_404_log();
		} catch ( Functionalities_Test_Response $response ) {
			$this->assertTrue( $response->success );
		}
		Redirects::flush_buffer();
		$this->assertSame( array(), Redirects::get_404_log() );
		$this->buffer_missing( '/ignored' );
		$_POST['path'] = '/ignored';
		try {
			Redirects::ajax_ignore_404();
		} catch ( Functionalities_Test_Response $response ) {
			$this->assertTrue( $response->success );
		}
		// A request already in flight can still enqueue an ignored path.
		$this->buffer_missing( '/ignored' );
		Redirects::flush_buffer();
		$this->assertSame( array(), Redirects::get_404_log() );
	}

	public function test_older_retry_events_keep_the_latest_timestamp_and_referrer(): void {
		$this->seed_redirect();
		$now = time();
		Store::write( Directory::file( 'redirect-buffer.json' ), array( 'pending' => array(
			'new' => array( 'not_found' => array( '/missing' => array( 'count' => 2, 'last_seen' => $now, 'origin' => 'https://new.test' ) ) ),
			'old' => array( 'not_found' => array( '/missing' => array( 'count' => 3, 'last_seen' => $now - 10, 'origin' => 'https://old.test' ) ) ),
		) ) );
		Redirects::flush_buffer();
		$entry = Redirects::get_404_log()[0];
		$this->assertSame( 5, $entry['count'] );
		$this->assertSame( $now, $entry['last_seen'] );
		$this->assertSame( 'https://new.test', $entry['referrer_origin'] );
	}
}
