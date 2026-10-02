<?php
/**
 * Bounded, private operational activity history.
 *
 * @package Functionalities\Features
 */
namespace Functionalities\Features;

use Functionalities\Storage\Atomic_JSON_Store;
use Functionalities\Storage\Data_Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Record deliberate site changes without storing content or credentials. */
class Site_Activity {
	const CRON_HOOK = 'functionalities_activity_prune';
	const LIMIT     = 1000;
	const RETENTION = 30 * DAY_IN_SECONDS;
	/**
	 * Last storage error for the admin workspace.
	 *
	 * @var string
	 */
	private static $storage_error = '';

	/**
	 * Successful item results awaiting the upgrader completion hook.
	 *
	 * @var array
	 */
	private static $pending_updates = array();

	/** Attach event hooks only while enabled. */
	public static function init(): void {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'site-activity' ) ) {
			return;
		}
		\add_action( 'updated_option', array( __CLASS__, 'settings_changed' ), 10, 3 );
		\add_action( 'added_option', array( __CLASS__, 'settings_added' ), 10, 2 );
		\add_action( 'activated_plugin', array( __CLASS__, 'plugin_activated' ) );
		\add_action( 'deactivated_plugin', array( __CLASS__, 'plugin_deactivated' ) );
		\add_action( 'switch_theme', array( __CLASS__, 'theme_switched' ), 10, 2 );
		\add_filter( 'upgrader_install_package_result', array( __CLASS__, 'upgraded' ), PHP_INT_MAX, 2 );
		\add_action( 'upgrader_process_complete', array( __CLASS__, 'upgrade_complete' ), 10, 2 );
		\add_action( 'automatic_updates_complete', array( __CLASS__, 'automatic_updates_complete' ) );
		\add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
		\add_action( 'admin_init', array( __CLASS__, 'sync_schedule' ) );
		\add_action( self::CRON_HOOK, array( __CLASS__, 'prune' ) );
	}

	/** Synchronize the cleanup event after configuration changes. */
	public static function sync_schedule(): void {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'site-activity' ) ) {
			\wp_clear_scheduled_hook( self::CRON_HOOK );
		} elseif ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			\wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Remove expired entries and enforce a hard memory/disk bound. */
	public static function retained( array $entries ): array {
		$cutoff  = time() - self::RETENTION;
		$entries = array_filter(
			$entries,
			static function ( $row ) use ( $cutoff ) {
				return is_array( $row ) && (int) ( $row['time'] ?? 0 ) >= $cutoff;
			}
		);
		return array_values( array_slice( $entries, -self::LIMIT ) );
	}

	/** Append only known event shapes. Logging failure never aborts the operation. */
	public static function record( string $event, string $target, array $details = array() ): bool {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'site-activity' ) || ! in_array( $event, array( 'settings_changed', 'plugin_activated', 'plugin_deactivated', 'theme_switched', 'plugin_updated', 'theme_updated', 'status_changed' ), true ) ) {
			return false;
		}
		$path = Data_Directory::file( 'site-activity.json' );
		if ( '' === $path ) {
			self::$storage_error = 'storage_unavailable';
			return false;
		}
		$clean = array();
		if ( 'settings_changed' === $event ) {
			$clean['fields'] = array_slice( array_values( array_unique( array_map( 'sanitize_key', (array) ( $details['fields'] ?? array() ) ) ) ), 0, 50 );
		} elseif ( 'status_changed' === $event ) {
			$clean = array(
				'from' => \sanitize_key( $details['from'] ?? '' ),
				'to'   => \sanitize_key( $details['to'] ?? '' ),
			);
		}
		$row                 = array(
			'time'    => time(),
			'actor'   => \get_current_user_id(),
			'event'   => $event,
			'target'  => substr( \sanitize_text_field( $target ), 0, 180 ),
			'details' => $clean,
		);
		$result              = Atomic_JSON_Store::update(
			$path,
			static function ( $data ) use ( $row ) {
				$entries   = (array) ( $data['entries'] ?? array() );
				$entries[] = $row;
				return array( 'entries' => self::retained( $entries ) );
			}
		);
		self::$storage_error = $result['error'];
		return $result['success'];
	}

	/** Read a bounded history; the caller controls presentation permissions. */
	public static function entries(): array {
		$path = Data_Directory::file( 'site-activity.json' );
		if ( '' === $path ) {
			self::$storage_error = 'storage_unavailable';
			return array();
		}
		$result              = Atomic_JSON_Store::read( $path );
		self::$storage_error = $result['error'];
		return self::retained( (array) ( $result['data']['entries'] ?? array() ) );
	}

	/** Surface failed writes/reads in the workspace. */
	public static function storage_error(): string {
		return self::$storage_error;
	}

	/** Clear explicitly; protected by the admin controller's capability and nonce. */
	public static function clear(): bool {
		$path = Data_Directory::file( 'site-activity.json' );
		if ( '' === $path ) {
			return false;
		}
		$result              = Atomic_JSON_Store::write( $path, array( 'entries' => array() ) );
		self::$storage_error = $result['error'];
		return $result['success'];
	}

	/** Retention cleanup, also callable by the event scheduler. */
	public static function prune(): void {
		$path = Data_Directory::file( 'site-activity.json' );
		if ( '' !== $path ) {
			Atomic_JSON_Store::update(
				$path,
				static function ( $data ) {
					return array( 'entries' => self::retained( (array) ( $data['entries'] ?? array() ) ) );
				}
			);
		}
	}

	/** Compare field names only, never serialize values into a log entry. */
	public static function settings_changed( string $option, $old, $new ): void {
		foreach ( \Functionalities\Core\Module_Registry::get_definitions() as $definition ) {
			if ( $definition['option'] !== $option ) {
				continue;
			}
			$fields = array();
			foreach ( array_unique( array_merge( array_keys( (array) $old ), array_keys( (array) $new ) ) ) as $key ) {
				if ( ( ( (array) $old )[ $key ] ?? null ) !== ( ( (array) $new )[ $key ] ?? null ) ) {
					$fields[] = (string) $key;
				}
			}
			if ( $fields ) {
				self::record( 'settings_changed', $option, array( 'fields' => $fields ) );
			}
			return;
		}
	}

	/** Capture first-time settings as well as updates. */
	public static function settings_added( string $option, $value ): void {
		self::settings_changed( $option, array(), $value );
	}

	public static function plugin_activated( string $plugin ): void {
		self::record( 'plugin_activated', $plugin );
	}
	public static function plugin_deactivated( string $plugin ): void {
		self::record( 'plugin_deactivated', $plugin );
	}
	public static function theme_switched( string $name, $theme ): void {
		self::record( 'theme_switched', $theme->get_stylesheet() );
	}

	/** Capture final per-item install results; bulk calls omit action/type at this hook. */
	public static function upgraded( $response, array $extra ) {
		foreach ( array( 'plugin', 'theme' ) as $type ) {
			$target = $extra[ $type ] ?? '';
			if ( is_string( $target ) && '' !== $target ) {
				self::$pending_updates[ $type ][ $target ] = ! \is_wp_error( $response ) && ! empty( $response );
			}
		}
		return $response;
	}

	/** Manual single/bulk updates commit only their successful item results. */
	public static function upgrade_complete( $upgrader, array $extra ): void {
		$type = $extra['type'] ?? '';
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return;
		}
		$targets   = (array) ( $extra[ $type . 's' ] ?? array( $extra[ $type ] ?? '' ) );
		$automatic = isset( $upgrader->skin ) && is_object( $upgrader->skin ) && 'Automatic_Upgrader_Skin' === get_class( $upgrader->skin );
		foreach ( $targets as $target ) {
			if ( ! is_string( $target ) ) {
				continue;
			}
			$success = self::$pending_updates[ $type ][ $target ] ?? false;
			unset( self::$pending_updates[ $type ][ $target ] );
			if ( $success && ! $automatic && 'update' === ( $extra['action'] ?? '' ) ) {
				self::record( $type . '_updated', $target );
			}
		}
	}

	/** Automatic outcomes are final only after core's fatal-error and rollback checks. */
	public static function automatic_updates_complete( array $results ): void {
		foreach ( array( 'plugin', 'theme' ) as $type ) {
			foreach ( (array) ( $results[ $type ] ?? array() ) as $update ) {
				$target = $update->item->{$type} ?? '';
				if ( ! empty( $update->result ) && ! \is_wp_error( $update->result ) && is_string( $target ) && '' !== $target ) {
					self::record( $type . '_updated', $target );
				}
			}
		}
	}

	/** Skip revisions, autosaves, automatic drafts, and identical states. */
	public static function status_changed( string $new, string $old, \WP_Post $post ): void {
		if ( $new === $old || ( 'new' === $old && 'publish' !== $new ) || 'auto-draft' === $new || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || \wp_is_post_autosave( $post->ID ) || \wp_is_post_revision( $post->ID ) ) {
			return;
		}
		self::record(
			'status_changed',
			(string) $post->ID,
			array(
				'from' => $old,
				'to'   => $new,
			)
		);
	}

	/** Delete actor references without deleting useful operational history. */
	public static function anonymize_user( int $user_id ): bool {
		if ( ! \get_option( Data_Directory::KEY_OPTION, '' ) ) {
			return false;
		}
		$path = Data_Directory::file( 'site-activity.json' );
		if ( '' === $path ) {
			self::$storage_error = 'storage_unavailable';
			return false;
		}
		$changed             = false;
		$result              = Atomic_JSON_Store::update(
			$path,
			static function ( $data ) use ( $user_id, &$changed ) {
				$entries = self::retained( (array) ( $data['entries'] ?? array() ) );
				foreach ( $entries as &$row ) {
					if ( $user_id > 0 && (int) $row['actor'] === $user_id ) {
						$row['actor'] = 0;
						$changed      = true;
					}
				}
				unset( $row );
				return array( 'entries' => $entries );
			}
		);
		self::$storage_error = $result['error'];
		return $result['success'] && $changed;
	}

	/** Erase actor references on every site when a network account is deleted. */
	public static function anonymize_network_user( int $user_id ): void {
		$offset = 0;
		do {
			$ids = \get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $ids as $blog_id ) {
				\switch_to_blog( (int) $blog_id );
				try {
					self::anonymize_user( $user_id );
				} finally {
					\restore_current_blog();
				}
			}
			$count   = count( $ids );
			$offset += $count;
		} while ( 100 === $count );
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['functionalities-site-activity'] = array(
			'eraser_friendly_name' => \__( 'Functionalities Site Activity', 'functionalities' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}
	public static function register_exporter( array $exporters ): array {
		$exporters['functionalities-site-activity'] = array(
			'exporter_friendly_name' => \__( 'Functionalities Site Activity', 'functionalities' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}
	public static function erase_personal_data( string $email ): array {
		self::$storage_error = '';
		$user                = \get_user_by( 'email', $email );
		$removed             = $user ? self::anonymize_user( (int) $user->ID ) : false;
		return array(
			'items_removed'  => $removed,
			'items_retained' => '' !== self::$storage_error,
			'messages'       => self::$storage_error ? array( \__( 'Site Activity history could not be anonymized. Check private storage and retry.', 'functionalities' ) ) : array(),
			'done'           => true,
		);
	}
	public static function export_personal_data( string $email ): array {
		$user = \get_user_by( 'email', $email );
		$data = array();
		foreach ( $user ? self::entries() : array() as $index => $entry ) {
			if ( (int) $entry['actor'] !== (int) $user->ID ) {
				continue;
			}
			$data[] = array(
				'group_id'    => 'functionalities-site-activity',
				'group_label' => \__( 'Site Activity', 'functionalities' ),
				'item_id'     => 'activity-' . $index,
				'data'        => array(
					array(
						'name'  => \__( 'Time', 'functionalities' ),
						'value' => gmdate( 'c', $entry['time'] ),
					),
					array(
						'name'  => \__( 'Event', 'functionalities' ),
						'value' => $entry['event'],
					),
					array(
						'name'  => \__( 'Target', 'functionalities' ),
						'value' => $entry['target'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}
}
