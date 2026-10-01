<?php
/**
 * Production releases must use the quality gate and exclude prereleases.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase {
	public function test_changelog_extraction_excludes_upgrade_notices(): void {
		$fixture = tempnam( sys_get_temp_dir(), 'functionalities-changelog-' );
		file_put_contents( $fixture, "== Description ==\n= 1.6.3 =\nDescription text.\n== Changelog ==\n= 1.6.3 =\n* Fixed: Tasks save correctly.\n\n= 1.6.2 =\n* Older change.\n== Upgrade Notice ==\n= 1.6.3 =\nUpgrade instructions.\n" );
		try {
			$output = array();
			$status = 0;
			exec( 'bash ' . escapeshellarg( dirname( __DIR__ ) . '/bin/extract-changelog.sh' ) . ' 1.6.3 ' . escapeshellarg( $fixture ), $output, $status );
			$this->assertSame( 0, $status );
			$this->assertSame( '* Fixed: Tasks save correctly.', trim( implode( "\n", $output ) ) );
		} finally {
			unlink( $fixture );
		}
	}

	public function test_missing_changelog_version_fails(): void {
		$output = array();
		$status = 0;
		exec( 'bash ' . escapeshellarg( dirname( __DIR__ ) . '/bin/extract-changelog.sh' ) . ' 99.99.99 ' . escapeshellarg( dirname( __DIR__ ) . '/readme.txt' ), $output, $status );
		$this->assertSame( 2, $status );
		$this->assertSame( array(), $output );
	}
	public function test_release_runs_reusable_quality_checks_before_packaging(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' );
		$release = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release.yml' );
		$this->assertStringContainsString( 'workflow_call:', $quality );
		$this->assertStringContainsString( 'uses: ./.github/workflows/quality.yml', $release );
		$this->assertMatchesRegularExpression( '/build:\s*\n\s+name:[^\n]*\n\s+needs: quality/', $release );
	}

	public function test_prereleases_do_not_deploy_to_wordpress_org(): void {
		$release = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release.yml' );
		$this->assertMatchesRegularExpression( '/deploy:\s*\n\s+name:[^\n]*\n\s+if:.*github\.event\.release\.prerelease == false/', $release );
	}
}
