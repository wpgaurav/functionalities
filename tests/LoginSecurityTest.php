<?php
/**
 * Login Security lockout tests.
 *
 * @package FunctionalitiesTests
 */

use Functionalities\Features\Login_Security;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-login-security.php';

/**
 * Cover the allowlist and the username throttle.
 */
final class LoginSecurityTest extends TestCase {

	/**
	 * Reset options and transients.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['functionalities_test_options']    = array();
		$GLOBALS['functionalities_test_transients'] = array();
		$_SERVER['REMOTE_ADDR']                     = '203.0.113.7';

		$property = new ReflectionProperty( Login_Security::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	/**
	 * Configure the module.
	 *
	 * @param array $overrides Option overrides.
	 * @return void
	 */
	private function configure( array $overrides = array() ): void {
		$GLOBALS['functionalities_test_options']['functionalities_login_security'] = array_merge(
			array(
				'enabled'              => true,
				'limit_login_attempts' => true,
				'max_attempts'         => 3,
				'lockout_duration'     => 15,
				'lock_usernames'       => true,
				'allowlist_ips'        => '',
			),
			$overrides
		);

		$property = new ReflectionProperty( Login_Security::class, 'options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	/**
	 * An allowlisted address is never locked out.
	 *
	 * Behind a CDN every visitor can share one address, so a single attacker
	 * would otherwise lock out the whole site including its owner.
	 *
	 * @return void
	 */
	public function test_allowlisted_addresses_are_never_locked(): void {
		$this->configure( array( 'allowlist_ips' => "198.51.100.1\n203.0.113.*" ) );

		$this->assertTrue( Login_Security::is_allowlisted( '203.0.113.7' ) );
		$this->assertTrue( Login_Security::is_allowlisted( '198.51.100.1' ) );
		$this->assertFalse( Login_Security::is_allowlisted( '192.0.2.5' ) );

		for ( $i = 0; $i < 10; $i++ ) {
			Login_Security::record_failed_attempt( 'admin' );
		}

		$this->assertFalse( Login_Security::is_locked_out( '203.0.113.7' ) );
	}

	/**
	 * Reaching the attempt limit locks the address.
	 *
	 * @return void
	 */
	public function test_repeated_failures_lock_the_address(): void {
		$this->configure();

		Login_Security::record_failed_attempt( 'admin' );
		$this->assertFalse( Login_Security::is_locked_out( '203.0.113.7' ) );

		Login_Security::record_failed_attempt( 'admin' );
		Login_Security::record_failed_attempt( 'admin' );

		$this->assertTrue( Login_Security::is_locked_out( '203.0.113.7' ) );
	}

	/**
	 * A successful login clears both counters.
	 *
	 * @return void
	 */
	public function test_successful_login_clears_the_username_lock(): void {
		$this->configure();

		for ( $i = 0; $i < 6; $i++ ) {
			Login_Security::record_failed_attempt( 'editor' );
		}

		$locked = Login_Security::check_lockout( null, 'editor', 'secret' );
		$this->assertInstanceOf( WP_Error::class, $locked );

		Login_Security::unlock_ip( '203.0.113.7' );
		Login_Security::clear_attempts( 'editor', null );

		$this->assertNull( Login_Security::check_lockout( null, 'editor', 'secret' ) );
	}

	/**
	 * Unlocking a username removes its throttle.
	 *
	 * @return void
	 */
	public function test_unlock_username_clears_the_throttle(): void {
		$this->configure();

		for ( $i = 0; $i < 6; $i++ ) {
			Login_Security::record_failed_attempt( 'editor' );
		}

		Login_Security::unlock_ip( '203.0.113.7' );
		$this->assertInstanceOf( WP_Error::class, Login_Security::check_lockout( null, 'editor', 'x' ) );

		Login_Security::unlock_username( 'editor' );
		$this->assertNull( Login_Security::check_lockout( null, 'editor', 'x' ) );
	}
}
