<?php
/**
 * Site Health and scheduled scan integration.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expose assumption findings as native WordPress health signals.
 */
class Site_Health_Controller {

	const CRON_HOOK = 'functionalities_assumption_background_scan';

	/**
	 * Register health, cron, and settings hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		\add_filter( 'site_status_tests', array( __CLASS__, 'register_site_health_test' ) );
		\add_action( self::CRON_HOOK, array( __CLASS__, 'run_background_scan' ) );
		\add_action( 'admin_init', array( __CLASS__, 'reconcile_schedule' ), 5 );
		\add_action( 'admin_init', array( __CLASS__, 'register_settings_fields' ), 20 );
		\add_action( 'update_option_functionalities_assumption_detection', array( __CLASS__, 'reconcile_schedule' ), 10, 2 );
		\add_action( 'add_option_functionalities_assumption_detection', array( __CLASS__, 'reconcile_schedule' ) );
	}

	/**
	 * Register the direct Site Health test.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public static function register_site_health_test( array $tests ): array {
		$tests['direct']['functionalities_assumptions']  = array(
			'label' => \__( 'Functionalities assumption scan', 'functionalities' ),
			'test'  => array( __CLASS__, 'get_site_health_result' ),
		);
		$tests['async']['functionalities_data_exposure'] = array(
			'label'             => \__( 'Functionalities data files are private', 'functionalities' ),
			'test'              => 'functionalities_data_exposure',
			'has_rest'          => true,
			'async_direct_test' => array( __CLASS__, 'get_data_exposure_result' ),
		);
		return $tests;
	}

	/**
	 * Confirm the private data directory is not served over HTTP.
	 *
	 * A harmless canary uses the same file guard as redirects and task data.
	 * The loopback distinguishes actual source exposure from a failed check.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_data_exposure_result(): array {
		$result = array(
			'label'       => \__( 'Functionalities data files are not publicly readable', 'functionalities' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'Functionalities', 'functionalities' ),
				'color' => 'blue',
			),
			'description' => '<p>' . \esc_html__( 'The server refuses public access to the protected storage files used for redirects, 404 activity, and task notes.', 'functionalities' ) . '</p>',
			'actions'     => '',
			'test'        => 'functionalities_data_exposure',
		);

		$probe_url = \Functionalities\Storage\Data_Directory::probe_url();
		if ( \Functionalities\Storage\Data_Directory::get_errors() ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'Functionalities data storage needs attention', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'The protected storage directory could not be prepared or existing data could not be migrated. Resolve the storage error before saving redirects or tasks.', 'functionalities' ) . '</p>';
			return $result;
		}
		if ( '' === $probe_url ) {
			$result['status']      = 'recommended';
			$result['label']       = \__( 'The privacy of Functionalities data files could not be checked', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'The custom storage directory has no configured probe URL. Its public-access check could not be completed.', 'functionalities' ) . '</p>';
			return $result;
		}

		$response = \wp_remote_get(
			$probe_url,
			array(
				'timeout'             => 5,
				'sslverify'           => false,
				'redirection'         => 0,
				'limit_response_size' => 4096,
			)
		);

		if ( \is_wp_error( $response ) ) {
			$result['status']      = 'recommended';
			$result['label']       = \__( 'The privacy of Functionalities data files could not be checked', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'A loopback request to the data folder failed, so this check could not complete. This is usually a loopback restriction rather than a problem with the files themselves.', 'functionalities' ) . '</p>';
			return $result;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		if ( false !== strpos( (string) \wp_remote_retrieve_body( $response ), 'functionalities_private_canary' ) ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'Functionalities storage files are publicly readable', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'The server returned the protected file source instead of refusing access. Correct PHP handling or deny public access to the storage directory before storing private task notes or redirect data.', 'functionalities' ) . '</p>';
		} elseif ( ! in_array( $code, array( 403, 404 ), true ) ) {
			$result['status']      = 'recommended';
			$result['label']       = \__( 'The privacy of Functionalities data files could not be checked', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'The storage probe received an unexpected response. This does not confirm that the files are private; check the server response and run Site Health again.', 'functionalities' ) . '</p>';
		}

		return $result;
	}

	/**
	 * Build a current Site Health result.
	 *
	 * @return array
	 */
	public static function get_site_health_result(): array {
		$options  = (array) \get_option(
			'functionalities_assumption_detection',
			array(
				'enabled'       => false,
				'scan_schedule' => 'daily',
			)
		);
		$last_run = (int) \get_option( 'functionalities_assumptions_last_run', 0 );
		$findings = (array) \get_option( \Functionalities\Features\Assumption_Detection::OPTION_KEY, array() );
		$count    = count( $findings );
		$result   = array(
			'label'       => \__( 'Assumption Detection is current', 'functionalities' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'Functionalities', 'functionalities' ),
				'color' => 'blue',
			),
			'description' => '<p>' . \esc_html__( 'No active technical assumption changes were detected.', 'functionalities' ) . '</p>',
			'actions'     => '',
			'test'        => 'functionalities_assumptions',
		);

		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'assumption-detection' ) ) {
			$result['status']      = 'recommended';
			$result['label']       = \__( 'Assumption Detection is disabled', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'Enable the module to monitor schema, analytics, security, and runtime assumptions.', 'functionalities' ) . '</p>';
			return $result;
		}
		$scan_status = \Functionalities\Features\Assumption_Detection::get_scan_status();
		if ( 'error' === ( $scan_status['state'] ?? '' ) ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'The assumption scan failed', 'functionalities' );
			$result['description'] = '<p>' . \esc_html( $scan_status['error']['message'] ?? \__( 'The latest scan could not inspect the homepage. Previous findings were retained. Run the scan again after resolving the request failure.', 'functionalities' ) ) . '</p>';
			return $result;
		}
		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'The assumption scan is not scheduled', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'WordPress does not have a future background scan event. Save the module settings to reschedule it, then check WP-Cron if it disappears again.', 'functionalities' ) . '</p>';
			return $result;
		}

		$interval = self::get_interval( $options['scan_schedule'] ?? 'daily' );
		if ( ! $last_run || time() - $last_run > ( 2 * $interval ) ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'The assumption scan is stale', 'functionalities' );
			$result['description'] = '<p>' . \esc_html__( 'The scheduled scan has not completed within two expected intervals. This is different from a clean scan.', 'functionalities' ) . '</p>';
			return $result;
		}

		if ( $count > 0 ) {
			$critical_types   = array( 'debug_exposure', 'mixed_content', 'missing_security_headers' );
			$has_critical     = (bool) array_filter(
				$findings,
				static function ( array $finding ) use ( $critical_types ) {
					return 'critical' === ( $finding['severity'] ?? '' ) || in_array( $finding['type'] ?? '', $critical_types, true );
				}
			);
			$result['status'] = $has_critical ? 'critical' : 'recommended';
			$result['label']  = sprintf(
				/* translators: %d: number of findings. */
				_n( '%d assumption change needs review', '%d assumption changes need review', $count, 'functionalities' ),
				$count
			);
			$result['description'] = '<p>' . \esc_html__( 'Review the findings in Functionalities. You can acknowledge, snooze, or ignore each finding.', 'functionalities' ) . '</p>';
		} else {
			$result['description'] = '<p>' . sprintf(
				/* translators: %s: human-readable time difference. */
				\esc_html__( 'The last successful scan completed %s ago and found no active changes.', 'functionalities' ),
				\esc_html( \human_time_diff( $last_run, time() ) )
			) . '</p>';
		}

		return $result;
	}

	/**
	 * Register scan schedule and notification controls in the existing module.
	 *
	 * @return void
	 */
	public static function register_settings_fields(): void {
		\add_settings_field(
			'assumption_scan_schedule',
			\__( 'Background scans', 'functionalities' ),
			array( __CLASS__, 'render_schedule_field' ),
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);
		\add_settings_field(
			'assumption_email_notifications',
			\__( 'Email summaries', 'functionalities' ),
			array( __CLASS__, 'render_notification_field' ),
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);
	}

	/**
	 * Render scan frequency control.
	 *
	 * @return void
	 */
	public static function render_schedule_field(): void {
		$options = (array) \get_option( 'functionalities_assumption_detection', array() );
		$value   = $options['scan_schedule'] ?? 'daily';
		echo '<select name="functionalities_assumption_detection[scan_schedule]">';
		foreach ( array(
			'hourly'     => \__( 'Hourly', 'functionalities' ),
			'twicedaily' => \__( 'Twice daily', 'functionalities' ),
			'daily'      => \__( 'Daily', 'functionalities' ),
			'weekly'     => \__( 'Weekly', 'functionalities' ),
		) as $key => $label ) {
			echo '<option value="' . \esc_attr( $key ) . '" ' . \selected( $value, $key, false ) . '>' . \esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render opt-in email control.
	 *
	 * @return void
	 */
	public static function render_notification_field(): void {
		$options = (array) \get_option( 'functionalities_assumption_detection', array() );
		echo '<label><input type="checkbox" name="functionalities_assumption_detection[email_notifications]" value="1" ' . \checked( ! empty( $options['email_notifications'] ), true, false ) . '> ' . \esc_html__( 'Email the site administrator only when the finding set changes', 'functionalities' ) . '</label>';
	}

	/**
	 * Ensure the selected recurring event is scheduled exactly once.
	 *
	 * @return void
	 */
	public static function reconcile_schedule(): void {
		$options    = (array) \get_option(
			'functionalities_assumption_detection',
			array(
				'enabled'       => false,
				'scan_schedule' => 'daily',
			)
		);
		$recurrence = $options['scan_schedule'] ?? 'daily';
		$event      = \wp_get_scheduled_event( self::CRON_HOOK );

		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'assumption-detection' ) ) {
			if ( $event ) {
				\wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}

		if ( ! $event || $event->schedule !== $recurrence ) {
			\wp_clear_scheduled_hook( self::CRON_HOOK );
			\wp_schedule_event( time() + 300, $recurrence, self::CRON_HOOK );
		}
	}

	/**
	 * Run a scan and send a deduplicated, rate-limited summary when enabled.
	 *
	 * @return void
	 */
	public static function run_background_scan(): void {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'assumption-detection' ) ) {
			return;
		}
		\Functionalities\Core\Module_Registry::boot_module( 'assumption-detection' );
		$findings    = \Functionalities\Features\Assumption_Detection::force_run_detection();
		$scan_status = \Functionalities\Features\Assumption_Detection::get_scan_status();
		if ( 'error' === ( $scan_status['state'] ?? '' ) ) {
			return;
		}
		$signature = array_map(
			static function ( array $finding ) {
				unset( $finding['detected'], $finding['timestamp'] );
				return $finding;
			},
			$findings
		);
		usort(
			$signature,
			static function ( array $a, array $b ) {
				return strcmp( \wp_json_encode( $a ), \wp_json_encode( $b ) );
			}
		);
		$digest  = md5( \wp_json_encode( $signature ) );
		$state   = (array) \get_option( 'functionalities_assumption_scan_summary', array() );
		$options = (array) \get_option( 'functionalities_assumption_detection', array() );
		$now     = time();

		if ( ! empty( $options['email_notifications'] ) && $digest !== ( $state['notified_digest'] ?? '' ) && $now - (int) ( $state['notified_at'] ?? 0 ) >= DAY_IN_SECONDS ) {
			$subject = sprintf(
				/* translators: %d: number of findings. */
				\__( '[Functionalities] Assumption scan found %d item(s)', 'functionalities' ),
				count( $findings )
			);
			$message = \__( 'The finding set changed. Review it in WordPress under Functionalities > Assumption Detection. No content, URLs, or diagnostics are included in this email.', 'functionalities' );
			if ( \wp_mail( \get_option( 'admin_email' ), $subject, $message ) ) {
				$state['notified_at']     = $now;
				$state['notified_digest'] = $digest;
			}
		}
		$state['digest']  = $digest;
		$state['count']   = count( $findings );
		$state['scanned'] = $now;
		\update_option( 'functionalities_assumption_scan_summary', $state, false );
	}

	/**
	 * Return the expected number of seconds between scans.
	 *
	 * @param string $schedule Recurrence key.
	 * @return int
	 */
	private static function get_interval( string $schedule ): int {
		$interval = array(
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => WEEK_IN_SECONDS,
		);
		return $interval[ $schedule ] ?? DAY_IN_SECONDS;
	}
}
