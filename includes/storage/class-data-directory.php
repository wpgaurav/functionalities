<?php
/**
 * Private data directory resolution and hardening.
 *
 * @package Functionalities\Storage
 */

namespace Functionalities\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve, create, and harden the directory holding plugin data files.
 *
 * Redirects, the bounded 404 log, and Task Manager projects are JSON files under
 * `wp-content/`. Until 1.6.0 they sat at a fixed, guessable path guarded only by
 * an `index.php` and, for tasks, an Apache 2.2 style `.htaccess` that nginx and
 * Caddy ignore — so `wp-content/functionalities/redirects.json` and every task
 * file were readable by URL on a large share of hosts. Task notes are the
 * author's private working list, and the redirect map describes a site's whole
 * URL history.
 *
 * Files now live in a per-site subdirectory whose name carries 20 random
 * characters. Directory listing is blocked, so the name cannot be discovered
 * over HTTP even where server rules are ignored. The server rules are still
 * written, because defense in depth costs nothing here.
 *
 * @since 1.6.0
 */
class Data_Directory {

	/**
	 * Option holding the random directory segment.
	 *
	 * @var string
	 */
	const KEY_OPTION = 'functionalities_data_key';

	/**
	 * Resolved absolute path, cached per request.
	 *
	 * @var string
	 */
	private static $path = '';

	/**
	 * Return the parent directory holding all plugin data.
	 *
	 * @return string
	 */
	public static function base(): string {
		/**
		 * Filters the parent directory used for plugin data files.
		 *
		 * @since 1.6.0
		 *
		 * @param string $base Absolute path, without a trailing slash.
		 */
		return (string) \apply_filters( 'functionalities_data_base_dir', WP_CONTENT_DIR . '/functionalities' );
	}

	/**
	 * Return the private directory for data files, creating it when needed.
	 *
	 * @return string Absolute path without a trailing slash.
	 */
	public static function path(): string {
		if ( '' !== self::$path ) {
			return self::$path;
		}

		$base = self::base();
		$key  = self::key();
		$path = $base . '/' . $key;

		if ( ! is_dir( $path ) ) {
			\wp_mkdir_p( $path );
		}

		self::harden( $base );
		self::harden( $path );
		self::migrate_legacy_files( $base, $path );

		self::$path = $path;

		return self::$path;
	}

	/**
	 * Return an absolute path to a file or folder inside the private directory.
	 *
	 * @param string $relative Relative name, for example "redirects.json".
	 * @return string
	 */
	public static function file( string $relative ): string {
		return self::path() . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Return the stored random directory segment, generating it on first use.
	 *
	 * @return string
	 */
	public static function key(): string {
		$key = (string) \get_option( self::KEY_OPTION, '' );

		if ( '' !== $key && preg_match( '/^[A-Za-z0-9]{8,64}$/', $key ) ) {
			return $key;
		}

		$key = \function_exists( 'wp_generate_password' )
			? \wp_generate_password( 20, false, false )
			: substr( md5( uniqid( '', true ) ), 0, 20 );

		\update_option( self::KEY_OPTION, $key, true );

		return $key;
	}

	/**
	 * Write server rules and an index file into a directory.
	 *
	 * @param string $directory Absolute directory path.
	 * @return void
	 */
	public static function harden( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "# Apache 2.4+\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n\n# Apache 2.2\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
		);

		foreach ( $files as $name => $contents ) {
			$target = $directory . '/' . $name;
			if ( file_exists( $target ) ) {
				continue;
			}
			self::write( $target, $contents );
		}
	}

	/**
	 * Move data written by earlier versions into the private directory.
	 *
	 * @param string $base    Parent directory.
	 * @param string $private Private directory.
	 * @return void
	 */
	private static function migrate_legacy_files( string $base, string $private ): void {
		if ( $base === $private ) {
			return;
		}

		foreach ( array( 'redirects.json', '404-log.json', 'tasks' ) as $name ) {
			$from = $base . '/' . $name;
			$to   = $private . '/' . $name;

			if ( ! file_exists( $from ) || file_exists( $to ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged -- Same-filesystem move of the plugin's own data; a failed migration must not break the request, the caller simply creates fresh files.
			@rename( $from, $to );

			// Legacy lock sidecars are recreated on demand and never carry data.
			if ( file_exists( $from . '.lock' ) ) {
				\wp_delete_file( $from . '.lock' );
			}
		}
	}

	/**
	 * Write a small protective file.
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents File contents.
	 * @return void
	 */
	private static function write( string $path, string $contents ): void {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( WP_Filesystem() && $wp_filesystem ) {
			$wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}
	}

	/**
	 * Return the public URL the private directory would answer on, for probing.
	 *
	 * @return string
	 */
	public static function probe_url(): string {
		return \content_url( 'functionalities/' . self::key() . '/redirects.json' );
	}

	/**
	 * Reset the request-local cache. Used by tests.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$path = '';
	}
}
