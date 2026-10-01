<?php
/**
 * Login Security - Login protection and customization.
 *
 * @package Functionalities\Features
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login_Security class.
 *
 * Provides login security features including:
 * - Login attempt limiting
 * - XML-RPC disable for authentication
 * - Login page customization
 * - Failed login logging
 */
class Login_Security {

	/**
	 * Failed attempts transient prefix.
	 *
	 * @var string
	 */
	private const ATTEMPTS_PREFIX = 'funct_login_attempts_';

	/**
	 * Lockout transient prefix.
	 *
	 * @var string
	 */
	private const LOCKOUT_PREFIX = 'funct_login_lockout_';

	/**
	 * Failed attempts by username prefix.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const USER_ATTEMPTS_PREFIX = 'funct_login_user_attempts_';

	/**
	 * Username lockout prefix.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const USER_LOCKOUT_PREFIX = 'funct_login_user_lockout_';

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init(): void {
		$opts = self::get_options();

		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'login-security' ) ) {
			return;
		}

		// Limit login attempts.
		if ( ! empty( $opts['limit_login_attempts'] ) ) {
			\add_filter( 'authenticate', array( __CLASS__, 'check_lockout' ), 30, 3 );
			\add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ) );
			\add_action( 'wp_login', array( __CLASS__, 'clear_attempts' ), 10, 2 );
			\add_filter( 'login_errors', array( __CLASS__, 'custom_login_error' ) );
		}

		if ( \is_admin() ) {
			\add_action( 'wp_ajax_functionalities_login_unlock', array( __CLASS__, 'ajax_unlock' ) );
		}

		// Disable XML-RPC authentication.
		if ( ! empty( $opts['disable_xmlrpc_auth'] ) ) {
			\add_filter( 'xmlrpc_enabled', '__return_false' );
			\add_filter( 'xmlrpc_methods', array( __CLASS__, 'disable_xmlrpc_methods' ) );
		}

		// Disable application passwords.
		if ( ! empty( $opts['disable_application_passwords'] ) ) {
			\add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}

		// Hide login errors.
		if ( ! empty( $opts['hide_login_errors'] ) ) {
			\add_filter( 'login_errors', array( __CLASS__, 'generic_login_error' ) );
		}

		// Custom login logo.
		if ( ! empty( $opts['custom_logo_url'] ) ) {
			\add_action( 'login_enqueue_scripts', array( __CLASS__, 'custom_login_logo' ) );
			\add_filter( 'login_headerurl', array( __CLASS__, 'login_logo_url' ) );
			\add_filter( 'login_headertext', array( __CLASS__, 'login_logo_title' ) );
		}

		// Custom login background.
		if ( ! empty( $opts['custom_background_color'] ) || ! empty( $opts['custom_form_background'] ) ) {
			\add_action( 'login_enqueue_scripts', array( __CLASS__, 'custom_login_styles' ) );
		}
	}

	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Get module options with defaults.
	 *
	 * @return array Options.
	 */
	public static function get_options(): array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults      = array(
			'enabled'                       => false,
			'limit_login_attempts'          => true,
			'max_attempts'                  => 5,
			'lockout_duration'              => 15, // minutes
			'disable_xmlrpc_auth'           => true,
			'disable_application_passwords' => false,
			'hide_login_errors'             => true,
			'trust_proxy_headers'           => false,
			'trusted_proxy_ips'             => '',
			'lock_usernames'                => true,
			'allowlist_ips'                 => '',
			'custom_logo_url'               => '',
			'custom_background_color'       => '',
			'custom_form_background'        => '',
		);
		$opts          = (array) \get_option( 'functionalities_login_security', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Get client IP address.
	 *
	 * Defaults to REMOTE_ADDR (the actual TCP peer). Forwarding headers are
	 * consulted only when the peer belongs to the configured trusted proxies.
	 * Walk X-Forwarded-For from right to left, stopping at the first untrusted
	 * hop so attacker-prepended addresses cannot impersonate an allowlisted IP.
	 * The ingress proxy must append or overwrite this header. Client-IP is
	 * deliberately ignored because ordinary proxies forward it unchanged.
	 *
	 * @since 0.3.0
	 * @since 1.4.6 Proxy headers are now opt-in via `trust_proxy_headers`.
	 *
	 * @return string IP address (validated, or empty string on failure).
	 */
	private static function get_client_ip(): string {
		$opts = self::get_options();
		$peer = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		if ( false === filter_var( $peer, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$peer = inet_ntop( inet_pton( $peer ) );
		if ( empty( $opts['trust_proxy_headers'] ) || ! self::is_trusted_proxy( $peer ) || empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $peer;
		}

		$forwarded = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$client    = $peer;
		foreach ( array_reverse( explode( ',', $forwarded ) ) as $hop ) {
			if ( ! self::is_trusted_proxy( $client ) ) {
				break;
			}
			$hop = trim( $hop );
			if ( false === filter_var( $hop, FILTER_VALIDATE_IP ) ) {
				return $peer;
			}
			$client = inet_ntop( inet_pton( $hop ) );
		}
		return $client;
	}

	/**
	 * Match an address against explicitly configured proxy IPs and CIDRs.
	 *
	 * @param string $ip Address to check.
	 * @return bool
	 */
	private static function is_trusted_proxy( string $ip ): bool {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}
		$opts = self::get_options();
		foreach ( preg_split( '/[\r\n,]+/', (string) ( $opts['trusted_proxy_ips'] ?? '' ) ) as $entry ) {
			$parts   = explode( '/', trim( $entry ) );
			$network = false !== filter_var( $parts[0], FILTER_VALIDATE_IP ) ? inet_pton( $parts[0] ) : false;
			if ( count( $parts ) > 2 || false === $network || strlen( $network ) !== strlen( $packed ) ) {
				continue;
			}
			if ( ! isset( $parts[1] ) ) {
				if ( $network === $packed ) {
					return true;
				}
				continue;
			}
			$bits = (int) $parts[1];
			if ( ! ctype_digit( $parts[1] ) || $bits < 1 || $bits > strlen( $packed ) * 8 ) {
				continue;
			}
			$bytes = intdiv( $bits, 8 );
			$tail  = $bits % 8;
			if ( $bytes > 0 && substr( $packed, 0, $bytes ) !== substr( $network, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $tail || ( ord( $packed[ $bytes ] ) & ( 0xff << ( 8 - $tail ) ) ) === ( ord( $network[ $bytes ] ) & ( 0xff << ( 8 - $tail ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether an IP is exempt from lockouts.
	 *
	 * Behind a CDN or reverse proxy with proxy headers switched off, every
	 * visitor shares one REMOTE_ADDR, so a single attacker can lock out the whole
	 * site. An allowlist gives the site owner a way back in.
	 *
	 * @since 1.6.0
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public static function is_allowlisted( string $ip ): bool {
		if ( '' === $ip ) {
			return false;
		}

		$opts = self::get_options();
		$raw  = (string) ( $opts['allowlist_ips'] ?? '' );
		if ( '' === trim( $raw ) ) {
			return false;
		}

		foreach ( preg_split( '/[\r\n,]+/', $raw ) as $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( $entry === $ip ) {
				return true;
			}
			// Prefix wildcard, for example 203.0.113.*
			if ( '*' === substr( $entry, -1 ) && 0 === strpos( $ip, rtrim( $entry, '*' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the request is locked out by IP or by username.
	 *
	 * @param mixed  $user     User object or error.
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return mixed User or WP_Error.
	 */
	public static function check_lockout( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		$ip = self::get_client_ip();
		if ( self::is_allowlisted( $ip ) ) {
			return $user;
		}

		$opts   = self::get_options();
		$locked = (bool) \get_transient( self::LOCKOUT_PREFIX . md5( $ip ) );

		if ( ! $locked && ! empty( $opts['lock_usernames'] ) ) {
			$locked = (bool) \get_transient( self::USER_LOCKOUT_PREFIX . md5( strtolower( (string) $username ) ) );
		}

		if ( $locked ) {
			return new \WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: %d: lockout duration in minutes */
					\__( 'Too many failed login attempts. Please try again in %d minutes.', 'functionalities' ),
					$opts['lockout_duration']
				)
			);
		}

		return $user;
	}

	/**
	 * Record failed login attempt.
	 *
	 * @param string $username Username that failed.
	 * @return void
	 */
	public static function record_failed_attempt( $username ): void {
		$opts = self::get_options();
		$ip   = self::get_client_ip();

		if ( self::is_allowlisted( $ip ) ) {
			return;
		}

		$attempts_key     = self::ATTEMPTS_PREFIX . md5( $ip );
		$lockout_key      = self::LOCKOUT_PREFIX . md5( $ip );
		$max_attempts     = isset( $opts['max_attempts'] ) ? (int) $opts['max_attempts'] : 5;
		$lockout_duration = isset( $opts['lockout_duration'] ) ? (int) $opts['lockout_duration'] : 15;

		// Get current attempts.
		$attempts = (int) \get_transient( $attempts_key );
		++$attempts;

		// Store attempts for 1 hour.
		\set_transient( $attempts_key, $attempts, HOUR_IN_SECONDS );

		// Check if should lockout.
		if ( $attempts >= $max_attempts ) {
			\set_transient( $lockout_key, true, $lockout_duration * MINUTE_IN_SECONDS );

			// Log the lockout.
			self::log_lockout( $ip, $username, $attempts );

			// Clear attempts after lockout.
			\delete_transient( $attempts_key );
		}

		// Also throttle per username, so a distributed attempt against one
		// account is caught even when each request comes from a fresh address.
		if ( empty( $opts['lock_usernames'] ) || '' === (string) $username ) {
			return;
		}

		$user_hash         = md5( strtolower( (string) $username ) );
		$user_attempts_key = self::USER_ATTEMPTS_PREFIX . $user_hash;
		$user_attempts     = (int) \get_transient( $user_attempts_key ) + 1;

		\set_transient( $user_attempts_key, $user_attempts, HOUR_IN_SECONDS );

		if ( $user_attempts >= max( $max_attempts, 1 ) * 2 ) {
			\set_transient( self::USER_LOCKOUT_PREFIX . $user_hash, true, $lockout_duration * MINUTE_IN_SECONDS );
			self::log_lockout( $ip, $username, $user_attempts );
			\delete_transient( $user_attempts_key );
		}
	}

	/**
	 * Clear attempts on successful login.
	 *
	 * @param string   $username Username.
	 * @param \WP_User $user     User object.
	 * @return void
	 */
	public static function clear_attempts( $username, $user ): void {
		\delete_transient( self::ATTEMPTS_PREFIX . md5( self::get_client_ip() ) );

		if ( '' !== (string) $username ) {
			$user_hash = md5( strtolower( (string) $username ) );
			\delete_transient( self::USER_ATTEMPTS_PREFIX . $user_hash );
			\delete_transient( self::USER_LOCKOUT_PREFIX . $user_hash );
		}
	}

	/**
	 * Custom login error showing remaining attempts.
	 *
	 * @param string $error Original error.
	 * @return string Modified error.
	 */
	public static function custom_login_error( $error ): string {
		$opts         = self::get_options();
		$ip           = self::get_client_ip();
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		$attempts     = (int) \get_transient( $attempts_key );
		$max_attempts = isset( $opts['max_attempts'] ) ? (int) $opts['max_attempts'] : 5;

		$remaining = $max_attempts - $attempts;
		if ( $remaining > 0 && $remaining < $max_attempts && ! empty( $opts['hide_login_errors'] ) ) {
			return sprintf(
				/* translators: %d: remaining attempts */
				\__( 'Login failed. %d attempt(s) remaining before lockout.', 'functionalities' ),
				$remaining
			);
		}

		return $error;
	}

	/**
	 * Generic login error message.
	 *
	 * @param string $error Original error.
	 * @return string Generic error.
	 */
	public static function generic_login_error( $error ): string {
		// Check if it's a lockout message.
		if ( strpos( $error, 'Too many failed login attempts' ) !== false ) {
			return $error;
		}

		// Check for remaining attempts message.
		if ( strpos( $error, 'attempt(s) remaining' ) !== false ) {
			return $error;
		}

		return \__( 'Invalid username or password.', 'functionalities' );
	}

	/**
	 * Log lockout event.
	 *
	 * @param string $ip       IP address.
	 * @param string $username Username attempted.
	 * @param int    $attempts Number of attempts.
	 * @return void
	 */
	private static function log_lockout( $ip, $username, $attempts ): void {
		$logs = \get_option( 'functionalities_login_lockouts', array() );

		$logs[] = array(
			'ip'       => $ip,
			'username' => $username,
			'attempts' => $attempts,
			'time'     => current_time( 'mysql' ),
		);

		// Keep only last 100 entries.
		$logs = array_slice( $logs, -100 );

		\update_option( 'functionalities_login_lockouts', $logs, false );
	}

	/**
	 * Get lockout log.
	 *
	 * @param int $limit Number of entries.
	 * @return array Log entries.
	 */
	public static function get_lockout_log( int $limit = 20 ): array {
		$logs = \get_option( 'functionalities_login_lockouts', array() );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear lockout log.
	 *
	 * @return bool True on success.
	 */
	public static function clear_lockout_log(): bool {
		return \delete_option( 'functionalities_login_lockouts' );
	}

	/**
	 * Disable XML-RPC authentication methods.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array Filtered methods.
	 */
	public static function disable_xmlrpc_methods( $methods ): array {
		// Remove authentication-related methods.
		unset( $methods['wp.getUsersBlogs'] );
		unset( $methods['wp.getCategories'] );
		unset( $methods['wp.getTags'] );
		unset( $methods['wp.getCommentCount'] );
		unset( $methods['wp.getPostFormats'] );
		unset( $methods['wp.getPostTypes'] );
		unset( $methods['wp.getPostType'] );
		unset( $methods['wp.getRevisions'] );
		unset( $methods['wp.restoreRevision'] );

		return $methods;
	}

	/**
	 * Output custom login logo CSS.
	 *
	 * @return void
	 */
	public static function custom_login_logo(): void {
		$opts     = self::get_options();
		$logo_url = esc_url( $opts['custom_logo_url'] );
		if ( empty( $logo_url ) ) {
			return;
		}
		?>
		<style type="text/css">
			#login h1 a, .login h1 a {
				background-image: url(<?php echo esc_url( $logo_url ); ?>);
				background-size: contain;
				background-repeat: no-repeat;
				background-position: center;
				width: 100%;
				height: 80px;
			}
		</style>
		<?php
	}

	/**
	 * Custom login styles.
	 *
	 * @return void
	 */
	public static function custom_login_styles(): void {
		$opts     = self::get_options();
		$bg_color = sanitize_hex_color( $opts['custom_background_color'] ?? '' );
		$form_bg  = sanitize_hex_color( $opts['custom_form_background'] ?? '' );

		if ( empty( $bg_color ) && empty( $form_bg ) ) {
			return;
		}
		?>
		<style type="text/css">
			<?php if ( ! empty( $bg_color ) ) : ?>
			body.login {
				background-color: <?php echo esc_attr( $bg_color ); ?>;
			}
			<?php endif; ?>
			<?php if ( ! empty( $form_bg ) ) : ?>
			.login form {
				background-color: <?php echo esc_attr( $form_bg ); ?>;
			}
			<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * Login logo URL.
	 *
	 * @return string Home URL.
	 */
	public static function login_logo_url(): string {
		return \home_url();
	}

	/**
	 * Login logo title.
	 *
	 * @return string Site name.
	 */
	public static function login_logo_title(): string {
		return \get_bloginfo( 'name' );
	}

	/**
	 * Check if an IP is currently locked out.
	 *
	 * @param string $ip IP address (optional, uses current if not provided).
	 * @return bool True if locked out.
	 */
	public static function is_locked_out( string $ip = '' ): bool {
		if ( empty( $ip ) ) {
			$ip = self::get_client_ip();
		}
		$lockout_key = self::LOCKOUT_PREFIX . md5( $ip );
		return (bool) \get_transient( $lockout_key );
	}

	/**
	 * Get current attempts for an IP.
	 *
	 * @param string $ip IP address (optional).
	 * @return int Current attempts.
	 */
	public static function get_attempts( string $ip = '' ): int {
		if ( empty( $ip ) ) {
			$ip = self::get_client_ip();
		}
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		return (int) \get_transient( $attempts_key );
	}

	/**
	 * Manually unlock an IP.
	 *
	 * @param string $ip IP address.
	 * @return bool True on success.
	 */
	public static function unlock_ip( string $ip ): bool {
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		$lockout_key  = self::LOCKOUT_PREFIX . md5( $ip );

		\delete_transient( $attempts_key );
		\delete_transient( $lockout_key );

		return true;
	}

	/**
	 * Manually unlock a username.
	 *
	 * @since 1.6.0
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	public static function unlock_username( string $username ): bool {
		if ( '' === $username ) {
			return false;
		}

		$hash = md5( strtolower( $username ) );
		\delete_transient( self::USER_ATTEMPTS_PREFIX . $hash );
		\delete_transient( self::USER_LOCKOUT_PREFIX . $hash );

		return true;
	}

	/**
	 * Handle the unlock button on the lockout log.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public static function ajax_unlock(): void {
		\check_ajax_referer( 'functionalities_login_unlock', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Insufficient permissions.', 'functionalities' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer above.
		$ip = isset( $_POST['ip'] ) ? \sanitize_text_field( \wp_unslash( $_POST['ip'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer above.
		$username = isset( $_POST['username'] ) ? \sanitize_text_field( \wp_unslash( $_POST['username'] ) ) : '';

		if ( '' !== $ip ) {
			self::unlock_ip( $ip );
		}
		if ( '' !== $username ) {
			self::unlock_username( $username );
		}

		\wp_send_json_success( array( 'message' => \__( 'Unlocked.', 'functionalities' ) ) );
	}

	/**
	 * Report whether every recent lockout came from a single address.
	 *
	 * That pattern normally means the site sits behind a proxy or CDN and every
	 * visitor shares one REMOTE_ADDR, so IP lockouts hit everyone at once.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public static function lockouts_share_one_ip(): bool {
		$logs = self::get_lockout_log( 10 );
		if ( count( $logs ) < 5 ) {
			return false;
		}

		$ips = array_unique( array_filter( \wp_list_pluck( $logs, 'ip' ) ) );

		return 1 === count( $ips );
	}
}
