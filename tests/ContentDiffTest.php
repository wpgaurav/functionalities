<?php
/**
 * Content snapshot difference tests.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class ContentDiffTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__ ) . '/includes/features/class-content-regression.php';
	}

	public function test_snapshot_diff_explains_metric_and_heading_changes(): void {
		$diff = \Functionalities\Features\Content_Regression::build_snapshot_diff(
			array( 'internal_link_count' => 5, 'external_link_count' => 2, 'word_count' => 500, 'h1_count' => 1, 'heading_map' => array( array( 'level' => 2, 'text' => 'Old' ) ), 'timestamp' => 10 ),
			array( 'internal_link_count' => 3, 'external_link_count' => 3, 'word_count' => 450, 'h1_count' => 1, 'heading_map' => array( array( 'level' => 2, 'text' => 'New' ) ), 'timestamp' => 20 )
		);

		$this->assertSame( -2, $diff['metrics']['internal_link_count']['delta'] );
		$this->assertSame( -50, $diff['metrics']['word_count']['delta'] );
		$this->assertCount( 1, $diff['headings_added'] );
		$this->assertCount( 1, $diff['headings_removed'] );
	}
}
