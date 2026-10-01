<?php
/**
 * Accepted content baselines survive pending damaged updates.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-content-regression.php';

/**
 * Declare the fields used by the snapshotter without PHP dynamic properties.
 */
class Functionalities_Baseline_Test_Post extends WP_Post {
	public $ID = 1;
	public $post_content = '';
	public $post_title = 'Baseline test';
	public $post_date_gmt = '2020-01-01 00:00:00';
}

final class ContentBaselineTest extends TestCase {
	private $original_post;

	protected function setUp(): void {
		parent::setUp();
		$this->original_post = $_POST;
		$GLOBALS['functionalities_test_options'] = array(
			'functionalities_content_regression' => array(
				'enabled' => true,
				'word_count_enabled' => false,
				'heading_enabled' => false,
				'detect_missing_alt' => false,
				'snapshot_rolling_count' => 2,
			),
		);
		$GLOBALS['functionalities_test_post_meta'] = array();
		$GLOBALS['functionalities_test_posts'] = array();
		$GLOBALS['functionalities_test_autosave'] = false;
		$GLOBALS['functionalities_test_caps'] = array( 'edit_posts' => true, 'edit_post' => true );
		$property = new ReflectionProperty( \Functionalities\Features\Content_Regression::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	protected function tearDown(): void {
		$_POST = $this->original_post;
		parent::tearDown();
	}

	private function publish_baseline(): Functionalities_Baseline_Test_Post {
		$post = new Functionalities_Baseline_Test_Post();
		$post->post_content = '<p><a href="/a">A</a><a href="/b">B</a><a href="/c">C</a><a href="/d">D</a></p>';
		$GLOBALS['functionalities_test_posts'][1] = $post;
		\Functionalities\Features\Content_Regression::on_save_post( 1, $post, false );
		return $post;
	}

	public function test_damaged_save_keeps_warning_and_previous_accepted_baseline(): void {
		$post = $this->publish_baseline();
		$post->post_content = '<p>A B C D</p>';
		\Functionalities\Features\Content_Regression::on_save_post( 1, $post, true );

		$baseline = \Functionalities\Features\Content_Regression::get_last_stable_snapshot( 1 );
		$status = get_post_meta( 1, \Functionalities\Features\Content_Regression::STATUS_KEY, true );
		$this->assertSame( 4, $baseline['internal_link_count'] );
		$this->assertSame( 1, $status['count'] );
		$this->assertSame( 'link_drop', $status['warnings'][0]['type'] );
		$this->assertCount( 1, \Functionalities\Features\Content_Regression::detect_regressions( 1 ) );
	}

	public function test_repeated_damaged_saves_cannot_evict_the_accepted_baseline(): void {
		$post = $this->publish_baseline();
		$post->post_content = '<p>A B C D</p>';
		for ( $i = 0; $i < 5; $i++ ) {
			\Functionalities\Features\Content_Regression::on_save_post( 1, $post, true );
		}

		$data = get_post_meta( 1, \Functionalities\Features\Content_Regression::META_KEY, true );
		$this->assertCount( 2, $data['snapshots'] );
		$this->assertFalse( $data['snapshots'][0]['is_stable_version'] );
		$this->assertFalse( $data['snapshots'][1]['is_stable_version'] );
		$this->assertSame( 4, \Functionalities\Features\Content_Regression::get_last_stable_snapshot( 1 )['internal_link_count'] );
		$this->assertCount( 1, \Functionalities\Features\Content_Regression::detect_regressions( 1 ) );
	}

	public function test_explicit_acceptance_advances_baseline_and_clears_cached_warning(): void {
		$post = $this->publish_baseline();
		$post->post_content = '<p>A B C D</p>';
		\Functionalities\Features\Content_Regression::on_save_post( 1, $post, true );
		$_POST = array( 'post_id' => 1, 'nonce' => 'test-nonce' );

		try {
			\Functionalities\Features\Content_Regression::ajax_mark_intentional();
			$this->fail( 'The AJAX action must return a JSON response.' );
		} catch ( Functionalities_Test_Response $response ) {
			$this->assertTrue( $response->success );
		}

		$this->assertSame( 0, \Functionalities\Features\Content_Regression::get_last_stable_snapshot( 1 )['internal_link_count'] );
		$this->assertSame( array(), \Functionalities\Features\Content_Regression::detect_regressions( 1 ) );
		$status = get_post_meta( 1, \Functionalities\Features\Content_Regression::STATUS_KEY, true );
		$this->assertSame( 0, $status['count'] );
		$this->assertSame( array(), $status['warnings'] );
	}

	public function test_legacy_snapshot_history_migrates_its_baseline_before_trimming(): void {
		$post = $this->publish_baseline();
		$data = get_post_meta( 1, \Functionalities\Features\Content_Regression::META_KEY, true );
		unset( $data['baseline'] );
		update_post_meta( 1, \Functionalities\Features\Content_Regression::META_KEY, $data );
		$post->post_content = '<p>A B C D</p>';
		for ( $i = 0; $i < 3; $i++ ) {
			\Functionalities\Features\Content_Regression::on_save_post( 1, $post, true );
		}
		$this->assertSame( 4, \Functionalities\Features\Content_Regression::get_last_stable_snapshot( 1 )['internal_link_count'] );
	}

	public function test_word_count_drop_stays_unresolved_after_saving(): void {
		$options = $GLOBALS['functionalities_test_options']['functionalities_content_regression'];
		$options['link_drop_enabled'] = false;
		$options['word_count_enabled'] = true;
		$options['word_count_min_age_days'] = 0;
		$GLOBALS['functionalities_test_options']['functionalities_content_regression'] = $options;
		$post = new Functionalities_Baseline_Test_Post();
		$post->post_content = '<p>' . str_repeat( 'word ', 100 ) . '</p>';
		$GLOBALS['functionalities_test_posts'][1] = $post;
		\Functionalities\Features\Content_Regression::on_save_post( 1, $post, false );
		$post->post_content = '<p>' . str_repeat( 'word ', 30 ) . '</p>';
		\Functionalities\Features\Content_Regression::on_save_post( 1, $post, true );
		$warnings = \Functionalities\Features\Content_Regression::detect_regressions( 1 );
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'word_count_drop', $warnings[0]['type'] );
		$this->assertSame( 100, $warnings[0]['before'] );
		$this->assertSame( 30, $warnings[0]['after'] );
	}
}
