<?php
/**
 * Regressions for the utility modules' permission, failure, and storage paths.
 *
 * @package FunctionalitiesTests
 */
use PHPUnit\Framework\TestCase;
use Functionalities\Features\Content_Tools as Content;
use Functionalities\Features\Link_Health as Links;
use Functionalities\Features\Site_Activity as Activity;
use Functionalities\Storage\Data_Directory as Directory;
use Functionalities\Storage\Atomic_JSON_Store as Store;

require_once dirname( __DIR__ ) . '/includes/core/class-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-data-directory.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-atomic-json-store.php';
require_once dirname( __DIR__ ) . '/includes/features/class-content-tools.php';
require_once dirname( __DIR__ ) . '/includes/features/class-link-health.php';
require_once dirname( __DIR__ ) . '/includes/features/class-site-activity.php';

class UtilityDatabaseSpy {
	public $posts = 'wp_posts';
	private $cursor = 0;
	public function prepare( $sql, $cursor ) {
		$this->cursor = $cursor;
		return $sql;
	}
	public function get_row( $sql ) {
		return isset( $GLOBALS['functionalities_test_posts'][ $this->cursor ] ) ? clone $GLOBALS['functionalities_test_posts'][ $this->cursor ] : null;
	}
	public function get_var( $sql ) {
		$posts = $GLOBALS['functionalities_test_posts'];
		ksort( $posts );
		foreach ( $posts as $id => $post ) {
			if ( $id > $this->cursor && Links::public_post( $id ) ) {
				return $id;
			}
		}
		return 0;
	}
}

final class UtilityModulesTest extends TestCase {
	private $base;
	protected function setUp(): void {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/functionalities-utility-content' );
		}
		$this->base = sys_get_temp_dir() . '/functionalities-utilities-' . bin2hex( random_bytes( 8 ) );
		$GLOBALS['functionalities_test_filter_values'] = array( 'functionalities_data_base_dir' => $this->base );
		$GLOBALS['functionalities_test_options'] = array( Directory::KEY_OPTION => 'testutilityprivate123', 'functionalities_content_tools' => array( 'enabled' => true ), 'functionalities_link_health' => array( 'enabled' => true ), 'functionalities_site_activity' => array( 'enabled' => true ) );
		$GLOBALS['functionalities_test_caps'] = array( 'edit_post' => true, 'edit_posts' => true, 'edit_pages' => true, 'edit_post_meta' => true, 'assign_terms' => true, 'manage_options' => true );
		$GLOBALS['functionalities_test_posts'] = array();
		$GLOBALS['functionalities_test_post_meta'] = array();
		$GLOBALS['functionalities_test_terms'] = array();
		$GLOBALS['functionalities_test_taxonomies'] = array();
		$GLOBALS['functionalities_test_transients'] = array();
		$GLOBALS['functionalities_test_schedule'] = array();
		$GLOBALS['functionalities_http_calls'] = 0;
		$GLOBALS['functionalities_http_handler'] = null;
		$GLOBALS['functionalities_http_fail'] = false;
		$GLOBALS['functionalities_test_deleted_posts'] = array();
		$GLOBALS['functionalities_test_meta_fail'] = false;
		$GLOBALS['functionalities_test_terms_fail'] = false;
		$GLOBALS['functionalities_test_insert_fail'] = false;
		$GLOBALS['functionalities_test_next_id'] = 100;
		$GLOBALS['functionalities_test_user_id'] = 7;
		$GLOBALS['wpdb'] = new UtilityDatabaseSpy();
		Directory::flush();
	}
	protected function tearDown(): void {
		if ( is_dir( $this->base ) ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}
			rmdir( $this->base );
		}
		$GLOBALS['functionalities_test_filter_values'] = array();
		$GLOBALS['functionalities_test_taxonomies'] = array();
		$GLOBALS['functionalities_http_handler'] = null;
		Directory::flush();
	}
	private function post( int $id = 10, string $content = '' ): WP_Post {
		$post = new WP_Post();
		$post->ID = $id;
		$post->post_content = $content;
		$post->post_title = 'Source title';
		$GLOBALS['functionalities_test_posts'][ $id ] = $post;
		return $post;
	}
	private function require_core(): void {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) || ! class_exists( 'WP_Http' ) ) {
			$this->markTestSkipped( 'Set FUNCTIONALITIES_WP_DIR for core HTML and URL parsing.' );
		}
	}

	public function test_duplication_preserves_blocks_and_copies_only_supported_metadata(): void {
		$markup = '<!-- wp:paragraph --><p data-value="a\\b">He said "test".</p><!-- /wp:paragraph -->';
		$post = $this->post( 10, $markup );
		$post->post_author = 99;
		$post->post_name = 'live-source';
		$post->post_password = 'source-password';
		$GLOBALS['functionalities_test_post_meta'][10] = array( '_thumbnail_id' => 77, '_wp_page_template' => 'default', '_edit_lock' => 'locked', 'builder_secret' => 'never-copy' );
		$result = Content::duplicate_post( 10 );
		$this->assertSame( 100, $result );
		$copy = get_post( 100 );
		$this->assertSame( $markup, $copy->post_content );
		$this->assertSame( 'draft', $copy->post_status );
		$this->assertSame( 7, $copy->post_author );
		$this->assertSame( '', $copy->post_name );
		$this->assertSame( '', $copy->post_password );
		$this->assertSame( 77, get_post_meta( 100, '_thumbnail_id', true ) );
		$this->assertSame( '', get_post_meta( 100, '_edit_lock', true ) );
		$this->assertSame( '', get_post_meta( 100, 'builder_secret', true ) );
		$this->assertSame( 'publish', $post->post_status );
	}
	public function test_duplicate_requires_both_source_and_destination_permissions(): void {
		$this->post();
		$GLOBALS['functionalities_test_caps']['edit_post'] = false;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$GLOBALS['functionalities_test_caps']['edit_post'] = true;
		$GLOBALS['functionalities_test_caps']['edit_posts'] = false;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$this->assertNull( get_post( 100 ) );
	}
	public function test_native_editor_metadata_uses_post_permissions_but_extensions_use_meta_permissions(): void {
		$this->post();
		$GLOBALS['functionalities_test_post_meta'][10]['_thumbnail_id'] = 77;
		$GLOBALS['functionalities_test_caps']['edit_post_meta'] = false;
		$this->assertSame( 100, Content::duplicate_post( 10 ) );
		$GLOBALS['functionalities_test_filter_values']['functionalities_content_tools_meta_keys'] = array( 'extension_field' );
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
	}

	public function test_duplicate_rejects_disabled_module_and_unsupported_types(): void {
		$post = $this->post();
		$post->post_type = 'attachment';
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$post->post_type = 'post';
		$GLOBALS['functionalities_test_options']['functionalities_content_tools']['enabled'] = false;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
	}
	public function test_duplicate_rolls_back_failed_metadata_without_touching_source(): void {
		$this->post( 10, 'Original' );
		$GLOBALS['functionalities_test_post_meta'][10]['_thumbnail_id'] = 77;
		$GLOBALS['functionalities_test_meta_fail'] = true;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$this->assertNull( get_post( 100 ) );
		$this->assertSame( 'Original', get_post( 10 )->post_content );
	}
	public function test_duplicate_taxonomy_permissions_and_failure_rollback(): void {
		$this->post();
		$GLOBALS['functionalities_test_taxonomies'] = array( (object) array( 'name' => 'category', 'cap' => (object) array( 'assign_terms' => 'assign_terms' ) ) );
		$GLOBALS['functionalities_test_terms'][10]['category'] = array( 5 );
		$GLOBALS['functionalities_test_caps']['assign_terms'] = false;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$this->assertNull( get_post( 100 ) );
		$GLOBALS['functionalities_test_caps']['assign_terms'] = true;
		$GLOBALS['functionalities_test_terms_fail'] = true;
		$this->assertInstanceOf( WP_Error::class, Content::duplicate_post( 10 ) );
		$this->assertNull( get_post( 100 ) );
	}

	public function test_head_failure_is_confirmed_by_get_before_flagging_broken(): void {
		$GLOBALS['functionalities_http_handler'] = static function ( $url, $args ) {
			return array( 'response' => array( 'code' => 'HEAD' === ( $args['method'] ?? '' ) ? 404 : 200 ) );
		};
		$result = Links::check_url( 'https://example.test/head-bug' );
		$this->assertSame( 'ok', $result['status'] );
		$this->assertSame( 2, $GLOBALS['functionalities_http_calls'] );
		$GLOBALS['functionalities_http_handler'] = static function () { return array( 'response' => array( 'code' => 410 ) ); };
		$this->assertSame( 'broken', Links::check_url( 'https://example.test/gone' )['status'] );
	}
	public function test_deduplicated_checks_are_cached_and_force_recheck_bypasses_cache(): void {
		Links::check_url( 'https://example.test/ok' );
		Links::check_url( 'https://example.test/ok' );
		$this->assertSame( 1, $GLOBALS['functionalities_http_calls'] );
		Links::check_url( 'https://example.test/ok', true );
		$this->assertSame( 2, $GLOBALS['functionalities_http_calls'] );
	}
	public function test_auth_rate_limit_and_server_errors_remain_inconclusive(): void {
		foreach ( array( 401, 403, 429, 500, 503 ) as $code ) {
			$GLOBALS['functionalities_http_handler'] = static function () use ( $code ) { return array( 'response' => array( 'code' => $code ) ); };
			$this->assertSame( 'unknown', Links::check_url( 'https://example.test/status/' . $code )['status'] );
		}
	}
	public function test_unsafe_targets_never_reach_http_transport(): void {
		foreach ( array( 'http://127.0.0.1/', 'http://10.0.0.1/', 'http://169.254.169.254/', 'http://localhost/', 'http://secret.internal/', 'http://[::1]/' ) as $url ) {
			$this->assertSame( 'unsafe', Links::check_url( $url )['status'] );
		}
		$this->assertSame( 0, $GLOBALS['functionalities_http_calls'] );
	}
	public function test_redirects_cannot_escape_to_private_hosts(): void {
		$this->require_core();
		$GLOBALS['functionalities_http_handler'] = static function () { return array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => 'http://127.0.0.1/secret' ) ); };
		$this->assertSame( 'unsafe', Links::check_url( 'https://example.test/redirect' )['status'] );
		$this->assertSame( 1, $GLOBALS['functionalities_http_calls'] );
	}
	public function test_parser_resolves_relative_links_deduplicates_and_skips_non_http(): void {
		$this->require_core();
		$content = '<a href="../target?x=1&amp;y=2#part">One</a><a href="../target?x=1&amp;y=2">Two</a><a href="mailto:a@example.test">Mail</a><a href="#here">Fragment</a><a href="https://user:pass@example.test/">Credentials</a>';
		$this->assertSame( array( 'https://example.test/target?x=1&y=2' ), Links::extract_links( $content, 'https://example.test/article/' ) );
	}
	public function test_batch_resumes_within_one_post_and_then_completes(): void {
		$this->require_core();
		$content = '';
		for ( $i = 0; $i < 6; ++$i ) { $content .= '<a href="https://example.test/link-' . $i . '">Link</a>'; }
		$this->post( 10, $content );
		$this->assertIsArray( Links::start_scan() );
		$first = Links::run_batch();
		$this->assertSame( 4, $first['offset'] );
		$this->assertFalse( get_post_meta( 10, Links::META_KEY, true )['complete'] );
		$second = Links::run_batch();
		$this->assertSame( 'completed', $second['status'] );
		$this->assertSame( 1, $second['posts'] );
		$this->assertCount( 6, get_post_meta( 10, Links::META_KEY, true )['rows'] );
		$this->assertStringStartsWith( Store::PHP_GUARD, file_get_contents( Directory::file( 'link-health-state.json' ) ) );
	}
	public function test_private_content_is_excluded_and_arbitrary_rechecks_are_rejected(): void {
		$this->require_core();
		$post = $this->post( 10, '<a href="https://example.test/public">Link</a>' );
		$this->assertInstanceOf( WP_Error::class, Links::recheck( 10, 'https://other.test/arbitrary' ) );
		$post->post_password = 'protected';
		$this->assertNull( Links::public_post( 10 ) );
		Links::start_scan();
		$this->assertSame( 'completed', Links::run_batch()['status'] );
		$this->assertSame( 0, $GLOBALS['functionalities_http_calls'] );
	}
	public function test_busy_lease_blocks_workers_and_start_does_not_reset_progress(): void {
		Links::start_scan();
		$path = Directory::file( 'link-health-state.json' );
		Store::update( $path, static function ( $data ) { $data['cursor'] = 25; $data['lease'] = array( 'token' => 'worker', 'until' => time() + 60 ); return $data; } );
		$this->assertInstanceOf( WP_Error::class, Links::run_batch() );
		$this->assertInstanceOf( WP_Error::class, Links::start_scan() );
		$this->assertSame( 25, Links::state()['cursor'] );
		$this->assertSame( 0, $GLOBALS['functionalities_http_calls'] );
	}
	public function test_content_edit_during_request_prevents_stale_result_publication(): void {
		$this->require_core();
		$this->post( 10, '<a href="https://example.test/old">Old</a>' );
		$GLOBALS['functionalities_http_handler'] = static function () { $GLOBALS['functionalities_test_posts'][10]->post_content = '<p>Changed</p>'; return array( 'response' => array( 'code' => 200 ) ); };
		Links::start_scan();
		Links::run_batch();
		$this->assertSame( '', get_post_meta( 10, Links::META_KEY, true ) );
		$this->assertSame( 0, Links::state()['offset'] );
	}
	public function test_recheck_refuses_to_overwrite_an_active_background_report(): void {
		$this->require_core();
		$this->post( 10, '<a href="https://example.test/one">One</a>' );
		Links::start_scan();
		Links::run_batch();
		$report = get_post_meta( 10, Links::META_KEY, true );
		Store::update( Directory::file( 'link-health-state.json' ), static function ( $state ) {
			$state['lease'] = array( 'token' => 'another-worker', 'until' => time() + 60 );
			return $state;
		} );
		$this->assertInstanceOf( WP_Error::class, Links::recheck( 10, 'https://example.test/one' ) );
		$this->assertSame( $report, get_post_meta( 10, Links::META_KEY, true ) );
	}

	public function test_expired_worker_cannot_publish_results_after_losing_its_lease(): void {
		$this->require_core();
		$this->post( 10, '<a href="https://example.test/one">One</a>' );
		$GLOBALS['functionalities_http_handler'] = static function () {
			Store::update( Directory::file( 'link-health-state.json' ), static function ( $state ) {
				$state['lease'] = array( 'token' => 'replacement-worker', 'until' => time() + 60 );
				return $state;
			} );
			return array( 'response' => array( 'code' => 200 ) );
		};
		Links::start_scan();
		$this->assertInstanceOf( WP_Error::class, Links::run_batch() );
		$this->assertSame( '', get_post_meta( 10, Links::META_KEY, true ) );
	}

	public function test_slug_changes_invalidate_relative_link_reports(): void {
		$this->require_core();
		$post = $this->post( 10, '<a href="relative">One</a>' );
		Links::start_scan(); Links::run_batch();
		$post->post_name = 'new-slug';
		$this->assertInstanceOf( WP_Error::class, Links::recheck( 10, 'https://example.test/post-10/relative' ) );
	}

	public function test_disabling_scan_removes_both_schedules_and_preserves_state(): void {
		$GLOBALS['functionalities_test_options']['functionalities_link_health']['weekly_scan'] = true;
		Links::start_scan();
		Links::sync_schedule();
		$this->assertNotFalse( wp_next_scheduled( Links::WEEKLY_HOOK ) );
		$GLOBALS['functionalities_test_options']['functionalities_link_health']['enabled'] = false;
		Links::sync_schedule();
		$this->assertFalse( wp_next_scheduled( Links::WEEKLY_HOOK ) );
		$this->assertFalse( wp_next_scheduled( Links::CRON_HOOK ) );
		$this->assertSame( 'running', Links::state()['status'] );
	}

	public function test_activity_logs_fields_without_secret_or_code_values(): void {
		Activity::settings_changed( 'functionalities_snippets', array( 'header' => 'old' ), array( 'header' => '<script>SECRET_TOKEN</script>' ) );
		Activity::record( 'plugin_updated', 'plugin/main.php', array( 'password' => 'SECRET_PASSWORD' ) );
		$entries = Activity::entries();
		$this->assertSame( array( 'header' ), $entries[0]['details']['fields'] );
		$this->assertSame( array(), $entries[1]['details'] );
		$this->assertStringNotContainsString( 'SECRET', json_encode( $entries ) );
		$this->assertStringStartsWith( Store::PHP_GUARD, file_get_contents( Directory::file( 'site-activity.json' ) ) );
	}
	public function test_activity_retention_and_anonymization_preserve_event_history(): void {
		$entries = array( array( 'time' => time() - Activity::RETENTION - 5, 'actor' => 7 ) );
		for ( $i = 0; $i < 1005; ++$i ) { $entries[] = array( 'time' => time(), 'actor' => 7, 'event' => 'plugin_updated', 'target' => 'plugin-' . $i, 'details' => array() ); }
		Store::write( Directory::file( 'site-activity.json' ), array( 'entries' => $entries ) );
		$this->assertCount( 1000, Activity::entries() );
		$this->assertTrue( Activity::anonymize_user( 7 ) );
		$this->assertSame( array( 0 ), array_unique( array_column( Activity::entries(), 'actor' ) ) );
		$this->assertCount( 1000, Activity::entries() );
	}
	public function test_failed_upgrade_is_not_logged_and_successful_item_is_logged(): void {
		$error = new WP_Error( 'install_failed', 'Installation failed.' );
		$extra = array( 'action' => 'update', 'type' => 'plugin', 'plugin' => 'example/main.php' );
		$this->assertSame( $error, Activity::upgraded( $error, $extra ) );
		$this->assertSame( array(), Activity::entries() );
		$this->assertTrue( Activity::upgraded( true, $extra ) );
		$this->assertSame( 'plugin_updated', Activity::entries()[0]['event'] );
	}

	public function test_unknown_activity_events_and_disabled_recording_are_rejected(): void {
		$this->assertFalse( Activity::record( 'visitor_ip', '192.0.2.1' ) );
		$GLOBALS['functionalities_test_options']['functionalities_site_activity']['enabled'] = false;
		$this->assertFalse( Activity::record( 'plugin_updated', 'plugin/main.php' ) );
	}
}
