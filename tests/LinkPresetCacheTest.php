<?php
/**
 * Exception preset caching tests.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Features\Link_Management;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-link-management.php';

/**
 * The remote exception preset must be fetched once per cache window, not once
 * per page load, and the cache must clear when a post or page is edited.
 */
final class LinkPresetCacheTest extends TestCase {

	/**
	 * Reset caches and the fetch counter.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_options']    = array();
		$GLOBALS['functionalities_test_transients'] = array();
		$GLOBALS['functionalities_http_calls']      = 0;

		foreach ( array( 'options', 'cached_exceptions' ) as $name ) {
			$property = new ReflectionProperty( Link_Management::class, $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( null, null );
		}
	}

	/**
	 * Reset only the request-local cache, leaving the transient in place.
	 *
	 * @return void
	 */
	private function next_request(): void {
		$property = new ReflectionProperty( Link_Management::class, 'cached_exceptions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	/**
	 * Point the module at a remote preset.
	 *
	 * @return void
	 */
	private function use_remote_preset(): void {
		$GLOBALS['functionalities_test_options']['functionalities_link_management'] = array(
			'enabled'         => true,
			'json_preset_url' => 'https://cdn.example.test/exceptions.json',
		);
	}

	/**
	 * Repeated requests hit the network once, not once per page load.
	 *
	 * @return void
	 */
	public function test_remote_preset_is_fetched_once_per_window(): void {
		$this->use_remote_preset();

		$this->assertSame( array( 'https://partner.example' ), Link_Management::get_preset_exceptions() );
		$this->assertSame( 1, $GLOBALS['functionalities_http_calls'] );

		$this->next_request();
		$this->assertSame( array( 'https://partner.example' ), Link_Management::get_preset_exceptions() );
		$this->next_request();
		Link_Management::get_preset_exceptions();

		$this->assertSame( 1, $GLOBALS['functionalities_http_calls'], 'The preset must be served from cache.' );
	}

	/**
	 * Editing a post drops the cache so a changed list takes effect at once.
	 *
	 * @return void
	 */
	public function test_editing_a_post_clears_the_cache(): void {
		$this->use_remote_preset();
		Link_Management::get_preset_exceptions();
		$this->assertSame( 1, $GLOBALS['functionalities_http_calls'] );

		Link_Management::flush_preset_cache_on_save( 41 );

		$this->assertArrayNotHasKey( Link_Management::PRESET_TRANSIENT, $GLOBALS['functionalities_test_transients'] );

		Link_Management::get_preset_exceptions();
		$this->assertSame( 2, $GLOBALS['functionalities_http_calls'] );
	}

	/**
	 * Autosaves and revisions leave the cache alone.
	 *
	 * @return void
	 */
	public function test_autosaves_do_not_clear_the_cache(): void {
		$this->use_remote_preset();
		Link_Management::get_preset_exceptions();

		$GLOBALS['functionalities_test_autosave'] = true;
		Link_Management::flush_preset_cache_on_save( 41 );
		$GLOBALS['functionalities_test_autosave'] = false;

		$this->assertArrayHasKey( Link_Management::PRESET_TRANSIENT, $GLOBALS['functionalities_test_transients'] );
	}

	/**
	 * A failed fetch keeps serving the last good list.
	 *
	 * @return void
	 */
	public function test_a_failed_fetch_reuses_the_last_good_list(): void {
		$this->use_remote_preset();
		Link_Management::get_preset_exceptions();

		Link_Management::flush_preset_cache();
		$GLOBALS['functionalities_http_fail'] = true;

		$this->assertSame( array( 'https://partner.example' ), Link_Management::get_preset_exceptions() );

		$GLOBALS['functionalities_http_fail'] = false;
	}
}
