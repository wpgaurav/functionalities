<?php
/**
 * Version-to-version maintenance.
 *
 * @package Functionalities\Core
 */

namespace Functionalities\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the small migrations a new plugin version needs.
 *
 * Settings changes that only affect new writes are not enough on their own: an
 * option written by an older version keeps the storage characteristics it was
 * saved with. The icon library, for example, stays in the autoloaded set until
 * something rewrites it.
 *
 * @since 1.6.0
 */
class Upgrader {

	/**
	 * Option holding the version that last completed maintenance.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'functionalities_version';

	/**
	 * Run maintenance when the stored version is behind the running one.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) \get_option( self::VERSION_OPTION, '' );

		if ( FUNCTIONALITIES_VERSION === $stored ) {
			return;
		}

		if ( '' === $stored || version_compare( $stored, '1.6.0', '<' ) ) {
			self::upgrade_to_160();
		}

		\update_option( self::VERSION_OPTION, FUNCTIONALITIES_VERSION, true );
	}

	/**
	 * Maintenance for 1.6.0.
	 *
	 * @return void
	 */
	private static function upgrade_to_160(): void {
		// The SVG library holds full markup for every icon, so it must leave the
		// autoloaded option set. update_option() cannot do this: it returns early
		// when the value is unchanged and never reaches the autoload column.
		self::set_autoload( 'functionalities_svg_icons', false );

		// Create, harden, and migrate the private data directory now rather than
		// on whichever request first happens to touch storage.
		if ( Module_Registry::is_enabled( 'redirect-manager' ) || Module_Registry::is_enabled( 'task-manager' ) ) {
			\Functionalities\Storage\Data_Directory::path();
		}

		// The exception preset is now cached; drop anything left from before.
		\delete_transient( 'functionalities_link_preset' );
		\delete_transient( 'func_redirects_json' );
	}

	/**
	 * Change an existing option's autoload flag.
	 *
	 * @since 1.6.0
	 *
	 * @param string $option   Option name.
	 * @param bool   $autoload Whether the option should autoload.
	 * @return void
	 */
	private static function set_autoload( string $option, bool $autoload ): void {
		if ( \function_exists( 'wp_set_option_autoload' ) ) {
			\wp_set_option_autoload( $option, $autoload );
			return;
		}

		// WordPress 6.3 has no such helper. Rewrite the row instead.
		$value = \get_option( $option, null );
		if ( null === $value ) {
			return;
		}

		\delete_option( $option );
		\add_option( $option, $value, '', $autoload );
	}
}
