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
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Limit login attempts.
		if ( ! empty( $opts['limit_login_attempts'] ) ) {
			\add_filter( 'authenticate', array( __CLASS__, 'check_lockout' ), 30, 3 );
			\add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ) );
			\add_action( 'wp_login', array( __CLASS__, 'clear_attempts' ), 10, 2 );
			\add_filter( 'login_errors', array( __CLASS__, 'custom_login_error' ) );
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
	 * Get module options with defaults.
	 *
	 * @return array Options.
	 */
	public static function get_options() : array {
		$defaults = array(
			'enabled'                      => false,
			'limit_login_attempts'         => true,
			'max_attempts'                 => 5,
			'lockout_duration'             => 15, // minutes
			'disable_xmlrpc_auth'          => true,
			'disable_application_passwords' => false,
			'hide_login_errors'            => true,
			'custom_logo_url'              => '',
			'custom_background_color'      => '',
			'custom_form_background'       => '',
		);
		$opts = (array) \get_option( 'functionalities_login_security', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() : string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field( trim( $ip ) );
	}

	/**
	 * Check if IP is locked out.
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
		$lockout_key = self::LOCKOUT_PREFIX . md5( $ip );

		if ( \get_transient( $lockout_key ) ) {
			$opts = self::get_options();
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
	public static function record_failed_attempt( $username ) : void {
		$opts = self::get_options();
		$ip = self::get_client_ip();
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		$lockout_key = self::LOCKOUT_PREFIX . md5( $ip );

		// Get current attempts.
		$attempts = (int) \get_transient( $attempts_key );
		$attempts++;

		// Store attempts for 1 hour.
		\set_transient( $attempts_key, $attempts, HOUR_IN_SECONDS );

		// Check if should lockout.
		$max_attempts = isset( $opts['max_attempts'] ) ? (int) $opts['max_attempts'] : 5;
		if ( $attempts >= $max_attempts ) {
			$lockout_duration = isset( $opts['lockout_duration'] ) ? (int) $opts['lockout_duration'] : 15;
			\set_transient( $lockout_key, true, $lockout_duration * MINUTE_IN_SECONDS );

			// Log the lockout.
			self::log_lockout( $ip, $username, $attempts );

			// Clear attempts after lockout.
			\delete_transient( $attempts_key );
		}
	}

	/**
	 * Clear attempts on successful login.
	 *
	 * @param string   $username Username.
	 * @param \WP_User $user     User object.
	 * @return void
	 */
	public static function clear_attempts( $username, $user ) : void {
		$ip = self::get_client_ip();
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		\delete_transient( $attempts_key );
	}

	/**
	 * Custom login error showing remaining attempts.
	 *
	 * @param string $error Original error.
	 * @return string Modified error.
	 */
	public static function custom_login_error( $error ) : string {
		$opts = self::get_options();
		$ip = self::get_client_ip();
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		$attempts = (int) \get_transient( $attempts_key );
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
	public static function generic_login_error( $error ) : string {
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
	private static function log_lockout( $ip, $username, $attempts ) : void {
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
	public static function get_lockout_log( int $limit = 20 ) : array {
		$logs = \get_option( 'functionalities_login_lockouts', array() );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear lockout log.
	 *
	 * @return bool True on success.
	 */
	public static function clear_lockout_log() : bool {
		return \delete_option( 'functionalities_login_lockouts' );
	}

	/**
	 * Disable XML-RPC authentication methods.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array Filtered methods.
	 */
	public static function disable_xmlrpc_methods( $methods ) : array {
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
	public static function custom_login_logo() : void {
		$opts = self::get_options();
		$logo_url = esc_url( $opts['custom_logo_url'] );
		if ( empty( $logo_url ) ) {
			return;
		}
		?>
		<style type="text/css">
			#login h1 a, .login h1 a {
				background-image: url(<?php echo $logo_url; ?>);
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
	public static function custom_login_styles() : void {
		$opts = self::get_options();
		$bg_color = sanitize_hex_color( $opts['custom_background_color'] ?? '' );
		$form_bg = sanitize_hex_color( $opts['custom_form_background'] ?? '' );

		if ( empty( $bg_color ) && empty( $form_bg ) ) {
			return;
		}
		?>
		<style type="text/css">
			<?php if ( ! empty( $bg_color ) ) : ?>
			body.login {
				background-color: <?php echo $bg_color; ?>;
			}
			<?php endif; ?>
			<?php if ( ! empty( $form_bg ) ) : ?>
			.login form {
				background-color: <?php echo $form_bg; ?>;
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
	public static function login_logo_url() : string {
		return \home_url();
	}

	/**
	 * Login logo title.
	 *
	 * @return string Site name.
	 */
	public static function login_logo_title() : string {
		return \get_bloginfo( 'name' );
	}

	/**
	 * Check if an IP is currently locked out.
	 *
	 * @param string $ip IP address (optional, uses current if not provided).
	 * @return bool True if locked out.
	 */
	public static function is_locked_out( string $ip = '' ) : bool {
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
	public static function get_attempts( string $ip = '' ) : int {
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
	public static function unlock_ip( string $ip ) : bool {
		$attempts_key = self::ATTEMPTS_PREFIX . md5( $ip );
		$lockout_key = self::LOCKOUT_PREFIX . md5( $ip );

		\delete_transient( $attempts_key );
		\delete_transient( $lockout_key );

		return true;
	}
}
